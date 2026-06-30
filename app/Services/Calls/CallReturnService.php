<?php

namespace App\Services\Calls;

use App\Models\Buyer;
use App\Models\CallReturn;
use App\Models\CallSession;
use App\Models\User;
use App\Support\Products\CallLogicProduct;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class CallReturnService
{
    public function __construct(
        protected CallBillingService $billing,
    ) {}

    public function returnWindowDays(CallSession $session): int
    {
        return (int) (CallLogicProduct::settings($session->account)['call_return_window_days'] ?? 7);
    }

    public function canSubmit(CallSession $session, Buyer $buyer): bool
    {
        return $this->submissionError($session, $buyer) === null;
    }

    public function submissionError(CallSession $session, Buyer $buyer): ?string
    {
        if ($session->sold_to_buyer_id !== $buyer->id) {
            return 'This call does not belong to your account.';
        }

        if ($session->billed_at === null) {
            return 'This call has not been billed yet.';
        }

        if ($session->refunded_at !== null) {
            return 'This call has already been refunded.';
        }

        if ($session->callReturn()->exists()) {
            return 'A return has already been submitted for this call.';
        }

        $anchor = $session->billed_at ?? $session->completed_at ?? $session->created_at;
        $windowDays = $this->returnWindowDays($session);

        if ($anchor->lt(now()->subDays($windowDays))) {
            return "Returns must be submitted within {$windowDays} days of billing.";
        }

        return null;
    }

    public function submit(CallSession $session, Buyer $buyer, string $reason): CallReturn
    {
        $validated = Validator::make(['reason' => $reason], [
            'reason' => 'required|string|max:500',
        ])->validate();

        if ($error = $this->submissionError($session, $buyer)) {
            throw ValidationException::withMessages(['reason' => $error]);
        }

        return CallReturn::create([
            'call_session_id' => $session->id,
            'buyer_id' => $buyer->id,
            'reason' => $validated['reason'],
            'status' => 'pending',
        ]);
    }

    public function approve(CallReturn $return, User $resolver): CallReturn
    {
        if (! $return->isPending()) {
            throw ValidationException::withMessages(['return' => 'This return has already been resolved.']);
        }

        $session = $return->callSession()->firstOrFail();
        $transaction = $this->billing->refundSoldCall($session);

        $return->update([
            'status' => 'approved',
            'credit_amount' => $session->fresh()->billed_amount,
            'refund_transaction_id' => $transaction?->id,
            'resolved_by' => $resolver->id,
            'resolved_at' => now(),
        ]);

        return $return->fresh();
    }

    public function reject(CallReturn $return, User $resolver): CallReturn
    {
        if (! $return->isPending()) {
            throw ValidationException::withMessages(['return' => 'This return has already been resolved.']);
        }

        $return->update([
            'status' => 'rejected',
            'resolved_by' => $resolver->id,
            'resolved_at' => now(),
        ]);

        return $return->fresh();
    }

    public function pendingCountForBuyer(int $buyerId): int
    {
        return CallReturn::query()
            ->where('buyer_id', $buyerId)
            ->where('status', 'pending')
            ->count();
    }
}
