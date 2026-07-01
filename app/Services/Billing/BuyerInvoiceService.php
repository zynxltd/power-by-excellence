<?php

namespace App\Services\Billing;

use App\Mail\BuyerInvoiceMail;
use App\Models\Buyer;
use App\Models\BuyerInvoice;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Mail;
use Stripe\Invoice as StripeInvoice;
use Stripe\Stripe;

class BuyerInvoiceService
{
    public function syncFromStripeInvoice(object $stripeInvoice): ?BuyerInvoice
    {
        $buyer = $this->resolveBuyer($stripeInvoice);
        if (! $buyer) {
            return null;
        }

        $stripeInvoiceId = (string) ($stripeInvoice->id ?? '');
        if ($stripeInvoiceId === '') {
            return null;
        }

        $invoice = BuyerInvoice::query()->updateOrCreate(
            ['stripe_invoice_id' => $stripeInvoiceId],
            [
                'buyer_id' => $buyer->id,
                'pdf_url' => $this->resolvePdfUrl($stripeInvoice, $buyer),
                'amount' => $this->amountFromStripeInvoice($stripeInvoice),
                'currency' => strtoupper((string) ($stripeInvoice->currency ?? $buyer->resolvedCurrency())),
                'period_start' => $this->timestampToCarbon($stripeInvoice->period_start ?? null),
                'period_end' => $this->timestampToCarbon($stripeInvoice->period_end ?? null),
                'status' => (string) ($stripeInvoice->status ?? 'open'),
            ],
        );

        if (! $invoice->email_sent_at) {
            $this->queueInvoiceEmail($invoice);
        }

        return $invoice->fresh();
    }

    public function resolvePdfUrl(object $stripeInvoice, ?Buyer $buyer = null): ?string
    {
        if (! empty($stripeInvoice->invoice_pdf)) {
            return (string) $stripeInvoice->invoice_pdf;
        }

        if (! empty($stripeInvoice->hosted_invoice_url)) {
            return (string) $stripeInvoice->hosted_invoice_url;
        }

        $stripeInvoiceId = $stripeInvoice->id ?? null;
        if (! $stripeInvoiceId) {
            return null;
        }

        $buyer = $buyer ?? $this->resolveBuyer($stripeInvoice);
        if (! $buyer) {
            return null;
        }

        try {
            $this->configureStripe($buyer);
            $retrieved = StripeInvoice::retrieve((string) $stripeInvoiceId);

            if (! empty($retrieved->invoice_pdf)) {
                return (string) $retrieved->invoice_pdf;
            }

            if (! empty($retrieved->hosted_invoice_url)) {
                return (string) $retrieved->hosted_invoice_url;
            }
        } catch (\Throwable) {
            return null;
        }

        return null;
    }

    public function queueInvoiceEmail(BuyerInvoice $invoice): void
    {
        $invoice->loadMissing('buyer.account');
        $buyer = $invoice->buyer;
        if (! $buyer) {
            return;
        }

        $platformName = $buyer->account?->brand_name ?: $buyer->account?->name ?: 'PowerByExcellence';

        foreach ($this->invoiceRecipients($buyer) as $email) {
            Mail::to($email)->queue(new BuyerInvoiceMail($buyer, $invoice, $platformName));
        }

        $invoice->update(['email_sent_at' => now()]);
    }

    public function resendInvoiceEmail(BuyerInvoice $invoice): void
    {
        $invoice->update(['email_sent_at' => null]);
        $this->queueInvoiceEmail($invoice->fresh());
    }

    /**
     * @return list<string>
     */
    public function invoiceRecipients(Buyer $buyer): array
    {
        $emails = [];

        if ($buyer->email && filter_var($buyer->email, FILTER_VALIDATE_EMAIL)) {
            $emails[] = $buyer->email;
        }

        $buyer->loadMissing('portalUsers');

        foreach ($buyer->portalUsers as $user) {
            if (! $user->isBuyerPortal()) {
                continue;
            }

            if ($user->email && filter_var($user->email, FILTER_VALIDATE_EMAIL)) {
                $emails[] = $user->email;
            }
        }

        return array_values(array_unique($emails));
    }

    protected function amountFromStripeInvoice(object $stripeInvoice): float
    {
        if (isset($stripeInvoice->amount_due)) {
            return (float) ($stripeInvoice->amount_due / 100);
        }

        if (isset($stripeInvoice->total)) {
            return (float) ($stripeInvoice->total / 100);
        }

        return 0.0;
    }

    protected function timestampToCarbon(mixed $timestamp): ?Carbon
    {
        if ($timestamp === null || $timestamp === '') {
            return null;
        }

        return Carbon::createFromTimestamp((int) $timestamp);
    }

    protected function resolveBuyer(object $stripeInvoice): ?Buyer
    {
        $buyerId = (int) ($stripeInvoice->metadata->buyer_id ?? 0);
        if ($buyerId) {
            return Buyer::withoutGlobalScopes()->find($buyerId);
        }

        $customerId = $stripeInvoice->customer ?? null;
        if (! $customerId) {
            return null;
        }

        return Buyer::withoutGlobalScopes()
            ->where('stripe_customer_id', (string) $customerId)
            ->first();
    }

    protected function configureStripe(Buyer $buyer): void
    {
        $settings = $buyer->account?->settings['stripe'] ?? [];
        Stripe::setApiKey($settings['secret'] ?? config('stripe.secret'));
    }
}
