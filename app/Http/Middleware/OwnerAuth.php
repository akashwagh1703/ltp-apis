<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class OwnerAuth
{
    public function handle(Request $request, Closure $next)
    {
        if (!auth('sanctum')->check() || !auth('sanctum')->user() instanceof \App\Models\Owner) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        return $next($request);
    }
}
