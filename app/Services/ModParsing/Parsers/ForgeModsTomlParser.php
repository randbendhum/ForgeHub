<?php

declare(strict_types=1);

namespace App\Services\ModParsing\Parsers;

use App\Enums\ManifestType;
use App\Services\ModParsing\Contracts\ManifestParser;
use App\Services\ModParsing\Data\ParseContext;
use App\Services\ModParsing\Data\ParsedManifest;
use App\Services\ModParsing\Data\ValidationReport;
use App\Services\ModParsing\Support\Snippet;

final class ForgeModsTomlParser implements ManifestParser
{
    public function supports(string $filename, string $rawContent): bool
    {
        $lower = strtolower(basename($filename));
        if (in_array($lower, ['mods.toml', 'neoforge.mods.toml'], true)) {
            return true;
        }

        if (str_ends_with($lower, '.toml')) {
            return str_contains($rawContent, '[[mods]]') || str_contains($rawContent, 'modLoader');
        }

        return false;
    }

    public function parse(string $rawContent, ParseContext $context): ValidationReport
    {
        $report = new ValidationReport(ManifestType::ForgeMcmod);

        if (trim($rawContent) === '') {
            return $report->error('TOML_EMPTY', 'The mods.toml / neoforge.mods.toml manifest is empty.', 1, 1);
        }

        $parsed = $this->parseTomlStructure($rawContent);
        if ($parsed['syntax_error'] !== null) {
            return $report->error(
                code: 'TOML_SYNTAX',
                message: $parsed['syntax_error']['message'],
                line: $parsed['syntax_error']['line'],
                column: 1,
                context: Snippet::around($rawContent, $parsed['syntax_error']['line']),
            );
        }

        $root = $parsed['root'];
        $mods = $parsed['mods'];
        $dependencies = $parsed['dependencies'];

        if ($mods === []) {
            return $report->error(
                code: 'FORGE_TOML_NO_MODS',
                message: 'Missing mandatory [[mods]] table array in mods.toml / neoforge.mods.toml.',
                line: 1,
                context: Snippet::around($rawContent, 1),
            );
        }

        $modEntry = $mods[0];
        $modId = trim((string) ($modEntry['modId'] ?? $modEntry['modid'] ?? ''));

        if ($modId === '' || ! preg_match('/^[a-z][a-z0-9_]{1,63}$/', $modId)) {
            $report->error(
                code: 'FORGE_INVALID_MODID',
                message: 'The "modId" field inside [[mods]] is mandatory and must match ^[a-z][a-z0-9_]{1,63}$.',
                line: 1,
                context: Snippet::around($rawContent, 1),
            );
        }

        $displayName = trim((string) ($modEntry['displayName'] ?? $modEntry['name'] ?? ''));
        if ($displayName === '') {
            if ($modId !== '') {
                $displayName = ucfirst(str_replace('_', ' ', $modId));
                $report->warning(
                    code: 'FORGE_MISSING_DISPLAY_NAME',
                    message: 'Missing "displayName" inside [[mods]]; defaulted to humanized modId.',
                );
            } else {
                $report->error(
                    code: 'FORGE_MISSING_NAME',
                    message: 'Mandatory "displayName" field is missing inside [[mods]].',
                    line: 1,
                );
            }
        }

        $rawVersion = trim((string) ($modEntry['version'] ?? ''));
        $version = $this->resolveVersion($rawVersion, $context, $report);

        $authorsRaw = $modEntry['authors'] ?? $root['authors'] ?? '';
        $author = is_array($authorsRaw)
            ? implode(', ', array_filter($authorsRaw, 'is_string'))
            : trim((string) $authorsRaw);

        if ($author === '') {
            $report->warning(
                code: 'FORGE_MISSING_AUTHOR',
                message: 'Field "authors" is not declared in [[mods]]; defaulted to "Unknown".',
            );
            $author = 'Unknown';
        }

        $license = trim((string) ($root['license'] ?? $modEntry['license'] ?? ''));
        if ($license === '') {
            $report->warning(
                code: 'FORGE_MISSING_LICENSE',
                message: 'Top-level "license" field is missing in mods.toml.',
            );
        }

        $description = isset($modEntry['description']) ? trim((string) $modEntry['description']) : null;
        $compatibleVersions = $this->extractMinecraftCompatibility($dependencies, $context);

        $isNeoForge = str_contains(strtolower($context->manifestFilename), 'neoforge');
        foreach ($dependencies as $dep) {
            if (strtolower((string) ($dep['modId'] ?? '')) === 'neoforge') {
                $isNeoForge = true;
            }
        }

        if (! $report->hasErrors()) {
            $report->withManifest(new ParsedManifest(
                identifier: $modId,
                name: $displayName,
                version: $version,
                author: $author,
                description: $description !== '' ? $description : null,
                compatibleGameVersions: $compatibleVersions,
                suggestedTags: ['Forge'],
                extra: [
                    'loader' => $isNeoForge ? 'NeoForge' : 'Forge',
                    'mod_loader' => $root['modLoader'] ?? 'javafml',
                    'loader_version' => $root['loaderVersion'] ?? null,
                    'license' => $license !== '' ? $license : null,
                    'display_url' => $modEntry['displayURL'] ?? $root['displayURL'] ?? null,
                    'issue_tracker_url' => $root['issueTrackerURL'] ?? null,
                    'logo_file' => $modEntry['logoFile'] ?? null,
                ],
            ));
        }

        return $report;
    }

    private function resolveVersion(string $rawVersion, ParseContext $context, ValidationReport $report): string
    {
        if ($rawVersion !== '' && ! str_contains($rawVersion, '${')) {
            return $rawVersion;
        }

        if ($context->jarVersion !== null && $context->jarVersion !== '') {
            return $context->jarVersion;
        }

        // Try extracting version from JAR filename (e.g., Mekanism-1.20.1-10.4.9.61.jar -> 10.4.9.61)
        $base = $context->archiveBaseName();
        if (preg_match_all('/(\d+\.\d+(?:\.\d+){0,2}(?:-[a-zA-Z0-9.+]+)?)/', $base, $matches) && ! empty($matches[1])) {
            $candidates = $matches[1];
            return (string) end($candidates);
        }

        if (str_contains($rawVersion, '${')) {
            $report->warning(
                code: 'FORGE_UNEXPANDED_JAR_VERSION',
                message: sprintf('Version token "%s" could not be resolved from META-INF/MANIFEST.MF; defaulted to 1.0.0.', $rawVersion),
            );

            return '1.0.0';
        }

        $report->error(
            code: 'FORGE_INVALID_VERSION',
            message: 'Mandatory "version" field is missing inside [[mods]].',
            line: 1,
        );

        return '0.0.0';
    }

    /**
     * @param  list<array<string, mixed>>  $dependencies
     * @return list<string>
     */
    private function extractMinecraftCompatibility(array $dependencies, ParseContext $context): array
    {
        $rawRange = '';
        foreach ($dependencies as $dep) {
            $depId = strtolower(trim((string) ($dep['modId'] ?? $dep['modid'] ?? '')));
            if ($depId === 'minecraft') {
                $rawRange = trim((string) ($dep['versionRange'] ?? ''));
                break;
            }
        }

        $haystack = $rawRange . ' ' . $context->archiveBaseName();
        $versions = [];

        if (str_contains($haystack, '1.21.4')) {
            $versions[] = '1.21.4';
        }
        if (str_contains($haystack, '1.21')) {
            $versions[] = '1.21.1';
        }
        if (str_contains($haystack, '1.20')) {
            $versions[] = '1.20.x';
        }
        if (str_contains($haystack, '1.19')) {
            $versions[] = '1.19.x';
        }
        if (str_contains($haystack, '1.12')) {
            $versions[] = '1.12.2';
        }

        if ($versions === [] && $rawRange !== '') {
            if (preg_match('/(\d+\.\d+(?:\.\d+)?)/', $rawRange, $m) === 1) {
                $versions[] = $m[1];
            }
        }

        return array_values(array_unique($versions));
    }

    /**
     * Lightweight TOML parser tailored for Minecraft Forge/NeoForge mods.toml & neoforge.mods.toml.
     *
     * @return array{
     *     root: array<string, mixed>,
     *     mods: list<array<string, mixed>>,
     *     dependencies: list<array<string, mixed>>,
     *     syntax_error: ?array{line: int, message: string}
     * }
     */
    private function parseTomlStructure(string $rawContent): array
    {
        $root = [];
        $mods = [];
        $dependencies = [];

        $sectionType = 'root'; // 'root', 'mods', 'dependencies', 'other'
        $currentIndex = -1;

        $lines = preg_split('/\R/', $rawContent) ?: [];
        $totalLines = count($lines);
        $i = 0;

        while ($i < $totalLines) {
            $lineNum = $i + 1;
            $line = $this->stripTomlComment($lines[$i]);
            $trimmed = trim($line);

            if ($trimmed === '') {
                $i++;
                continue;
            }

            // Table Array header: [[mods]] or [[dependencies.mekanism]]
            if (str_starts_with($trimmed, '[[') && str_ends_with($trimmed, ']]')) {
                $header = trim(substr($trimmed, 2, -2));
                $headerLower = strtolower($header);

                if ($headerLower === 'mods') {
                    $sectionType = 'mods';
                    $mods[] = [];
                    $currentIndex = count($mods) - 1;
                } elseif (str_starts_with($headerLower, 'dependencies')) {
                    $sectionType = 'dependencies';
                    $dependencies[] = [];
                    $currentIndex = count($dependencies) - 1;
                } else {
                    $sectionType = 'other';
                }

                $i++;
                continue;
            }

            // Standard Table header: [properties] or [mods.features]
            if (str_starts_with($trimmed, '[') && str_ends_with($trimmed, ']')) {
                $sectionType = 'other';
                $i++;
                continue;
            }

            // Key = Value assignment
            $eqPos = strpos($trimmed, '=');
            if ($eqPos === false) {
                return [
                    'root' => $root,
                    'mods' => $mods,
                    'dependencies' => $dependencies,
                    'syntax_error' => [
                        'line' => $lineNum,
                        'message' => sprintf('Invalid TOML statement on line %d: expected key = value or [table] header.', $lineNum),
                    ],
                ];
            }

            $key = trim(substr($trimmed, 0, $eqPos), " \t\"'");
            $rhs = ltrim(substr($trimmed, $eqPos + 1));

            // Multi-line triple-quoted string (''' or """)
            if (str_starts_with($rhs, "'''") || str_starts_with($rhs, '"""')) {
                $delimiter = substr($rhs, 0, 3);
                $afterOpen = substr($rhs, 3);
                $closePos = strpos($afterOpen, $delimiter);

                if ($closePos !== false) {
                    $value = substr($afterOpen, 0, $closePos);
                } else {
                    $buffer = [$afterOpen];
                    $foundClose = false;
                    while (++$i < $totalLines) {
                        $nextLine = $lines[$i];
                        $endPos = strpos($nextLine, $delimiter);
                        if ($endPos !== false) {
                            $buffer[] = substr($nextLine, 0, $endPos);
                            $foundClose = true;
                            break;
                        }
                        $buffer[] = $nextLine;
                    }

                    if (! $foundClose) {
                        return [
                            'root' => $root,
                            'mods' => $mods,
                            'dependencies' => $dependencies,
                            'syntax_error' => [
                                'line' => $lineNum,
                                'message' => sprintf('Unclosed multi-line string (%s) starting on line %d.', $delimiter, $lineNum),
                            ],
                        ];
                    }

                    $value = implode("\n", $buffer);
                }
            } elseif (str_starts_with($rhs, '[') && ! str_ends_with(rtrim($rhs), ']')) {
                $buffer = [$rhs];
                while (++$i < $totalLines) {
                    $nextClean = trim($this->stripTomlComment($lines[$i]));
                    $buffer[] = $nextClean;
                    if (str_ends_with($nextClean, ']')) {
                        break;
                    }
                }
                $value = implode(' ', $buffer);
            } else {
                $value = $this->parseScalarValue($rhs);
            }

            if ($sectionType === 'root') {
                $root[$key] = $value;
            } elseif ($sectionType === 'mods' && $currentIndex >= 0) {
                $mods[$currentIndex][$key] = $value;
            } elseif ($sectionType === 'dependencies' && $currentIndex >= 0) {
                $dependencies[$currentIndex][$key] = $value;
            }

            $i++;
        }

        return [
            'root' => $root,
            'mods' => $mods,
            'dependencies' => $dependencies,
            'syntax_error' => null,
        ];
    }

    private function stripTomlComment(string $line): string
    {
        $inSingle = false;
        $inDouble = false;
        $len = strlen($line);

        for ($i = 0; $i < $len; $i++) {
            $ch = $line[$i];
            $prev = $i > 0 ? $line[$i - 1] : '';

            if ($ch === '"' && ! $inSingle && $prev !== '\\') {
                $inDouble = ! $inDouble;
            } elseif ($ch === "'" && ! $inDouble) {
                $inSingle = ! $inSingle;
            } elseif ($ch === '#' && ! $inSingle && ! $inDouble) {
                return substr($line, 0, $i);
            }
        }

        return $line;
    }

    private function parseScalarValue(string $rhs): mixed
    {
        $rhs = trim($rhs);
        if (
            (str_starts_with($rhs, '"') && str_ends_with($rhs, '"'))
            || (str_starts_with($rhs, "'") && str_ends_with($rhs, "'"))
        ) {
            return substr($rhs, 1, -1);
        }

        if (strtolower($rhs) === 'true') {
            return true;
        }
        if (strtolower($rhs) === 'false') {
            return false;
        }

        return $rhs;
    }
}
