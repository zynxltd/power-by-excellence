<?php

namespace App\Services\VerticalFieldTemplates;

use App\Models\Campaign;
use App\Models\CampaignField;
use App\Models\VerticalFieldTemplate;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class VerticalFieldTemplateApplyService
{
    public const STRATEGY_REPLACE_ALL = 'replace-all';

    public const STRATEGY_MERGE_BY_NAME = 'merge-by-name';

    public function assertVerticalMatch(Campaign $campaign, VerticalFieldTemplate $template): void
    {
        if ($campaign->vertical_id !== $template->vertical_id) {
            throw ValidationException::withMessages([
                'vertical_id' => "Template vertical ({$template->vertical_id}) does not match campaign vertical ({$campaign->vertical_id}).",
            ]);
        }
    }

    /**
     * @return array{
     *     strategy: string,
     *     to_add: list<array<string, mixed>>,
     *     to_replace: list<array{before: array<string, mixed>, after: array<string, mixed>}>,
     *     to_remove: list<array<string, mixed>>
     * }
     */
    public function buildDiff(Campaign $campaign, VerticalFieldTemplate $template, string $strategy = self::STRATEGY_REPLACE_ALL): array
    {
        $this->assertVerticalMatch($campaign, $template);

        $strategy = $this->normalizeStrategy($strategy);
        $current = $campaign->fields()
            ->orderBy('sort_order')
            ->get()
            ->map(fn (CampaignField $field) => $this->normalizeField($field->toArray()))
            ->keyBy('name');
        $incoming = collect($template->fields ?? [])
            ->map(fn (array $field) => $this->normalizeField($field))
            ->keyBy('name');

        if ($strategy === self::STRATEGY_REPLACE_ALL) {
            return [
                'strategy' => $strategy,
                'to_add' => $incoming->values()->all(),
                'to_replace' => [],
                'to_remove' => $current->values()->all(),
            ];
        }

        $toAdd = [];
        $toReplace = [];

        foreach ($incoming as $name => $field) {
            if (! $current->has($name)) {
                $toAdd[] = $field;
            } elseif ($this->fieldDiffers($current[$name], $field)) {
                $toReplace[] = [
                    'before' => $current[$name],
                    'after' => $field,
                ];
            }
        }

        return [
            'strategy' => $strategy,
            'to_add' => $toAdd,
            'to_replace' => $toReplace,
            'to_remove' => [],
        ];
    }

    public function apply(Campaign $campaign, VerticalFieldTemplate $template, string $strategy = self::STRATEGY_REPLACE_ALL): void
    {
        $this->assertVerticalMatch($campaign, $template);
        $strategy = $this->normalizeStrategy($strategy);

        DB::transaction(function () use ($campaign, $template, $strategy) {
            if ($strategy === self::STRATEGY_MERGE_BY_NAME) {
                $this->applyMergeByName($campaign, $template);
            } else {
                $this->applyReplaceAll($campaign, $template);
            }
        });
    }

    protected function applyReplaceAll(Campaign $campaign, VerticalFieldTemplate $template): void
    {
        CampaignField::where('campaign_id', $campaign->id)->delete();

        foreach ($template->fields ?? [] as $index => $field) {
            CampaignField::create($this->fieldPayload($field, $campaign->id, $index));
        }
    }

    protected function applyMergeByName(Campaign $campaign, VerticalFieldTemplate $template): void
    {
        $existing = CampaignField::where('campaign_id', $campaign->id)->get()->keyBy('name');

        foreach ($template->fields ?? [] as $index => $field) {
            $payload = $this->fieldPayload($field, $campaign->id, $index);
            $name = $payload['name'];

            if ($existing->has($name)) {
                $existing[$name]->update($payload);
            } else {
                CampaignField::create($payload);
            }
        }
    }

    /**
     * @param  array<string, mixed>  $field
     * @return array<string, mixed>
     */
    protected function fieldPayload(array $field, int $campaignId, int $sortOrder): array
    {
        $normalized = $this->normalizeField($field);

        return [
            'campaign_id' => $campaignId,
            'name' => $normalized['name'],
            'label' => $normalized['label'],
            'type' => $normalized['type'],
            'required' => $normalized['required'],
            'ping_field' => $normalized['ping_field'],
            'sort_order' => $sortOrder,
        ];
    }

    /**
     * @param  array<string, mixed>  $field
     * @return array{name: string, label: string, type: string, required: bool, ping_field: bool}
     */
    protected function normalizeField(array $field): array
    {
        return [
            'name' => (string) ($field['name'] ?? ''),
            'label' => (string) ($field['label'] ?? $field['name'] ?? ''),
            'type' => (string) ($field['type'] ?? 'text'),
            'required' => (bool) ($field['required'] ?? false),
            'ping_field' => (bool) ($field['ping_field'] ?? false),
        ];
    }

    /**
     * @param  array<string, mixed>  $left
     * @param  array<string, mixed>  $right
     */
    protected function fieldDiffers(array $left, array $right): bool
    {
        foreach (['label', 'type', 'required', 'ping_field'] as $key) {
            if (($left[$key] ?? null) !== ($right[$key] ?? null)) {
                return true;
            }
        }

        return false;
    }

    protected function normalizeStrategy(string $strategy): string
    {
        return in_array($strategy, [self::STRATEGY_REPLACE_ALL, self::STRATEGY_MERGE_BY_NAME], true)
            ? $strategy
            : self::STRATEGY_REPLACE_ALL;
    }
}
