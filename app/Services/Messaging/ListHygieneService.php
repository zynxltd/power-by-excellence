<?php

namespace App\Services\Messaging;

use App\Models\Account;
use App\Models\Lead;
use App\Models\LeadTag;
use App\Models\MarketingOptOut;
use App\Models\MessageSend;
use App\Models\Segment;
use App\Services\Leads\FieldHashService;

class ListHygieneService
{
    public const TAG_SUPPRESS_MARKETING = 'suppress_marketing';

    public const TAG_BOUNCED = 'bounced';

    public const TAG_INACTIVE = 'inactive';

    public function __construct(
        protected SegmentService $segments,
        protected FieldHashService $fieldHasher,
    ) {}

    /**
     * @return array{
     *     list_hygiene_enabled: bool,
     *     inactive_days_threshold: int,
     *     hygiene_auto_suppress_bounces: bool,
     *     hygiene_last_run: ?array<string, mixed>
     * }
     */
    public function settings(Account $account): array
    {
        $messaging = $account->settings['messaging'] ?? [];

        return [
            'list_hygiene_enabled' => (bool) ($messaging['list_hygiene_enabled'] ?? false),
            'inactive_days_threshold' => max(1, (int) ($messaging['inactive_days_threshold'] ?? 180)),
            'hygiene_auto_suppress_bounces' => (bool) ($messaging['hygiene_auto_suppress_bounces'] ?? true),
            'hygiene_last_run' => $messaging['hygiene_last_run'] ?? null,
        ];
    }

    /**
     * @param  array{
     *     list_hygiene_enabled?: bool,
     *     inactive_days_threshold?: int,
     *     hygiene_auto_suppress_bounces?: bool,
     * }  $validated
     * @return array<string, mixed>
     */
    public function mergeSettingsIntoAccount(Account $account, array $validated): array
    {
        $settings = $account->settings ?? [];
        $messaging = $settings['messaging'] ?? [];

        if (array_key_exists('list_hygiene_enabled', $validated)) {
            $messaging['list_hygiene_enabled'] = (bool) $validated['list_hygiene_enabled'];
        }

        if (array_key_exists('inactive_days_threshold', $validated)) {
            $messaging['inactive_days_threshold'] = max(1, (int) $validated['inactive_days_threshold']);
        }

        if (array_key_exists('hygiene_auto_suppress_bounces', $validated)) {
            $messaging['hygiene_auto_suppress_bounces'] = (bool) $validated['hygiene_auto_suppress_bounces'];
        }

        $settings['messaging'] = $messaging;

        return $settings;
    }

    /**
     * @return array{bounces_tagged: int, inactive_tagged: int, dry_run: bool, skipped: bool}
     */
    public function run(Account $account, bool $dryRun = false, bool $force = false): array
    {
        $settings = $this->settings($account);

        if (! $settings['list_hygiene_enabled'] && ! $force) {
            return [
                'bounces_tagged' => 0,
                'inactive_tagged' => 0,
                'dry_run' => $dryRun,
                'skipped' => true,
            ];
        }

        $bouncesTagged = 0;
        $inactiveTagged = 0;

        if ($settings['hygiene_auto_suppress_bounces']) {
            $bouncesTagged = $this->scrubBounces($account, $dryRun);
        }

        $inactiveTagged = $this->pruneInactive($account, $settings['inactive_days_threshold'], $dryRun);

        if (! $dryRun) {
            $this->storeLastRun($account, $bouncesTagged, $inactiveTagged);
        }

        return [
            'bounces_tagged' => $bouncesTagged,
            'inactive_tagged' => $inactiveTagged,
            'dry_run' => $dryRun,
            'skipped' => false,
        ];
    }

    public function runForEnabledAccounts(bool $dryRun = false): int
    {
        $processed = 0;

        Account::query()
            ->where('is_active', true)
            ->orderBy('id')
            ->chunkById(50, function ($accounts) use ($dryRun, &$processed): void {
                foreach ($accounts as $account) {
                    if (! $this->settings($account)['list_hygiene_enabled']) {
                        continue;
                    }

                    $this->run($account, $dryRun);
                    $processed++;
                }
            });

        return $processed;
    }

    protected function scrubBounces(Account $account, bool $dryRun): int
    {
        $leadIds = collect($this->bouncedLeadIds($account->id))->unique()->values();

        if ($leadIds->isEmpty()) {
            return 0;
        }

        if (! $dryRun) {
            $this->ensureBouncedSegment($account);
        }

        $tagged = 0;

        Lead::withoutGlobalScopes()
            ->where('account_id', $account->id)
            ->whereIn('id', $leadIds)
            ->each(function (Lead $lead) use ($dryRun, &$tagged): void {
                if ($this->leadHasAllHygieneTags($lead, [self::TAG_SUPPRESS_MARKETING, self::TAG_BOUNCED])) {
                    return;
                }

                if ($dryRun) {
                    $tagged++;

                    return;
                }

                $this->segments->tagLead($lead, self::TAG_SUPPRESS_MARKETING);
                $this->segments->tagLead($lead->fresh(), self::TAG_BOUNCED);
                $tagged++;
            });

        return $tagged;
    }

    protected function pruneInactive(Account $account, int $inactiveDays, bool $dryRun): int
    {
        $leadIds = collect($this->inactiveLeadIds($account->id, $inactiveDays));

        if ($leadIds->isEmpty()) {
            return 0;
        }

        $tagged = 0;

        Lead::withoutGlobalScopes()
            ->where('account_id', $account->id)
            ->whereIn('id', $leadIds)
            ->each(function (Lead $lead) use ($dryRun, &$tagged): void {
                if ($this->leadHasHygieneTags($lead, [self::TAG_INACTIVE])) {
                    return;
                }

                if ($dryRun) {
                    $tagged++;

                    return;
                }

                $this->segments->tagLead($lead, self::TAG_INACTIVE);
                $this->removeActiveMarketingTags($lead);
                $tagged++;
            });

        return $tagged;
    }

    /**
     * @return list<int>
     */
    protected function bouncedLeadIds(int $accountId): array
    {
        $fromEvents = MessageSend::withoutGlobalScopes()
            ->where('account_id', $accountId)
            ->whereNotNull('lead_id')
            ->whereHas('events', fn ($query) => $query->where('type', 'bounce'))
            ->pluck('lead_id');

        $bounceHashes = MarketingOptOut::withoutGlobalScopes()
            ->where('account_id', $accountId)
            ->where('field_type', 'email')
            ->whereIn('source', ['esp_bounce', 'bounce'])
            ->pluck('hash')
            ->flip();

        if ($bounceHashes->isEmpty()) {
            return $fromEvents->unique()->filter()->values()->all();
        }

        $fromOptOut = Lead::withoutGlobalScopes()
            ->where('account_id', $accountId)
            ->whereNotNull('field_data->email')
            ->get(['id', 'field_data'])
            ->filter(function (Lead $lead) use ($bounceHashes): bool {
                $email = (string) $lead->getField('email', '');

                if ($email === '') {
                    return false;
                }

                $hash = $this->fieldHasher->resolveHash('email', $email);

                return $hash && $bounceHashes->has($hash);
            })
            ->pluck('id');

        return $fromEvents->merge($fromOptOut)->unique()->filter()->values()->all();
    }

    /**
     * @return list<int>
     */
    protected function inactiveLeadIds(int $accountId, int $inactiveDays): array
    {
        $engagedIds = collect([
            ...$this->leadIdsWithEngagement($accountId, 'open', $inactiveDays),
            ...$this->leadIdsWithEngagement($accountId, 'click', $inactiveDays),
        ])->unique();

        $sentLeadIds = MessageSend::withoutGlobalScopes()
            ->where('account_id', $accountId)
            ->whereNotNull('lead_id')
            ->pluck('lead_id')
            ->unique()
            ->filter();

        return $sentLeadIds->diff($engagedIds)->values()->all();
    }

    /**
     * @return list<int>
     */
    protected function leadIdsWithEngagement(int $accountId, string $type, int $days): array
    {
        return MessageSend::withoutGlobalScopes()
            ->where('account_id', $accountId)
            ->whereNotNull('lead_id')
            ->whereHas('events', fn ($query) => $query
                ->where('type', $type)
                ->where('occurred_at', '>=', now()->subDays($days)))
            ->pluck('lead_id')
            ->unique()
            ->filter()
            ->values()
            ->all();
    }

    protected function ensureBouncedSegment(Account $account): Segment
    {
        return Segment::withoutGlobalScopes()->firstOrCreate(
            [
                'account_id' => $account->id,
                'name' => 'bounced',
            ],
            [
                'type' => 'dynamic',
                'rules' => [
                    'tags' => [self::TAG_BOUNCED, self::TAG_SUPPRESS_MARKETING],
                ],
            ],
        );
    }

    /**
     * @param  list<string>  $tags
     */
    protected function leadHasAllHygieneTags(Lead $lead, array $tags): bool
    {
        $existing = LeadTag::query()
            ->where('lead_id', $lead->id)
            ->whereIn('tag', $tags)
            ->pluck('tag');

        return collect($tags)->every(fn (string $tag) => $existing->contains($tag));
    }

    /**
     * @param  list<string>  $tags
     */
    protected function leadHasHygieneTags(Lead $lead, array $tags): bool
    {
        return LeadTag::query()
            ->where('lead_id', $lead->id)
            ->whereIn('tag', $tags)
            ->exists();
    }

    protected function removeActiveMarketingTags(Lead $lead): void
    {
        LeadTag::query()
            ->where('lead_id', $lead->id)
            ->whereIn('tag', ['active', 'marketing_active'])
            ->each(fn (LeadTag $tag) => $this->segments->untagLead($lead, $tag->tag));
    }

    protected function storeLastRun(Account $account, int $bouncesTagged, int $inactiveTagged): void
    {
        $settings = $account->settings ?? [];
        $messaging = $settings['messaging'] ?? [];

        $messaging['hygiene_last_run'] = [
            'ran_at' => now()->toIso8601String(),
            'bounces_tagged' => $bouncesTagged,
            'inactive_tagged' => $inactiveTagged,
        ];

        $settings['messaging'] = $messaging;
        $account->update(['settings' => $settings]);
    }
}
