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
use Illuminate\Http\Request;

class TurfController extends Controller
{
    public function index(Request $request)
    {
        $query = Turf::with(['owner', 'images', 'amenities', 'pricing'])->latest();

        if ($request->status) {
            $query->where('status', $request->status);
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
        ];
        
        $turf = Turf::create($data);

        // Handle image uploads
        if ($request->hasFile('images')) {
            $images = $request->file('images');
            $validImages = array_filter($images, function($image) {
                return $image !== null && is_object($image) && method_exists($image, 'isValid') && $image->isValid();
            });
            
            $index = 0;
            foreach ($validImages as $image) {
                $path = $image->store('turfs', 'public');
                if ($path) {
                    TurfImage::create([
                        'turf_id' => $turf->id,
                        'image_path' => $path,
                        'is_primary' => $index === 0,
                        'order' => $index,
                    ]);
                    $index++;
                }
            }
        }

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
        
        if ($request->has('status')) {
            $data['status'] = $request->status;
        }
        
        $turf->update($data);

        // Update images only if new files uploaded
        if ($request->hasFile('images')) {
            $images = $request->file('images');
            $validImages = array_filter($images, function($image) {
                return $image !== null && is_object($image) && method_exists($image, 'isValid') && $image->isValid();
            });
            
            if (count($validImages) > 0) {
                $turf->images()->delete();
                $index = 0;
                foreach ($validImages as $image) {
                    $path = $image->store('turfs', 'public');
                    if ($path) {
                        TurfImage::create([
                            'turf_id' => $turf->id,
                            'image_path' => $path,
                            'is_primary' => $index === 0,
                            'order' => $index,
                        ]);
                        $index++;
                    }
                }
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
            }
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
        $turf = Turf::findOrFail($id);
        $turf->update(['status' => 'approved']);
        return response()->json(['message' => 'Turf approved successfully', 'data' => new TurfResource($turf)]);
    }

    public function reject(Request $request, $id)
    {
        $turf = Turf::findOrFail($id);
        $turf->update(['status' => 'suspended']);
        return response()->json(['message' => 'Turf rejected successfully', 'data' => new TurfResource($turf)]);
    }

    public function suspend(Request $request, $id)
    {
        $turf = Turf::findOrFail($id);
        $turf->update(['status' => 'suspended']);
        return response()->json(['message' => 'Turf suspended successfully', 'data' => new TurfResource($turf)]);
    }

    public function activate($id)
    {
        $turf = Turf::findOrFail($id);
        $turf->update(['status' => 'approved']);
        return response()->json(['message' => 'Turf activated successfully', 'data' => new TurfResource($turf)]);
    }
}
