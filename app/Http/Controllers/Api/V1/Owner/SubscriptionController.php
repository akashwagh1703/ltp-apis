<?php

namespace App\Http\Controllers\Api\V1\Owner;

use App\Http\Controllers\Controller;
use App\Http\Resources\SubscriptionPaymentResource;
use App\Models\Booking;
use App\Models\Setting;
use App\Models\SubscriptionPayment;
use App\Models\SubscriptionPlan;
use Illuminate\Http\Request;

class SubscriptionController extends Controller
{
    public function show(Request $request)
    {
        $owner = $request->user();
        $owner->load('subscriptions.plan');
        $current = $owner->currentSubscription();
        $plan = $current?->plan;
        $isTrial = $plan && (float) $plan->price === 0.0;
        $canBook = $owner->canAcceptOnlineBookings();
        $daysRemaining = $current?->end_date
            ? (int) now()->startOfDay()->diffInDays($current->end_date->copy()->startOfDay(), false)
            : null;

        $payablePlans = SubscriptionPlan::where('is_active', true)
            ->where('price', '>', 0)
            ->orderBy('price')
            ->get(['id', 'name', 'type', 'price', 'duration_days']);

        $defaultPlan = $payablePlans->firstWhere('type', 'monthly') ?? $payablePlans->first();
        $amountDue = $canBook && $isTrial ? 0 : (float) ($defaultPlan->price ?? 0);

        $pending = SubscriptionPayment::with('plan')
            ->where('owner_id', $owner->id)
            ->where('status', SubscriptionPayment::STATUS_AWAITING_ADMIN)
            ->latest()
            ->first();

        $hideDays = Setting::getSubscriptionOverdueHideDays();
        $hiddenFrom = $current?->end_date
            ? $current->end_date->copy()->addDays($hideDays)->toDateString()
            : null;

        return response()->json([
            'success' => true,
            'data' => [
                'plan' => $plan ? [
                    'id' => $plan->id,
                    'name' => $plan->name,
                    'type' => $plan->type,
                    'price' => (float) $plan->price,
                    'duration_days' => $plan->duration_days,
                ] : null,
                'start_date' => $current?->start_date?->toDateString(),
                'end_date' => $current?->end_date?->toDateString(),
                'status' => $canBook
                    ? ($isTrial ? 'trial' : ($daysRemaining !== null && $daysRemaining <= 7 ? 'expiring_soon' : 'active'))
                    : 'expired',
                'is_trial' => (bool) $isTrial,
                'days_remaining' => $daysRemaining,
                'amount_due' => $amountDue,
                'can_accept_online_bookings' => $canBook,
                'hidden_from_search_at' => $canBook ? null : $hiddenFrom,
                'platform_upi_id' => Setting::platformUpiId(),
                'platform_qr_url' => Setting::platformQrUrl(),
                'pending_payment' => $pending ? (new SubscriptionPaymentResource($pending))->resolve() : null,
                'plans' => $payablePlans,
                'earnings' => $this->earnings($owner->id),
            ],
        ]);
    }

    public function markPaid(Request $request)
    {
        $owner = $request->user();

        $validated = $request->validate([
            'plan_id' => 'nullable|exists:subscription_plans,id',
        ]);

        $plan = !empty($validated['plan_id'])
            ? SubscriptionPlan::findOrFail($validated['plan_id'])
            : SubscriptionPlan::where('is_active', true)->where('type', 'monthly')->where('price', '>', 0)->first();

        if (!$plan || (float) $plan->price <= 0) {
            return response()->json([
                'success' => true,
                'message' => 'Trial is active. No payment due.',
                'data' => null,
            ]);
        }

        $existing = SubscriptionPayment::with('plan')
            ->where('owner_id', $owner->id)
            ->where('status', SubscriptionPayment::STATUS_AWAITING_ADMIN)
            ->latest()
            ->first();

        if ($existing) {
            $existing->update([
                'plan_id' => $plan->id,
                'amount' => $plan->price,
                'marked_paid_at' => now(),
            ]);
            $payment = $existing->fresh(['plan']);
        } else {
            $payment = SubscriptionPayment::create([
                'owner_id' => $owner->id,
                'plan_id' => $plan->id,
                'amount' => $plan->price,
                'status' => SubscriptionPayment::STATUS_AWAITING_ADMIN,
                'marked_paid_at' => now(),
            ]);
            $payment->load('plan');
        }

        return response()->json([
            'success' => true,
            'data' => (new SubscriptionPaymentResource($payment))->resolve(),
            'message' => 'Waiting for LTP to confirm…',
        ]);
    }

    protected function earnings(int $ownerId): array
    {
        $success = Booking::where('owner_id', $ownerId)->where('payment_status', 'success');

        return [
            'today' => (float) (clone $success)->whereDate('booking_date', today())->sum('final_amount'),
            'week' => (float) (clone $success)->where('booking_date', '>=', now()->startOfWeek())->sum('final_amount'),
            'month' => (float) (clone $success)->where('booking_date', '>=', now()->startOfMonth())->sum('final_amount'),
        ];
    }
}
