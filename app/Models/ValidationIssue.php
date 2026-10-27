<?php

namespace App\Models;

use App\Enums\IssueSeverity;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ValidationIssue extends Model
{
    use HasFactory;

    protected $fillable = [
        'mod_version_id',
        'severity',
        'code',
        'message',
        'line',
        'column',
        'context',
    ];

    protected function casts(): array
    {
        return [
            'severity' => IssueSeverity::class,
            'line' => 'integer',
            'column' => 'integer',
        ];
    }

    public function modVersion(): BelongsTo
    {
        return $this->belongsTo(ModVersion::class);
    }
}

