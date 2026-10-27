<?php

use App\Http\Middleware\AdminMiddleware;
use App\Support\Cors;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'admin' => AdminMiddleware::class,
        ]);

        // The API normally sits behind a reverse proxy: Apache in the Docker
        // image, Render's router, or an ngrok tunnel. Trust the immediately
        // calling IP's forwarding headers so Laravel sees the real
        // scheme/host/port. Without this an HTTPS request behind the proxy
        // looks like plain HTTP, which breaks generated URLs, redirects and
        // secure cookies.
        //
        // Note: this closure runs before .env is loaded, so read runtime
        // configuration from config/*.php (or a provider), never env() here.
        $middleware->trustProxies(at: '*', headers:
            Request::HEADER_X_FORWARDED_FOR
            | Request::HEADER_X_FORWARDED_HOST
            | Request::HEADER_X_FORWARDED_PORT
            | Request::HEADER_X_FORWARDED_PROTO
            | Request::HEADER_X_FORWARDED_PREFIX
        );

        // Sanctum SPA support (session cookies / CSRF for first-party fronts).
        $middleware->statefulApi();

        // API clients authenticate with Bearer tokens, so CSRF is not needed.
        $middleware->validateCsrfTokens(except: ['api/*']);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // Always answer API requests with JSON, including 404/405/500.
        $exceptions->shouldRenderJsonWhen(
            fn ($request, $e) => $request->is('api/*') || $request->expectsJson()
        );

        // Error responses never reach HandleCors' success path, so without this
        // a 500 shows up in the browser as a misleading CORS error.
        $exceptions->respond(
            fn ($response, $e, $request) => Cors::apply($request, $response)
        );
    })->create();
