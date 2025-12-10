<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class PlayerAuth
{
    public function handle(Request $request, Closure $next)
    {
        if (!auth('sanctum')->check() || !auth('sanctum')->user() instanceof \App\Models\Player) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        return $next($request);
    }
}
