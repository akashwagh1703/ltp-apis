<?php

namespace App\Http\Controllers\Api\V1\Owner;

use App\Http\Controllers\Controller;
use App\Http\Resources\PayoutResource;
use App\Models\Payout;
use Illuminate\Http\Request;

class PayoutController extends Controller
{
    public function index(Request $request)
    {
        $payouts = Payout::with('transactions')
            ->where('owner_id', $request->user()->id)
            ->latest()
            ->paginate(15);

        return PayoutResource::collection($payouts);
    }

    public function show($id)
    {
        $payout = Payout::with('transactions')
            ->where('id', $id)
            ->where('owner_id', auth()->id())
            ->firstOrFail();

        return new PayoutResource($payout);
    }
}
