<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\SubscriptionPaymentResource;
use App\Models\SubscriptionPayment;
use App\Services\FcmService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Throwable;

class SubscriptionPaymentController extends Controller
{
    public function index(Request $request)
    {
        $query = SubscriptionPayment::with(['owner', 'plan'])->latest();

        if ($request->status) {
            $query->where('status', $request->status);
        }

        return SubscriptionPaymentResource::collection($query->paginate(20));
    }

    public function confirm(Request $request, $id)
    {
        $payment = SubscriptionPayment::with(['owner', 'plan'])->findOrFail($id);

        if ($payment->status === SubscriptionPayment::STATUS_CONFIRMED) {
            return response()->json(['message' => 'Payment already confirmed'], 400);
        }

        $payment->update([
            'status' => SubscriptionPayment::STATUS_CONFIRMED,
            'confirmed_at' => now(),
            'confirmed_by' => $request->user()->id,
            'note' => $request->input('note'),
        ]);

        $subscription = $payment->owner->applySubscriptionPayment($payment);

        try {
            app(FcmService::class)->sendFeeConfirmedNotification(
                $payment->owner_id,
                $subscription->end_date
            );
        } catch (Throwable $e) {
            Log::warning('Fee confirm FCM skipped: '.$e->getMessage());
        }

        return response()->json([
            'success' => true,
            'message' => 'Fee confirmed. Plan dates updated.',
            'data' => (new SubscriptionPaymentResource($payment->fresh(['owner', 'plan'])))->resolve(),
            'subscription' => [
                'start_date' => $subscription->start_date?->toDateString(),
                'end_date' => $subscription->end_date?->toDateString(),
                'status' => $subscription->status,
                'plan' => $subscription->plan?->name,
            ],
        ]);
    }

    public function reject(Request $request, $id)
    {
        $payment = SubscriptionPayment::with(['owner', 'plan'])->findOrFail($id);

        if ($payment->status === SubscriptionPayment::STATUS_CONFIRMED) {
            return response()->json(['message' => 'Confirmed payments cannot be rejected'], 400);
        }

        $payment->update([
            'status' => SubscriptionPayment::STATUS_REJECTED,
            'note' => $request->input('note'),
            'confirmed_by' => $request->user()->id,
            'confirmed_at' => now(),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Told the owner the fee was not received.',
            'data' => (new SubscriptionPaymentResource($payment))->resolve(),
        ]);
    }
}
