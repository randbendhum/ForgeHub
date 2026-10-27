<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\ModParsing\ArchiveInspector;
use Tests\Support\ZipFixture;
use Tests\TestCase;

class ArchiveInspectorTest extends TestCase
{
    public function test_inspects_valid_zip_and_extracts_moddesc_in_memory(): void
    {
        $xml = (string) file_get_contents(__DIR__ . '/../Fixtures/mods/modDesc_valid_fs25.xml');
        $zipPath = ZipFixture::createArchive([
            'modDesc.xml' => $xml,
            'icon_fendt.dds' => 'DDS_STUB',
        ]);

        try {
            $inspector = new ArchiveInspector();
            $result = $inspector->inspect($zipPath, 'FS25_Fendt1050.zip');

            $this->assertNull($result['error_code']);
            $this->assertSame('modDesc.xml', $result['manifest_filename']);
            $this->assertStringContainsString('<modDesc', (string) $result['content']);
            $this->assertTrue($result['context']->hasArchiveEntry('icon_fendt.dds'));
        } finally {
            @unlink($zipPath);
        }
    }

    public function test_returns_manifest_not_found_when_zip_has_no_supported_manifest(): void
    {
        $zipPath = ZipFixture::createArchive([
            'readme.txt' => 'No manifest here',
        ]);

        try {
            $inspector = new ArchiveInspector();
            $result = $inspector->inspect($zipPath, 'EmptyMod.zip');

            $this->assertSame('MANIFEST_NOT_FOUND', $result['error_code']);
        } finally {
            @unlink($zipPath);
        }
    }

    public function test_blocks_path_traversal_entries_in_zip(): void
    {
        $zipPath = ZipFixture::createArchive([
            '../evil.php' => '<?php echo 1;',
            'modDesc.xml' => '<modDesc descVersion="95"/>',
        ]);

        try {
            $inspector = new ArchiveInspector();
            $safetyError = $inspector->validateSafety($zipPath);

            $this->assertNotNull($safetyError);
            $this->assertStringContainsString('Path Traversal', $safetyError);
        } finally {
            @unlink($zipPath);
        }
    }
}

