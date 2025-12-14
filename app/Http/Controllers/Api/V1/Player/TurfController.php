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
            ->where('status', 'approved')
            ->whereHas('owner.activeSubscription');

        if ($request->city) {
            $query->where('city', $request->city);
        }

        if ($request->search) {
            $query->where('name', 'like', "%{$request->search}%");
        }

        // Location-based sorting (nearest first)
        if ($request->lat && $request->lng) {
            $lat = $request->lat;
            $lng = $request->lng;
            
            $query->whereNotNull('latitude')
                  ->whereNotNull('longitude')
                  ->selectRaw(
                      'turfs.*, owners.commission_rate,
                      (6371 * acos(cos(radians(?)) * cos(radians(latitude)) * cos(radians(longitude) - radians(?)) + sin(radians(?)) * sin(radians(latitude)))) AS distance',
                      [$lat, $lng, $lat]
                  )
                  ->leftJoin('owners', 'turfs.owner_id', '=', 'owners.id')
                  ->orderBy('distance', 'ASC')
                  ->orderByRaw('COALESCE(owners.commission_rate, 5.00) DESC');
        } else {
            // Default sorting by commission rate
            $query->leftJoin('owners', 'turfs.owner_id', '=', 'owners.id')
                  ->orderByRaw('COALESCE(owners.commission_rate, 5.00) DESC')
                  ->orderBy('turfs.is_featured', 'DESC')
                  ->select('turfs.*');
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
