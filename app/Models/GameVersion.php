<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class GameVersion extends Model
{
    use HasFactory;

    protected $fillable = [
        'game_id',
        'name',
        'sort_order',
    ];

    public function game(): BelongsTo
    {
        return $this->belongsTo(Game::class);
    }

    public function modVersions(): BelongsToMany
    {
        return $this->belongsToMany(ModVersion::class, 'game_version_mod_version');
    }
}

