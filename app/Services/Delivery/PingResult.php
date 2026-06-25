<?php

namespace App\Services\Delivery;

use App\Models\DeliveryLog;

class PingResult
{
    public function __construct(
        public bool $success,
        public float $revenue = 0,
        public array $pingBody = [],
        public ?DeliveryLog $log = null,
        public bool $skipped = false,
        public ?string $skipReason = null,
    ) {}

    public static function success(float $revenue, array $pingBody, DeliveryLog $log): self
    {
        return new self(true, $revenue, $pingBody, $log);
    }

    public static function skipped(string $reason, DeliveryLog $log): self
    {
        return new self(false, log: $log, skipped: true, skipReason: $reason);
    }

    public static function failed(DeliveryLog $log): self
    {
        return new self(false, log: $log);
    }
}
