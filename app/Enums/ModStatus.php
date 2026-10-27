<?php

namespace App\Enums;

enum ModStatus: string
{
    case Validated = 'validated';
    case Warning = 'warning';
    case Invalid = 'invalid';

    public function label(): string
    {
        return match ($this) {
            self::Validated => 'VALIDATED',
            self::Warning => 'VALIDATED W/ WARNINGS',
            self::Invalid => 'XML/JSON SYNTAX ERROR',
        };
    }

    public function shortLabel(): string
    {
        return match ($this) {
            self::Validated => 'VALIDATED',
            self::Warning => 'WARNING',
            self::Invalid => 'XML ERROR',
        };
    }

    public function badgeClass(): string
    {
        return match ($this) {
            self::Validated => 'badge badge--validated',
            self::Warning => 'badge badge--warning',
            self::Invalid => 'badge badge--invalid',
        };
    }

    public function isDownloadable(): bool
    {
        return $this !== self::Invalid;
    }
}
