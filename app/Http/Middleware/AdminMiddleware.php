<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class AdminMiddleware
{
    public function handle(Request $request, Closure $next)
    {
        // Get user via Sanctum guard (Laravel 10+)
        $user = auth('sanctum')->user();

        // If no user (unauthenticated)
        if (!$user) {
            return response()->json(['message' => 'Unauthenticated'], 401);
        }

        // If user is not admin
        if ($user->role !== 'admin') {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        return $next($request);
    }
}
