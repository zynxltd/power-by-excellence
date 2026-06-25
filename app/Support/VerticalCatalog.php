<?php

namespace App\Support;

class VerticalCatalog
{
    public static function all(): array
    {
        return config('verticals', []);
    }

    public static function label(?string $verticalId): string
    {
        if (! $verticalId) {
            return 'General';
        }

        return self::all()[$verticalId]['label'] ?? ucwords(str_replace('_', ' ', $verticalId));
    }

    public static function options(): array
    {
        return collect(self::all())
            ->map(fn (array $vertical, string $id) => [
                'id' => $id,
                'label' => $vertical['label'],
                'description' => $vertical['description'] ?? '',
                'default_floor' => $vertical['default_floor'] ?? 10,
                'default_payout' => $vertical['default_payout'] ?? 5,
            ])
            ->values()
            ->all();
    }

    public static function fieldsFor(?string $verticalId): array
    {
        if (! $verticalId || ! isset(self::all()[$verticalId]['fields'])) {
            return self::defaultFields();
        }

        return self::all()[$verticalId]['fields'];
    }

    public static function defaultFields(): array
    {
        return [
            ['name' => 'firstname', 'label' => 'First Name', 'required' => true, 'ping_field' => false],
            ['name' => 'lastname', 'label' => 'Last Name', 'required' => true, 'ping_field' => false],
            ['name' => 'email', 'label' => 'Email', 'required' => true, 'ping_field' => false],
            ['name' => 'phone1', 'label' => 'Phone', 'required' => true, 'ping_field' => false],
            ['name' => 'zipcode', 'label' => 'Postcode', 'required' => true, 'ping_field' => true],
        ];
    }

    public static function decorateCampaigns(iterable $campaigns): array
    {
        return collect($campaigns)->map(function ($campaign) {
            $array = is_array($campaign) ? $campaign : $campaign->toArray();
            $array['vertical_label'] = self::label($array['vertical_id'] ?? null);

            return $array;
        })->all();
    }
}
