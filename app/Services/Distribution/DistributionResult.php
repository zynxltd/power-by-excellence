<?php

namespace App\Services\Distribution;

class DistributionResult
{
    public function __construct(
        public bool $sold,
        public float $revenue = 0,
        public ?int $buyerId = null,
    ) {}
}
