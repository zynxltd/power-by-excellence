<?php

namespace App\Services\Messaging;

use App\Models\Lead;
use App\Models\MessageTemplate;
use App\Services\Delivery\TagInterpolator;

class TemplateRenderService
{
    public function __construct(
        protected TagInterpolator $interpolator,
    ) {}

    /**
     * @return array<int, array{tag: string, label: string, sample: string}>
     */
    public static function availableTags(): array
    {
        return [
            ['tag' => 'first_name', 'label' => 'First name', 'sample' => 'Alex'],
            ['tag' => 'last_name', 'label' => 'Last name', 'sample' => 'Morgan'],
            ['tag' => 'email', 'label' => 'Email', 'sample' => 'alex@example.com'],
            ['tag' => 'phone1', 'label' => 'Phone', 'sample' => '+447700900123'],
            ['tag' => 'zipcode', 'label' => 'Postcode', 'sample' => 'SW1A 1AA'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function defaultPreviewData(): array
    {
        return [
            'first_name' => 'Alex',
            'firstname' => 'Alex',
            'last_name' => 'Morgan',
            'lastname' => 'Morgan',
            'email' => 'alex@example.com',
            'phone1' => '+447700900123',
            'zipcode' => 'SW1A 1AA',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function fieldsForLead(?Lead $lead, ?array $previewData = null): array
    {
        $fields = $lead ? $lead->allFields() : [];
        $fields = $this->normalizeFieldAliases($fields);

        if ($previewData) {
            $fields = array_merge($this->normalizeFieldAliases($previewData), $fields);
        }

        return array_merge($this->defaultPreviewData(), $fields);
    }

    /**
     * @param  array<string, string|null>  $parts
     * @return array{subject: ?string, body: ?string, html_body: ?string}
     */
    public function renderParts(array $parts, ?Lead $lead = null, ?array $previewData = null): array
    {
        $fields = $this->fieldsForLead($lead, $previewData);

        return [
            'subject' => filled($parts['subject'] ?? null)
                ? $this->render((string) $parts['subject'], $fields)
                : null,
            'body' => filled($parts['body'] ?? null)
                ? $this->render((string) $parts['body'], $fields)
                : null,
            'html_body' => filled($parts['html_body'] ?? null)
                ? $this->render((string) $parts['html_body'], $fields)
                : null,
        ];
    }

    public function renderTemplate(MessageTemplate $template, ?Lead $lead = null): array
    {
        return $this->renderParts([
            'subject' => $template->subject,
            'body' => $template->body,
            'html_body' => $template->html_body,
        ], $lead, $template->preview_data);
    }

    public function render(string $template, array $fields): string
    {
        return $this->interpolator->interpolate($template, $this->normalizeFieldAliases($fields));
    }

    /**
     * @param  array<string, mixed>  $fields
     * @return array<string, mixed>
     */
    protected function normalizeFieldAliases(array $fields): array
    {
        $aliases = [
            'first_name' => 'firstname',
            'last_name' => 'lastname',
            'phone' => 'phone1',
        ];

        foreach ($aliases as $from => $to) {
            if (! isset($fields[$from]) && isset($fields[$to])) {
                $fields[$from] = $fields[$to];
            }
            if (! isset($fields[$to]) && isset($fields[$from])) {
                $fields[$to] = $fields[$from];
            }
        }

        return $fields;
    }
}
