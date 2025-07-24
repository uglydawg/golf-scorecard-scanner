<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | OCR Configuration
    |--------------------------------------------------------------------------
    |
    | This section defines the OCR providers and their configuration.
    | You can set the default provider and configure each provider's settings.
    |
    */

    'ocr' => [
        'default' => env('SCORECARD_OCR_PROVIDER', 'mock'),

        'providers' => [
            'mock' => [
                'driver' => 'mock',
                'confidence' => 0.95,
            ],

            'ocrspace' => [
                'driver' => 'ocrspace',
                'api_key' => env('OCRSPACE_API_KEY'),
                'base_url' => env('OCRSPACE_BASE_URL', 'https://api.ocr.space/parse/image'),
                'language' => env('OCRSPACE_LANGUAGE', 'eng'),
                'timeout' => env('OCRSPACE_TIMEOUT', 30),
            ],

            'google' => [
                'driver' => 'google',
                'credentials_path' => env('GOOGLE_CLOUD_CREDENTIALS_PATH'),
                'project_id' => env('GOOGLE_CLOUD_PROJECT_ID'),
                'timeout' => env('GOOGLE_VISION_TIMEOUT', 30),
            ],

            'aws' => [
                'driver' => 'aws',
                'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
                'access_key_id' => env('AWS_ACCESS_KEY_ID'),
                'secret_access_key' => env('AWS_SECRET_ACCESS_KEY'),
                'timeout' => env('AWS_TEXTRACT_TIMEOUT', 30),
            ],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Storage Configuration
    |--------------------------------------------------------------------------
    |
    | Configure where uploaded scorecard images are stored and how long
    | they should be retained.
    |
    */

    'storage' => [
        'disk' => env('SCORECARD_STORAGE_DISK', 'local'),
        'path' => env('SCORECARD_STORAGE_PATH', 'scorecards'),
        'cleanup_after_days' => env('SCORECARD_CLEANUP_DAYS', 30),
    ],

    /*
    |--------------------------------------------------------------------------
    | Processing Configuration
    |--------------------------------------------------------------------------
    |
    | Configure processing parameters such as confidence thresholds,
    | file size limits, and allowed file types.
    |
    */

    'processing' => [
        'confidence_threshold' => env('SCORECARD_CONFIDENCE_THRESHOLD', 0.8),
        'max_file_size' => env('SCORECARD_MAX_FILE_SIZE', 10485760), // 10MB in bytes
        'allowed_types' => ['jpg', 'jpeg', 'png', 'gif', 'bmp', 'webp'],
        'image_quality' => env('SCORECARD_IMAGE_QUALITY', 85),
        'max_width' => env('SCORECARD_MAX_WIDTH', 2048),
        'max_height' => env('SCORECARD_MAX_HEIGHT', 2048),
    ],

    /*
    |--------------------------------------------------------------------------
    | Database Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration for database table names and connection settings.
    |
    */

    'database' => [
        'connection' => env('SCORECARD_DB_CONNECTION', null),
        'tables' => [
            'scorecard_scans' => 'scorecard_scans',
            'golf_courses' => 'golf_courses',
            'golf_holes' => 'golf_holes',
            'player_scores' => 'player_scores',
            'scorecard_players' => 'scorecard_players',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | API Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration for API endpoints and rate limiting.
    |
    */

    'api' => [
        'prefix' => env('SCORECARD_API_PREFIX', 'api'),
        'middleware' => ['auth:sanctum'],
        'rate_limit' => env('SCORECARD_RATE_LIMIT', '60,1'), // 60 requests per minute
    ],

];