<?php

namespace App\Http\Controllers\Api\V1\Player;

use App\Http\Controllers\Controller;
use App\Http\Resources\TurfResource;
use App\Models\Turf;
use Illuminate\Http\Request;

class TurfController extends Controller
{
    public function index(Request $request)
    {
        $query = Turf::with(['images', 'amenities', 'pricing', 'owner.activeSubscription', 'owner'])
            ->where('turfs.status', 'approved')
            ->whereHas('owner.activeSubscription');

        if ($request->city) {
            $query->where('turfs.city', $request->city);
        }

        if ($request->search) {
            $query->where('turfs.name', 'like', "%{$request->search}%");
        }

        // Location-based sorting (nearest first)
        if ($request->lat && $request->lng) {
            $lat = $request->lat;
            $lng = $request->lng;
            
            $query->whereNotNull('turfs.latitude')
                  ->whereNotNull('turfs.longitude')
                  ->selectRaw(
                      'turfs.*,
                      (6371 * acos(cos(radians(?)) * cos(radians(turfs.latitude)) * cos(radians(turfs.longitude) - radians(?)) + sin(radians(?)) * sin(radians(turfs.latitude)))) AS distance',
                      [$lat, $lng, $lat]
                  )
                  ->orderBy('distance', 'ASC')
                  ->orderBy('turfs.is_featured', 'DESC');
        } else {
            // Default sorting by featured status
            $query->orderBy('turfs.is_featured', 'DESC')
                  ->orderBy('turfs.id', 'DESC');
        }

        $turfs = $query->paginate(15);

        return TurfResource::collection($turfs);
    }

    public function show($id)
    {
        $turf = Turf::with(['images', 'amenities', 'pricing', 'reviews'])
            ->where('status', 'approved')
            ->findOrFail($id);

        return new TurfResource($turf);
    }

    public function featured()
    {
        $turfs = Turf::with(['images', 'amenities', 'owner.activeSubscription'])
            ->where('status', 'approved')
            ->where('is_featured', true)
            ->whereHas('owner.activeSubscription')
            ->limit(10)
            ->get();

        return TurfResource::collection($turfs);
    }
}
