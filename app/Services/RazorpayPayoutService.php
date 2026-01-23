<?php

namespace App\Services;

use App\Models\Setting;
use App\Models\Owner;
use App\Models\Payout;
use Razorpay\Api\Api;
use Illuminate\Support\Facades\Log;

class RazorpayPayoutService
{
    protected $razorpay;
    protected $enabled;

    public function __construct()
    {
        $this->enabled = Setting::get('razorpay_payouts_enabled', 'false') === 'true';
        
        if ($this->enabled) {
            $keyId = Setting::get('razorpay_payout_key_id');
            $keySecret = Setting::get('razorpay_payout_key_secret');
            
            if ($keyId && $keySecret) {
                $this->razorpay = new Api($keyId, $keySecret);
            }
        }
    }

    public function createContact(Owner $owner)
    {
        if (!$this->enabled || !$this->razorpay) {
            throw new \Exception('Razorpay Payouts not configured');
        }

        try {
            $contact = $this->razorpay->contact->create([
                'name' => $owner->name,
                'email' => $owner->email,
                'contact' => $owner->phone,
                'type' => 'vendor',
                'reference_id' => 'owner_' . $owner->id
            ]);

            $owner->update(['razorpay_contact_id' => $contact['id']]);
            return $contact;
        } catch (\Exception $e) {
            Log::error('Razorpay contact creation failed: ' . $e->getMessage());
            throw $e;
        }
    }

    public function createFundAccount(Owner $owner)
    {
        if (!$owner->razorpay_contact_id) {
            $this->createContact($owner);
        }

        try {
            $fundAccount = $this->razorpay->fundAccount->create([
                'contact_id' => $owner->razorpay_contact_id,
                'account_type' => 'bank_account',
                'bank_account' => [
                    'name' => $owner->account_holder_name,
                    'ifsc' => $owner->ifsc_code,
                    'account_number' => $owner->account_number
                ]
            ]);

            $owner->update(['razorpay_fund_account_id' => $fundAccount['id']]);
            return $fundAccount;
        } catch (\Exception $e) {
            Log::error('Razorpay fund account creation failed: ' . $e->getMessage());
            throw $e;
        }
    }

    public function createPayout(Payout $payout)
    {
        if (!$this->enabled || !$this->razorpay) {
            throw new \Exception('Razorpay Payouts not configured');
        }

        $owner = $payout->owner;
        
        if (!$owner->razorpay_fund_account_id) {
            $this->createFundAccount($owner);
        }

        try {
            $razorpayPayout = $this->razorpay->payout->create([
                'fund_account_id' => $owner->razorpay_fund_account_id,
                'amount' => $payout->settlement_amount * 100, // Convert to paise
                'currency' => 'INR',
                'mode' => 'IMPS',
                'purpose' => 'payout',
                'queue_if_low_balance' => true,
                'reference_id' => 'payout_' . $payout->id,
                'narration' => 'LTP Payout for period ' . $payout->period_start . ' to ' . $payout->period_end
            ]);

            $payout->update([
                'razorpay_payout_id' => $razorpayPayout['id'],
                'status' => 'processing'
            ]);

            return $razorpayPayout;
        } catch (\Exception $e) {
            Log::error('Razorpay payout creation failed: ' . $e->getMessage());
            $payout->update(['status' => 'failed', 'failure_reason' => $e->getMessage()]);
            throw $e;
        }
    }

    public function getPayoutStatus($razorpayPayoutId)
    {
        if (!$this->enabled || !$this->razorpay) {
            return null;
        }

        try {
            return $this->razorpay->payout->fetch($razorpayPayoutId);
        } catch (\Exception $e) {
            Log::error('Razorpay payout status fetch failed: ' . $e->getMessage());
            return null;
        }
    }

    public function updatePayoutStatus(Payout $payout)
    {
        if (!$payout->razorpay_payout_id) {
            return false;
        }

        $razorpayPayout = $this->getPayoutStatus($payout->razorpay_payout_id);
        
        if (!$razorpayPayout) {
            return false;
        }

        $statusMap = [
            'queued' => 'processing',
            'pending' => 'processing',
            'processing' => 'processing',
            'processed' => 'paid',
            'cancelled' => 'failed',
            'rejected' => 'failed',
            'failed' => 'failed'
        ];

        $newStatus = $statusMap[$razorpayPayout['status']] ?? 'processing';
        
        $updateData = ['status' => $newStatus];
        
        if ($newStatus === 'paid') {
            $updateData['paid_date'] = now();
            $updateData['transaction_id'] = $razorpayPayout['utr'] ?? $razorpayPayout['id'];
        } elseif ($newStatus === 'failed') {
            $updateData['failure_reason'] = $razorpayPayout['failure_reason'] ?? 'Payout failed';
        }

        $payout->update($updateData);
        return true;
    }
}