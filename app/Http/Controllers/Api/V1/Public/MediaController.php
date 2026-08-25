<?php

namespace App\Http\Controllers\Api\V1\Public;

use App\Http\Controllers\Controller;
use App\Services\MediaService;
use Illuminate\Http\Request;

class MediaController extends Controller
{
    public function show(Request $request, string $path)
    {
        $path = ltrim(rawurldecode($path), '/');
        if ($path === '' || str_contains($path, '..')) {
            abort(404);
        }

        $media = app(MediaService::class);
        if (!$media->isPublicObjectKey($path)) {
            abort(404);
        }

        $disk = $media->disk();
        if (!$disk->exists($path)) {
            abort(404);
        }

        $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        $mime = match ($ext) {
            'webp' => 'image/webp',
            'png' => 'image/png',
            'gif' => 'image/gif',
            'jpg', 'jpeg' => 'image/jpeg',
            default => 'application/octet-stream',
        };

        return response($disk->get($path), 200, [
            'Content-Type' => $mime,
            'Cache-Control' => 'public, max-age=31536000, immutable',
        ]);
    }
}
