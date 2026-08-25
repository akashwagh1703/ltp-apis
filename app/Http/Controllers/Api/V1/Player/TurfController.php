<?php

namespace App\Http\Controllers\Api\V1\Player;

use App\Http\Controllers\Controller;
use App\Http\Resources\TurfResource;
use App\Models\Player;
use App\Models\Turf;
use Illuminate\Http\Request;

class TurfController extends Controller
{
    public function index(Request $request)
    {
        $query = Turf::with(['images', 'amenities', 'pricing', 'owner.activeSubscription', 'owner'])
            ->withAvg('approvedReviews', 'rating')
            ->withCount('approvedReviews')
            ->where('turfs.status', 'approved')
            ->whereHas('owner', function ($ownerQuery) {
                $ownerQuery->withUpiQr()->visibleInPlayerSearch();
            });

        $player = auth('sanctum')->user();
        if ($player instanceof Player) {
            $query->withExists([
                'favorites as is_favorite' => fn ($q) => $q->where('player_id', $player->id),
            ]);
        }

        if ($request->city) {
            $query->where('turfs.city', 'ilike', '%'.$request->city.'%');
        }

        if ($request->search) {
            $query->where('turfs.name', 'ilike', '%'.$request->search.'%');
        }

        if ($request->filled('sport_type')) {
            $query->where('turfs.sport_type', 'ilike', '%'.$request->sport_type.'%');
        }

        if ($request->boolean('favorites') && $player instanceof Player) {
            $query->whereHas('favorites', fn ($q) => $q->where('player_id', $player->id));
        }

        if ($request->filled('lat') && $request->filled('lng')) {
            $lat = (float) $request->lat;
            $lng = (float) $request->lng;
            $haversine = '(6371 * acos(LEAST(1, GREATEST(-1, cos(radians(?)) * cos(radians(turfs.latitude)) * cos(radians(turfs.longitude) - radians(?)) + sin(radians(?)) * sin(radians(turfs.latitude))))))';

            $query->select('turfs.*')
                ->selectRaw(
                    '(CASE WHEN turfs.latitude IS NULL OR turfs.longitude IS NULL THEN NULL ELSE '.$haversine.' END) AS distance',
                    [$lat, $lng, $lat]
                );

            if ($request->filled('max_km')) {
                $query->whereRaw(
                    'turfs.latitude IS NOT NULL AND turfs.longitude IS NOT NULL AND '.$haversine.' <= ?',
                    [$lat, $lng, $lat, (float) $request->max_km]
                );
            }

            $query->orderByRaw('distance ASC NULLS LAST')
                ->orderByDesc('turfs.is_featured');
        } else {
            $query->orderByDesc('turfs.is_featured')->orderByDesc('turfs.id');
        }

        return TurfResource::collection($query->paginate(15));
    }

    public function show($id)
    {
        $query = Turf::with(['images', 'amenities', 'pricing', 'reviews', 'owner'])
            ->withAvg('approvedReviews', 'rating')
            ->withCount('approvedReviews')
            ->where('status', 'approved')
            ->whereHas('owner', function ($ownerQuery) {
                $ownerQuery->withUpiQr()->visibleInPlayerSearch();
            });

        $player = auth('sanctum')->user();
        if ($player instanceof Player) {
            $query->withExists([
                'favorites as is_favorite' => fn ($q) => $q->where('player_id', $player->id),
            ]);
        }

        $turf = $query->findOrFail($id);

        return new TurfResource($turf);
    }

    public function featured()
    {
        $query = Turf::with(['images', 'amenities', 'owner.activeSubscription'])
            ->withAvg('approvedReviews', 'rating')
            ->withCount('approvedReviews')
            ->where('status', 'approved')
            ->where('is_featured', true)
            ->whereHas('owner', function ($ownerQuery) {
                $ownerQuery->withUpiQr()->visibleInPlayerSearch();
            });

        $player = auth('sanctum')->user();
        if ($player instanceof Player) {
            $query->withExists([
                'favorites as is_favorite' => fn ($q) => $q->where('player_id', $player->id),
            ]);
        }

        return TurfResource::collection($query->limit(10)->get());
    }
}
