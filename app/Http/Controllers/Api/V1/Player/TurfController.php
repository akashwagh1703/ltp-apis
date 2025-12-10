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
        $query = Turf::with(['images', 'amenities', 'pricing', 'owner.activeSubscription'])
            ->where('status', 'approved')
            ->whereHas('owner.activeSubscription');

        if ($request->city) {
            $query->where('city', $request->city);
        }

        if ($request->search) {
            $query->where('name', 'like', "%{$request->search}%");
        }

        if ($request->lat && $request->lng) {
            // Nearby turfs logic (simplified)
            $query->whereNotNull('latitude');
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
