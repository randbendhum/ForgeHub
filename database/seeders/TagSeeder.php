<?php

namespace Database\Seeders;

use App\Models\Tag;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class TagSeeder extends Seeder
{
    public function run(): void
    {
        $tags = [
            'Vehicles & Equipment',
            'Heavy Tractors',
            'Harvesters',
            'European Maps',
            'Lua Script',
            'Multiplayer',
            'Fabric',
            'Forge',
            'FPS Optimization',
            'Realism & Physics',
        ];

        foreach ($tags as $name) {
            Tag::query()->updateOrCreate(
                ['slug' => Str::slug($name)],
                ['name' => $name]
            );
        }
    }
}
