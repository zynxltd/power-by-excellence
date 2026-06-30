<?php

namespace App\Services\Calls;

use App\Enums\CallEventType;
use App\Models\Buyer;
use App\Models\CallSession;
use App\Models\Delivery;
use App\Services\Billing\BuyerBillingService;
use App\Services\Billing\RevenueCalculator;
use App\Support\Products\CallLogicProduct;

class CallBillingService
{
    public function __construct(
        protected BuyerBillingService $buyerBilling,
        protected RevenueCalculator $revenueCalculator,
        protected CallEventLogger $logger,
    ) {}

    public function canChargeForCall(CallSession $session, Delivery $delivery, ?float $amount = null): bool
    {
        $buyer = $delivery->buyer;

        if (! $buyer) {
            return false;
        }

        $chargeAmount = $amount ?? $this->resolveCallPrice($session, $delivery);

        if ($chargeAmount <= 0) {
            return true;
        }

        return $this->buyerBilling->hasCredit($buyer, $chargeAmount);
    }

    public function billSoldCall(CallSession $session, Delivery $delivery, ?float $amount = null): CallBillingResult
    {
        $session = $session->fresh();

        if ($session->billed_at !== null) {
            return CallBillingResult::alreadyBilled();
        }

        $buyer = $delivery->buyer ?? $session->soldToBuyer;

        if (! $buyer) {
            return CallBillingResult::skipped('no_buyer');
        }

        $chargeAmount = $amount ?? $this->resolveCallPrice($session, $delivery);

        if ($chargeAmount <= 0) {
            $session->update([
                'billed_at' => now(),
                'billed_amount' => 0,
            ]);

            return CallBillingResult::skipped('zero_amount');
        }

        if (! $this->buyerBilling->hasCredit($buyer, $chargeAmount)) {
            $this->logger->log(
                $session,
                CallEventType::Failed,
                'Call billing blocked: insufficient buyer credit',
                [
                    'buyer_id' => $buyer->id,
                    'amount' => $chargeAmount,
                    'delivery_id' => $delivery->id,
                ],
                'warning',
            );

            return CallBillingResult::insufficientCredit();
        }

        $transaction = $this->buyerBilling->charge(
            $buyer,
            $chargeAmount,
            $session->lead,
            'Call purchase',
            [
                'call_session_id' => $session->id,
                'call_session_uuid' => $session->uuid,
                'delivery_id' => $delivery->id,
            ],
        );

        $requirePrepay = $buyer->account?->settings['require_buyer_prepay'] ?? false;

        if ($requirePrepay && $transaction === null) {
            return CallBillingResult::insufficientCredit();
        }

        $session->update([
            'billed_at' => now(),
            'billed_amount' => $chargeAmount,
            'buyer_transaction_id' => $transaction?->id,
            'revenue' => $session->revenue ?? $chargeAmount,
        ]);

        $this->logger->log($session, CallEventType::Completed, 'Call billed to buyer', [
            'buyer_id' => $buyer->id,
            'amount' => $chargeAmount,
            'buyer_transaction_id' => $transaction?->id,
        ]);

        return CallBillingResult::billed($transaction);
    }

    public function resolveCallPrice(CallSession $session, ?Delivery $delivery = null): float
    {
        if ($session->revenue !== null && (float) $session->revenue > 0) {
            return (float) $session->revenue;
        }

        if ($delivery) {
            $calculated = (float) $this->revenueCalculator->calculate($delivery, $session->callAttributes());

            if ($calculated > 0) {
                return $calculated;
            }

            if ((float) $delivery->revenue_amount > 0) {
                return (float) $delivery->revenue_amount;
            }
        }

        $campaignPrice = data_get($session->campaign?->call_settings, 'per_call_price');

        if ($campaignPrice !== null && (float) $campaignPrice > 0) {
            return (float) $campaignPrice;
        }

        $buyer = $delivery?->buyer ?? $session->soldToBuyer;

        if ($buyer) {
            $buyerPrice = data_get($buyer->settings, 'per_call_price');

            if ($buyerPrice !== null && (float) $buyerPrice > 0) {
                return (float) $buyerPrice;
            }
        }

        return (float) (CallLogicProduct::settings($session->account)['default_per_call_price'] ?? 0);
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

    /**
     * @deprecated Use billSoldCall() for idempotent pay-per-call billing.
     */
    public function chargeForCall(CallSession $session, Buyer $buyer, float $amount): bool
    {
        $delivery = $session->winningDelivery;

        if (! $delivery) {
            return $this->buyerBilling->hasCredit($buyer, $amount);
        }

        return $this->billSoldCall($session, $delivery, $amount)->success;
    }
}
