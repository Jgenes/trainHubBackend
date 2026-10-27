<?php

use App\Http\Middleware\AdminMiddleware;
use App\Support\Cors;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

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
