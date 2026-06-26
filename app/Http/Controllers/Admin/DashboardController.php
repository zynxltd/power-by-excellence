<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Account;
use App\Models\Campaign;
use App\Models\Lead;
use App\Support\Tenancy\AccountContext;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __invoke(Request $request): Response
    {
        $chartDays = (int) $request->input('chart_days', 7);
        $chartDays = in_array($chartDays, [7, 14, 30], true) ? $chartDays : 7;

        $stats = [
            'leads_today' => Lead::whereDate('received_at', today())->count(),
            'sold_today' => Lead::whereDate('distributed_at', today())->where('status', 'sold')->count(),
            'unsold_today' => Lead::whereDate('received_at', today())->where('status', 'unsold')->count(),
            'revenue_today' => (float) DB::table('lead_financials')
                ->join('leads', 'leads.id', '=', 'lead_financials.lead_id')
                ->whereDate('leads.distributed_at', today())
                ->sum('lead_financials.revenue'),
            'reject_rate' => $this->rejectRate(),
            'quarantined' => Lead::where('status', 'quarantined')->count(),
            'pending' => Lead::where('status', 'pending')->count(),
        ];

        $recentLeads = Lead::with(['campaign', 'campaign.account', 'soldToBuyer', 'financials', 'account'])
            ->orderByDesc('received_at')
            ->paginate(15)
            ->withQueryString();

        $account = $request->attributes->get('account') ?? $request->user()?->account;
        $currency = $account?->default_currency ?? 'GBP';

        $tenantOverview = null;
        if ($request->user()?->isSuperAdmin()) {
            $tenantOverview = Account::withCount(['campaigns', 'leads', 'buyers', 'suppliers'])
                ->orderBy('name')
                ->get()
                ->map(fn (Account $a) => [
                    'id' => $a->id,
                    'name' => $a->brand_name ?: $a->name,
                    'slug' => $a->slug,
                    'campaigns_count' => $a->campaigns_count,
                    'leads_count' => $a->leads_count,
                    'buyers_count' => $a->buyers_count,
                    'suppliers_count' => $a->suppliers_count,
                    'leads_today' => Lead::withoutGlobalScope('account')
                        ->where('account_id', $a->id)
                        ->whereDate('received_at', today())
                        ->count(),
                    'is_active' => AccountContext::id() === $a->id,
                ]);

        }

        return Inertia::render('Admin/Dashboard', [
            'stats' => $stats,
            'recentLeads' => $recentLeads,
            'charts' => $this->chartData($chartDays),
            'chartDays' => $chartDays,
            'currency' => $currency,
            'tenantOverview' => $tenantOverview,
            'showTenantColumn' => ! AccountContext::id() && $request->user()?->isSuperAdmin(),
            'quickLinkGroups' => $this->quickLinkGroups($request),
        ]);
    }

    /**
     * @return list<array{title: string, links: list<array{label: string, href: string}>}>
     */
    protected function quickLinkGroups(Request $request): array
    {
        $isSuperAdmin = $request->user()?->isSuperAdmin();

        $tenantLinks = [
            ['label' => 'Buyers', 'href' => route('buyers.index')],
            ['label' => 'Suppliers', 'href' => route('suppliers.index')],
            ['label' => 'Users', 'href' => route('users.index')],
        ];

        if ($isSuperAdmin && \App\Support\Tenancy\TenantResolver::isCentralHost($request->getHost())) {
            array_unshift($tenantLinks, ['label' => 'Partner platforms', 'href' => route('accounts.index')]);
            if (AccountContext::id()) {
                array_unshift($tenantLinks, ['label' => 'Branding', 'href' => route('branding.edit')]);
                array_unshift($tenantLinks, ['label' => 'Platform settings', 'href' => route('settings.edit')]);
            }
        } else {
            array_unshift($tenantLinks, ['label' => 'Branding', 'href' => route('branding.edit')]);
            array_unshift($tenantLinks, ['label' => 'Platform settings', 'href' => route('settings.edit')]);
        }

        return [
            [
                'title' => 'Tenant management',
                'links' => $tenantLinks,
            ],
            [
                'title' => 'Campaigns & leads',
                'links' => [
                    ['label' => 'All campaigns', 'href' => route('campaigns.index')],
                    ['label' => 'Lead pipeline', 'href' => route('leads.index')],
                    ['label' => 'Hosted forms', 'href' => route('forms.index')],
                    ['label' => 'Import data', 'href' => route('imports.index')],
                ],
            ],
            [
                'title' => 'Operations',
                'links' => [
                    ['label' => 'Live operations', 'href' => route('operations.index')],
                    ['label' => 'Deliveries', 'href' => route('deliveries.index')],
                    ['label' => 'Delivery logs', 'href' => route('logs.delivery')],
                    ['label' => 'Quarantine', 'href' => route('quarantine.index')],
                ],
            ],
            [
                'title' => 'Finance & reporting',
                'links' => [
                    ['label' => 'Billing', 'href' => route('billing.index')],
                    ['label' => 'Reports', 'href' => route('reports.index')],
                    ['label' => 'API keys', 'href' => route('api-keys.index')],
                    ['label' => 'Integrations', 'href' => route('integrations.index')],
                ],
            ],
        ];
    }

    protected function chartData(int $days = 7): array
    {
        $labels = [];
        $leads = [];
        $sold = [];

        for ($i = $days - 1; $i >= 0; $i--) {
            $date = today()->subDays($i);
            $labels[] = $date->format('D j');
            $leads[] = Lead::whereDate('received_at', $date)->count();
            $sold[] = Lead::whereDate('distributed_at', $date)->where('status', 'sold')->count();
        }

        $statusBreakdown = Lead::query()
            ->select('status', DB::raw('count(*) as count'))
            ->whereDate('received_at', '>=', today()->subDays($days))
            ->groupBy('status')
            ->pluck('count', 'status')
            ->toArray();

        return [
            'labels' => $labels,
            'leads' => $leads,
            'sold' => $sold,
            'status_breakdown' => $statusBreakdown,
            'chart_days' => $days,
        ];
    }

    protected function rejectRate(): float
    {
        $total = Lead::whereDate('received_at', today())->count();
        if ($total === 0) {
            return 0;
        }

        $rejected = Lead::whereDate('received_at', today())->where('status', 'rejected')->count();

        return round(($rejected / $total) * 100, 1);
    }
}
