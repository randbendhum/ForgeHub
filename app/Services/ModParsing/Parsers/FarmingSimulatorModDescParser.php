<?php

declare(strict_types=1);

namespace App\Services\ModParsing\Parsers;

use App\Enums\ManifestType;
use App\Services\ModParsing\Contracts\ManifestParser;
use App\Services\ModParsing\Data\ParseContext;
use App\Services\ModParsing\Data\ParsedManifest;
use App\Services\ModParsing\Data\ValidationReport;
use App\Services\ModParsing\Support\FsDescVersionMap;
use App\Services\ModParsing\Support\Snippet;
use DOMDocument;
use DOMElement;
use DOMXPath;
use Illuminate\Support\Str;

final class FarmingSimulatorModDescParser implements ManifestParser
{
    public function supports(string $filename, string $rawContent): bool
    {
        $lower = strtolower(basename($filename));
        if ($lower === 'moddesc.xml' || str_ends_with($lower, '.xml')) {
            return true;
        }

        return str_contains(substr($rawContent, 0, 512), '<modDesc');
    }

    public function parse(string $rawContent, ParseContext $context): ValidationReport
    {
        $report = new ValidationReport(ManifestType::FsModDesc);

        if (trim($rawContent) === '') {
            return $report->error('XML_EMPTY', 'The XML file is empty.', 1, 1);
        }

        if (preg_match('/<!(DOCTYPE|ENTITY)\b/i', $rawContent, $matches, PREG_OFFSET_CAPTURE)) {
            $offset = $matches[0][1];
            $line = substr_count(substr($rawContent, 0, $offset), "\n") + 1;

            return $report->error(
                code: 'XML_DOCTYPE_FORBIDDEN',
                message: 'DOCTYPE/ENTITY declarations are forbidden for security reasons (anti-XXE protection).',
                line: $line,
                column: 1,
                context: Snippet::around($rawContent, $line),
            );
        }

        $previousInternalErrors = libxml_use_internal_errors(true);
        libxml_clear_errors();

        try {
            $dom = new DOMDocument('1.0', 'UTF-8');
            $loaded = $dom->loadXML($rawContent, LIBXML_NONET | LIBXML_COMPACT);

            if (! $loaded) {
                foreach (libxml_get_errors() as $xmlError) {
                    $report->error(
                        code: 'XML_SYNTAX',
                        message: sprintf('XML syntax error: %s', trim($xmlError->message)),
                        line: $xmlError->line > 0 ? $xmlError->line : null,
                        column: $xmlError->column > 0 ? $xmlError->column : null,
                        context: Snippet::around($rawContent, $xmlError->line),
                    );
                }

                return $report;
            }

            $root = $dom->documentElement;
            if (! $root instanceof DOMElement || $root->tagName !== 'modDesc') {
                $line = $root?->getLineNo() ?: 1;

                return $report->error(
                    code: 'XML_INVALID_ROOT',
                    message: sprintf(
                        'Invalid root element <%s>. A Farming Simulator manifest requires <modDesc>.',
                        $root?->tagName ?? 'unknown'
                    ),
                    line: $line,
                    column: 1,
                    context: Snippet::around($rawContent, $line),
                );
            }

            $xpath = new DOMXPath($dom);
            $rootLine = $root->getLineNo() ?: 1;

            // 1. Validate descVersion attribute
            $descVersionRaw = trim($root->getAttribute('descVersion'));
            $compatibleVersions = [];
            $descVersionInt = null;

            if ($descVersionRaw === '' || ! ctype_digit($descVersionRaw)) {
                $report->error(
                    code: 'XML_MISSING_DESC_VERSION',
                    message: 'The mandatory descVersion attribute on <modDesc> is missing or non-numeric.',
                    line: $rootLine,
                    column: 1,
                    context: Snippet::around($rawContent, $rootLine),
                );
            } else {
                $descVersionInt = (int) $descVersionRaw;
                if ($descVersionInt < FsDescVersionMap::minimumSupported()) {
                    $report->error(
                        code: 'XML_UNSUPPORTED_DESC_VERSION',
                        message: sprintf(
                            'descVersion="%d" is below the minimum supported version (%d — Farming Simulator 19+).',
                            $descVersionInt,
                            FsDescVersionMap::minimumSupported()
                        ),
                        line: $rootLine,
                        column: 1,
                        context: Snippet::around($rawContent, $rootLine),
                    );
                } else {
                    $compatibleVersions = FsDescVersionMap::resolve($descVersionInt);
                    if ($descVersionInt > FsDescVersionMap::knownMaximum()) {
                        $report->warning(
                            code: 'XML_FUTURE_DESC_VERSION',
                            message: sprintf(
                                'descVersion="%d" exceeds the highest known version (%d). Verify compatibility with the latest game patch.',
                                $descVersionInt,
                                FsDescVersionMap::knownMaximum()
                            ),
                            line: $rootLine,
                            column: 1,
                            context: Snippet::around($rawContent, $rootLine),
                        );
                    }
                }
            }

            // 2. Validate ZIP archive filename (GIANTS Engine naming rule)
            if ($context->fromArchive) {
                $baseName = $context->archiveBaseName();
                if (! preg_match('/^[A-Za-z][A-Za-z0-9_]*$/', $baseName)) {
                    $report->error(
                        code: 'FS_INVALID_ARCHIVE_NAME',
                        message: sprintf(
                            'Archive name "%s" contains invalid characters for Farming Simulator (use only letters, digits, and underscores, starting with a letter).',
                            $context->originalFilename
                        ),
                        line: 1,
                    );
                }
            }

            // 3. Extract and validate <author>
            [$author, $authorLine] = $this->extractSingleText($xpath, '/modDesc/author');
            if ($author === '') {
                $report->error(
                    code: 'XML_MISSING_AUTHOR',
                    message: 'Mandatory <author> tag is missing or empty in modDesc.xml.',
                    line: $authorLine ?? $rootLine,
                    context: Snippet::around($rawContent, $authorLine ?? $rootLine),
                );
            }

            // 4. Extract and validate <version>
            [$version, $versionLine] = $this->extractSingleText($xpath, '/modDesc/version');
            if ($version === '') {
                $report->error(
                    code: 'XML_MISSING_VERSION',
                    message: 'Mandatory <version> tag is missing or empty in modDesc.xml.',
                    line: $versionLine ?? $rootLine,
                    context: Snippet::around($rawContent, $versionLine ?? $rootLine),
                );
            } elseif (! preg_match('/^\d+(\.\d+){1,3}$/', $version)) {
                $report->error(
                    code: 'XML_INVALID_VERSION_FORMAT',
                    message: sprintf(
                        'Invalid version format "%s". Farming Simulator requires 2 to 4 numeric segments (e.g. 1.0.0.0).',
                        $version
                    ),
                    line: $versionLine ?? $rootLine,
                    context: Snippet::around($rawContent, $versionLine ?? $rootLine),
                );
            }

            // 5. Extract and validate <title> (prefers <en>)
            [$title, $hasEnTitle, $titleLine] = $this->extractLocalizedText($xpath, '/modDesc/title');
            if ($title === '') {
                $report->error(
                    code: 'XML_MISSING_TITLE',
                    message: 'Mandatory <title> tag is missing or has no valid localized entries (<en>, <de>, <pt>).',
                    line: $titleLine ?? $rootLine,
                    context: Snippet::around($rawContent, $titleLine ?? $rootLine),
                );
            } elseif (! $hasEnTitle) {
                $report->warning(
                    code: 'XML_MISSING_EN_TITLE',
                    message: 'ModHub guideline: <title> is missing the default <en> localization tag.',
                    line: $titleLine ?? $rootLine,
                    context: Snippet::around($rawContent, $titleLine ?? $rootLine),
                );
            }

            // 6. Extract <description>
            [$description, , $descLine] = $this->extractLocalizedText($xpath, '/modDesc/description');
            if ($description === '') {
                $report->warning(
                    code: 'XML_MISSING_DESCRIPTION',
                    message: 'The <description> tag is missing or empty.',
                    line: $descLine ?? $rootLine,
                );
            }

            // 7. Validate <iconFilename>
            [$iconFilename, $iconLine] = $this->extractSingleText($xpath, '/modDesc/iconFilename');
            if ($iconFilename === '') {
                $report->warning(
                    code: 'XML_MISSING_ICON',
                    message: 'The <iconFilename> tag is not defined in the manifest.',
                    line: $iconLine ?? $rootLine,
                );
            } elseif ($context->fromArchive && ! $context->hasArchiveEntry($iconFilename)) {
                $report->warning(
                    code: 'FS_ICON_FILE_NOT_IN_ZIP',
                    message: sprintf('Referenced icon "%s" was not found inside the ZIP archive.', $iconFilename),
                    line: $iconLine ?? $rootLine,
                    context: Snippet::around($rawContent, $iconLine ?? $rootLine),
                );
            }

            // 8. Additional metadata: multiplayer, storeItems, specializations
            $mpNode = $xpath->query('/modDesc/multiplayer')->item(0);
            $multiplayerSupported = $mpNode instanceof DOMElement
                && strtolower($mpNode->getAttribute('supported')) === 'true';

            $storeItemsCount = $xpath->query('/modDesc/storeItems/storeItem')->length;
            $specializationsCount = $xpath->query('/modDesc/specializations/specialization')->length;

            $suggestedTags = [];
            if ($multiplayerSupported) {
                $suggestedTags[] = 'Multiplayer';
            }
            if ($storeItemsCount > 0) {
                $suggestedTags[] = 'Vehicles & Equipment';
            }
            if ($specializationsCount > 0) {
                $suggestedTags[] = 'Lua Script';
            }

            $identifier = $context->fromArchive && $context->archiveBaseName() !== '' && strtolower($context->archiveBaseName()) !== 'moddesc'
                ? $context->archiveBaseName()
                : Str::slug($title !== '' ? $title : 'fs-mod-' . substr(sha1($rawContent), 0, 8), '_');

            if (! $report->hasErrors()) {
                $report->withManifest(new ParsedManifest(
                    identifier: $identifier,
                    name: $title,
                    version: $version,
                    author: $author,
                    description: $description !== '' ? $description : null,
                    compatibleGameVersions: $compatibleVersions,
                    suggestedTags: $suggestedTags,
                    extra: [
                        'desc_version' => $descVersionInt,
                        'multiplayer' => $multiplayerSupported,
                        'store_items_count' => $storeItemsCount,
                        'specializations_count' => $specializationsCount,
                        'icon_filename' => $iconFilename !== '' ? $iconFilename : null,
                    ],
                ));
            }

            return $report;
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previousInternalErrors);
        }
    }

    /**
     * @return array{0: string, 1: ?int}
     */
    private function extractSingleText(DOMXPath $xpath, string $expression): array
    {
        $nodes = $xpath->query($expression);
        if ($nodes === false || $nodes->length === 0) {
            return ['', null];
        }

        $node = $nodes->item(0);

        return [trim((string) $node?->textContent), $node?->getLineNo() ?: null];
    }

    /**
     * @return array{0: string, 1: bool, 2: ?int}
     */
    private function extractLocalizedText(DOMXPath $xpath, string $basePath): array
    {
        $nodes = $xpath->query($basePath);
        if ($nodes === false || $nodes->length === 0) {
            return ['', false, null];
        }

        $container = $nodes->item(0);
        $line = $container?->getLineNo() ?: null;

        $enNodes = $xpath->query($basePath . '/en');
        if ($enNodes !== false && $enNodes->length > 0) {
            $enText = trim((string) $enNodes->item(0)?->textContent);
            if ($enText !== '') {
                return [$enText, true, $line];
            }
        }

        foreach (['de', 'pt', 'fr', 'es'] as $lang) {
            $langNodes = $xpath->query($basePath . '/' . $lang);
            if ($langNodes !== false && $langNodes->length > 0) {
                $text = trim((string) $langNodes->item(0)?->textContent);
                if ($text !== '') {
                    return [$text, false, $line];
                }
            }
        }

        $directText = trim((string) $container?->textContent);

        return [$directText, $directText !== '', $line];
    }
}
