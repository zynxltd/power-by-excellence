<?php

namespace App\Support;

use App\Models\Campaign;

class CampaignPipelineProfile
{
    public const STANDARD_SALE = 'standard_sale';

    public const SUPPRESS_STORE = 'suppress_store';

    public const REVIEW_BEFORE_ROUTING = 'review_before_routing';

    /**
     * @return list<array{id: string, label: string, description: string, stages: list<array{key: string, label: string, accent: string}>}>
     */
    public static function options(): array
    {
        return [
            self::definition(self::STANDARD_SALE),
            self::definition(self::SUPPRESS_STORE),
            self::definition(self::REVIEW_BEFORE_ROUTING),
        ];
    }

    /**
     * @return array{id: string, label: string, description: string, stages: list<array{key: string, label: string, accent: string}>}
     */
    public static function definition(string $profile): array
    {
        return match ($profile) {
            self::SUPPRESS_STORE => [
                'id' => self::SUPPRESS_STORE,
                'label' => 'Validate & store',
                'description' => 'Ingest and validate leads, then store them without pinging buyers. Use for suppression lists and list-building.',
                'stages' => [
                    ['key' => 'ingest', 'label' => 'Ingest', 'accent' => 'slate'],
                    ['key' => 'validate', 'label' => 'Validate', 'accent' => 'violet'],
                    ['key' => 'stored', 'label' => 'Stored', 'accent' => 'emerald'],
                ],
            ],
            self::REVIEW_BEFORE_ROUTING => [
                'id' => self::REVIEW_BEFORE_ROUTING,
                'label' => 'Review before routing',
                'description' => 'Hold questionable or unsold leads in quarantine for manual review before distribution completes.',
                'stages' => [
                    ['key' => 'ingest', 'label' => 'Ingest', 'accent' => 'slate'],
                    ['key' => 'validate', 'label' => 'Validate', 'accent' => 'violet'],
                    ['key' => 'quarantine', 'label' => 'Quarantine', 'accent' => 'rose'],
                    ['key' => 'distribute', 'label' => 'Distribute', 'accent' => 'indigo'],
                    ['key' => 'outcome', 'label' => 'Sold / Unsold', 'accent' => 'emerald'],
                ],
            ],
            default => [
                'id' => self::STANDARD_SALE,
                'label' => 'Standard sale',
                'description' => 'Full lead routing - validate, then distribute to buyers via ping tree or waterfall.',
                'stages' => [
                    ['key' => 'ingest', 'label' => 'Ingest', 'accent' => 'slate'],
                    ['key' => 'validate', 'label' => 'Validate', 'accent' => 'violet'],
                    ['key' => 'distribute', 'label' => 'Distribute', 'accent' => 'indigo'],
                    ['key' => 'outcome', 'label' => 'Sold / Unsold', 'accent' => 'emerald'],
                ],
            ],
        };
    }

    public static function infer(Campaign $campaign): string
    {
        if (filled($campaign->pipeline_profile)) {
            return $campaign->pipeline_profile;
        }

        if ($campaign->isSuppression()) {
            return self::SUPPRESS_STORE;
        }

        $validation = $campaign->validation_config ?? [];

        if (($validation['quarantine_on_validation_fail'] ?? false) || ($validation['quarantine_unsold'] ?? false)) {
            return self::REVIEW_BEFORE_ROUTING;
        }

        return self::STANDARD_SALE;
    }

    /**
     * @return array<string, mixed>
     */
    public static function attributesFor(string $profile, ?Campaign $existing = null): array
    {
        $validationConfig = $existing?->validation_config ?? [];

        return match ($profile) {
            self::SUPPRESS_STORE => [
                'pipeline_profile' => $profile,
                'type' => 'suppression',
            ],
            self::REVIEW_BEFORE_ROUTING => [
                'pipeline_profile' => $profile,
                'type' => 'standard',
                'validation_config' => array_merge($validationConfig, [
                    'quarantine_on_validation_fail' => true,
                    'quarantine_unsold' => true,
                ]),
            ],
            default => [
                'pipeline_profile' => self::STANDARD_SALE,
                'type' => 'standard',
            ],
        };
    }

    public static function isValid(string $profile): bool
    {
        return in_array($profile, [
            self::STANDARD_SALE,
            self::SUPPRESS_STORE,
            self::REVIEW_BEFORE_ROUTING,
        ], true);
    }
}
