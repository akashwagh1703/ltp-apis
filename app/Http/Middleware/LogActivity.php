<?php

namespace App\Http\Middleware;

use App\Models\ActivityLog;
use Closure;
use Illuminate\Http\Request;

class LogActivity
{
    public function handle(Request $request, Closure $next)
    {
        $response = $next($request);

        if (auth('sanctum')->check()) {
            $user = auth('sanctum')->user();
            $userType = match (get_class($user)) {
                \App\Models\Admin::class => 'admin',
                \App\Models\Player::class => 'player',
                \App\Models\Owner::class => 'owner',
                default => 'unknown',
            };

            ActivityLog::create([
                'user_type' => $userType,
                'user_id' => $user->id,
                'action' => $request->method() . ' ' . $request->path(),
                'description' => $request->method() . ' request to ' . $request->path(),
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'metadata' => [
                    'params' => $request->except(['password', 'password_confirmation']),
                ],
            ]);
        }

        return $response;
    }
}
