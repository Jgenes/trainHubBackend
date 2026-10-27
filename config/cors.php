<?php

/*
|--------------------------------------------------------------------------
| Cross-Origin Resource Sharing (CORS)
|--------------------------------------------------------------------------
|
| The API is consumed by a browser SPA that usually lives on a different host
| than the API. By default every origin is allowed. This is safe here because
| authentication uses Bearer tokens (not cookies); set CORS_ALLOWED_ORIGINS to
| an explicit comma-separated list in production if you want to lock it down.
|
| Note: when `supports_credentials` is true, Fruitcake's CORS service echoes
| the request's Origin instead of returning a literal "*" — browsers accept
| that, whereas "*" + credentials is rejected. So an allow-all config works
| both with and without credentials.
|
| IMPORTANT: after changing any CORS_* value (or config/cors.php) on a server
| that caches config, run `php artisan config:clear` (or re-run
| `php artisan config:cache`). Otherwise the old allowlist stays active.
*/

$origins = env('CORS_ALLOWED_ORIGINS');

if ($origins === null || trim((string) $origins) === '' || trim((string) $origins) === '*') {
    $origins = ['*'];
} else {
    $origins = array_values(array_filter(array_map('trim', explode(',', (string) $origins))));
}

// One optional regex for dynamic preview hosts (e.g. #^https://.*\.vercel\.app$#).
$pattern = trim((string) env('CORS_ALLOWED_ORIGIN_PATTERNS', ''));
$patterns = $pattern === '' ? [] : [$pattern];

return [

    // Apply to every path so even /up, error pages and web routes get headers.
    'paths' => ['*'],

    'allowed_methods' => ['*'],

    'allowed_origins' => $origins,

    'allowed_origins_patterns' => $patterns,

    'allowed_headers' => ['*'],

    'exposed_headers' => [],

    'max_age' => 0,

    'supports_credentials' => filter_var(env('CORS_SUPPORTS_CREDENTIALS', true), FILTER_VALIDATE_BOOLEAN),
];
