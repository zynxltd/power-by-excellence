<?php

namespace App\Services\Validation;

class ValidationContext
{
    public function __construct(
        public ?string $userAgent = null,
        public ?string $userLanguage = null,
    ) {}

    public static function fromLead(?\App\Models\Lead $lead): self
    {
        if (! $lead) {
            return new self;
        }

        return new self(
            userAgent: $lead->user_agent,
            userLanguage: null,
        );
    }
}
