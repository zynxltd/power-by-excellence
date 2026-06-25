<?php

namespace App\Services\Buyers;

use App\Mail\BuyerLeadPurchaseMail;
use App\Models\Buyer;
use App\Models\Lead;
use App\Support\Tenancy\TenantResolver;
use Illuminate\Support\Facades\Mail;

class BuyerNotificationService
{
    public function notifyLeadPurchase(Buyer $buyer, Lead $lead, float $revenue): void
    {
        $settings = $buyer->settings ?? [];

        if (! ($settings['notify_on_sale'] ?? false)) {
            return;
        }

        $email = $buyer->email;
        if (! $email) {
            $portalUser = \App\Models\User::query()
                ->where('buyer_id', $buyer->id)
                ->where('role', \App\Enums\UserRole::BuyerPortal)
                ->first();
            $email = $portalUser?->email;
        }

        if (! $email) {
            return;
        }

        $account = $buyer->account;
        $portalUrl = $account
            ? TenantResolver::portalUrl($account, '/portal/buyer/leads')
            : url('/portal/buyer/leads');

        $currency = $lead->financials?->currency
            ?? $lead->campaign?->currency
            ?? $account?->default_currency
            ?? 'GBP';

        try {
            Mail::to($email)->send(new BuyerLeadPurchaseMail($buyer, $lead, $revenue, $currency, $portalUrl));
        } catch (\Throwable) {
            // Non-blocking in dev/test
        }
    }
}
