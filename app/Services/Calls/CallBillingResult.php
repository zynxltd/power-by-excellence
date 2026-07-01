<?php

namespace App\Services\Calls;

use App\Models\BuyerTransaction;

class CallBillingResult
{
    public function __construct(
        public bool $success,
        public string $reason = 'ok',
        public ?BuyerTransaction $transaction = null,
        public bool $alreadyBilled = false,
    ) {}

    public static function billed(?BuyerTransaction $transaction = null): self
    {
        return new self(success: true, transaction: $transaction);
    }

    public static function alreadyBilled(): self
    {
        return new self(success: true, reason: 'already_billed', alreadyBilled: true);
    }

    public static function insufficientCredit(): self
    {
        return new self(success: false, reason: 'insufficient_credit');
    }

    public static function skipped(string $reason): self
    {
        return new self(success: true, reason: $reason);
    }
}
