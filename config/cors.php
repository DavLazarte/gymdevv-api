<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Cross-Origin Resource Sharing (CORS) Configuration
    |--------------------------------------------------------------------------
    */

    'paths' => ['api/*', 'sanctum/csrf-cookie', 'up'],

    'allowed_methods' => ['*'],

    'allowed_origins' => array_filter(array_map('trim', explode(',', env(
        'CORS_ALLOWED_ORIGINS',
        'http://localhost:3000,http://127.0.0.1:3000,https://fitdev.devsign.ar,https://fitdev.devsing.ar,https://devsign.ar,https://devsing.ar'
    )))),

    'allowed_origins_patterns' => [
        '#^https?://localhost(:\d+)?$#',
        '#^https?://127\.0\.0\.1(:\d+)?$#',
        '#^https://([a-z0-9-]+\.)*devsign\.ar$#i',
        '#^https://([a-z0-9-]+\.)*devsing\.ar$#i',
    ],

    'allowed_headers' => [
        'Content-Type',
        'Authorization',
        'X-Requested-With',
        'Accept',
        'Origin',
        'X-Tenant-Host',
        'X-Tenant-Id',
        'X-Tenant-Slug',
        'X-Superadmin-Key',
        'Cache-Control',
        'Pragma',
        'Expires',
    ],

    'exposed_headers' => [],

    'max_age' => 86400,

    'supports_credentials' => true,

];
