<?php

declare(strict_types=1);

namespace App\Services\ModParsing\Parsers;

use App\Enums\ManifestType;
use App\Services\ModParsing\Contracts\ManifestParser;
use App\Services\ModParsing\Data\ParseContext;
use App\Services\ModParsing\Data\ParsedManifest;
use App\Services\ModParsing\Data\ValidationReport;
use App\Services\ModParsing\Support\Snippet;
use JsonException;

final class FabricModJsonParser implements ManifestParser
{
    public function supports(string $filename, string $rawContent): bool
    {
        $lower = strtolower(basename($filename));
        if ($lower === 'fabric.mod.json') {
            return true;
        }

        if (str_ends_with($lower, '.json')) {
            return str_contains($rawContent, '"schemaVersion"');
        }

        return false;
    }

    public function parse(string $rawContent, ParseContext $context): ValidationReport
    {
        $report = new ValidationReport(ManifestType::FabricJson);

        if (trim($rawContent) === '') {
            return $report->error('JSON_EMPTY', 'The fabric.mod.json file is empty.', 1, 1);
        }

        try {
            $data = json_decode($rawContent, true, 64, JSON_THROW_ON_ERROR);
        } catch (JsonException $e) {
            $line = $this->estimateJsonErrorLine($rawContent);

            return $report->error(
                code: 'JSON_SYNTAX',
                message: sprintf('JSON syntax error in fabric.mod.json: %s', $e->getMessage()),
                line: $line,
                column: 1,
                context: Snippet::around($rawContent, $line),
            );
        }

        if (! is_array($data) || array_is_list($data)) {
            return $report->error(
                code: 'FABRIC_INVALID_ROOT',
                message: 'The root of fabric.mod.json must be a JSON object.',
                line: 1,
                column: 1,
                context: Snippet::around($rawContent, 1),
            );
        }

        // 1. schemaVersion === 1
        $schemaVersion = $data['schemaVersion'] ?? null;
        if ($schemaVersion !== 1) {
            $line = $this->findKeyLine($rawContent, 'schemaVersion') ?? 1;
            $report->error(
                code: 'FABRIC_INVALID_SCHEMA_VERSION',
                message: 'The mandatory "schemaVersion" field must be integer 1.',
                line: $line,
                context: Snippet::around($rawContent, $line),
            );
        }

        // 2. Mandatory id (^[a-z][a-z0-9_-]{1,63}$)
        $id = is_string($data['id'] ?? null) ? trim($data['id']) : '';
        if ($id === '') {
            $report->error(
                code: 'FABRIC_MISSING_ID',
                message: 'Mandatory "id" field is missing in fabric.mod.json.',
                line: 1,
                context: Snippet::around($rawContent, 1),
            );
        } elseif (! preg_match('/^[a-z][a-z0-9_-]{1,63}$/', $id)) {
            $line = $this->findKeyLine($rawContent, 'id') ?? 1;
            $report->error(
                code: 'FABRIC_INVALID_ID',
                message: sprintf(
                    'Mod identifier "%s" is invalid. Fabric Loader requires 2–64 characters [a-z0-9_-] starting with a lowercase letter.',
                    $id
                ),
                line: $line,
                context: Snippet::around($rawContent, $line),
            );
        }

        // 3. Mandatory version
        $version = is_string($data['version'] ?? null) ? trim($data['version']) : '';
        if ($version === '' || str_contains($version, '${')) {
            $line = $this->findKeyLine($rawContent, 'version') ?? 1;
            $report->error(
                code: 'FABRIC_INVALID_VERSION',
                message: 'Mandatory "version" field is empty or contains an unexpanded Gradle placeholder (${version}).',
                line: $line,
                context: Snippet::around($rawContent, $line),
            );
        }

        // 4. name (warning if omitted, falls back to id)
        $name = is_string($data['name'] ?? null) ? trim($data['name']) : '';
        if ($name === '') {
            $report->warning(
                code: 'FABRIC_MISSING_NAME',
                message: 'Field "name" is not defined; falling back to "id" as display name.',
            );
            $name = $id;
        }

        // 5. authors
        $authors = $this->extractAuthors($data['authors'] ?? null);
        if ($authors === '') {
            $report->warning(
                code: 'FABRIC_MISSING_AUTHORS',
                message: 'The "authors" list is empty or missing in fabric.mod.json.',
            );
            $authors = 'Unknown';
        }

        // 6. depends.minecraft
        $depends = is_array($data['depends'] ?? null) ? $data['depends'] : [];
        $mcConstraint = $depends['minecraft'] ?? null;
        $compatibleMcVersions = $this->extractMinecraftVersions($mcConstraint);

        if ($compatibleMcVersions === []) {
            $report->warning(
                code: 'FABRIC_MISSING_MC_DEPENDENCY',
                message: 'No recognized "depends.minecraft" version constraint was declared.',
            );
        }

        // 7. license
        $license = $data['license'] ?? null;
        $licenseStr = is_array($license) ? implode(', ', array_filter($license, 'is_string')) : (is_string($license) ? $license : '');
        if ($licenseStr === '') {
            $report->warning(
                code: 'FABRIC_MISSING_LICENSE',
                message: 'Fabric guideline: declare a "license" field in the manifest.',
            );
        }

        $description = is_string($data['description'] ?? null) ? trim($data['description']) : null;
        $environment = is_string($data['environment'] ?? null) ? $data['environment'] : '*';

        if (! $report->hasErrors()) {
            $report->withManifest(new ParsedManifest(
                identifier: $id,
                name: $name,
                version: $version,
                author: $authors,
                description: $description !== '' ? $description : null,
                compatibleGameVersions: $compatibleMcVersions,
                suggestedTags: ['Fabric'],
                extra: [
                    'schema_version' => $schemaVersion,
                    'environment' => $environment,
                    'license' => $licenseStr !== '' ? $licenseStr : null,
                    'minecraft_constraint' => $mcConstraint,
                    'fabric_loader' => $depends['fabricloader'] ?? null,
                ],
            ));
        }

        return $report;
    }

    private function extractAuthors(mixed $rawAuthors): string
    {
        if (! is_array($rawAuthors)) {
            return '';
        }

        $names = [];
        foreach ($rawAuthors as $entry) {
            if (is_string($entry) && trim($entry) !== '') {
                $names[] = trim($entry);
            } elseif (is_array($entry) && is_string($entry['name'] ?? null) && trim($entry['name']) !== '') {
                $names[] = trim($entry['name']);
            }
        }

        return implode(', ', $names);
    }

    /**
     * @return list<string>
     */
    private function extractMinecraftVersions(mixed $constraint): array
    {
        $rawStrings = [];
        if (is_string($constraint)) {
            $rawStrings[] = $constraint;
        } elseif (is_array($constraint)) {
            foreach ($constraint as $item) {
                if (is_string($item)) {
                    $rawStrings[] = $item;
                }
            }
        }

        $matched = [];
        $knownSeries = ['1.21.4', '1.21.1', '1.21', '1.20.4', '1.20.1', '1.20', '1.19.4', '1.19.2', '1.18.2'];

        foreach ($rawStrings as $str) {
            if (preg_match_all('/1\.\d+(?:\.\d+)?/', $str, $m)) {
                foreach ($m[0] as $ver) {
                    $matched[] = $ver;
                    if (str_starts_with($ver, '1.21')) {
                        $matched[] = '1.21.x';
                    } elseif (str_starts_with($ver, '1.20')) {
                        $matched[] = '1.20.x';
                    } elseif (str_starts_with($ver, '1.19')) {
                        $matched[] = '1.19.x';
                    }
                }
            } elseif (trim($str) === '*') {
                $matched[] = '1.21.x';
                $matched[] = '1.20.x';
            }
        }

        return array_values(array_unique(array_intersect($matched, array_merge($knownSeries, ['1.21.x', '1.20.x', '1.19.x'])) ?: $matched));
    }

    private function findKeyLine(string $rawContent, string $key): ?int
    {
        $lines = explode("\n", str_replace(["\r\n", "\r"], "\n", $rawContent));
        $pattern = '/"' . preg_quote($key, '/') . '"\s*:/';

        foreach ($lines as $idx => $line) {
            if (preg_match($pattern, $line)) {
                return $idx + 1;
            }
        }

        return null;
    }

    private function estimateJsonErrorLine(string $rawContent): int
    {
        $lines = explode("\n", str_replace(["\r\n", "\r"], "\n", $rawContent));
        foreach ($lines as $idx => $line) {
            if (str_contains($line, ':') && ! preg_match('/[,{}\[\]]\s*$/', trim($line))) {
                return min(count($lines), $idx + 1);
            }
        }

        return max(1, count($lines));
    }
}
