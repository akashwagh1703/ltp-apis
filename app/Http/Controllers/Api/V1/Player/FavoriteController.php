<?php

namespace App\Http\Controllers\Api\V1\Player;

use App\Http\Controllers\Controller;
use App\Http\Resources\TurfResource;
use App\Models\PlayerFavorite;
use App\Models\Turf;
use Illuminate\Http\Request;

class FavoriteController extends Controller
{
    public function index(Request $request)
    {
        $ids = PlayerFavorite::where('player_id', $request->user()->id)->pluck('turf_id');

        $turfs = Turf::with(['images', 'amenities', 'pricing', 'owner'])
            ->whereIn('id', $ids)
            ->where('status', 'approved')
            ->get();

        $turfs->each(fn ($turf) => $turf->is_favorite = true);

        return TurfResource::collection($turfs);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'turf_id' => 'required|exists:turfs,id',
        ]);

        PlayerFavorite::firstOrCreate([
            'player_id' => $request->user()->id,
            'turf_id' => $data['turf_id'],
        ]);

        return response()->json(['success' => true, 'is_favorite' => true]);
    }

    public function destroy(Request $request, $turfId)
    {
        PlayerFavorite::where('player_id', $request->user()->id)
            ->where('turf_id', $turfId)
            ->delete();

        return response()->json(['success' => true, 'is_favorite' => false]);
    }
}
