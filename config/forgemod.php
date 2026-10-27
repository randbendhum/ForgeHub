<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Limites de Upload e Inspeção de Pacotes (ZIP/JAR/XML/JSON)
    |--------------------------------------------------------------------------
    */
    'disk' => env('MODS_DISK', 'mods'),
    'max_upload_kb' => (int) env('FORGEMOD_MAX_UPLOAD_KB', 102400), // 100 MB
    'allowed_extensions' => ['zip', 'jar', 'xml', 'json', 'info', 'toml'],

    'archive' => [
        'max_entries' => 60000,
        'max_uncompressed_bytes' => 1073741824, // 1 GB
        'max_manifest_bytes' => 2097152,        // 2 MB
        'max_compression_ratio' => 150,
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

