<?php

return [
    'sim' => [
        'base_url' => env('SIM_BASE_URL', 'http://localhost:8081'),
    ],
    'mela' => [
        'storage_path' => env('MELA_STORAGE_PATH', storage_path('sftp')),
    ],
];
