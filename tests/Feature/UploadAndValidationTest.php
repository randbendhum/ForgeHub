<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\ModStatus;
use App\Models\Developer;
use App\Models\Mod;
use App\Models\ModVersion;
use Database\Seeders\GameSeeder;
use Database\Seeders\TagSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\Support\ZipFixture;
use Tests\TestCase;

class UploadAndValidationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('mods');
        $this->seed([GameSeeder::class, TagSeeder::class]);
    }

    public function test_guest_is_redirected_to_login_when_accessing_upload(): void
    {
        $this->get(route('upload.create'))->assertRedirect(route('login'));
        $this->post(route('upload.store'), [])->assertRedirect(route('login'));
    }

    public function test_form_request_rejects_disallowed_file_extension(): void
    {
        $developer = Developer::factory()->create();
        $badFile = UploadedFile::fake()->create('malware.exe', 100, 'application/x-msdownload');

        $response = $this->actingAs($developer)
            ->post(route('upload.store'), ['file' => $badFile]);

        $response->assertSessionHasErrors('file');
        $this->assertSame(0, ModVersion::count());
    }

    public function test_form_request_rejects_zip_with_path_traversal(): void
    {
        $developer = Developer::factory()->create();
        $unsafeZip = ZipFixture::uploadedArchive('FS25_Bad.zip', [
            '../../pwn.php' => '<?php system("id");',
        ]);

        try {
            $response = $this->actingAs($developer)
                ->postJson(route('upload.validate'), ['file' => $unsafeZip]);

            $response->assertStatus(422)
                ->assertJsonValidationErrors(['file']);
        } finally {
            @unlink($unsafeZip->getRealPath());
        }
    }

    public function test_dry_run_validate_endpoint_returns_json_without_persisting(): void
    {
        $developer = Developer::factory()->create();
        $xml = (string) file_get_contents(__DIR__ . '/../Fixtures/mods/modDesc_valid_fs25.xml');
        $zipFile = ZipFixture::uploadedArchive('FS25_Fendt1050.zip', [
            'modDesc.xml' => $xml,
            'icon_fendt.dds' => 'DDS',
        ]);

        try {
            $response = $this->actingAs($developer)
                ->postJson(route('upload.validate'), ['file' => $zipFile]);

            $response->assertOk()
                ->assertJsonPath('report.status', 'validated')
                ->assertJsonPath('report.manifest.version', '1.3.0.0')
                ->assertJsonPath('report.manifest.author', 'AgroTech Portugal');

            $this->assertSame(0, Mod::count());
            $this->assertSame(0, ModVersion::count());
        } finally {
            @unlink($zipFile->getRealPath());
        }
    }

    public function test_valid_zip_upload_creates_mod_version_and_stores_in_structured_disk(): void
    {
        $developer = Developer::factory()->create();
        $xml = (string) file_get_contents(__DIR__ . '/../Fixtures/mods/modDesc_valid_fs25.xml');
        $zipFile = ZipFixture::uploadedArchive('FS25_Fendt1050.zip', [
            'modDesc.xml' => $xml,
            'icon_fendt.dds' => 'DDS',
        ]);

        try {
            $response = $this->actingAs($developer)->post(route('upload.store'), [
                'file' => $zipFile,
                'tags' => 'Tratores Pesados, Telemetria',
                'changelog' => 'Release inicial 1.3.0.0',
            ]);

            $response->assertRedirect(route('inventory.index'));

            $mod = Mod::query()->first();
            $this->assertNotNull($mod);
            $this->assertSame('FS25_Fendt1050', $mod->identifier);
            $this->assertNotNull($mod->published_at);

            $version = ModVersion::query()->first();
            $this->assertNotNull($version);
            $this->assertSame(ModStatus::Validated, $version->status);
            $this->assertSame('1.3.0.0', $version->version);
            $this->assertTrue($version->gameVersions->pluck('name')->contains('FS25'));
            $this->assertTrue($mod->tags->pluck('slug')->contains('tratores-pesados'));
            $this->assertTrue($mod->tags->pluck('slug')->contains('multiplayer'));

            Storage::disk('mods')->assertExists($version->file_path);
            $this->assertStringStartsWith('farming-simulator/' . $mod->slug . '/1.3.0.0/', $version->file_path);
        } finally {
            @unlink($zipFile->getRealPath());
        }
    }

    public function test_malformed_xml_is_quarantined_and_marked_invalid_with_issues(): void
    {
        $developer = Developer::factory()->create();
        $badXml = (string) file_get_contents(__DIR__ . '/../Fixtures/mods/modDesc_malformed.xml');
        $uploadedXml = UploadedFile::fake()->createWithContent('modDesc_broken.xml', $badXml);

        $response = $this->actingAs($developer)->post(route('upload.store'), [
            'file' => $uploadedXml,
        ]);

        $response->assertRedirect(route('inventory.index'));

        $version = ModVersion::query()->with(['mod', 'issues'])->first();
        $this->assertNotNull($version);
        $this->assertSame(ModStatus::Invalid, $version->status);
        $this->assertNull($version->mod->published_at);
        $this->assertStringStartsWith('_quarantine/', $version->file_path);
        $this->assertNotEmpty($version->issues);
        Storage::disk('mods')->assertExists($version->file_path);
    }

    public function test_prevents_uploading_version_to_another_developers_mod(): void
    {
        $owner = Developer::factory()->create();
        $intruder = Developer::factory()->create();
        $mod = Mod::factory()->create(['developer_id' => $owner->id]);

        $xml = (string) file_get_contents(__DIR__ . '/../Fixtures/mods/modDesc_valid_fs25.xml');
        $zipFile = ZipFixture::uploadedArchive('FS25_Fendt1050.zip', [
            'modDesc.xml' => $xml,
            'icon_fendt.dds' => 'DDS',
        ]);

        try {
            $response = $this->actingAs($intruder)->post(route('upload.store'), [
                'file' => $zipFile,
                'mod_id' => $mod->id,
            ]);

            $response->assertForbidden();
        } finally {
            @unlink($zipFile->getRealPath());
        }
    }
}

