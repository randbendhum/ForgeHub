<?php

namespace Database\Seeders;

use App\Models\Developer;
use App\Services\ModPublisher;
use Illuminate\Database\Seeder;
use ZipArchive;

class DemoSeeder extends Seeder
{
    public function run(ModPublisher $publisher): void
    {
        $demoDev = Developer::query()->updateOrCreate(
            ['email' => 'demo@forgemod.test'],
            [
                'name' => 'Vasco Almeida (AgroTech Modding)',
                'username' => 'vasco_agrotech',
                'password' => 'password',
                'bio' => 'GIANTS Engine mod engineer (FS22/FS25) building heavy machinery & Lua/XML telemetry tools.',
            ]
        );

        $mcDev = Developer::query()->updateOrCreate(
            ['email' => 'voxel@forgemod.test'],
            [
                'name' => 'Ines Costa (VoxelForge Labs)',
                'username' => 'ines_voxelforge',
                'password' => 'password',
                'bio' => 'Creator of high-performance rendering and industrial automation mods for Minecraft Fabric & Forge.',
            ]
        );

        if ($demoDev->mods()->withTrashed()->count() > 0) {
            return;
        }

        $tmpDir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'forgemod_seed_' . uniqid();
        @mkdir($tmpDir, 0777, true);

        try {
            // 1. Validated FS25 Mod (complete ZIP with modDesc.xml + icon_valtra.dds)
            $zip1 = $tmpDir . DIRECTORY_SEPARATOR . 'FS25_ValtraTSeriesPro.zip';
            $this->buildZip($zip1, [
                'modDesc.xml' => <<<'XML'
<?xml version="1.0" encoding="utf-8" standalone="no" ?>
<modDesc descVersion="95">
    <author>Vasco Almeida (AgroTech)</author>
    <version>1.2.0.0</version>
    <title>
        <en>Valtra T-Series Pro Pack</en>
        <de>Valtra T-Serie Profi-Paket</de>
    </title>
    <description>
        <en><![CDATA[Heavy-duty Valtra T-Series agricultural tractor with full telemetry, interactive cabin controls, and realistic powershift gearbox curves for Farming Simulator 25.]]></en>
    </description>
    <iconFilename>icon_valtra.dds</iconFilename>
    <multiplayer supported="true" />
    <storeItems>
        <storeItem xmlFilename="valtraT255.xml" />
        <storeItem xmlFilename="valtraT235.xml" />
    </storeItems>
    <specializations>
        <specialization name="agroTelemetry" className="AgroTelemetry" filename="scripts/AgroTelemetry.lua" />
    </specializations>
</modDesc>
XML,
                'icon_valtra.dds' => 'DDS_BINARY_STUB_VALTRA',
                'valtraT255.xml' => '<vehicle type="tractor"/>',
            ]);

            $v1 = $publisher->publishFromPath(
                developer: $demoDev,
                sourceFilePath: $zip1,
                originalFilename: 'FS25_ValtraTSeriesPro.zip',
                tagNames: ['Heavy Tractors', 'Realism & Physics'],
                changelog: 'Added fuel consumption telemetry and updated descVersion to 95 (FS25 Patch 1.3).',
            );
            $v1->mod->update(['downloads_count' => 842]);
            $v1->update(['downloads_count' => 842]);

            // 2. FS22 Mod Validated with Warnings (missing <en> title localization & missing icon inside ZIP)
            $zip2 = $tmpDir . DIRECTORY_SEPARATOR . 'FS22_DouroValleyMap.zip';
            $this->buildZip($zip2, [
                'modDesc.xml' => <<<'XML'
<?xml version="1.0" encoding="utf-8" standalone="no" ?>
<modDesc descVersion="79">
    <author>Vasco Almeida &amp; LusitaniaMod</author>
    <version>2.0.1.0</version>
    <title>
        <de>Douro Valley Vineyards 4x</de>
    </title>
    <description>
        <en><![CDATA[4x European map inspired by the terraced vineyards of the Douro Valley, featuring 94 farmlands, cooperative wineries, and olive groves.]]></en>
    </description>
    <iconFilename>map_preview.dds</iconFilename>
    <multiplayer supported="true" />
</modDesc>
XML,
            ]);

            $v2 = $publisher->publishFromPath(
                developer: $demoDev,
                sourceFilePath: $zip2,
                originalFilename: 'FS22_DouroValleyMap.zip',
                tagNames: ['European Maps'],
                changelog: 'Fixed railway bridge collisions and added grape harvest contracts.',
            );
            $v2->mod->update(['downloads_count' => 1420]);
            $v2->update(['downloads_count' => 1420]);

            // 3. Malformed XML Mod (quarantined to showcase the vivid red XML Syntax Error badge in the Inventory Table)
            $badXmlPath = $tmpDir . DIRECTORY_SEPARATOR . 'modDesc_broken_harvester.xml';
            file_put_contents($badXmlPath, <<<'XML'
<?xml version="1.0" encoding="utf-8" standalone="no" ?>
<modDesc descVersion="95">
    <author>Vasco Almeida</author>
    <version>1.0.0.0</version>
    <title>
        <en>Claas Lexion 8900 Prototype
    </title>
    <description>
        <en>Unclosed <en> tag above causes a fatal libxml parser syntax error.</en>
    </description>
</modDesc>
XML);

            $publisher->publishFromPath(
                developer: $demoDev,
                sourceFilePath: $badXmlPath,
                originalFilename: 'modDesc_broken_harvester.xml',
                changelog: 'Attempted submission with unclosed <en> XML tag on line 6.',
            );

            // 4. Validated Minecraft Fabric Mod (.jar)
            $jar1 = $tmpDir . DIRECTORY_SEPARATOR . 'lithium-radon-fabric-1.21.4.jar';
            $this->buildZip($jar1, [
                'fabric.mod.json' => json_encode([
                    'schemaVersion' => 1,
                    'id' => 'lithium_radon_boost',
                    'version' => '0.14.2',
                    'name' => 'Lithium Radon Tick Optimizer',
                    'description' => 'General-purpose server tick, block physics, entity AI, and chunk lighting optimization mod for Minecraft Fabric without breaking vanilla mechanics.',
                    'authors' => ['Ines Costa', 'VoxelForge Labs'],
                    'license' => 'LGPL-3.0-only',
                    'environment' => '*',
                    'depends' => [
                        'fabricloader' => '>=0.16.0',
                        'minecraft' => '>=1.21.1 <=1.21.4',
                        'java' => '>=21',
                    ],
                ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
            ]);

            $v4 = $publisher->publishFromPath(
                developer: $mcDev,
                sourceFilePath: $jar1,
                originalFilename: 'lithium-radon-fabric-1.21.4.jar',
                tagNames: ['FPS Optimization'],
                changelog: 'Full Minecraft 1.21.4 compatibility and 28% server MSPT reduction.',
            );
            $v4->mod->update(['downloads_count' => 3190]);
            $v4->update(['downloads_count' => 3190]);
        } finally {
            foreach (glob($tmpDir . DIRECTORY_SEPARATOR . '*') ?: [] as $f) {
                @unlink($f);
            }
            @rmdir($tmpDir);
        }
    }

    /**
     * @param  array<string, string>  $files
     */
    private function buildZip(string $zipPath, array $files): void
    {
        $zip = new ZipArchive();
        $zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE);
        foreach ($files as $entryName => $content) {
            $zip->addFromString($entryName, $content);
        }
        $zip->close();
    }
}
