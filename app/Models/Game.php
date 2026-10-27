<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Game extends Model
{
    use HasFactory;

    protected $fillable = [
        'slug',
        'name',
        'short_code',
        'manifest_filename',
    ];

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    public function mods(): HasMany
    {
        return $this->hasMany(Mod::class);
    }

    public function gameVersions(): HasMany
    {
        return $this->hasMany(GameVersion::class)->orderByDesc('sort_order');
    }
}

