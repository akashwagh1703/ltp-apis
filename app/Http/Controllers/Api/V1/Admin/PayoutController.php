<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\PayoutResource;
use App\Models\Payout;
use App\Services\PayoutService;
use App\Services\RazorpayPayoutService;
use Illuminate\Http\Request;

class PayoutController extends Controller
{
    protected $payoutService;
    protected $razorpayPayoutService;

    public function __construct(PayoutService $payoutService, RazorpayPayoutService $razorpayPayoutService)
    {
        $this->payoutService = $payoutService;
        $this->razorpayPayoutService = $razorpayPayoutService;
    }

    public function index(Request $request)
    {
        $query = Payout::with('owner');

        if ($request->owner_id) {
            $query->where('owner_id', $request->owner_id);
        }

        if ($request->status) {
            $query->where('status', $request->status);
        }

        $payouts = $query->latest()->paginate(15);

        return PayoutResource::collection($payouts);
    }

    public function generate(Request $request)
    {
        $request->validate([
            'owner_id' => 'required|exists:owners,id',
            'period_start' => 'required|date',
            'period_end' => 'required|date|after:period_start',
        ]);

        $payout = $this->payoutService->generatePayout(
            $request->owner_id,
            $request->period_start,
            $request->period_end
        );

        return response()->json(new PayoutResource($payout->load('transactions')), 201);
    }

    public function generateBulk(Request $request)
    {
        $request->validate([
            'period_start' => 'required|date',
            'period_end' => 'required|date|after:period_start',
            'owner_ids' => 'array',
            'owner_ids.*' => 'exists:owners,id'
        ]);

        $ownerIds = $request->owner_ids ?? \App\Models\Owner::where('status', 'active')->pluck('id')->toArray();
        $generated = [];
        $errors = [];

        foreach ($ownerIds as $ownerId) {
            try {
                $payout = $this->payoutService->generatePayout(
                    $ownerId,
                    $request->period_start,
                    $request->period_end
                );
                $generated[] = $payout;
            } catch (\Exception $e) {
                $owner = \App\Models\Owner::find($ownerId);
                $errors[] = [
                    'owner_id' => $ownerId,
                    'owner_name' => $owner->name ?? 'Unknown',
                    'error' => $e->getMessage()
                ];
            }
        }

        return response()->json([
            'message' => count($generated) . ' payouts generated successfully',
            'generated' => PayoutResource::collection(collect($generated)),
            'errors' => $errors
        ], 201);
    }

    public function process($id)
    {
        $payout = Payout::findOrFail($id);
        
        if ($payout->status !== 'pending') {
            return response()->json(['message' => 'Only pending payouts can be processed'], 400);
        }
        
        $payout->update(['status' => 'processed']);

        return response()->json(['message' => 'Payout processed successfully']);
    }

    public function release($id)
    {
        $payout = Payout::findOrFail($id);
        
        if (!in_array($payout->status, ['pending', 'processed'])) {
            return response()->json(['message' => 'Only pending or processed payouts can be released'], 400);
        }
        
        try {
            // Try Razorpay payout first
            $razorpayPayout = $this->razorpayPayoutService->createPayout($payout);
            
            return response()->json([
                'message' => 'Payout initiated via Razorpay',
                'data' => new PayoutResource($payout->fresh()),
                'razorpay_payout_id' => $razorpayPayout['id']
            ]);
        } catch (\Exception $e) {
            // Fallback to manual release
            $payout->update([
                'status' => 'paid',
                'paid_date' => now(),
                'payment_method' => 'manual',
                'notes' => 'Manual release - Razorpay failed: ' . $e->getMessage()
            ]);

            return response()->json([
                'message' => 'Payout released manually (Razorpay unavailable)',
                'data' => new PayoutResource($payout)
            ]);
        }
    }

    public function updateStatus($id)
    {
        $payout = Payout::findOrFail($id);
        
        if (!$payout->razorpay_payout_id) {
            return response()->json(['message' => 'No Razorpay payout ID found'], 400);
        }
        
        $updated = $this->razorpayPayoutService->updatePayoutStatus($payout);
        
        if ($updated) {
            return response()->json([
                'message' => 'Payout status updated',
                'data' => new PayoutResource($payout->fresh())
            ]);
        }
        
        return response()->json(['message' => 'Failed to update payout status'], 500);
    }
}
