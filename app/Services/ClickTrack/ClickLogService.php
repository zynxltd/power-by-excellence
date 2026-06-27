<?php

namespace App\Services\ClickTrack;

use App\Models\Account;
use App\Models\Lead;
use App\Models\TrackingClick;
use App\Models\TrackingImpression;
use App\Models\TrackingLink;
use App\Support\Tenancy\AccountContext;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class ClickLogService
{
    public function __construct(
        protected ClickTrackEntitlementService $entitlement,
        protected ClickCapService $caps,
    ) {}

    /**
     * @return array<string, string|null>
     */
    public function subsFromRequest(Request $request, ?TrackingLink $link = null): array
    {
        $config = $link?->config ?? [];

        return [
            'sub1' => $request->query('sub1', $request->query('subid', $config['default_sub1'] ?? null)),
            'sub2' => $request->query('sub2', $config['default_sub2'] ?? null),
            'sub3' => $request->query('sub3', $config['default_sub3'] ?? null),
            'sub4' => $request->query('sub4', $config['default_sub4'] ?? null),
            'sub5' => $request->query('sub5', $config['default_sub5'] ?? null),
            'source' => $request->query('source', $config['default_source'] ?? null),
        ];
    }

    public function resolveLink(string $token): ?TrackingLink
    {
        return TrackingLink::withoutGlobalScopes()
            ->with(['campaign', 'account'])
            ->where('token', $token)
            ->first();
    }

    public function logImpression(TrackingLink $link, Request $request): TrackingImpression
    {
        AccountContext::set($link->account);

        return TrackingImpression::create([
            'account_id' => $link->account_id,
            'tracking_link_id' => $link->id,
            'impression_uuid' => (string) Str::uuid(),
            ...$this->subsFromRequest($request, $link),
            'ip_address' => $request->ip(),
            'user_agent' => Str::limit((string) $request->userAgent(), 500, ''),
            'impressed_at' => now(),
        ]);
    }

    public function logClick(TrackingLink $link, Request $request): TrackingClick
    {
        AccountContext::set($link->account);

        if (! $this->entitlement->isEntitled($link->account)) {
            abort(403, 'Click Track is not enabled for this platform.');
        }

        if ($this->caps->linkCapReached($link)) {
            abort(429, 'Offer cap reached for this tracking link.');
        }

        $subs = $this->subsFromRequest($request, $link);
        $ip = $request->ip();
        $windowHours = (int) config('click_track.unique_click_window_hours', 24);

        $isUnique = ! TrackingClick::withoutGlobalScopes()
            ->where('tracking_link_id', $link->id)
            ->where('ip_address', $ip)
            ->where('clicked_at', '>=', now()->subHours($windowHours))
            ->exists();

        $click = TrackingClick::create([
            'account_id' => $link->account_id,
            'tracking_link_id' => $link->id,
            'campaign_id' => $link->campaign_id,
            'supplier_id' => $link->supplier_id,
            'click_uuid' => (string) Str::uuid(),
            ...$subs,
            'referrer' => Str::limit((string) $request->headers->get('referer'), 500, ''),
            'ip_address' => $ip,
            'user_agent' => Str::limit((string) $request->userAgent(), 500, ''),
            'country' => $this->guessCountry($request),
            'device' => $this->guessDevice($request),
            'is_unique' => $isUnique,
            'clicked_at' => now(),
        ]);

        $this->incrementUsage($link->account);

        return $click;
    }

    public function attachLeadByClickUuid(Lead $lead, ?string $clickUuid): void
    {
        if (! $clickUuid) {
            return;
        }

        $click = TrackingClick::withoutGlobalScopes()
            ->where('click_uuid', $clickUuid)
            ->where('account_id', $lead->account_id)
            ->first();

        if (! $click) {
            return;
        }

        if ($click->lead_id && $click->lead_id !== $lead->id) {
            return;
        }

        $click->update(['lead_id' => $lead->id]);
        $lead->forceFill(['tracking_click_id' => $click->id])->save();

        if (! $lead->supplier_id && $click->supplier_id) {
            $lead->update(['supplier_id' => $click->supplier_id]);
        }
    }

    public function buildDestination(TrackingLink $link, TrackingClick $click, array $subs): string
    {
        $destination = $link->destination_url;
        $query = array_filter(array_merge($subs, [
            'click_id' => $click->click_uuid,
            'subid' => $subs['sub1'] ?? null,
        ]));

        $separator = str_contains($destination, '?') ? '&' : '?';

        return $destination.$separator.http_build_query($query);
    }

    protected function incrementUsage(Account $account): void
    {
        $settings = $account->settings ?? [];
        $clickTrack = $settings['click_track'] ?? [];
        $clickTrack['usage_count'] = (int) ($clickTrack['usage_count'] ?? 0) + 1;
        $settings['click_track'] = $clickTrack;
        $account->update(['settings' => $settings]);
    }

    protected function guessCountry(Request $request): ?string
    {
        $country = $request->headers->get('CF-IPCountry') ?? $request->headers->get('X-Country-Code');

        return $country ? strtoupper(substr((string) $country, 0, 2)) : null;
    }

    protected function guessDevice(Request $request): ?string
    {
        $ua = strtolower((string) $request->userAgent());
        if (str_contains($ua, 'mobile') || str_contains($ua, 'android') || str_contains($ua, 'iphone')) {
            return 'mobile';
        }
        if (str_contains($ua, 'tablet') || str_contains($ua, 'ipad')) {
            return 'tablet';
        }

        return $ua ? 'desktop' : null;
    }
}
