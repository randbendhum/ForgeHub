<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Limites de Upload e Inspeção de Pacotes (ZIP/JAR/XML/JSON)
    |--------------------------------------------------------------------------
    */
    'disk' => env('MODS_DISK', 'mods'),
    'max_upload_kb' => (int) env('FORGEMOD_MAX_UPLOAD_KB', 51200), // 50 MB
    'allowed_extensions' => ['zip', 'jar', 'xml', 'json'],

    'archive' => [
        'max_entries' => 10000,
        'max_uncompressed_bytes' => 536870912, // 512 MB
        'max_manifest_bytes' => 1048576,       // 1 MB
        'max_compression_ratio' => 100,
    ],

    /*
    |--------------------------------------------------------------------------
    | Mapa de descVersion do Farming Simulator (GIANTS Engine)
    |--------------------------------------------------------------------------
    */
    'fs_desc_versions' => [
        'FS19' => ['min' => 40, 'max' => 59],
        'FS22' => ['min' => 60, 'max' => 89],
        'FS25' => ['min' => 90, 'max' => 120],
    ],
    'fs_known_max_desc_version' => 120,
];

