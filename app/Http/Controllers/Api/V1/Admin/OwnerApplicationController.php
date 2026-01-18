<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Models\OwnerApplication;
use Illuminate\Http\Request;

class OwnerApplicationController extends Controller
{
    public function index(Request $request)
    {
        $query = OwnerApplication::query();

        if ($request->status) {
            $query->where('status', $request->status);
        }

        $applications = $query->latest()->paginate(15);

        return response()->json($applications);
    }

    public function show($id)
    {
        $application = OwnerApplication::findOrFail($id);
        return response()->json($application);
    }

    public function updateStatus(Request $request, $id)
    {
        $request->validate([
            'status' => 'required|in:pending,approved,rejected',
            'notes' => 'nullable|string|max:1000',
        ]);

        $application = OwnerApplication::findOrFail($id);
        $application->update([
            'status' => $request->status,
            'notes' => $request->notes,
        ]);

        return response()->json([
            'message' => 'Application status updated successfully',
            'application' => $application
        ]);
    }
}