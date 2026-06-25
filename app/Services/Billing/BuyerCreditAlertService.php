<?php

namespace App\Services\Billing;

use App\Mail\BuyerLowCreditMail;
use App\Models\Buyer;
use Illuminate\Support\Facades\Mail;

class BuyerCreditAlertService
{
    public function checkAfterDebit(Buyer $buyer, bool $suppressAlerts = false): void
    {
        if ($suppressAlerts) {
            return;
        }

        $buyer->refresh();
        $threshold = $this->thresholdFor($buyer);

        if ($threshold === null) {
            return;
        }

        if ((float) $buyer->credit_balance > $threshold) {
            return;
        }

        $cacheKey = "buyer_low_credit_alert:{$buyer->id}";
        if (cache()->has($cacheKey)) {
            return;
        }

        cache()->put($cacheKey, true, now()->addHours(6));

        $account = $buyer->account;
        $currency = $buyer->resolvedCurrency();
        $recipients = $this->alertRecipients($buyer);

        foreach ($recipients as $email) {
            try {
                Mail::to($email)->send(new BuyerLowCreditMail(
                    $buyer,
                    (float) $buyer->credit_balance,
                    $threshold,
                    $currency,
                    $account?->brand_name ?: $account?->name ?: 'PowerByExcellence',
                ));
            } catch (\Throwable) {
                // Non-blocking
            }
        }
    }

    public function thresholdFor(Buyer $buyer): ?float
    {
        $buyerThreshold = $buyer->settings['low_credit_alert'] ?? null;

        if ($buyerThreshold !== null && $buyerThreshold !== '') {
            return (float) $buyerThreshold;
        }

        $accountThreshold = $buyer->account?->settings['default_low_credit_alert'] ?? null;

        if ($accountThreshold !== null && $accountThreshold !== '') {
            return (float) $accountThreshold;
        }

        return null;
    }

    /**
     * @return list<string>
     */
    public function alertRecipients(Buyer $buyer): array
    {
        $emails = [];

        if ($buyer->email) {
            $emails[] = $buyer->email;
        }

        $accountEmails = $buyer->account?->settings['billing_alert_emails'] ?? '';
        foreach (preg_split('/[\s,;]+/', (string) $accountEmails) as $email) {
            $email = trim($email);
            if (filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $emails[] = $email;
            }
        }

        return array_values(array_unique($emails));
    }

    public function isBelowThreshold(Buyer $buyer): bool
    {
        $threshold = $this->thresholdFor($buyer);

        if ($threshold === null) {
            return false;
        }

        return (float) $buyer->credit_balance <= $threshold;
    }
}
