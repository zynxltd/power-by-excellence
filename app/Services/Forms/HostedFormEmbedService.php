<?php

namespace App\Services\Forms;

use App\Models\Account;
use App\Models\Campaign;
use App\Models\HostedForm;
use App\Models\Source;
use App\Models\Supplier;
use App\Services\Forms\SupplierHostedFormService;
use Illuminate\Http\Request;

class HostedFormEmbedService
{
    /** @var list<string> */
    public const TRACKING_QUERY_PARAMS = [
        'sid',
        'ssid',
        'subid',
        'supplier_id',
        'click_id',
        'gclid',
        'fbclid',
        'utm_source',
        'utm_medium',
        'utm_campaign',
        'utm_content',
        'utm_term',
    ];

    public function accountAllowsSupplierIframeEmbed(?Account $account): bool
    {
        if (! $account) {
            return false;
        }

        return (bool) ($account->settings['supplier_iframe_embed'] ?? false);
    }

    public function isEmbedRequest(Request $request): bool
    {
        return $request->boolean('embed') || $request->query('embed') === '1';
    }

    public function assertEmbedAllowed(HostedForm $form, Request $request): void
    {
        if (! $this->isEmbedRequest($request)) {
            return;
        }

        $form->loadMissing('account');

        if (! $this->accountAllowsSupplierIframeEmbed($form->account)) {
            abort(403, 'Iframe embed is not enabled for this platform.');
        }
    }

    /**
     * @param  array<string, string>  $params
     * @return array<string, mixed>
     */
    public function embedPayload(HostedForm $form, array $params = [], bool $iframe = false): array
    {
        $height = (int) ($form->config['embed_height'] ?? 720);

        return [
            'directUrl' => $this->embedUrl($form, $params, false),
            'iframeUrl' => $this->embedUrl($form, $params, true),
            'iframeHtml' => $this->iframeSnippet($form, $params, $height),
            'trackingParams' => self::TRACKING_QUERY_PARAMS,
        ];
    }

    /**
     * @return array<string, string>
     */
    public function formTrackingParams(HostedForm $form, Supplier $supplier): array
    {
        $config = $form->config ?? [];

        if (! empty($config['default_source_id'])) {
            $source = $supplier->sources()->whereKey((int) $config['default_source_id'])->first();
        } else {
            $source = null;
        }

        if (! $source && ! empty($config['default_sid'])) {
            $source = $supplier->sources()->where('sid', $config['default_sid'])->first();
        }

        return $this->supplierTrackingParams($supplier, $source);
    }

    /**
     * @return array<string, string>
     */
    public function supplierTrackingParams(Supplier $supplier, ?Source $source = null): array
    {
        $source ??= $supplier->sources()->orderBy('sid')->first();

        return array_filter([
            'supplier_id' => (string) $supplier->id,
            'sid' => $source?->sid,
        ]);
    }

    /**
     * @return list<HostedForm>
     */
    public function formsForSupplier(Supplier $supplier): array
    {
        $campaignIds = $supplier->campaignSuppliers()->pluck('campaign_id');

        return HostedForm::withoutGlobalScopes()
            ->where('account_id', $supplier->account_id)
            ->live()
            ->where(function ($q) use ($campaignIds, $supplier) {
                $q->where(function ($inner) use ($supplier) {
                    $inner->where('supplier_id', $supplier->id)
                        ->where('approval_status', SupplierHostedFormService::STATUS_APPROVED);
                })->orWhere(function ($inner) use ($supplier) {
                    $inner->whereNull('supplier_id')
                        ->where(function ($config) use ($supplier) {
                            $config->where('config->default_supplier_id', $supplier->id)
                                ->orWhere('config->default_supplier_id', (string) $supplier->id);
                        });
                });

                if ($campaignIds->isNotEmpty()) {
                    $q->orWhere(function ($inner) use ($campaignIds) {
                        $inner->whereNull('supplier_id')
                            ->whereIn('campaign_id', $campaignIds);
                    });
                }
            })
            ->with('campaign:id,name,reference')
            ->orderBy('name')
            ->get()
            ->all();
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, string>
     */
    public function resolveTracking(HostedForm $form, Request $request, array $overrides = []): array
    {
        $config = $form->config ?? [];
        $defaults = array_filter([
            'supplier_id' => isset($config['default_supplier_id']) ? (string) $config['default_supplier_id'] : null,
            'sid' => $config['default_sid'] ?? null,
        ]);

        $fromQuery = collect(self::TRACKING_QUERY_PARAMS)
            ->mapWithKeys(fn (string $key) => [$key => $request->query($key)])
            ->filter(fn ($value) => $value !== null && $value !== '')
            ->map(fn ($value) => (string) $value)
            ->all();

        $tracking = array_merge($defaults, $fromQuery, array_filter($overrides));

        if (! empty($tracking['subid']) && empty($tracking['ssid'])) {
            $tracking['ssid'] = $tracking['subid'];
        }

        return $tracking;
    }

    /**
     * @param  array<string, string>  $params
     */
    public function embedUrl(HostedForm $form, array $params = [], bool $iframe = false): string
    {
        $query = array_filter(array_merge(
            $iframe ? ['embed' => '1'] : [],
            $params,
        ));

        $url = route('forms.show', $form->slug);

        return $query === [] ? $url : $url.'?'.http_build_query($query);
    }

    /**
     * @param  array<string, string>  $params
     */
    public function iframeSnippet(HostedForm $form, array $params = [], int $height = 720): string
    {
        $src = htmlspecialchars($this->embedUrl($form, $params, true), ENT_QUOTES, 'UTF-8');

        return sprintf(
            '<iframe src="%s" title="%s" width="100%%" height="%d" frameborder="0" loading="lazy" style="border:0;max-width:100%%;"></iframe>',
            $src,
            htmlspecialchars($form->name, ENT_QUOTES, 'UTF-8'),
            $height
        );
    }

    public function frameAncestorsDirective(HostedForm $form): ?string
    {
        $form->loadMissing('account');

        if (! $this->accountAllowsSupplierIframeEmbed($form->account)) {
            return "frame-ancestors 'self'";
        }

        $allowed = $form->config['allowed_domains'] ?? [];
        if ($allowed === []) {
            return null;
        }

        $hosts = collect($allowed)
            ->map(fn (string $domain) => trim($domain))
            ->filter()
            ->map(fn (string $domain) => 'https://'.$domain)
            ->implode(' ');

        return "frame-ancestors 'self' {$hosts}";
    }

    public function assertSubmitRefererAllowed(HostedForm $form, Request $request): void
    {
        if ($this->isEmbedRequest($request)) {
            $this->assertEmbedAllowed($form, $request);
        }

        $allowed = $form->config['allowed_domains'] ?? [];
        if ($allowed === []) {
            return;
        }

        $refererHost = parse_url($request->headers->get('referer', ''), PHP_URL_HOST);
        if (! $refererHost) {
            return;
        }

        $appHost = parse_url(config('app.url'), PHP_URL_HOST);
        if ($refererHost === $appHost) {
            return;
        }

        if (! in_array($refererHost, $allowed, true)) {
            abort(403, 'Domain not allowed');
        }
    }

    /**
     * @return array{supplier_id: ?int, source_id: ?int, sub_supplier_id: ?int, sid: ?string, ssid: ?string}
     */
    public function resolveSupplierContext(Campaign $campaign, array $data): array
    {
        $supplierId = isset($data['supplier_id']) ? (int) $data['supplier_id'] : null;
        $sid = $data['sid'] ?? null;
        $ssid = $data['ssid'] ?? $data['subid'] ?? null;

        if ($supplierId) {
            $belongs = Supplier::withoutGlobalScopes()
                ->where('account_id', $campaign->account_id)
                ->whereKey($supplierId)
                ->exists();
            if (! $belongs) {
                $supplierId = null;
            }
        }

        $sourceId = null;
        $subSupplierId = null;

        if ($sid) {
            $sourceQuery = Source::query()
                ->whereHas('supplier', fn ($q) => $q->where('account_id', $campaign->account_id))
                ->where('sid', $sid);

            if ($supplierId) {
                $sourceQuery->where('supplier_id', $supplierId);
            }

            $source = $sourceQuery->first();
            $sourceId = $source?->id;
            $supplierId ??= $source?->supplier_id;
        }

        if ($ssid && $sourceId) {
            $sub = \App\Models\SubSupplier::where('source_id', $sourceId)->where('ssid', $ssid)->first();
            $subSupplierId = $sub?->id;
        }

        return [
            'supplier_id' => $supplierId,
            'source_id' => $sourceId,
            'sub_supplier_id' => $subSupplierId,
            'sid' => $sid,
            'ssid' => $ssid,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function trackingMetadata(array $data): array
    {
        $keys = array_merge(self::TRACKING_QUERY_PARAMS, ['embed']);
        $tracking = collect($data)->only($keys)->filter(fn ($v) => $v !== null && $v !== '')->all();

        return $tracking === [] ? [] : ['tracking' => $tracking];
    }
}
