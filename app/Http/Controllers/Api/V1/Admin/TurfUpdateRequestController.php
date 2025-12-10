<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Models\TurfUpdateRequest;
use Illuminate\Http\Request;

class TurfUpdateRequestController extends Controller
{
    public function index(Request $request)
    {
        $query = TurfUpdateRequest::with(['turf', 'owner']);

        if ($request->status) {
            $query->where('status', $request->status);
        }

        $requests = $query->latest()->paginate(15);

        return response()->json($requests);
    }

    public function approve($id)
    {
        $request = TurfUpdateRequest::findOrFail($id);
        
        $request->turf->update([
            $request->field_name => $request->new_value,
        ]);

        $request->update([
            'status' => 'approved',
            'reviewed_at' => now(),
        ]);

        return response()->json(['message' => 'Request approved']);
    }

    public function reject(Request $request, $id)
    {
        $updateRequest = TurfUpdateRequest::findOrFail($id);
        
        $updateRequest->update([
            'status' => 'rejected',
            'admin_remarks' => $request->remarks,
            'reviewed_at' => now(),
        ]);

        return response()->json(['message' => 'Request rejected']);
    }
}
