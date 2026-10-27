<?php

namespace App\Enums;

enum IssueSeverity: string
{
    case Error = 'error';
    case Warning = 'warning';

    public function label(): string
    {
        return match ($this) {
            self::Error => 'CRITICAL ERROR',
            self::Warning => 'STRUCTURAL WARNING',
        };
    }

    public function badgeClass(): string
    {
        return match ($this) {
            self::Error => 'badge badge--invalid',
            self::Warning => 'badge badge--warning',
        };
    }
}
