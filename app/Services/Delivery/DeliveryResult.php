<?php

namespace App\Services\Delivery;

class DeliveryResult
{
    public function __construct(
        public bool $success,
        public bool $skipped = false,
        public ?string $skipReason = null,
        public float $revenue = 0,
        public ?int $httpStatus = null,
        public ?string $error = null,
    ) {}

    public static function success(float $revenue = 0, ?int $httpStatus = null): self
    {
        return new self(true, revenue: $revenue, httpStatus: $httpStatus);
    }

    public static function skipped(string $reason): self
    {
        return new self(false, skipped: true, skipReason: $reason);
    }

    public static function failed(string $error, ?int $httpStatus = null): self
    {
        return new self(false, error: $error, httpStatus: $httpStatus);
    }
}
