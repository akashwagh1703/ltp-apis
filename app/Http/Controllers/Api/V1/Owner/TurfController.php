<?php

namespace App\Http\Controllers\Api\V1\Owner;

use App\Http\Controllers\Controller;
use App\Http\Resources\TurfResource;
use App\Models\Turf;
use App\Models\TurfUpdateRequest;
use Illuminate\Http\Request;

class TurfController extends Controller
{
    public function index(Request $request)
    {
        $turfs = Turf::with(['images', 'amenities', 'pricing'])
            ->where('owner_id', $request->user()->id)
            ->get();

        return TurfResource::collection($turfs);
    }

    public function show($id)
    {
        $turf = Turf::with(['images', 'amenities', 'pricing'])
            ->where('id', $id)
            ->where('owner_id', auth()->id())
            ->firstOrFail();

        return new TurfResource($turf);
    }

    public function requestUpdate(Request $request, $id)
    {
        $turf = Turf::where('id', $id)
            ->where('owner_id', auth()->id())
            ->firstOrFail();

        TurfUpdateRequest::create([
            'turf_id' => $turf->id,
            'owner_id' => auth()->id(),
            'request_type' => 'update',
            'changes' => json_encode($request->updates),
            'status' => 'pending',
        ]);

        return response()->json(['message' => 'Update request submitted']);
    }
}
