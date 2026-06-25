<?php

namespace App\Services\Billing;

use App\Models\Buyer;
use App\Models\BuyerTransaction;
use App\Models\Lead;
use InvalidArgumentException;

class BuyerBillingService
{
    public function hasCredit(Buyer $buyer, float $amount): bool
    {
        if (($buyer->status ?? 'active') !== 'active') {
            return false;
        }

        $account = $buyer->account;
        if ($account && ! app(AccountBillingService::class)->canProcessLeads($account)) {
            return false;
        }

        $requirePrepay = $account?->settings['require_buyer_prepay'] ?? false;

        if (! $requirePrepay) {
            return true;
        }

        return (float) $buyer->credit_balance >= $amount;
    }

    public function charge(Buyer $buyer, float $amount, ?Lead $lead = null, string $description = 'Lead purchase'): bool
    {
        if (($buyer->status ?? 'active') !== 'active') {
            return false;
        }

        $requirePrepay = $buyer->account?->settings['require_buyer_prepay'] ?? false;

        if (! $requirePrepay) {
            return true;
        }

        if ((float) $buyer->credit_balance < $amount) {
            return false;
        }

        $buyer->decrement('credit_balance', $amount);

        BuyerTransaction::create([
            'buyer_id' => $buyer->id,
            'lead_id' => $lead?->id,
            'type' => 'debit',
            'amount' => -$amount,
            'balance_after' => $buyer->fresh()->credit_balance,
            'description' => $description,
        ]);

        app(BuyerCreditAlertService::class)->checkAfterDebit($buyer);

        return true;
    }

    public function credit(Buyer $buyer, float $amount, string $description = 'Top-up', array $meta = []): BuyerTransaction
    {
        return $this->adjust($buyer, $amount, 'credit', $description, $meta);
    }

    public function refund(Buyer $buyer, float $amount, Lead $lead, string $description = 'Lead return'): void
    {
        $this->adjust($buyer, $amount, 'refund', $description, ['lead_id' => $lead->id]);
    }

    /**
     * @param  array{
     *     bypass_prepay?: bool,
     *     bypass_account_lock?: bool,
     *     skip_ledger?: bool,
     *     allow_negative?: bool,
     *     suppress_alerts?: bool,
     *     lead_id?: int|null,
     *     performed_by?: int|null
     * }  $options
     */
    public function adjust(
        Buyer $buyer,
        float $amount,
        string $type,
        string $description,
        array $options = [],
    ): BuyerTransaction {
        $allowed = ['credit', 'debit', 'refund', 'adjustment', 'correction', 'goodwill', 'chargeback', 'manual_debit'];
        if (! in_array($type, $allowed, true)) {
            throw new InvalidArgumentException("Invalid ledger type: {$type}");
        }

        $isDebit = in_array($type, ['debit', 'manual_debit', 'chargeback'], true);
        $signedAmount = $isDebit ? -abs($amount) : abs($amount);

        $account = $buyer->account;
        if ($account && ! ($options['bypass_account_lock'] ?? false)) {
            if (! app(AccountBillingService::class)->canProcessLeads($account) && $signedAmount < 0) {
                throw new InvalidArgumentException('Account billing is locked.');
            }
        }

        $newBalance = (float) $buyer->credit_balance + $signedAmount;
        if ($newBalance < 0 && ! ($options['allow_negative'] ?? false)) {
            throw new InvalidArgumentException('Insufficient credit balance.');
        }

        if ($options['skip_ledger'] ?? false) {
            $buyer->update(['credit_balance' => $newBalance]);

            return new BuyerTransaction([
                'buyer_id' => $buyer->id,
                'type' => $type,
                'amount' => $signedAmount,
                'balance_after' => $newBalance,
                'description' => $description.' (balance only)',
            ]);
        }

        $buyer->update(['credit_balance' => $newBalance]);

        $transaction = BuyerTransaction::create([
            'buyer_id' => $buyer->id,
            'lead_id' => $options['lead_id'] ?? null,
            'type' => $type,
            'amount' => $signedAmount,
            'balance_after' => $newBalance,
            'description' => $description,
            'meta' => array_filter([
                'bypass_prepay' => $options['bypass_prepay'] ?? false,
                'bypass_account_lock' => $options['bypass_account_lock'] ?? false,
                'allow_negative' => $options['allow_negative'] ?? false,
                'suppress_alerts' => $options['suppress_alerts'] ?? false,
                'performed_by' => $options['performed_by'] ?? null,
            ]),
        ]);

        if ($signedAmount < 0) {
            app(BuyerCreditAlertService::class)->checkAfterDebit(
                $buyer,
                (bool) ($options['suppress_alerts'] ?? false),
            );
        }

        return $transaction;
    }
}
