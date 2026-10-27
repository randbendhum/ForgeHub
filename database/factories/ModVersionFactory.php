<?php

namespace Database\Factories;

use App\Enums\ManifestType;
use App\Enums\ModStatus;
use App\Models\Mod;
use App\Models\ModVersion;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ModVersion>
 */
class ModVersionFactory extends Factory
{
    protected $model = ModVersion::class;

    public function definition(): array
    {
        $version = sprintf('%d.%d.0.0', fake()->numberBetween(1, 3), fake()->numberBetween(0, 9));

        return [
            'mod_id' => Mod::factory(),
            'version' => $version,
            'author' => fake()->name(),
            'status' => ModStatus::Validated,
            'manifest_type' => ManifestType::FsModDesc,
            'disk' => 'mods',
            'file_path' => 'farming-simulator/sample/' . $version . '/FS25_Sample.zip',
            'original_filename' => 'FS25_Sample.zip',
            'file_size' => fake()->numberBetween(15000, 5000000),
            'sha256' => hash('sha256', uniqid('mod_', true)),
            'metadata' => ['desc_version' => 95, 'multiplayer' => true],
            'changelog' => 'Versão inicial validada.',
            'downloads_count' => fake()->numberBetween(0, 500),
        ];
    }
}

