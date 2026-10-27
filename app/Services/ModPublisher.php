<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\ManifestType;
use App\Enums\ModStatus;
use App\Models\Developer;
use App\Models\Game;
use App\Models\GameVersion;
use App\Models\Mod;
use App\Models\ModVersion;
use App\Models\Tag;
use App\Services\ModParsing\Data\ValidationReport;
use App\Services\ModParsing\ModValidator;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Throwable;

final class ModPublisher
{
    public function __construct(
        private readonly ModValidator $validator = new ModValidator(),
    ) {}

    /**
     * Validates, stores the file in the structured 'mods' disk, and persists Mod + ModVersion + Issues + Tags.
     *
     * @param  list<string>  $tagNames
     */
    public function publishUploadedFile(
        Developer $developer,
        UploadedFile $file,
        ?Mod $targetMod = null,
        array $tagNames = [],
        ?string $changelog = null,
    ): ModVersion {
        if ($targetMod !== null && (int) $targetMod->developer_id !== (int) $developer->id) {
            throw new AuthorizationException('You are not authorized to publish a new version for this mod.');
        }

        $report = $this->validator->validateUploadedFile($file);
        $realPath = (string) $file->getRealPath();
        $originalFilename = $file->getClientOriginalName() ?: $file->hashName();

        return $this->persist(
            developer: $developer,
            sourceFilePath: $realPath,
            originalFilename: $originalFilename,
            fileSize: (int) $file->getSize(),
            report: $report,
            targetMod: $targetMod,
            tagNames: $tagNames,
            changelog: $changelog,
        );
    }

    /**
     * Publishes from a local disk path (used by DemoSeeder and automated tests).
     *
     * @param  list<string>  $tagNames
     */
    public function publishFromPath(
        Developer $developer,
        string $sourceFilePath,
        ?string $originalFilename = null,
        ?Mod $targetMod = null,
        array $tagNames = [],
        ?string $changelog = null,
    ): ModVersion {
        if ($targetMod !== null && (int) $targetMod->developer_id !== (int) $developer->id) {
            throw new AuthorizationException('You are not authorized to publish a new version for this mod.');
        }

        $originalFilename ??= basename($sourceFilePath);
        $report = $this->validator->validatePath($sourceFilePath, $originalFilename);

        return $this->persist(
            developer: $developer,
            sourceFilePath: $sourceFilePath,
            originalFilename: $originalFilename,
            fileSize: (int) @filesize($sourceFilePath),
            report: $report,
            targetMod: $targetMod,
            tagNames: $tagNames,
            changelog: $changelog,
        );
    }

    /**
     * @param  list<string>  $tagNames
     */
    private function persist(
        Developer $developer,
        string $sourceFilePath,
        string $originalFilename,
        int $fileSize,
        ValidationReport $report,
        ?Mod $targetMod,
        array $tagNames,
        ?string $changelog,
    ): ModVersion {
        $diskName = (string) config('forgemod.disk', 'mods');
        $disk = Storage::disk($diskName);
        $sha256 = hash_file('sha256', $sourceFilePath) ?: hash('sha256', $originalFilename . microtime());
        $storedPath = null;

        try {
            return DB::transaction(function () use (
                $developer,
                $sourceFilePath,
                $originalFilename,
                $fileSize,
                $report,
                $targetMod,
                $tagNames,
                $changelog,
                $diskName,
                $disk,
                $sha256,
                &$storedPath,
            ): ModVersion {
                $game = $this->resolveGame($report->manifestType, $originalFilename, $targetMod);
                $manifest = $report->manifest();
                $status = $report->status();

                $mod = $this->resolveOrCreateMod(
                    developer: $developer,
                    game: $game,
                    targetMod: $targetMod,
                    report: $report,
                    originalFilename: $originalFilename,
                    sha256: $sha256,
                );

                if ($manifest !== null) {
                    $duplicateVersion = ModVersion::query()
                        ->where('mod_id', $mod->id)
                        ->where('version', $manifest->version)
                        ->exists();

                    if ($duplicateVersion) {
                        throw ValidationException::withMessages([
                            'file' => sprintf('Version "%s" already exists for mod "%s". Increment the <version> tag before submitting.', $manifest->version, $mod->name),
                        ]);
                    }
                }

                $duplicateHash = ModVersion::query()
                    ->where('mod_id', $mod->id)
                    ->where('sha256', $sha256)
                    ->exists();

                if ($duplicateHash) {
                    throw ValidationException::withMessages([
                        'file' => 'This exact file (identical SHA-256 hash) has already been submitted for this mod.',
                    ]);
                }

                $safeFilename = $this->sanitizeFilename($originalFilename);
                if ($status === ModStatus::Invalid || $manifest === null) {
                    $storedPath = sprintf(
                        '_quarantine/%s/%s/%s-%s',
                        now()->format('Y'),
                        now()->format('m'),
                        substr($sha256, 0, 12),
                        $safeFilename
                    );
                } else {
                    $safeVersionFolder = preg_replace('/[^A-Za-z0-9._-]/', '_', $manifest->version) ?: '1.0.0';
                    $storedPath = sprintf(
                        '%s/%s/%s/%s',
                        $game->slug,
                        $mod->slug,
                        $safeVersionFolder,
                        $safeFilename
                    );
                }

                $stream = fopen($sourceFilePath, 'rb');
                if ($stream === false) {
                    throw ValidationException::withMessages([
                        'file' => 'Unable to open file stream for storage.',
                    ]);
                }

                try {
                    $disk->put($storedPath, $stream);
                } finally {
                    if (is_resource($stream)) {
                        fclose($stream);
                    }
                }

                $modVersion = ModVersion::query()->create([
                    'mod_id' => $mod->id,
                    'version' => $manifest?->version,
                    'author' => $manifest?->author,
                    'status' => $status,
                    'manifest_type' => $report->manifestType ?? ManifestType::FsModDesc,
                    'disk' => $diskName,
                    'file_path' => $storedPath,
                    'original_filename' => $originalFilename,
                    'file_size' => $fileSize,
                    'sha256' => $sha256,
                    'metadata' => $manifest?->extra ?? ['parse_failed' => true],
                    'changelog' => $changelog,
                    'downloads_count' => 0,
                ]);

                foreach ($report->issues() as $issue) {
                    $modVersion->issues()->create([
                        'severity' => $issue->severity,
                        'code' => $issue->code,
                        'message' => $issue->message,
                        'line' => $issue->line,
                        'column' => $issue->column,
                        'context' => $issue->context,
                    ]);
                }

                if ($manifest !== null && $manifest->compatibleGameVersions !== []) {
                    $gameVersionIds = [];
                    foreach ($manifest->compatibleGameVersions as $verName) {
                        $gv = GameVersion::query()->firstOrCreate(
                            ['game_id' => $game->id, 'name' => $verName],
                            ['sort_order' => 50]
                        );
                        $gameVersionIds[] = $gv->id;
                    }
                    $modVersion->gameVersions()->sync($gameVersionIds);
                }

                $combinedTags = array_values(array_unique(array_filter(array_merge(
                    $tagNames,
                    $manifest?->suggestedTags ?? []
                ))));

                if ($combinedTags !== []) {
                    $tagIds = [];
                    foreach (array_slice($combinedTags, 0, 8) as $rawTagName) {
                        $slug = Str::slug($rawTagName);
                        if ($slug === '') {
                            continue;
                        }
                        $tag = Tag::query()->firstOrCreate(
                            ['slug' => $slug],
                            ['name' => Str::headline($rawTagName)]
                        );
                        $tagIds[] = $tag->id;
                    }
                    if ($tagIds !== []) {
                        $mod->tags()->syncWithoutDetaching($tagIds);
                    }
                }

                if ($status !== ModStatus::Invalid && $manifest !== null) {
                    $mod->update([
                        'name' => $manifest->name,
                        'description' => $manifest->description ?? $mod->description,
                        'latest_version_id' => $modVersion->id,
                        'published_at' => $mod->published_at ?? now(),
                    ]);
                }

                return $modVersion->load(['mod.game', 'mod.developer', 'mod.tags', 'issues', 'gameVersions']);
            });
        } catch (Throwable $e) {
            if ($storedPath !== null && $disk->exists($storedPath)) {
                $disk->delete($storedPath);
            }

            throw $e;
        }
    }

    private function resolveGame(?ManifestType $manifestType, string $originalFilename, ?Mod $targetMod): Game
    {
        if ($targetMod !== null) {
            return $targetMod->game;
        }

        $slug = $manifestType?->gameSlug();
        if ($slug === null) {
            $ext = strtolower(pathinfo($originalFilename, PATHINFO_EXTENSION));
            $slug = in_array($ext, ['jar', 'json', 'info'], true) ? 'minecraft' : 'farming-simulator';
        }

        return Game::query()->firstOrCreate(
            ['slug' => $slug],
            [
                'name' => $slug === 'minecraft' ? 'Minecraft' : 'Farming Simulator',
                'short_code' => $slug === 'minecraft' ? 'MC' : 'FS',
                'manifest_filename' => $slug === 'minecraft' ? 'fabric.mod.json' : 'modDesc.xml',
            ]
        );
    }

    private function resolveOrCreateMod(
        Developer $developer,
        Game $game,
        ?Mod $targetMod,
        ValidationReport $report,
        string $originalFilename,
        string $sha256,
    ): Mod {
        if ($targetMod !== null) {
            return $targetMod;
        }

        $manifest = $report->manifest();

        if ($manifest !== null) {
            $existing = Mod::withTrashed()
                ->where('game_id', $game->id)
                ->where('identifier', $manifest->identifier)
                ->first();

            if ($existing !== null) {
                if ((int) $existing->developer_id !== (int) $developer->id) {
                    throw new AuthorizationException(sprintf(
                        'Mod identifier "%s" is already registered by another creator for this game.',
                        $manifest->identifier
                    ));
                }

                if ($existing->trashed()) {
                    $existing->restore();
                }

                return $existing;
            }

            $baseSlug = Str::slug($manifest->name . '-' . $manifest->identifier) ?: Str::slug($manifest->identifier);

            return Mod::query()->create([
                'developer_id' => $developer->id,
                'game_id' => $game->id,
                'identifier' => $manifest->identifier,
                'name' => $manifest->name,
                'slug' => $this->uniqueModSlug($baseSlug),
                'description' => $manifest->description,
                'published_at' => now(),
            ]);
        }

        $baseName = pathinfo($originalFilename, PATHINFO_FILENAME) ?: 'invalid-mod';
        $draftIdentifier = 'draft_' . Str::slug($baseName, '_') . '_' . substr($sha256, 0, 8);
        $draftTitle = sprintf('[XML ERROR] %s', Str::limit($baseName, 80, ''));

        return Mod::query()->create([
            'developer_id' => $developer->id,
            'game_id' => $game->id,
            'identifier' => $draftIdentifier,
            'name' => $draftTitle,
            'slug' => $this->uniqueModSlug('draft-' . $baseName . '-' . substr($sha256, 0, 6)),
            'description' => 'Submission quarantined due to critical syntax or structural errors in the manifest.',
            'published_at' => null,
        ]);
    }

    private function uniqueModSlug(string $base): string
    {
        $slug = Str::slug($base) ?: 'mod-' . Str::lower(Str::random(6));
        $candidate = $slug;
        $counter = 2;

        while (Mod::withTrashed()->where('slug', $candidate)->exists()) {
            $candidate = $slug . '-' . $counter;
            $counter++;
        }

        return $candidate;
    }

    private function sanitizeFilename(string $filename): string
    {
        $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        $name = pathinfo($filename, PATHINFO_FILENAME);
        $safeName = preg_replace('/[^A-Za-z0-9._-]/', '_', $name) ?: 'package';

        return $ext !== '' ? "{$safeName}.{$ext}" : $safeName;
    }
}
