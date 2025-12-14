<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\CreateOwnerRequest;
use App\Http\Resources\OwnerResource;
use App\Models\Owner;
use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class OwnerController extends Controller
{
    public function index(Request $request)
    {
        $query = Owner::where('status', '!=', 'deleted')
            ->with('activeSubscription');

        if ($request->status) {
            $query->where('status', $request->status);
        }

        if ($request->search) {
            $query->where(function ($q) use ($request) {
                $q->where('name', 'like', "%{$request->search}%")
                  ->orWhere('email', 'like', "%{$request->search}%")
                  ->orWhere('phone', 'like', "%{$request->search}%");
            });
        }

        $owners = $query->withCount('turfs')->latest()->paginate(15);

        return OwnerResource::collection($owners);
    }

    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|min:3|max:255',
            'email' => 'nullable|email|unique:owners,email',
            'phone' => 'required|digits:10|unique:owners,phone',
            'address' => 'nullable|string|max:500',
            'pan_number' => 'nullable|string|size:10|regex:/^[A-Z]{5}[0-9]{4}[A-Z]{1}$/|unique:owners,pan_number',
            'bank_name' => 'nullable|string|max:255',
            'account_holder_name' => 'nullable|string|max:255',
            'account_number' => 'nullable|digits_between:9,18',
            'ifsc_code' => 'nullable|string|size:11|regex:/^[A-Z]{4}0[A-Z0-9]{6}$/',
        ], [
            'phone.digits' => 'Phone number must be exactly 10 digits',
            'phone.unique' => 'Phone number already exists',
            'email.unique' => 'Email already exists',
            'pan_number.regex' => 'Invalid PAN format (e.g., ABCDE1234F)',
            'pan_number.unique' => 'PAN number already exists',
            'ifsc_code.regex' => 'Invalid IFSC code format',
        ]);

        $owner = Owner::create([
            'name' => $request->name,
            'email' => $request->email,
            'phone' => $request->phone,
            'address' => $request->address,
            'pan_number' => $request->pan_number,
            'bank_name' => $request->bank_name,
            'account_holder_name' => $request->account_holder_name,
            'account_number' => $request->account_number,
            'ifsc_code' => $request->ifsc_code,
            'status' => 'active',
        ]);

        // Automatically assign free plan to new owner
        $freePlan = SubscriptionPlan::where('name', 'Free Plan')->where('is_active', true)->first();
        if ($freePlan) {
            Subscription::create([
                'owner_id' => $owner->id,
                'plan_id' => $freePlan->id,
                'start_date' => now(),
                'end_date' => now()->addDays($freePlan->duration_days),
                'status' => 'active',
                'amount_paid' => 0.00,
                'payment_method' => 'free',
                'transaction_id' => 'FREE-' . strtoupper(uniqid()),
            ]);
        }

        return response()->json([
            'message' => 'Owner added successfully with free subscription',
            'data' => new OwnerResource($owner->load('activeSubscription'))
        ], 201);
    }

    public function show($id)
    {
        $owner = Owner::with('turfs')->findOrFail($id);
        return new OwnerResource($owner);
    }

    public function update(Request $request, $id)
    {
        $owner = Owner::findOrFail($id);

        $validated = $request->validate([
            'name' => 'required|string|min:3|max:255',
            'email' => 'nullable|email|unique:owners,email,' . $id,
            'phone' => 'required|digits:10|unique:owners,phone,' . $id,
            'address' => 'nullable|string|max:500',
            'pan_number' => 'nullable|string|size:10|regex:/^[A-Z]{5}[0-9]{4}[A-Z]{1}$/|unique:owners,pan_number,' . $id,
            'bank_name' => 'nullable|string|max:255',
            'account_holder_name' => 'nullable|string|max:255',
            'account_number' => 'nullable|string',
            'ifsc_code' => 'nullable|string|size:11|regex:/^[A-Z]{4}0[A-Z0-9]{6}$/',
            'status' => 'nullable|in:active,inactive,suspended',
        ], [
            'phone.digits' => 'Phone number must be exactly 10 digits',
            'phone.unique' => 'Phone number already exists',
            'email.unique' => 'Email already exists',
            'pan_number.regex' => 'Invalid PAN format',
            'pan_number.unique' => 'PAN number already exists',
        ]);

        $updateData = array_filter($validated, function($value) {
            return $value !== null && $value !== '';
        });

        $owner->update($updateData);

        return response()->json([
            'message' => 'Owner updated successfully',
            'data' => new OwnerResource($owner)
        ]);
    }

    public function destroy($id)
    {
        $owner = Owner::findOrFail($id);
        
        if ($owner->turfs()->count() > 0) {
            return response()->json([
                'message' => 'Cannot delete owner with active turfs. Please delete turfs first.'
            ], 400);
        }

        $owner->update(['status' => 'deleted']);
        
        return response()->json(['message' => 'Owner deleted successfully']);
    }

    public function updateStatus(Request $request, $id)
    {
        $request->validate([
            'status' => 'required|in:active,inactive,suspended'
        ]);

        $owner = Owner::findOrFail($id);
        $owner->update(['status' => $request->status]);

        if (in_array($request->status, ['inactive', 'suspended'])) {
            $owner->turfs()->update(['status' => 'suspended']);
        } elseif ($request->status === 'active') {
            $owner->turfs()->where('status', 'suspended')->update(['status' => 'approved']);
        }

        return response()->json([
            'message' => 'Owner status updated successfully',
            'data' => new OwnerResource($owner)
        ]);
    }

    public function updateCommissionRate(Request $request, $id)
    {
        $request->validate([
            'commission_rate' => 'nullable|numeric|min:0|max:100'
        ]);

        $owner = Owner::findOrFail($id);
        $owner->update(['commission_rate' => $request->commission_rate]);

        return response()->json([
            'message' => 'Commission rate updated successfully',
            'data' => new OwnerResource($owner)
        ]);
    }
}
