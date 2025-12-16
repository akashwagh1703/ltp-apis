<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\PayoutResource;
use App\Models\Payout;
use App\Services\PayoutService;
use Illuminate\Http\Request;

class PayoutController extends Controller
{
    protected $payoutService;

    public function __construct(PayoutService $payoutService)
    {
        $this->payoutService = $payoutService;
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
        
        if ($payout->status !== 'processed') {
            return response()->json(['message' => 'Only processed payouts can be released'], 400);
        }
        
        $payout->update([
            'status' => 'paid',
            'paid_date' => now(),
        ]);

        return response()->json(['message' => 'Payout released successfully', 'data' => new PayoutResource($payout)]);
    }
}
