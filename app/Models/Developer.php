<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class Developer extends Authenticatable
{
    use HasFactory, Notifiable;

    protected $table = 'developers';

    protected $fillable = [
        'name',
        'username',
        'email',
        'password',
        'bio',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'username';
    }

    public function mods(): HasMany
    {
        return $this->hasMany(Mod::class);
    }

    public function modVersions(): HasManyThrough
    {
        return $this->hasManyThrough(ModVersion::class, Mod::class);
    }
}

