<?php

namespace App\Http\Controllers;

use App\Services\Billing\StripeCheckoutService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class StripeWebhookController extends Controller
{
    public function __invoke(Request $request, StripeCheckoutService $stripe): Response
    {
        $payload = $request->getContent();
        $signature = $request->header('Stripe-Signature');

        try {
            $event = $stripe->constructWebhookEvent($payload, $signature);
        } catch (\Throwable $e) {
            return response('Invalid payload', 400);
        }

        $type = is_object($event) ? ($event->type ?? null) : ($event['type'] ?? null);
        $data = is_object($event) ? ($event->data->object ?? null) : ($event['data']['object'] ?? null);

        if (! $type || ! $data) {
            return response('OK', 200);
        }

        match ($type) {
            'checkout.session.completed' => $stripe->handleWebhookCompleted($data),
            'invoice.finalized' => $stripe->handleInvoiceFinalized($data),
            'invoice.paid' => $stripe->handleInvoicePaid($data),
            'customer.subscription.updated' => $stripe->handleSubscriptionUpdated($data),
            'customer.subscription.deleted' => $stripe->handleSubscriptionDeleted($data),
            default => null,
        };

        return response('OK', 200);
    }
}
