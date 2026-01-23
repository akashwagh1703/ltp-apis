<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Models\TurfUpdateRequest;
use Illuminate\Http\Request;

class TurfUpdateRequestController extends Controller
{
    public function index(Request $request)
    {
        $query = TurfUpdateRequest::with(['turf', 'owner'])
            ->selectRaw('turf_update_requests.*, turfs.name as turf_name, owners.name as owner_name, turf_update_requests.id as request_id, turf_update_requests.created_at as requested_at')
            ->join('turfs', 'turf_update_requests.turf_id', '=', 'turfs.id')
            ->join('owners', 'turf_update_requests.owner_id', '=', 'owners.id');

        if ($request->status) {
            $query->where('turf_update_requests.status', $request->status);
        }

        $requests = $query->latest('turf_update_requests.created_at')->get();
        
        // Parse changes JSON for each request
        $requests->transform(function ($request) {
            $request->changes = json_decode($request->changes, true) ?? [];
            return $request;
        });

        return response()->json([
            'success' => true,
            'data' => $requests
        ]);
    }

    public function approve($id)
    {
        $request = TurfUpdateRequest::findOrFail($id);
        $changes = json_decode($request->changes, true);
        
        // Apply all changes to the turf
        $updateData = [];
        foreach ($changes as $field => $values) {
            $updateData[$field] = $values['new'];
        }
        
        $request->turf->update($updateData);

        $request->update([
            'status' => 'approved',
            'reviewed_at' => now(),
            'reviewed_by' => auth()->id(),
        ]);

        return response()->json(['message' => 'Request approved']);
    }

    public function reject(Request $request, $id)
    {
        $updateRequest = TurfUpdateRequest::findOrFail($id);
        
        $updateRequest->update([
            'status' => 'rejected',
            'admin_notes' => $request->reason ?? $request->remarks,
            'reviewed_at' => now(),
            'reviewed_by' => auth()->id(),
        ]);

        return response()->json(['message' => 'Request rejected']);
    }
}
