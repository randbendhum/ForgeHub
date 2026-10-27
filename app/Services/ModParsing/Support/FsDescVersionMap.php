<?php

declare(strict_types=1);

namespace App\Services\ModParsing\Support;

final class FsDescVersionMap
{
    /**
     * Resolve a versão do Farming Simulator a partir do atributo descVersion do modDesc.xml.
     *
     * @return list<string>
     */
    public static function resolve(int $descVersion): array
    {
        if ($descVersion >= 40 && $descVersion <= 59) {
            return ['FS19'];
        }

        if ($descVersion >= 60 && $descVersion <= 89) {
            return ['FS22'];
        }

        if ($descVersion >= 90) {
            return ['FS25'];
        }

        return [];
    }

    public static function minimumSupported(): int
    {
        return 40;
    }

    public static function knownMaximum(): int
    {
        if (function_exists('app') && app()->bound('config')) {
            return (int) config('forgemod.fs_known_max_desc_version', 120);
        }

        return 120;
    }
}
