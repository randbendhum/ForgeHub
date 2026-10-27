<?php

declare(strict_types=1);

namespace Tests\Unit\Parsers;

use App\Enums\ModStatus;
use App\Services\ModParsing\Data\ParseContext;
use App\Services\ModParsing\Parsers\FarmingSimulatorModDescParser;
use App\Services\ModParsing\Support\FsDescVersionMap;
use PHPUnit\Framework\TestCase;

class FarmingSimulatorModDescParserTest extends TestCase
{
    private FarmingSimulatorModDescParser $parser;

    protected function setUp(): void
    {
        parent::setUp();
        $this->parser = new FarmingSimulatorModDescParser();
    }

    public function test_parses_valid_fs25_moddesc_and_extracts_metadata(): void
    {
        $xml = (string) file_get_contents(__DIR__ . '/../../Fixtures/mods/modDesc_valid_fs25.xml');
        $ctx = new ParseContext(
            originalFilename: 'FS25_Fendt1050.zip',
            manifestFilename: 'modDesc.xml',
            fromArchive: true,
            archiveEntries: ['modDesc.xml', 'icon_fendt.dds', 'fendt1050.xml'],
        );

        $report = $this->parser->parse($xml, $ctx);

        $this->assertSame(ModStatus::Validated, $report->status());
        $this->assertNotNull($report->manifest());
        $this->assertSame('FS25_Fendt1050', $report->manifest()->identifier);
        $this->assertSame('Fendt 1050 Vario Pro', $report->manifest()->name);
        $this->assertSame('1.3.0.0', $report->manifest()->version);
        $this->assertSame('AgroTech Portugal', $report->manifest()->author);
        $this->assertSame(['FS25'], $report->manifest()->compatibleGameVersions);
        $this->assertTrue($report->manifest()->extra['multiplayer']);
        $this->assertSame(1, $report->manifest()->extra['store_items_count']);
    }

    public function test_maps_desc_version_ranges_to_fs19_fs22_and_fs25(): void
    {
        $this->assertSame(['FS19'], FsDescVersionMap::resolve(45));
        $this->assertSame(['FS22'], FsDescVersionMap::resolve(72));
        $this->assertSame(['FS25'], FsDescVersionMap::resolve(95));
        $this->assertSame([], FsDescVersionMap::resolve(20));
    }

    public function test_detects_malformed_xml_with_line_and_snippet(): void
    {
        $xml = (string) file_get_contents(__DIR__ . '/../../Fixtures/mods/modDesc_malformed.xml');
        $ctx = new ParseContext('modDesc_malformed.xml', 'modDesc.xml');

        $report = $this->parser->parse($xml, $ctx);

        $this->assertSame(ModStatus::Invalid, $report->status());
        $this->assertNull($report->manifest());
        $this->assertNotEmpty($report->errors());
        $this->assertSame('XML_SYNTAX', $report->errors()[0]->code);
        $this->assertNotNull($report->errors()[0]->line);
        $this->assertStringContainsString('>>', (string) $report->errors()[0]->context);
    }

    public function test_detects_missing_mandatory_author_tag(): void
    {
        $xml = (string) file_get_contents(__DIR__ . '/../../Fixtures/mods/modDesc_missing_author.xml');
        $ctx = new ParseContext('modDesc_missing_author.xml', 'modDesc.xml');

        $report = $this->parser->parse($xml, $ctx);

        $this->assertSame(ModStatus::Invalid, $report->status());
        $codes = array_map(fn ($e) => $e->code, $report->errors());
        $this->assertContains('XML_MISSING_AUTHOR', $codes);
    }

    public function test_rejects_doctype_and_xxe_declarations(): void
    {
        $xml = (string) file_get_contents(__DIR__ . '/../../Fixtures/mods/modDesc_doctype_xxe.xml');
        $ctx = new ParseContext('modDesc.xml', 'modDesc.xml');

        $report = $this->parser->parse($xml, $ctx);

        $this->assertSame(ModStatus::Invalid, $report->status());
        $this->assertSame('XML_DOCTYPE_FORBIDDEN', $report->errors()[0]->code);
    }

    public function test_emits_warning_when_icon_file_is_missing_from_zip_archive(): void
    {
        $xml = (string) file_get_contents(__DIR__ . '/../../Fixtures/mods/modDesc_valid_fs25.xml');
        $ctx = new ParseContext(
            originalFilename: 'FS25_Fendt1050.zip',
            manifestFilename: 'modDesc.xml',
            fromArchive: true,
            archiveEntries: ['modDesc.xml'], // icon_fendt.dds ausente
        );

        $report = $this->parser->parse($xml, $ctx);

        $this->assertSame(ModStatus::Warning, $report->status());
        $warningCodes = array_map(fn ($w) => $w->code, $report->warnings());
        $this->assertContains('FS_ICON_FILE_NOT_IN_ZIP', $warningCodes);
    }
}

