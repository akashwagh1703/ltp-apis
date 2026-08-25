<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreTurfRequest;
use App\Http\Requests\UpdateTurfRequest;
use App\Http\Resources\TurfResource;
use App\Models\Turf;
use App\Models\TurfImage;
use App\Models\TurfAmenity;
use App\Models\TurfPricing;
use App\Support\UploadedFiles;
use Illuminate\Http\Request;

class TurfController extends Controller
{
    public function index(Request $request)
    {
        $query = Turf::with(['owner', 'images', 'amenities', 'pricing'])->latest();

        if ($request->status) {
            $query->where('status', $request->status);
        } else {
            $query->where('status', '!=', Turf::STATUS_DRAFT);
        }

        if ($request->owner_id) {
            $query->where('owner_id', $request->owner_id);
        }

        if ($request->search) {
            $query->where('name', 'like', "%{$request->search}%");
        }

        $turfs = $query->paginate(15);

        return TurfResource::collection($turfs);
    }

    public function store(StoreTurfRequest $request)
    {
        $data = [
            'owner_id' => $request->owner_id,
            'name' => $request->name,
            'description' => $request->description,
            'sport_type' => $request->sport_type,
            'address_line1' => $request->address_line1,
            'address_line2' => $request->address_line2,
            'city' => $request->city,
            'state' => $request->state,
            'pincode' => $request->pincode,
            'latitude' => $request->latitude,
            'longitude' => $request->longitude,
            'size' => $request->size,
            'capacity' => $request->capacity,
            'opening_time' => $request->opening_time,
            'closing_time' => $request->closing_time,
            'slot_duration' => $request->slot_duration,
            'pricing_type' => $request->pricing_type,
            'uniform_price' => $request->uniform_price,
            'status' => Turf::STATUS_SUBMITTED,
            'submitted_at' => now(),
        ];
        
        $turf = Turf::create($data);

        $this->storeUploadedImages($turf, UploadedFiles::all($request));

        // Handle amenities (JSON string from FormData)
        if ($request->amenities) {
            $amenities = is_string($request->amenities) ? json_decode($request->amenities, true) : $request->amenities;
            if (is_array($amenities)) {
                foreach ($amenities as $amenity) {
                    TurfAmenity::create([
                        'turf_id' => $turf->id,
                        'amenity_name' => is_array($amenity) ? $amenity['name'] : $amenity,
                    ]);
                }
            }
        }

        // Handle dynamic pricing (JSON string from FormData)
        if ($request->pricing_type === 'dynamic' && $request->pricing) {
            $pricing = is_string($request->pricing) ? json_decode($request->pricing, true) : $request->pricing;
            if (is_array($pricing)) {
                foreach ($pricing as $price) {
                    TurfPricing::create([
                        'turf_id' => $turf->id,
                        'day_type' => $price['day_type'],
                        'time_slot' => $price['time_slot'],
                        'price' => $price['price'],
                    ]);
                }
            }
        }

        // Send WhatsApp notification to owner (non-blocking)
        try {
            $owner = $turf->owner;
            if ($owner && $owner->phone) {
                $whatsappService = app(\App\Services\WhatsAppService::class);
                $whatsappService->sendTurfDetails($owner->phone, [
                    'name' => $turf->name,
                    'city' => $turf->city,
                    'state' => $turf->state,
                    'sport_type' => $turf->sport_type,
                    'status' => $turf->status,
                    'owner_name' => $owner->name,
                    'owner_phone' => $owner->phone,
                ]);
            }
        } catch (\Exception $e) {
            \Log::warning('WhatsApp turf notification failed: ' . $e->getMessage());
        }

        return response()->json(new TurfResource($turf->load(['images', 'amenities', 'pricing'])), 201);
    }

    public function show($id)
    {
        $turf = Turf::with(['owner', 'images', 'amenities', 'pricing'])->findOrFail($id);
        return new TurfResource($turf);
    }

    public function update(UpdateTurfRequest $request, $id)
    {
        $turf = Turf::findOrFail($id);
        
        $data = [
            'name' => $request->name,
            'description' => $request->description,
            'sport_type' => $request->sport_type,
            'address_line1' => $request->address_line1,
            'address_line2' => $request->address_line2,
            'city' => $request->city,
            'state' => $request->state,
            'pincode' => $request->pincode,
            'latitude' => $request->latitude,
            'longitude' => $request->longitude,
            'size' => $request->size,
            'capacity' => $request->capacity,
            'opening_time' => $request->opening_time,
            'closing_time' => $request->closing_time,
            'slot_duration' => $request->slot_duration,
            'pricing_type' => $request->pricing_type,
            'uniform_price' => $request->uniform_price,
        ];
        
        $turf->update($data);

        $uploaded = UploadedFiles::all($request);
        if ($uploaded) {
            $old = $turf->images()->get();
            $added = $this->storeUploadedImages($turf, $uploaded);
            if ($added > 0) {
                $old->each->delete();
            }
        }

        // Update amenities if provided
        if ($request->has('amenities')) {
            $amenities = is_string($request->amenities) ? json_decode($request->amenities, true) : $request->amenities;
            if (is_array($amenities)) {
                $turf->amenities()->delete();
                foreach ($amenities as $amenity) {
                    TurfAmenity::create([
                        'turf_id' => $turf->id,
                        'amenity_name' => is_array($amenity) ? $amenity['name'] : $amenity,
                    ]);
                }
            }
        }

        // Update pricing if provided
        if ($request->has('pricing_type') && $request->pricing_type === 'dynamic' && $request->has('pricing')) {
            $pricing = is_string($request->pricing) ? json_decode($request->pricing, true) : $request->pricing;
            if (is_array($pricing)) {
                $turf->pricing()->delete();
                foreach ($pricing as $price) {
                    TurfPricing::create([
                        'turf_id' => $turf->id,
                        'day_type' => $price['day_type'],
                        'time_slot' => $price['time_slot'],
                        'price' => $price['price'],
                    ]);
                }
                
                // Update existing slot prices for future dates
                $this->updateSlotPrices($turf->id);
            }
        } elseif ($request->has('uniform_price') && $request->pricing_type === 'uniform') {
            // Update slot prices for uniform pricing changes
            $this->updateSlotPrices($turf->id);
        }

        return new TurfResource($turf->load(['images', 'amenities', 'pricing']));
    }

    public function destroy($id)
    {
        $turf = Turf::findOrFail($id);
        $turf->delete();
        return response()->json(['message' => 'Turf deleted successfully']);
    }

    public function approve($id)
    {
        $turf = Turf::with('owner')->findOrFail($id);

        if ($turf->status !== Turf::STATUS_SUBMITTED) {
            return response()->json([
                'success' => false,
                'message' => 'Only submitted turfs can be approved.',
            ], 422);
        }

        if ($error = $this->upiRequiredResponse($turf)) {
            return $error;
        }

        $turf->update(['status' => Turf::STATUS_LIVE, 'rejection_reason' => null]);
        return response()->json([
            'message' => 'Turf approved. It can now take bookings.',
            'data' => new TurfResource($turf),
        ]);
    }

    public function reject(Request $request, $id)
    {
        $turf = Turf::findOrFail($id);
        $reason = $request->input('reason') ?: $request->input('admin_notes') ?: 'Does not meet requirements';
        $turf->update([
            'status' => Turf::STATUS_DRAFT,
            'rejection_reason' => $reason,
        ]);
        return response()->json([
            'message' => 'Told the owner. They can fix it and submit again.',
            'data' => new TurfResource($turf),
        ]);
    }

    public function suspend(Request $request, $id)
    {
        $turf = Turf::findOrFail($id);
        $turf->update(['status' => 'suspended']);
        return response()->json(['message' => 'Turf suspended successfully', 'data' => new TurfResource($turf)]);
    }

    public function activate($id)
    {
        $turf = Turf::with('owner')->findOrFail($id);

        if ($error = $this->upiRequiredResponse($turf)) {
            return $error;
        }

        $turf->update(['status' => 'approved']);
        return response()->json(['message' => 'Turf activated successfully', 'data' => new TurfResource($turf)]);
    }

    public function toggleFeatured($id)
    {
        $turf = Turf::findOrFail($id);
        $turf->update(['is_featured' => !$turf->is_featured]);
        return response()->json([
            'message' => $turf->is_featured ? 'Turf marked as featured' : 'Turf removed from featured',
            'data' => new TurfResource($turf)
        ]);
    }

    private function upiRequiredResponse(Turf $turf)
    {
        $owner = $turf->owner;
        if ($owner && $owner->hasUpiSetup()) {
            return null;
        }

        return response()->json([
            'success' => false,
            'error' => [
                'code' => 'UPI_REQUIRED',
                'message' => 'Owner must add a UPI ID and QR before this turf can go live.',
            ],
            'message' => 'Owner must add a UPI ID and QR before this turf can go live.',
        ], 422);
    }

    private function updateSlotPrices($turfId)
    {
        try {
            $turf = Turf::with('pricing')->find($turfId);
            if (!$turf) return;

            // Update future available slots only
            $slots = \App\Models\TurfSlot::where('turf_id', $turfId)
                ->where('status', 'available')
                ->where('date', '>=', now()->toDateString())
                ->get();

            $updated = 0;
            foreach ($slots as $slot) {
                $dayType = \Carbon\Carbon::parse($slot->date)->isWeekend() ? 'weekend' : 'weekday';
                $slotTime = \Carbon\Carbon::parse($slot->start_time);
                $price = $this->calculateSlotPrice($turf, $dayType, $slotTime);
                
                if ($slot->price != $price) {
                    $slot->price = $price;
                    $slot->save();
                    $updated++;
                }
            }

            \Log::info("Updated {$updated} slot prices for turf {$turfId}");
        } catch (\Exception $e) {
            \Log::error("Failed to update slot prices for turf {$turfId}: " . $e->getMessage());
        }
    }

    private function calculateSlotPrice($turf, $dayType, $slotTime)
    {
        if ($turf->pricing_type === 'uniform') {
            return $turf->uniform_price ?? 500.00;
        }

        $hour = $slotTime->hour;
        if ($hour >= 6 && $hour < 12) $timeSlot = 'morning';
        elseif ($hour >= 12 && $hour < 17) $timeSlot = 'afternoon';
        elseif ($hour >= 17 && $hour < 21) $timeSlot = 'evening';
        else $timeSlot = 'night';

        $pricing = $turf->pricing->where('day_type', $dayType)
            ->where('time_slot', $timeSlot)
            ->first();

        return $pricing ? $pricing->price : ($turf->uniform_price ?? 500.00);
    }

    private function storeUploadedImages(Turf $turf, $images): int
    {
        $images = is_array($images) ? $images : [$images];
        $media = app(\App\Services\MediaService::class);
        $count = 0;

        foreach ($images as $image) {
            if (!$image || !$image->isValid()) {
                continue;
            }
            if ($count >= 9) {
                break;
            }
            try {
                $path = $media->putUploadedFile(
                    $image,
                    $media->turfPhotoStem($turf->id, $count === 0),
                    true
                );
                TurfImage::create([
                    'turf_id' => $turf->id,
                    'image_path' => $path,
                    'is_primary' => $count === 0,
                    'order' => $count,
                ]);
                $count++;
            } catch (\RuntimeException $e) {
                \Log::warning('Image upload skipped: ' . $e->getMessage());
            }
        }

        return $count;
    }
}
