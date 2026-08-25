<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\TurfImageResource;
use App\Models\Turf;
use App\Models\TurfImage;
use App\Services\MediaService;
use App\Support\UploadedFiles;
use Illuminate\Http\Request;
use Throwable;

class TurfImageController extends Controller
{
    public function upload(Request $request, $turfId)
    {
        $turf = Turf::with('images')->findOrFail($turfId);
        $files = UploadedFiles::all($request);

        if (!$files) {
            \Log::warning('Admin turf photo missing', [
                'turf_id' => $turfId,
                'content_type' => $request->header('Content-Type'),
                'keys' => array_keys($request->all()),
                'files' => array_keys($request->allFiles()),
            ]);

            return response()->json(['message' => 'No files received'], 400);
        }

        $media = app(MediaService::class);
        $uploaded = [];
        $existing = $turf->images->count();

        foreach ($files as $index => $file) {
            if ($existing + count($uploaded) >= 9) {
                break;
            }
            try {
                $cover = $existing === 0 && $index === 0;
                $path = $media->putUploadedFile(
                    $file,
                    $media->turfPhotoStem($turf->id, $cover && count($uploaded) === 0),
                    true
                );
                $uploaded[] = TurfImage::create([
                    'turf_id' => $turf->id,
                    'image_path' => $path,
                    'is_primary' => $cover && count($uploaded) === 0,
                    'order' => $existing + count($uploaded),
                ]);
            } catch (Throwable $e) {
                \Log::error('Admin turf image upload failed', [
                    'turf_id' => $turf->id,
                    'error' => $e->getMessage(),
                ]);

                return response()->json([
                    'success' => false,
                    'message' => $e->getMessage(),
                ], 422);
            }
        }

        if (!$uploaded) {
            return response()->json(['message' => 'No valid images uploaded'], 400);
        }

        return response()->json([
            'message' => 'Images uploaded successfully',
            'images' => TurfImageResource::collection($uploaded),
            'count' => count($uploaded),
        ]);
    }

    public function delete($id)
    {
        $image = TurfImage::findOrFail($id);
        $image->delete();

        return response()->json(['message' => 'Image deleted successfully']);
    }

    public function setPrimary($id)
    {
        $image = TurfImage::findOrFail($id);

        TurfImage::where('turf_id', $image->turf_id)->update(['is_primary' => false]);
        $image->update(['is_primary' => true]);

        return response()->json(['message' => 'Primary image updated']);
    }
}
