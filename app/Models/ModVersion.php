<?php

namespace App\Models;

use App\Enums\ManifestType;
use App\Enums\ModStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ModVersion extends Model
{
    use HasFactory;

    protected $fillable = [
        'mod_id',
        'version',
        'author',
        'status',
        'manifest_type',
        'disk',
        'file_path',
        'original_filename',
        'file_size',
        'sha256',
        'metadata',
        'changelog',
        'downloads_count',
    ];

    protected function casts(): array
    {
        return [
            'status' => ModStatus::class,
            'manifest_type' => ManifestType::class,
            'metadata' => 'array',
            'file_size' => 'integer',
            'downloads_count' => 'integer',
        ];
    }

    public function mod(): BelongsTo
    {
        return $this->belongsTo(Mod::class);
    }

    public function issues(): HasMany
    {
        return $this->hasMany(ValidationIssue::class);
    }

    public function gameVersions(): BelongsToMany
    {
        return $this->belongsToMany(GameVersion::class, 'game_version_mod_version')
            ->orderByDesc('sort_order');
    }

    public function formattedSize(): string
    {
        $bytes = max(0, (int) $this->file_size);
        if ($bytes < 1024) {
            return $bytes . ' B';
        }
        if ($bytes < 1048576) {
            return number_format($bytes / 1024, 1) . ' KB';
        }

        return number_format($bytes / 1048576, 2) . ' MB';
    }
}

