<?php

namespace App\Services\Calls;

use App\Models\Delivery;
use App\Models\Lead;

class CallRoutingResult
{
    public function __construct(
        public bool $success,
        public ?string $destination = null,
        public float $revenue = 0,
        public ?Delivery $delivery = null,
        public ?Lead $fallbackLead = null,
        public string $reason = '',
    ) {}

    public static function success(string $destination, float $revenue, Delivery $delivery): self
    {
        return new self(true, $destination, $revenue, $delivery);
    }

    public static function failed(string $reason): self
    {
        return new self(false, reason: $reason);
    }

    public static function unsold(): self
    {
        return new self(false, reason: 'unsold');
    }

    public static function hybridFallback(Lead $lead): self
    {
        return new self(false, fallbackLead: $lead, reason: 'hybrid_fallback');
    }
}
