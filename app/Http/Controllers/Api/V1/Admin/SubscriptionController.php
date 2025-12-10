<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use App\Models\Owner;
use Illuminate\Http\Request;
use Carbon\Carbon;

class SubscriptionController extends Controller
{
    public function index(Request $request)
    {
        $query = Subscription::with(['owner', 'plan', 'owner.turfs']);

        if ($request->status) {
            $query->where('status', $request->status);
        }

        $subscriptions = $query->latest()->paginate(15);

        // Update statuses
        foreach ($subscriptions as $subscription) {
            $subscription->updateStatus();
        }

        return response()->json($subscriptions);
    }

    public function store(Request $request)
    {
        $request->validate([
            'owner_id' => 'required|exists:owners,id',
            'plan_id' => 'required|exists:subscription_plans,id',
            'start_date' => 'required|date',
            'amount_paid' => 'required|numeric',
            'payment_method' => 'nullable|string',
            'transaction_id' => 'nullable|string',
        ]);

        $plan = SubscriptionPlan::findOrFail($request->plan_id);
        $startDate = Carbon::parse($request->start_date);
        $endDate = $startDate->copy()->addDays($plan->duration_days);

        $subscription = Subscription::create([
            'owner_id' => $request->owner_id,
            'plan_id' => $request->plan_id,
            'start_date' => $startDate,
            'end_date' => $endDate,
            'status' => 'active',
            'amount_paid' => $request->amount_paid,
            'payment_method' => $request->payment_method,
            'transaction_id' => $request->transaction_id,
        ]);

        return response()->json($subscription->load(['owner', 'plan']), 201);
    }

    public function renew(Request $request, $id)
    {
        $subscription = Subscription::findOrFail($id);
        $plan = $subscription->plan;

        $startDate = Carbon::now();
        $endDate = $startDate->copy()->addDays($plan->duration_days);

        $newSubscription = Subscription::create([
            'owner_id' => $subscription->owner_id,
            'plan_id' => $subscription->plan_id,
            'start_date' => $startDate,
            'end_date' => $endDate,
            'status' => 'active',
            'amount_paid' => $request->amount_paid ?? $plan->price,
            'payment_method' => $request->payment_method,
            'transaction_id' => $request->transaction_id,
        ]);

        return response()->json($newSubscription->load(['owner', 'plan']));
    }

    public function plans()
    {
        return response()->json(SubscriptionPlan::where('is_active', true)->get());
    }

    public function updatePlan(Request $request, $id)
    {
        $request->validate([
            'price' => 'required|numeric|min:0',
        ]);

        $plan = SubscriptionPlan::findOrFail($id);
        $plan->update(['price' => $request->price]);

        return response()->json($plan);
    }

    public function ownersWithoutSubscription()
    {
        $owners = Owner::whereDoesntHave('subscriptions', function ($query) {
            $query->where('status', 'active');
        })->with('turfs')->get();

        return response()->json($owners);
    }

    public function statistics()
    {
        $total = Subscription::count();
        $active = Subscription::where('status', 'active')->count();
        $expiringSoon = Subscription::where('status', 'expiring_soon')->count();
        $expired = Subscription::where('status', 'expired')->count();

        return response()->json([
            'total' => $total,
            'active' => $active,
            'expiring_soon' => $expiringSoon,
            'expired' => $expired,
        ]);
    }
}
