<?php

namespace App\Http\Controllers\Api\V1\Public;

use App\Http\Controllers\Controller;
use App\Models\OwnerApplication;
use Illuminate\Http\Request;

class OwnerApplicationController extends Controller
{
    public function store(Request $request)
    {
        $request->validate([
            'full_name' => 'required|string|max:255',
            'email' => 'required|email|unique:owner_applications,email',
            'phone' => 'required|string|max:15',
            'address' => 'required|string|max:1000',
        ]);

        $application = OwnerApplication::create([
            'full_name' => $request->full_name,
            'email' => $request->email,
            'phone' => $request->phone,
            'address' => $request->address,
            'status' => 'pending',
        ]);

        return response()->json([
            'message' => 'Application submitted successfully! We will contact you within 24 hours.',
            'application_id' => $application->id,
        ], 201);
    }
}