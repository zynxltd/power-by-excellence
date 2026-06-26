<?php

namespace App\Services\Validation;

class ValidationContext
{
    public function __construct(
        public ?string $userAgent = null,
        public ?string $userLanguage = null,
        public ?string $ipWhitelist = null,
    ) {}

    public static function fromLead(?\App\Models\Lead $lead, ?string $ipWhitelist = null): self
    {
        if (! $lead) {
            return new self(ipWhitelist: $ipWhitelist);
        }

        return new self(
            userAgent: $lead->user_agent,
            userLanguage: null,
            ipWhitelist: $ipWhitelist,
        );
    }
}
