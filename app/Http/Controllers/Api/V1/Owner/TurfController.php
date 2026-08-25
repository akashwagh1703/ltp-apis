<?php

namespace App\Http\Controllers\Api\V1\Owner;

use App\Http\Controllers\Controller;
use App\Http\Resources\TurfImageResource;
use App\Http\Resources\TurfResource;
use App\Models\Turf;
use App\Models\TurfImage;
use App\Models\TurfPricing;
use App\Models\TurfUpdateRequest;
use Illuminate\Http\Request;

class TurfController extends Controller
{
    public const SPORTS = ['cricket', 'football', 'badminton', 'tennis', 'basketball', 'volleyball'];

    public function index(Request $request)
    {
        $turfs = Turf::with(['images', 'amenities', 'pricing'])
            ->where('owner_id', $request->user()->id)
            ->latest()
            ->get();

        return TurfResource::collection($turfs);
    }

    public function show($id)
    {
        $turf = $this->ownedTurf($id, ['images', 'amenities', 'pricing']);

        return new TurfResource($turf);
    }

    public function store(Request $request)
    {
        $turf = Turf::create([
            'owner_id' => $request->user()->id,
            'name' => 'Untitled turf',
            'description' => '',
            'sport_type' => 'football',
            'address_line1' => '',
            'city' => '',
            'state' => '',
            'pincode' => '000000',
            'opening_time' => '06:00:00',
            'closing_time' => '22:00:00',
            'slot_duration' => 60,
            'pricing_type' => 'uniform',
            'status' => Turf::STATUS_DRAFT,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Draft started',
            'data' => (new TurfResource($turf->load(['images', 'pricing'])))->resolve(),
        ], 201);
    }

    public function update(Request $request, $id)
    {
        $turf = $this->ownedTurf($id, ['images', 'pricing']);

        if ($turf->status === Turf::STATUS_SUSPENDED) {
            return response()->json([
                'success' => false,
                'error' => ['code' => 'SUSPENDED', 'message' => 'This turf is suspended. Contact LTP.'],
                'message' => 'This turf is suspended. Contact LTP.',
            ], 403);
        }

        foreach (['opening_time', 'closing_time'] as $timeField) {
            $value = $request->input($timeField);
            if (is_string($value) && preg_match('/^\d{2}:\d{2}:\d{2}/', $value)) {
                $request->merge([$timeField => substr($value, 0, 5)]);
            }
        }
        if ($request->has('pincode') && $request->input('pincode') === '') {
            $request->merge(['pincode' => '000000']);
        }

        $validated = $request->validate([
            'name' => 'sometimes|nullable|string|max:255',
            'description' => 'sometimes|nullable|string|max:1000',
            'sport_type' => 'sometimes|nullable|in:' . implode(',', self::SPORTS),
            'address_line1' => 'sometimes|nullable|string|max:255',
            'address_line2' => 'sometimes|nullable|string|max:255',
            'city' => 'sometimes|nullable|string|max:100',
            'state' => 'sometimes|nullable|string|max:100',
            'pincode' => 'sometimes|nullable|string|size:6',
            'latitude' => 'sometimes|nullable|numeric|between:-90,90',
            'longitude' => 'sometimes|nullable|numeric|between:-180,180',
            'opening_time' => 'sometimes|nullable|date_format:H:i',
            'closing_time' => 'sometimes|nullable|date_format:H:i',
            'slot_duration' => 'sometimes|nullable|integer|in:30,60,90,120',
            'uniform_price' => 'sometimes|nullable|numeric|min:0',
            'weekend_price' => 'sometimes|nullable|numeric|min:0',
        ]);

        $identityKeys = ['name', 'sport_type', 'address_line1', 'address_line2', 'city', 'state', 'pincode', 'latitude', 'longitude'];
        $identityChanged = false;
        if ($turf->status === Turf::STATUS_LIVE) {
            foreach ($identityKeys as $key) {
                if (array_key_exists($key, $validated) && (string) $turf->{$key} !== (string) $validated[$key]) {
                    $identityChanged = true;
                    break;
                }
            }
        }

        $play = $validated;
        unset($play['weekend_price']);
        $turf->fill($play);

        if (isset($validated['uniform_price']) || array_key_exists('weekend_price', $validated)) {
            $weekday = $validated['uniform_price'] ?? $turf->uniform_price;
            $weekend = $validated['weekend_price'] ?? null;
            $this->syncPrices($turf, $weekday, $weekend);
        }

        if ($identityChanged) {
            $turf->status = Turf::STATUS_SUBMITTED;
            $turf->submitted_at = now();
        }

        $turf->save();

        return response()->json([
            'success' => true,
            'message' => $identityChanged
                ? 'Saved. Name or location changes need a quick LTP review.'
                : 'Saved',
            'data' => (new TurfResource($turf->fresh(['images', 'pricing'])))->resolve(),
        ]);
    }

    public function submit(Request $request, $id)
    {
        $owner = $request->user();
        $turf = $this->ownedTurf($id, ['images', 'pricing']);

        if ($turf->status === Turf::STATUS_LIVE) {
            return response()->json(['success' => true, 'message' => 'Already live', 'data' => (new TurfResource($turf))->resolve()]);
        }

        if ($turf->status === Turf::STATUS_SUSPENDED) {
            return response()->json([
                'success' => false,
                'error' => ['code' => 'SUSPENDED', 'message' => 'This turf is suspended.'],
            ], 403);
        }

        if (!$owner->hasUpiSetup()) {
            return response()->json([
                'success' => false,
                'error' => ['code' => 'UPI_REQUIRED', 'message' => 'Add UPI to submit this turf.'],
                'message' => 'Add UPI to submit this turf.',
            ], 422);
        }

        $errors = $this->submitErrors($turf);
        if ($errors) {
            return response()->json([
                'success' => false,
                'error' => ['code' => 'INCOMPLETE', 'message' => $errors[0]],
                'message' => $errors[0],
                'errors' => $errors,
            ], 422);
        }

        $turf->update([
            'status' => Turf::STATUS_SUBMITTED,
            'submitted_at' => now(),
            'rejection_reason' => null,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Submitted. LTP will review it.',
            'data' => (new TurfResource($turf->fresh(['images', 'pricing'])))->resolve(),
        ]);
    }

    public function uploadImage(Request $request, $id)
    {
        $turf = $this->ownedTurf($id, ['images']);

        $request->validate([
            'photo' => 'required|file|max:12288',
        ]);

        if ($turf->images->count() >= 9) {
            return response()->json([
                'success' => false,
                'message' => 'You can add a cover plus 8 photos.',
            ], 422);
        }

        try {
            $media = app(\App\Services\MediaService::class);
            $cover = $turf->images->count() === 0;
            $path = $media->putUploadedFile(
                $request->file('photo'),
                $media->turfPhotoStem($turf->id, $cover),
                true
            );
        } catch (\RuntimeException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }

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
    }

    public function deleteImage($id, $imageId)
    {
        $turf = $this->ownedTurf($id, ['images']);
        $image = TurfImage::where('turf_id', $turf->id)->where('id', $imageId)->firstOrFail();

        $wasPrimary = $image->is_primary;
        $image->delete();

        if ($wasPrimary) {
            $next = TurfImage::where('turf_id', $turf->id)->orderBy('order')->first();
            if ($next) {
                $next->update(['is_primary' => true]);
            }
        }

        return response()->json(['success' => true, 'message' => 'Photo removed']);
    }

    public function requestUpdate(Request $request, $id)
    {
        $turf = $this->ownedTurf($id);

        TurfUpdateRequest::create([
            'turf_id' => $turf->id,
            'owner_id' => auth()->id(),
            'request_type' => 'update',
            'changes' => json_encode($request->updates),
            'status' => 'pending',
        ]);

        return response()->json(['message' => 'Update request submitted']);
    }

    public function getUpdateRequests(Request $request)
    {
        $requests = TurfUpdateRequest::with('turf')
            ->where('owner_id', $request->user()->id)
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $requests,
        ]);
    }

    protected function ownedTurf($id, array $with = []): Turf
    {
        return Turf::with($with)
            ->where('id', $id)
            ->where('owner_id', auth()->id())
            ->firstOrFail();
    }

    protected function submitErrors(Turf $turf): array
    {
        $errors = [];
        if (!filled($turf->name) || $turf->name === 'Untitled turf') {
            $errors[] = 'Add a turf name.';
        }
        if (!in_array($turf->sport_type, self::SPORTS, true)) {
            $errors[] = 'Pick a sport.';
        }
        if (!filled($turf->city) || !filled($turf->address_line1)) {
            $errors[] = 'Add city and address.';
        }
        if (!filled($turf->opening_time) || !filled($turf->closing_time)) {
            $errors[] = 'Set opening and closing times.';
        }
        if (!$turf->slot_duration) {
            $errors[] = 'Set slot length.';
        }
        if ($turf->uniform_price === null || (float) $turf->uniform_price <= 0) {
            $errors[] = 'Set a price.';
        }
        if ($turf->images->count() < 1) {
            $errors[] = 'Add at least one photo.';
        }

        return $errors;
    }

    protected function syncPrices(Turf $turf, $weekday, $weekend): void
    {
        $weekday = $weekday !== null && $weekday !== '' ? (float) $weekday : null;
        $weekend = $weekend !== null && $weekend !== '' ? (float) $weekend : null;

        if ($weekday === null) {
            return;
        }

        $turf->uniform_price = $weekday;

        if ($weekend === null || $weekend === $weekday) {
            $turf->pricing_type = 'uniform';
            TurfPricing::where('turf_id', $turf->id)->delete();
            return;
        }

        $turf->pricing_type = 'dynamic';
        foreach (['weekday' => $weekday, 'weekend' => $weekend] as $day => $price) {
            foreach (['morning', 'afternoon', 'evening', 'night'] as $slot) {
                TurfPricing::updateOrCreate(
                    ['turf_id' => $turf->id, 'day_type' => $day, 'time_slot' => $slot],
                    ['price' => $price]
                );
            }
        }
    }
}
