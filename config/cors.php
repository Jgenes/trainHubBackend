<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Cross-Origin Resource Sharing (CORS) Configuration
    |--------------------------------------------------------------------------
    */

    'paths' => ['api/*', 'sanctum/csrf-cookie', 'login', 'logout'],

    'allowed_methods' => ['*'],

    // Ensure BOTH localhost and 127.0.0.1 are listed if you use them interchangeably
    'allowed_origins' => [
        'http://localhost:5173', 
    'http://127.0.0.1:5173'
    ],

    'allowed_origins_patterns' => [],

    'allowed_headers' => ['*'],

    'exposed_headers' => [],

    'max_age' => 0,

    // THIS IS THE KEY FIX: Change to true
    'supports_credentials' => true,

];