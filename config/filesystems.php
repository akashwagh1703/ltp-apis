<?php

return [

    'default' => env('FILESYSTEM_DISK', 'local'),

    /*
    | Public turf photos, avatars, and UPI QRs.
    | Set FILESYSTEM_MEDIA_DISK=minio after buckets exist; otherwise public disk.
    */
    'media_disk' => env('FILESYSTEM_MEDIA_DISK', 'public'),

    'disks' => [

        'local' => [
            'driver' => 'local',
            'root' => storage_path('app/private'),
            'serve' => true,
            'throw' => false,
        ],

        'public' => [
            'driver' => 'local',
            'root' => storage_path('app/public'),
            'url' => rtrim((string) env('APP_URL'), '/') . '/storage',
            'visibility' => 'public',
            'throw' => false,
        ],

        'minio' => [
            'driver' => 's3',
            'key' => env('MINIO_ACCESS_KEY', env('AWS_ACCESS_KEY_ID')),
            'secret' => env('MINIO_SECRET_KEY', env('AWS_SECRET_ACCESS_KEY')),
            'region' => env('MINIO_REGION', 'us-east-1'),
            'bucket' => env('MINIO_BUCKET', 'ltp-media'),
            'endpoint' => env('MINIO_ENDPOINT'),
            'use_path_style_endpoint' => env('MINIO_USE_PATH_STYLE', true),
            'url' => env('MINIO_URL'),
            'throw' => false,
        ],

        'minio_private' => [
            'driver' => 's3',
            'key' => env('MINIO_ACCESS_KEY', env('AWS_ACCESS_KEY_ID')),
            'secret' => env('MINIO_SECRET_KEY', env('AWS_SECRET_ACCESS_KEY')),
            'region' => env('MINIO_REGION', 'us-east-1'),
            'bucket' => env('MINIO_PRIVATE_BUCKET', 'ltp-private'),
            'endpoint' => env('MINIO_ENDPOINT'),
            'use_path_style_endpoint' => env('MINIO_USE_PATH_STYLE', true),
            'throw' => false,
        ],

        's3' => [
            'driver' => 's3',
            'key' => env('AWS_ACCESS_KEY_ID'),
            'secret' => env('AWS_SECRET_ACCESS_KEY'),
            'region' => env('AWS_DEFAULT_REGION'),
            'bucket' => env('AWS_BUCKET'),
            'url' => env('AWS_URL'),
            'endpoint' => env('AWS_ENDPOINT'),
            'use_path_style_endpoint' => env('AWS_USE_PATH_STYLE_ENDPOINT', false),
            'throw' => false,
        ],

    ],

    'links' => [
        public_path('storage') => storage_path('app/public'),
    ],

];
