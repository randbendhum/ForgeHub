<?php

namespace Database\Factories;

use App\Models\Developer;
use App\Models\Game;
use App\Models\Mod;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Mod>
 */
class ModFactory extends Factory
{
    protected $model = Mod::class;

    public function definition(): array
    {
        $name = 'FS25 ' . ucwords(fake()->unique()->words(3, true));
        $identifier = 'FS25_' . Str::studly($name) . '_' . fake()->unique()->numberBetween(10, 9999);

        return [
            'developer_id' => Developer::factory(),
            'game_id' => Game::factory(),
            'identifier' => $identifier,
            'name' => $name,
            'slug' => Str::slug($name . '-' . fake()->unique()->numberBetween(10, 9999)),
            'description' => fake()->paragraph(),
            'downloads_count' => fake()->numberBetween(0, 1500),
            'published_at' => now(),
        ];
    }
}

