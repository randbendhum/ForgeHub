<?php

namespace Database\Seeders;

use App\Models\Game;
use App\Models\GameVersion;
use Illuminate\Database\Seeder;

class GameSeeder extends Seeder
{
    public function run(): void
    {
        $fs = Game::query()->updateOrCreate(
            ['slug' => 'farming-simulator'],
            [
                'name' => 'Farming Simulator',
                'short_code' => 'FS',
                'manifest_filename' => 'modDesc.xml',
            ]
        );

        foreach ([
            ['name' => 'FS25', 'sort_order' => 100],
            ['name' => 'FS22', 'sort_order' => 90],
            ['name' => 'FS19', 'sort_order' => 80],
        ] as $ver) {
            GameVersion::query()->updateOrCreate(
                ['game_id' => $fs->id, 'name' => $ver['name']],
                ['sort_order' => $ver['sort_order']]
            );
        }

        $mc = Game::query()->updateOrCreate(
            ['slug' => 'minecraft'],
            [
                'name' => 'Minecraft',
                'short_code' => 'MC',
                'manifest_filename' => 'fabric.mod.json',
            ]
        );

        foreach ([
            ['name' => '1.21.4', 'sort_order' => 110],
            ['name' => '1.21.1', 'sort_order' => 105],
            ['name' => '1.21.x', 'sort_order' => 100],
            ['name' => '1.20.4', 'sort_order' => 95],
            ['name' => '1.20.1', 'sort_order' => 92],
            ['name' => '1.20.x', 'sort_order' => 90],
            ['name' => '1.19.x', 'sort_order' => 80],
            ['name' => '1.12.2', 'sort_order' => 60],
        ] as $ver) {
            GameVersion::query()->updateOrCreate(
                ['game_id' => $mc->id, 'name' => $ver['name']],
                ['sort_order' => $ver['sort_order']]
            );
        }
    }
}

