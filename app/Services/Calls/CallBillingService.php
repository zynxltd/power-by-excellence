<?php

namespace App\Services\Calls;

use App\Models\Buyer;
use App\Models\BuyerTransaction;
use App\Models\CallSession;
use App\Services\Billing\AccountBillingService;
use App\Services\Billing\BuyerBillingService;
use App\Services\Billing\BuyerCreditAlertService;

class CallBillingService
{
    public function __construct(
        protected BuyerBillingService $buyerBilling,
    ) {}

    public function chargeForCall(CallSession $session, Buyer $buyer, float $amount): bool
    {
        if ($amount <= 0) {
            return true;
        }

        if (! $this->buyerBilling->hasCredit($buyer, $amount)) {
            return false;
        }

        $requirePrepay = $buyer->account?->settings['require_buyer_prepay'] ?? false;

        if (! $requirePrepay) {
            return true;
        }

        $buyer->decrement('credit_balance', $amount);

        BuyerTransaction::create([
            'buyer_id' => $buyer->id,
            'type' => 'debit',
            'amount' => -$amount,
            'balance_after' => $buyer->fresh()->credit_balance,
            'description' => 'Call purchase',
            'meta' => ['call_session_id' => $session->uuid],
        ]);

        app(BuyerCreditAlertService::class)->checkAfterDebit($buyer);

        return true;
    }

    public function isBillable(CallSession $session): bool
    {
        $minDuration = $session->min_duration_seconds
            ?: ($session->campaign?->call_settings['min_duration_seconds'] ?? config('telephony.default_min_duration_seconds', 60));

        return $session->duration_seconds >= $minDuration;
    }

    public function calculateBillableSeconds(CallSession $session): int
    {
        if (! $this->isBillable($session)) {
            return 0;
        }

        return $session->duration_seconds;
    }
}
