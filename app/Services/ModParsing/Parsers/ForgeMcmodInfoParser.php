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

final class ForgeMcmodInfoParser implements ManifestParser
{
    public function supports(string $filename, string $rawContent): bool
    {
        $lower = strtolower(basename($filename));
        if ($lower === 'mcmod.info') {
            return true;
        }

        if (str_ends_with($lower, '.json')) {
            return str_contains($rawContent, '"modid"') || str_contains($rawContent, '"modList"');
        }

        return false;
    }

    public function parse(string $rawContent, ParseContext $context): ValidationReport
    {
        $report = new ValidationReport(ManifestType::ForgeMcmod);

        if (trim($rawContent) === '') {
            return $report->error('JSON_EMPTY', 'The mcmod.info file is empty.', 1, 1);
        }

        try {
            $decoded = json_decode($rawContent, true, 64, JSON_THROW_ON_ERROR);
        } catch (JsonException $e) {
            return $report->error(
                code: 'JSON_SYNTAX',
                message: sprintf('JSON syntax error in mcmod.info: %s', $e->getMessage()),
                line: 1,
                column: 1,
                context: Snippet::around($rawContent, 1),
            );
        }

        $modEntry = null;
        if (is_array($decoded)) {
            if (array_is_list($decoded) && isset($decoded[0]) && is_array($decoded[0])) {
                $modEntry = $decoded[0];
            } elseif (isset($decoded['modList']) && is_array($decoded['modList']) && isset($decoded['modList'][0]) && is_array($decoded['modList'][0])) {
                $modEntry = $decoded['modList'][0];
            } elseif (isset($decoded['modid'])) {
                $modEntry = $decoded;
            }
        }

        if (! is_array($modEntry)) {
            return $report->error(
                code: 'FORGE_INVALID_STRUCTURE',
                message: 'Invalid mcmod.info structure: expected an array of mods or a { "modList": [...] } object.',
                line: 1,
                context: Snippet::around($rawContent, 1),
            );
        }

        $modid = is_string($modEntry['modid'] ?? null) ? trim($modEntry['modid']) : '';
        if ($modid === '' || ! preg_match('/^[a-z][a-z0-9_]{1,63}$/', $modid)) {
            $report->error(
                code: 'FORGE_INVALID_MODID',
                message: 'The "modid" field is mandatory and must contain only lowercase letters, digits, and underscores.',
                line: 1,
                context: Snippet::around($rawContent, 1),
            );
        }

        $name = is_string($modEntry['name'] ?? null) ? trim($modEntry['name']) : '';
        if ($name === '') {
            $report->error(
                code: 'FORGE_MISSING_NAME',
                message: 'Mandatory "name" field is missing in mcmod.info.',
                line: 1,
            );
        }

        $version = is_string($modEntry['version'] ?? null) ? trim($modEntry['version']) : '';
        if ($version === '' || str_contains($version, '${')) {
            $report->error(
                code: 'FORGE_INVALID_VERSION',
                message: 'Mandatory "version" field is missing or contains an unexpanded build token.',
                line: 1,
            );
        }

        $authorsList = $modEntry['authorList'] ?? $modEntry['authors'] ?? [];
        $author = is_array($authorsList)
            ? implode(', ', array_filter($authorsList, 'is_string'))
            : (is_string($authorsList) ? trim($authorsList) : '');

        if ($author === '') {
            $report->warning(
                code: 'FORGE_MISSING_AUTHOR',
                message: 'Missing "authorList" in mcmod.info.',
            );
            $author = 'Unknown';
        }

        $mcVersion = is_string($modEntry['mcversion'] ?? null) ? trim($modEntry['mcversion']) : '';
        $compatibleVersions = [];
        if ($mcVersion !== '') {
            $compatibleVersions[] = $mcVersion;
            if (str_starts_with($mcVersion, '1.20')) {
                $compatibleVersions[] = '1.20.x';
            } elseif (str_starts_with($mcVersion, '1.19')) {
                $compatibleVersions[] = '1.19.x';
            } elseif (str_starts_with($mcVersion, '1.12')) {
                $compatibleVersions[] = '1.12.2';
            }
        } else {
            $report->warning(
                code: 'FORGE_MISSING_MCVERSION',
                message: 'Field "mcversion" was not specified in mcmod.info.',
            );
        }

        $description = is_string($modEntry['description'] ?? null) ? trim($modEntry['description']) : null;

        if (! $report->hasErrors()) {
            $report->withManifest(new ParsedManifest(
                identifier: $modid,
                name: $name,
                version: $version,
                author: $author,
                description: $description !== '' ? $description : null,
                compatibleGameVersions: array_values(array_unique($compatibleVersions)),
                suggestedTags: ['Forge'],
                extra: [
                    'mcversion' => $mcVersion !== '' ? $mcVersion : null,
                    'url' => $modEntry['url'] ?? null,
                    'credits' => $modEntry['credits'] ?? null,
                ],
            ));
        }

        return $report;
    }
}
