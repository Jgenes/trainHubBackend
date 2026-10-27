<?php

namespace App\Support;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Adds CORS headers to responses that do not pass through HandleCors' normal
 * success path — most importantly exception/error responses. Without this a
 * 500 would surface in the browser as a misleading "No Access-Control-Allow-
 * Origin header" error instead of the real API error.
 */
class Cors
{
    public static function apply(Request $request, Response $response): Response
    {
        $origin = (string) $request->headers->get('Origin');

        if ($origin === '' || ! self::originAllowed($origin)) {
            return $response;
        }

        // Never duplicate / overwrite a header HandleCors already set.
        if (! $response->headers->has('Access-Control-Allow-Origin')) {
            $allowed = (array) config('cors.allowed_origins', ['*']);
            $credentials = (bool) config('cors.supports_credentials', false);

            if (in_array('*', $allowed, true) && ! $credentials) {
                $response->headers->set('Access-Control-Allow-Origin', '*');
            } else {
                $response->headers->set('Access-Control-Allow-Origin', $origin);
                self::vary($response, 'Origin');
            }

            if ($credentials) {
                $response->headers->set('Access-Control-Allow-Credentials', 'true');
            }
        }

        return $response;
    }

    public static function originAllowed(string $origin): bool
    {
        $allowed = (array) config('cors.allowed_origins', ['*']);

        if (in_array('*', $allowed, true) || in_array($origin, $allowed, true)) {
            return true;
        }

        foreach ((array) config('cors.allowed_origins_patterns', []) as $pattern) {
            if (is_string($pattern) && @preg_match($pattern, $origin) === 1) {
                return true;
            }
        }

        return false;
    }

    private static function vary(Response $response, string $value): void
    {
        $current = (string) $response->headers->get('Vary', '');

        if (stripos($current, $value) !== false) {
            return;
        }

        $response->headers->set('Vary', $current === '' ? $value : $current.', '.$value);
    }
}
