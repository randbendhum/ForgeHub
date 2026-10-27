<?php

namespace App\Enums;

enum ManifestType: string
{
    case FsModDesc = 'fs_moddesc';
    case FabricJson = 'fabric_json';
    case ForgeMcmod = 'forge_mcmod';

    public function label(): string
    {
        return match ($this) {
            self::FsModDesc => 'Farming Simulator (modDesc.xml)',
            self::FabricJson => 'Minecraft Fabric (fabric.mod.json)',
            self::ForgeMcmod => 'Minecraft Forge / NeoForge (mods.toml / mcmod.info)',
        };
    }

    public function gameSlug(): string
    {
        return match ($this) {
            self::FsModDesc => 'farming-simulator',
            self::FabricJson, self::ForgeMcmod => 'minecraft',
        };
    }

    public function filename(): string
    {
        return match ($this) {
            self::FsModDesc => 'modDesc.xml',
            self::FabricJson => 'fabric.mod.json',
            self::ForgeMcmod => 'mcmod.info',
        };
    }
}

