<?php

declare(strict_types=1);

namespace Tests\Unit\Parsers;

use App\Enums\ModStatus;
use App\Services\ModParsing\Data\ParseContext;
use App\Services\ModParsing\Parsers\FabricModJsonParser;
use App\Services\ModParsing\Parsers\ForgeMcmodInfoParser;
use PHPUnit\Framework\TestCase;

class MinecraftManifestParsersTest extends TestCase
{
    public function test_parses_valid_fabric_mod_json(): void
    {
        $raw = (string) file_get_contents(__DIR__ . '/../../Fixtures/mods/fabric_valid.json');
        $parser = new FabricModJsonParser();

        $report = $parser->parse($raw, new ParseContext('sodium.jar', 'fabric.mod.json', true));

        $this->assertSame(ModStatus::Validated, $report->status());
        $this->assertNotNull($report->manifest());
        $this->assertSame('sodium_ultra', $report->manifest()->identifier);
        $this->assertSame('Sodium Ultra Renderer', $report->manifest()->name);
        $this->assertSame('0.6.1', $report->manifest()->version);
        $this->assertSame('JellySquid, Inês Costa', $report->manifest()->author);
        $this->assertContains('1.21.1', $report->manifest()->compatibleGameVersions);
    }

    public function test_rejects_fabric_mod_with_invalid_id(): void
    {
        $raw = (string) file_get_contents(__DIR__ . '/../../Fixtures/mods/fabric_bad_id.json');
        $parser = new FabricModJsonParser();

        $report = $parser->parse($raw, new ParseContext('bad.json', 'fabric.mod.json'));

        $this->assertSame(ModStatus::Invalid, $report->status());
        $codes = array_map(fn ($e) => $e->code, $report->errors());
        $this->assertContains('FABRIC_INVALID_ID', $codes);
    }

    public function test_parses_valid_forge_mcmod_info(): void
    {
        $raw = (string) file_get_contents(__DIR__ . '/../../Fixtures/mods/mcmod_valid.info');
        $parser = new ForgeMcmodInfoParser();

        $report = $parser->parse($raw, new ParseContext('industrial.jar', 'mcmod.info', true));

        $this->assertSame(ModStatus::Validated, $report->status());
        $this->assertNotNull($report->manifest());
        $this->assertSame('industrial_foregoing_pt', $report->manifest()->identifier);
        $this->assertSame('3.4.2', $report->manifest()->version);
        $this->assertContains('1.20.1', $report->manifest()->compatibleGameVersions);
    }
}

