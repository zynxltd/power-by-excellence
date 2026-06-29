<?php

namespace App\Services\Messaging;

use App\Models\Lead;
use App\Models\LeadTag;
use App\Models\MessageEvent;
use App\Models\MessageSend;
use App\Models\Segment;
use Illuminate\Database\Eloquent\Builder;

class SegmentService
{
    public function tagLead(Lead $lead, string $tag): LeadTag
    {
        $leadTag = LeadTag::firstOrCreate([
            'account_id' => $lead->account_id,
            'lead_id' => $lead->id,
            'tag' => strtolower(trim($tag)),
        ]);

        app(\App\Services\Automation\AutomationSequenceService::class)
            ->dispatchForSegmentEntry($lead->fresh());

        return $leadTag;
    }

    public function untagLead(Lead $lead, string $tag): void
    {
        LeadTag::query()
            ->where('lead_id', $lead->id)
            ->where('tag', strtolower(trim($tag)))
            ->delete();
    }

    public function leadsForSegment(Segment $segment): Builder
    {
        $query = Lead::query()->where('account_id', $segment->account_id);

        if ($segment->campaign_id) {
            $query->where('campaign_id', $segment->campaign_id);
        }

        $rules = $segment->rules ?? [];

        if (! empty($rules['tags'])) {
            $tags = (array) $rules['tags'];
            $query->whereHas('tags', fn (Builder $q) => $q->whereIn('tag', $tags));
        }

        if (! empty($rules['status'])) {
            $query->where('status', $rules['status']);
        }

        if (! empty($rules['days'])) {
            $query->where('received_at', '>=', now()->subDays((int) $rules['days']));
        }

        if (! empty($rules['has_email'])) {
            $query->whereNotNull('field_data->email');
        }

        if (! empty($rules['has_phone'])) {
            $query->whereNotNull('field_data->phone1');
        }

        if (! empty($rules['opened_last_days'])) {
            $days = (int) $rules['opened_last_days'];
            $query->whereIn('id', $this->leadIdsWithEvent('open', $segment->account_id, $days));
        }

        if (! empty($rules['clicked_last_days'])) {
            $days = (int) $rules['clicked_last_days'];
            $query->whereIn('id', $this->leadIdsWithEvent('click', $segment->account_id, $days));
        }

        if (! empty($rules['never_opened'])) {
            $opened = $this->leadIdsWithEvent('open', $segment->account_id, 3650);
            if ($opened) {
                $query->whereNotIn('id', $opened);
            }
        }

        return $query;
    }

    public function countForSegment(Segment $segment): int
    {
        return $this->leadsForSegment($segment)->count();
    }

    /**
     * @return array<int, int>
     */
    protected function leadIdsWithEvent(string $type, int $accountId, int $days): array
    {
        return MessageSend::withoutGlobalScopes()
            ->where('account_id', $accountId)
            ->whereNotNull('lead_id')
            ->whereHas('events', fn (Builder $q) => $q
                ->where('type', $type)
                ->where('occurred_at', '>=', now()->subDays($days)))
            ->pluck('lead_id')
            ->unique()
            ->filter()
            ->values()
            ->all();
    }
}
