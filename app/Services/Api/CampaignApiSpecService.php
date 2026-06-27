<?php

namespace App\Services\Api;

use App\Models\Campaign;
use App\Models\CampaignField;
use App\Support\VerticalCatalog;

class CampaignApiSpecService
{
    public function defaultSpec(Campaign $campaign): array
    {
        $campaign->loadMissing('fields');
        $locked = (bool) ($campaign->api_spec['locked'] ?? false);

        if (! empty($campaign->api_spec['fields'])) {
            return array_merge($campaign->api_spec, ['locked' => $locked]);
        }

        $fields = $campaign->fields->isNotEmpty()
            ? $campaign->fields
            : collect(VerticalCatalog::fieldsFor($campaign->vertical_id));

        return [
            'version' => '1.0',
            'locked' => $locked,
            'description' => "Lead ingest API for {$campaign->name}",
            'endpoint' => [
                'method' => 'POST',
                'path' => '/api/v1/leads',
            ],
            'authentication' => [
                'type' => 'bearer',
                'header' => 'Authorization',
                'format' => 'Bearer {key_prefix}|{secret}',
            ],
            'fields' => collect($fields)->map(function ($field, int $i) {
                $array = is_array($field) ? $field : $field->toArray();

                return $this->normalizeField($array, $i);
            })->values()->all(),
        ];
    }

    public function normalizeField(array $field, int $sort = 0): array
    {
        $name = $field['name'] ?? 'field_'.$sort;
        $type = $this->inferApiType($name, $field['type'] ?? 'string');

        return [
            'name' => $name,
            'label' => $field['label'] ?? ucwords(str_replace('_', ' ', $name)),
            'type' => $type,
            'required' => (bool) ($field['required'] ?? false),
            'ping_field' => (bool) ($field['ping_field'] ?? false),
            'description' => $field['description'] ?? '',
            'example' => $field['example'] ?? $this->defaultExample($name, $type),
            'enum' => $field['enum'] ?? [],
            'form_type' => $field['form_type'] ?? $this->mapFormType($name, $type),
        ];
    }

    public function isLocked(Campaign $campaign): bool
    {
        return (bool) ($campaign->api_spec['locked'] ?? false);
    }

    public function syncFieldsToCampaign(Campaign $campaign, array $spec): void
    {
        $fields = $spec['fields'] ?? [];
        $existing = $campaign->fields()->pluck('id', 'name');

        foreach ($fields as $i => $field) {
            $payload = [
                'label' => $field['label'] ?? $field['name'],
                'type' => $field['type'] ?? 'string',
                'required' => (bool) ($field['required'] ?? false),
                'ping_field' => (bool) ($field['ping_field'] ?? false),
                'validation' => array_filter([
                    'description' => $field['description'] ?? null,
                    'example' => $field['example'] ?? null,
                    'enum' => $field['enum'] ?? null,
                ]),
                'sort_order' => $i,
            ];

            if ($existing->has($field['name'])) {
                CampaignField::where('id', $existing[$field['name']])->update($payload);
            } else {
                CampaignField::create(array_merge($payload, [
                    'campaign_id' => $campaign->id,
                    'name' => $field['name'],
                ]));
            }
        }
    }

    public function specToFormSteps(array $spec): array
    {
        $fields = collect($spec['fields'] ?? [])->map(fn (array $f) => [
            'name' => $f['name'],
            'label' => $f['label'] ?? $f['name'],
            'type' => $f['form_type'] ?? $this->mapFormType($f['name'], $f['type'] ?? 'string'),
            'required' => (bool) ($f['required'] ?? false),
            'options' => ! empty($f['enum']) ? $f['enum'] : [],
        ])->values()->all();

        if (count($fields) <= 4) {
            return [[
                'id' => 'step-1',
                'title' => 'Your details',
                'description' => 'Complete the form below',
                'fields' => $fields,
            ]];
        }

        $contact = collect($fields)->filter(fn ($f) => in_array($f['name'], ['firstname', 'lastname', 'email', 'phone1'], true))->values()->all();
        $rest = collect($fields)->reject(fn ($f) => in_array($f['name'], ['firstname', 'lastname', 'email', 'phone1'], true))->values()->all();
        $steps = [];

        if ($contact) {
            $steps[] = ['id' => 'step-contact', 'title' => 'Contact details', 'description' => 'How can we reach you?', 'fields' => $contact];
        }

        if ($rest) {
            $chunks = array_chunk($rest, 4);
            foreach ($chunks as $i => $chunk) {
                $steps[] = [
                    'id' => 'step-'.($i + 2),
                    'title' => 'Additional details',
                    'description' => 'Step '.($i + 2).' of '.(count($chunks) + ($contact ? 1 : 0)),
                    'fields' => $chunk,
                ];
            }
        }

        return $steps ?: [[
            'id' => 'step-1',
            'title' => 'Your details',
            'description' => '',
            'fields' => $fields,
        ]];
    }

    public function sampleRequest(Campaign $campaign, array $spec): array
    {
        $body = [
            'campaign_reference' => $campaign->reference,
            'source' => 'api_example',
        ];

        foreach ($spec['fields'] ?? [] as $field) {
            $body[$field['name']] = $field['example'] ?? '';
        }

        return $body;
    }

    public function sampleResponse(): array
    {
        return [
            'status' => 'queued',
            'queue_id' => 'q_example123abc',
            'lead_id' => 'e1c9b4db-e334-475e-b452-301b17b01ad2',
        ];
    }

    public function sampleStatusResponse(): array
    {
        return [
            'status' => 'accepted',
            'lead_id' => 'e1c9b4db-e334-475e-b452-301b17b01ad2',
            'queue_id' => 'q_example123abc',
            'test_mode' => true,
            'reject_reason' => null,
            'buyer_reference' => null,
            'revenue' => null,
            'currency' => 'GBP',
            'redirect_url' => null,
            'decline_url' => null,
            'received_at' => '2026-06-26T12:00:00+00:00',
            'distributed_at' => null,
        ];
    }

    public function buildGetCurl(string $baseUrl, string $path, ?string $apiKeyExample = null): string
    {
        $url = rtrim($baseUrl, '/').$path;
        $key = $apiKeyExample ?? 'your_prefix|your_secret';

        return "curl '{$url}' \\\n"
            ."  -H 'Authorization: Bearer {$key}' \\\n"
            ."  -H 'Accept: application/json'";
    }

    public function buildCurl(string $baseUrl, ?string $apiKeyExample, array $spec, array $sample): string
    {
        $url = rtrim($baseUrl, '/').($spec['endpoint']['path'] ?? '/api/v1/leads');
        $key = $apiKeyExample ?? 'your_prefix|your_secret';
        $json = json_encode($sample, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

        return "curl -X POST '{$url}' \\\n"
            ."  -H 'Authorization: Bearer {$key}' \\\n"
            ."  -H 'Content-Type: application/json' \\\n"
            ."  -H 'Accept: application/json' \\\n"
            ."  -d '".str_replace("'", "'\\''", $json)."'";
    }

    protected function inferApiType(string $name, string $type): string
    {
        if ($type !== 'string' && $type !== 'text') {
            return match ($type) {
                'email' => 'email',
                'tel', 'phone' => 'phone',
                'number' => 'number',
                'boolean', 'checkbox' => 'boolean',
                'date' => 'date',
                'radio', 'select' => 'enum',
                default => 'string',
            };
        }

        return match (true) {
            str_contains($name, 'email') => 'email',
            str_contains($name, 'phone') => 'phone',
            in_array($name, ['zipcode', 'postcode'], true) => 'postcode',
            default => 'string',
        };
    }

    protected function mapFormType(string $name, string $apiType): string
    {
        return match ($apiType) {
            'email' => 'email',
            'phone' => 'tel',
            'number' => 'number',
            'postcode' => 'postcode',
            'date' => 'date',
            'boolean' => 'checkbox',
            'enum' => 'radio',
            default => 'text',
        };
    }

    protected function defaultExample(string $name, string $type): string
    {
        return match (true) {
            str_contains($name, 'email') => 'jane@example.com',
            str_contains($name, 'phone') => '07700900123',
            $name === 'firstname' => 'Jane',
            $name === 'lastname' => 'Smith',
            in_array($name, ['zipcode', 'postcode'], true) => 'SW1A 1AA',
            $type === 'number' => '15000',
            $type === 'boolean' => 'true',
            default => 'example',
        };
    }
}
