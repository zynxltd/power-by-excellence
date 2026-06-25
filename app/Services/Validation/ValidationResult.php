<?php

namespace App\Services\Validation;

class ValidationResult
{
    public function __construct(
        public bool $passed,
        public ?string $reason = null,
        public array $meta = [],
    ) {}

    public static function pass(array $meta = []): self
    {
        return new self(true, null, $meta);
    }

    public static function fail(string $reason, array $meta = []): self
    {
        return new self(false, $reason, $meta);
    }
}
