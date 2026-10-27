<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Mod extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'developer_id',
        'game_id',
        'identifier',
        'name',
        'slug',
        'description',
        'latest_version_id',
        'downloads_count',
        'published_at',
    ];

    protected function casts(): array
    {
        return [
            'published_at' => 'datetime',
            'downloads_count' => 'integer',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    public function developer(): BelongsTo
    {
        return $this->belongsTo(Developer::class);
    }

    public function game(): BelongsTo
    {
        return $this->belongsTo(Game::class);
    }

    public function versions(): HasMany
    {
        return $this->hasMany(ModVersion::class)->latest();
    }

    public function latestVersion(): BelongsTo
    {
        return $this->belongsTo(ModVersion::class, 'latest_version_id');
    }

    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(Tag::class, 'mod_tag');
    }

    public function scopePublished(Builder $query): Builder
    {
        return $query->whereNotNull('published_at')->whereNotNull('latest_version_id');
    }

    public function scopeForGame(Builder $query, ?string $gameSlug): Builder
    {
        if (! $gameSlug) {
            return $query;
        }

        return $query->whereHas('game', fn (Builder $q) => $q->where('slug', $gameSlug));
    }

    public function scopeWithTag(Builder $query, ?string $tagSlug): Builder
    {
        if (! $tagSlug) {
            return $query;
        }

        return $query->whereHas('tags', fn (Builder $q) => $q->where('slug', $tagSlug));
    }

    public function scopeSearch(Builder $query, ?string $term): Builder
    {
        $term = trim((string) $term);
        if ($term === '') {
            return $query;
        }

        return $query->where(function (Builder $q) use ($term) {
            $q->where('name', 'like', "%{$term}%")
                ->orWhere('identifier', 'like', "%{$term}%")
                ->orWhere('description', 'like', "%{$term}%");
        });
    }
}

