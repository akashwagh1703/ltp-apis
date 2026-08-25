<?php

namespace App\Support;

class CorsOrigins
{
    public static function allowed(): array
    {
        return config('cors.allowed_origins', []);
    }

    public static function headerFor(?string $origin): ?string
    {
        if (!filled($origin)) {
            return null;
        }

        foreach (self::allowed() as $allowed) {
            if (strcasecmp($origin, $allowed) === 0) {
                return $origin;
            }
        }

        return null;
    }
}
