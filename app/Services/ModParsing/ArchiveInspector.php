<?php

declare(strict_types=1);

namespace App\Services\ModParsing;

use App\Services\ModParsing\Data\ParseContext;
use ZipArchive;

final class ArchiveInspector
{
    private const KNOWN_MANIFESTS = [
        'moddesc.xml' => 'modDesc.xml',
        'fabric.mod.json' => 'fabric.mod.json',
        'quilt.mod.json' => 'quilt.mod.json',
        'meta-inf/neoforge.mods.toml' => 'META-INF/neoforge.mods.toml',
        'meta-inf/mods.toml' => 'META-INF/mods.toml',
        'neoforge.mods.toml' => 'neoforge.mods.toml',
        'mods.toml' => 'mods.toml',
        'mcmod.info' => 'mcmod.info',
    ];

    /**
     * Validates structural integrity and security of a .zip or .jar archive.
     * Returns null if safe, or an error message if a security constraint is violated.
     */
    public function validateSafety(string $archivePath): ?string
    {
        if (! is_file($archivePath) || ! is_readable($archivePath)) {
            return 'Unable to read the uploaded package file.';
        }

        $zip = new ZipArchive();
        $openStatus = $zip->open($archivePath, ZipArchive::RDONLY);
        if ($openStatus !== true) {
            return 'The ZIP/JAR package is corrupted or not a valid archive.';
        }

        try {
            $maxEntries = (int) config('forgemod.archive.max_entries', 60000);
            $maxUncompressed = (int) config('forgemod.archive.max_uncompressed_bytes', 1073741824);
            $maxRatio = (int) config('forgemod.archive.max_compression_ratio', 150);

            if ($zip->numFiles === 0) {
                return 'The ZIP/JAR package is empty.';
            }

            if ($zip->numFiles > $maxEntries) {
                return sprintf('Archive exceeds the security limit of %d internal entries.', $maxEntries);
            }

            $totalUncompressed = 0;

            for ($i = 0; $i < $zip->numFiles; $i++) {
                $stat = $zip->statIndex($i);
                if ($stat === false) {
                    return 'Failed to read the internal entry table of the ZIP archive.';
                }

                $name = (string) $stat['name'];

                // Zip Slip / Path Traversal protection
                if (
                    str_starts_with($name, '/')
                    || str_starts_with($name, '\\')
                    || str_contains($name, '\\')
                    || str_contains($name, '../')
                    || str_contains($name, '..\\')
                    || $name === '..'
                    || preg_match('#(^|/)\\.\\.(/|$)#', $name) === 1
                ) {
                    return sprintf('Unsafe archive entry detected (Path Traversal blocked): "%s".', $name);
                }

                $uncompressedSize = (int) ($stat['size'] ?? 0);
                $compressedSize = max(1, (int) ($stat['comp_size'] ?? 1));

                $totalUncompressed += $uncompressedSize;
                if ($totalUncompressed > $maxUncompressed) {
                    return 'Total uncompressed archive size exceeds the security limit (anti Zip-Bomb protection).';
                }

                if ($uncompressedSize > 1048576 && ($uncompressedSize / $compressedSize) > $maxRatio) {
                    return 'Suspicious compression ratio detected in archive (anti Zip-Bomb protection).';
                }
            }

            return null;
        } finally {
            $zip->close();
        }
    }

    /**
     * Locates and extracts the manifest (modDesc.xml, fabric.mod.json, or mcmod.info) in memory.
     *
     * @return array{
     *     manifest_filename: ?string,
     *     content: ?string,
     *     context: ParseContext,
     *     error_code: ?string,
     *     error_message: ?string
     * }
     */
    public function inspect(string $archivePath, string $originalFilename): array
    {
        $safetyError = $this->validateSafety($archivePath);
        if ($safetyError !== null) {
            return [
                'manifest_filename' => null,
                'content' => null,
                'context' => new ParseContext($originalFilename, '', true, []),
                'error_code' => 'ARCHIVE_UNSAFE',
                'error_message' => $safetyError,
            ];
        }

        $zip = new ZipArchive();
        $zip->open($archivePath, ZipArchive::RDONLY);

        try {
            $entries = [];
            $foundIndex = null;
            $foundCanonicalName = null;
            $foundSize = 0;
            $jarManifestIndex = null;

            for ($i = 0; $i < $zip->numFiles; $i++) {
                $stat = $zip->statIndex($i);
                if ($stat === false) {
                    continue;
                }

                $entryName = (string) $stat['name'];
                $entries[] = $entryName;

                $normalized = strtolower(ltrim(str_replace('\\', '/', $entryName), '/'));
                if ($normalized === 'meta-inf/manifest.mf') {
                    $jarManifestIndex = $i;
                }

                if (isset(self::KNOWN_MANIFESTS[$normalized]) && $foundIndex === null) {
                    $foundIndex = $i;
                    $foundCanonicalName = self::KNOWN_MANIFESTS[$normalized];
                    $foundSize = (int) ($stat['size'] ?? 0);
                }
            }

            $jarVersion = null;
            if ($jarManifestIndex !== null) {
                $mfContent = $zip->getFromIndex($jarManifestIndex);
                if (is_string($mfContent)) {
                    $jarVersion = $this->extractJarManifestVersion($mfContent);
                }
            }

            if ($foundIndex === null || $foundCanonicalName === null) {
                return [
                    'manifest_filename' => null,
                    'content' => null,
                    'context' => new ParseContext($originalFilename, '', true, $entries, $jarVersion),
                    'error_code' => 'MANIFEST_NOT_FOUND',
                    'error_message' => 'No supported manifest (modDesc.xml, fabric.mod.json, META-INF/mods.toml, META-INF/neoforge.mods.toml, or mcmod.info) was found in the archive.',
                ];
            }

            $maxManifestBytes = (int) config('forgemod.archive.max_manifest_bytes', 2097152);
            if ($foundSize > $maxManifestBytes) {
                return [
                    'manifest_filename' => $foundCanonicalName,
                    'content' => null,
                    'context' => new ParseContext($originalFilename, $foundCanonicalName, true, $entries, $jarVersion),
                    'error_code' => 'MANIFEST_TOO_LARGE',
                    'error_message' => sprintf('Manifest %s exceeds the maximum allowed size.', $foundCanonicalName),
                ];
            }

            $content = $zip->getFromIndex($foundIndex);
            if ($content === false) {
                return [
                    'manifest_filename' => $foundCanonicalName,
                    'content' => null,
                    'context' => new ParseContext($originalFilename, $foundCanonicalName, true, $entries, $jarVersion),
                    'error_code' => 'MANIFEST_READ_ERROR',
                    'error_message' => sprintf('Unable to extract %s from archive.', $foundCanonicalName),
                ];
            }

            return [
                'manifest_filename' => $foundCanonicalName,
                'content' => $content,
                'context' => new ParseContext(
                    originalFilename: $originalFilename,
                    manifestFilename: $foundCanonicalName,
                    fromArchive: true,
                    archiveEntries: $entries,
                    jarVersion: $jarVersion,
                ),
                'error_code' => null,
                'error_message' => null,
            ];
        } finally {
            $zip->close();
        }
    }

    private function extractJarManifestVersion(string $mfContent): ?string
    {
        if (preg_match('/^Implementation-Version:\s*(.+)$/mi', $mfContent, $matches) === 1) {
            $v = trim($matches[1]);
            if ($v !== '' && ! str_contains($v, '${')) {
                return $v;
            }
        }

        if (preg_match('/^Specification-Version:\s*(.+)$/mi', $mfContent, $matches) === 1) {
            $v = trim($matches[1]);
            if ($v !== '' && ! str_contains($v, '${')) {
                return $v;
            }
        }

        return null;
    }
}
