<?php

namespace App\Http\Controllers\Api\V1\Owner;

use App\Http\Controllers\Controller;
use App\Http\Resources\TurfImageResource;
use App\Models\Turf;
use App\Models\TurfImage;
use App\Services\MediaService;
use Illuminate\Http\Request;
use RuntimeException;

class MediaController extends Controller
{
    public function __construct(protected MediaService $media)
    {
    }

    public function presign(Request $request)
    {
        if (!$this->media->usingObjectStore()) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'DIRECT_UPLOAD',
                    'message' => 'Upload the photo on the existing form endpoint.',
                ],
            ], 409);
        }

        $validated = $request->validate([
            'purpose' => 'required|in:turf_photo,upi_qr',
            'turf_id' => 'required_if:purpose,turf_photo|nullable|integer',
            'content_type' => 'nullable|string|max:80',
        ]);

        if ($validated['purpose'] === 'turf_photo') {
            $this->ownedTurf((int) $validated['turf_id']);
        }

        $contentType = $validated['content_type'] ?? 'application/octet-stream';
        $tmp = $this->media->tmpStem('owner', (int) $request->user()->id);

        try {
            $payload = $this->media->presignPut($tmp, $contentType);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'Could not prepare upload.',
            ], 503);
        }

        return response()->json(['success' => true, 'data' => $payload]);
    }

    public function complete(Request $request)
    {
        $validated = $request->validate([
            'key' => 'required|string|max:500',
            'purpose' => 'required|in:turf_photo,upi_qr',
            'turf_id' => 'required_if:purpose,turf_photo|nullable|integer',
        ]);

        try {
            $this->media->assertTmpKey($validated['key'], 'owner', (int) $request->user()->id);

            if ($validated['purpose'] === 'upi_qr') {
                $owner = $request->user();
                $this->media->delete($owner->upi_qr_path);
                $path = $this->media->completeTmp($validated['key'], $this->media->ownerQrStem($owner->id), false);
                $owner->update(['upi_qr_path' => $path]);

                return response()->json([
                    'success' => true,
                    'message' => 'QR saved',
                    'data' => ['qr_url' => $owner->fresh()->upiQrUrl(), 'has_upi' => $owner->fresh()->hasUpiSetup()],
                ]);
            }

            $turf = $this->ownedTurf((int) $validated['turf_id'], ['images']);
            if ($turf->images->count() >= 9) {
                return response()->json(['success' => false, 'message' => 'You can add a cover plus 8 photos.'], 422);
            }

            $cover = $turf->images->count() === 0;
            $path = $this->media->completeTmp(
                $validated['key'],
                $this->media->turfPhotoStem($turf->id, $cover),
                true
            );
            $image = TurfImage::create([
                'turf_id' => $turf->id,
                'image_path' => $path,
                'is_primary' => $cover,
                'order' => $turf->images->count(),
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Photo added',
                'data' => (new TurfImageResource($image))->resolve(),
            ]);
        } catch (RuntimeException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }
    }

    protected function ownedTurf(int $id, array $with = []): Turf
    {
        return Turf::with($with)
            ->where('id', $id)
            ->where('owner_id', auth()->id())
            ->firstOrFail();
    }
}
