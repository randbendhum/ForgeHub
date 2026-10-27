<?php

namespace Database\Factories;

use App\Models\Developer;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends Factory<Developer>
 */
class DeveloperFactory extends Factory
{
    protected $model = Developer::class;

    protected static ?string $password = null;

    public function definition(): array
    {
        $name = fake()->name();

        return [
            'name' => $name,
            'username' => Str::slug($name, '_') . '_' . fake()->unique()->numberBetween(10, 9999),
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('password'),
            'bio' => fake()->sentence(10),
            'remember_token' => Str::random(10),
        ];
    }
}

