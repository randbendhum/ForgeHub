<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\ModStatus;
use App\Models\Developer;
use App\Services\ModPublisher;
use Database\Seeders\GameSeeder;
use Database\Seeders\TagSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\Support\ZipFixture;
use Tests\TestCase;

class CatalogueInventoryAndDownloadTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('mods');
        $this->seed([GameSeeder::class, TagSeeder::class]);
    }

    public function test_catalogue_lists_published_mods_and_hides_quarantined_drafts(): void
    {
        $dev = Developer::factory()->create();
        $publisher = app(ModPublisher::class);

        $validZip = ZipFixture::createArchive([
            'modDesc.xml' => (string) file_get_contents(__DIR__ . '/../Fixtures/mods/modDesc_valid_fs25.xml'),
            'icon_fendt.dds' => 'DDS',
        ]);

        try {
            $validVersion = $publisher->publishFromPath($dev, $validZip, 'FS25_Fendt1050.zip');
            $invalidVersion = $publisher->publishFromPath(
                $dev,
                __DIR__ . '/../Fixtures/mods/modDesc_malformed.xml',
                'modDesc_broken.xml'
            );

            $response = $this->get(route('catalogue.index'));
            $response->assertOk()
                ->assertSee($validVersion->mod->name)
                ->assertDontSee($invalidVersion->mod->name);

            // Inventário do developer mostra ambos (Validado + Erro Sintaxe XML)
            $invResponse = $this->actingAs($dev)->get(route('inventory.index'));
            $invResponse->assertOk()
                ->assertSee($validVersion->mod->name)
                ->assertSee('modDesc_broken.xml')
                ->assertSee('XML/JSON SYNTAX ERROR');
        } finally {
            @unlink($validZip);
        }
    }

    public function test_download_increments_counters_for_valid_version_and_blocks_invalid(): void
    {
        $dev = Developer::factory()->create();
        $publisher = app(ModPublisher::class);

        $validZip = ZipFixture::createArchive([
            'modDesc.xml' => (string) file_get_contents(__DIR__ . '/../Fixtures/mods/modDesc_valid_fs25.xml'),
            'icon_fendt.dds' => 'DDS',
        ]);

        try {
            $validVersion = $publisher->publishFromPath($dev, $validZip, 'FS25_Fendt1050.zip');
            $invalidVersion = $publisher->publishFromPath(
                $dev,
                __DIR__ . '/../Fixtures/mods/modDesc_malformed.xml',
                'modDesc_broken.xml'
            );

            $dlResponse = $this->get(route('versions.download', [$validVersion->mod, $validVersion]));
            $dlResponse->assertOk();

            $this->assertSame(1, $validVersion->fresh()->downloads_count);
            $this->assertSame(1, $validVersion->mod->fresh()->downloads_count);

            // Versão inválida em quarentena não pode ser descarregada (404)
            $this->get(route('versions.download', [$invalidVersion->mod, $invalidVersion]))
                ->assertNotFound();
        } finally {
            @unlink($validZip);
        }
    }

    public function test_owner_can_delete_version_and_remove_file_from_disk(): void
    {
        $dev = Developer::factory()->create();
        $publisher = app(ModPublisher::class);

        $validZip = ZipFixture::createArchive([
            'modDesc.xml' => (string) file_get_contents(__DIR__ . '/../Fixtures/mods/modDesc_valid_fs25.xml'),
            'icon_fendt.dds' => 'DDS',
        ]);

        try {
            $version = $publisher->publishFromPath($dev, $validZip, 'FS25_Fendt1050.zip');
            $path = $version->file_path;
            Storage::disk('mods')->assertExists($path);

            $this->actingAs($dev)
                ->delete(route('versions.destroy', $version))
                ->assertRedirect(route('inventory.index'));

            Storage::disk('mods')->assertMissing($path);
            $this->assertDatabaseMissing('mod_versions', ['id' => $version->id]);
        } finally {
            @unlink($validZip);
        }
    }

    public function test_demo_seeder_populates_valid_warning_and_invalid_mods_and_all_views_render(): void
    {
        $this->seed(\Database\Seeders\DemoSeeder::class);

        $dev = Developer::query()->where('email', 'demo@forgemod.test')->firstOrFail();

        $this->get(route('catalogue.index'))
            ->assertOk()
            ->assertSee('Valtra T-Series Pro Pack')
            ->assertSee('Lithium Radon Tick Optimizer');

        $this->get(route('developers.show', $dev))->assertOk();

        $this->actingAs($dev)->get(route('inventory.index'))
            ->assertOk()
            ->assertSee('VALIDATED')
            ->assertSee('VALIDATED W/ WARNINGS')
            ->assertSee('XML/JSON SYNTAX ERROR');

        $this->actingAs($dev)->get(route('upload.create'))->assertOk();

        foreach (\App\Models\ModVersion::all() as $ver) {
            $this->actingAs($dev)->get(route('versions.report', $ver))->assertOk();
        }
    }
}
