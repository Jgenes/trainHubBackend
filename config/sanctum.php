<?php

use Laravel\Sanctum\Sanctum;

return [

    /*
    |--------------------------------------------------------------------------
    | Stateful Domains
    |--------------------------------------------------------------------------
    |
    | Hapa tunaeleza domains/hosts ambazo zitapata stateful API cookies.
    | Hii ni muhimu kwa React/Vite frontend, localhost, na production domains.
    | Hii inahakikisha CSRF na Sanctum cookies zinatumika vizuri.
    |
    */
    'stateful' => explode(',', env('SANCTUM_STATEFUL_DOMAINS', sprintf(
        '%s%s',
        'localhost,localhost:5173,127.0.0.1,127.0.0.1:8000,::1',
        Sanctum::currentApplicationUrlWithPort()
    ))),

    /*
    |--------------------------------------------------------------------------
    | Sanctum Guards
    |--------------------------------------------------------------------------
    |
    | Hapa tunataja guards ambazo Sanctum itatumia ku-authenticate requests.
    | Kwa SPA ya kwanza (first-party), default ni 'web'.
    |
    */
    'guard' => ['web'],

    /*
    |--------------------------------------------------------------------------
    | Expiration Minutes
    |--------------------------------------------------------------------------
    |
    | Hapa tunaweka muda wa token kuisha. Null inamaanisha token haija-expire.
    |
    */
    'expiration' => null,

    /*
    |--------------------------------------------------------------------------
    | Token Prefix
    |--------------------------------------------------------------------------
    |
    | Hii inatumiwa kwa security scans. Default ni empty.
    |
    */
    'token_prefix' => env('SANCTUM_TOKEN_PREFIX', ''),

    /*
    |--------------------------------------------------------------------------
    | Sanctum Middleware
    |--------------------------------------------------------------------------
    |
    | Middleware hizi ni kwa SPA authentication. Hizi zinahakikisha session
    | authentication na CSRF validation zinakaa safi.
    |
    */
    'middleware' => [
        'authenticate_session' => Laravel\Sanctum\Http\Middleware\AuthenticateSession::class,
        'encrypt_cookies' => Illuminate\Cookie\Middleware\EncryptCookies::class,
        'validate_csrf_token' => Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class,
    ],

];
