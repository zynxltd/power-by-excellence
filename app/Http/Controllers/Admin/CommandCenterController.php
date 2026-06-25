<?php

namespace App\Http\Controllers\Admin;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\Account;
use App\Models\DeliveryLog;
use App\Models\EventAlertFire;
use App\Models\Lead;
use App\Models\LeadEvent;
use App\Models\User;
use App\Services\Platform\PlatformNotificationService;
use App\Services\Platform\PlatformOpsCheck;
use App\Services\Platform\PlatformStatusService;
use App\Services\Platform\ProcessingMetrics;
use App\Services\Platform\TenantHealth;
use App\Support\Delivery\DeliveryLogClassifier;
use App\Support\Tenancy\AccountContext;
use App\Support\Tenancy\TenantResolver;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class CommandCenterController extends Controller
{
    public function index(Request $request): Response
    {
        abort_unless($request->user()?->isSuperAdmin(), 403);

        $tenants = Account::withCount(['campaigns', 'leads', 'buyers', 'suppliers'])
            ->orderBy('name')
            ->get()
            ->map(fn (Account $a) => [
                'id' => $a->id,
                'name' => $a->brand_name ?: $a->name,
                'slug' => $a->slug,
                'domain' => $a->resolvedDomain(),
                'portal_url' => TenantResolver::portalUrl($a, '/dashboard'),
                'admin_user' => $a->users()->whereIn('role', [UserRole::AccountAdmin, UserRole::Staff])->orderBy('id')->first(['id', 'name', 'email']),
                'is_active_context' => AccountContext::id() === $a->id,
                'campaigns_count' => $a->campaigns_count,
                'leads_count' => $a->leads_count,
                'buyers_count' => $a->buyers_count,
                'suppliers_count' => $a->suppliers_count,
                'leads_today' => Lead::withoutGlobalScopes()->where('account_id', $a->id)->whereDate('received_at', today())->count(),
                'sold_today' => Lead::withoutGlobalScopes()->where('account_id', $a->id)->whereDate('distributed_at', today())->where('status', 'sold')->count(),
                'pings_today' => $this->deliveryLogsForAccount($a->id)
                    ->whereDate('delivery_logs.created_at', today())
                    ->whereNotNull('delivery_logs.ping_request')
                    ->count(),
                'posts_today' => $this->deliveryLogsForAccount($a->id)
                    ->whereDate('delivery_logs.created_at', today())
                    ->whereNotNull('delivery_logs.post_request')
                    ->count(),
                'failed_today' => $this->deliveryLogsForAccount($a->id)
                    ->whereDate('delivery_logs.created_at', today())
                    ->where('delivery_logs.status', 'failed')
                    ->count(),
                'internal_failed_today' => $this->internalFailuresForAccount($a->id, today()),
                'buyer_failed_today' => $this->buyerFailuresForAccount($a->id, today()),
                'post_success_rate' => $this->postSuccessRateForAccount($a->id, today()),
                'skipped_today' => $this->deliveryLogsForAccount($a->id)
                    ->whereDate('delivery_logs.created_at', today())
                    ->where('delivery_logs.status', 'skipped')
                    ->count(),
                'pending' => Lead::withoutGlobalScopes()->where('account_id', $a->id)->whereIn('status', ['pending', 'processing'])->count(),
                'health' => app(TenantHealth::class)->status($a->id),
            ]);

        $processing = app(ProcessingMetrics::class);

        $platformStats = [
            'tenants' => $tenants->count(),
            'users' => User::count(),
            'leads_today' => Lead::withoutGlobalScopes()->whereDate('received_at', today())->count(),
            'sold_today' => Lead::withoutGlobalScopes()->whereDate('distributed_at', today())->where('status', 'sold')->count(),
            'pings_today' => DeliveryLog::whereDate('created_at', today())->whereNotNull('ping_request')->count(),
            'posts_today' => DeliveryLog::whereDate('created_at', today())->whereNotNull('post_request')->count(),
            'internal_failed_today' => $this->internalFailuresCount(today()),
            'post_success_rate' => $this->postSuccessRate(null, today()),
            'pending_queue' => Lead::withoutGlobalScopes()->whereIn('status', ['pending', 'processing'])->count(),
            'failed_jobs' => $this->failedJobsCount(),
            'avg_processing_ms' => $processing->avgProcessingMs(),
            'p95_processing_ms' => $processing->p95ProcessingMs(),
            'processing_target_ms' => $processing->targetMs(),
            'processing_on_target' => $processing->withinTarget(),
        ];

        $healthSummary = [
            'healthy' => $tenants->where('health', 'healthy')->count(),
            'warning' => $tenants->where('health', 'warning')->count(),
            'critical' => $tenants->where('health', 'critical')->count(),
            'idle' => $tenants->where('health', 'idle')->count(),
        ];

        $opsCheck = app(PlatformOpsCheck::class);
        app(PlatformNotificationService::class)->syncHerdLinkingAlert($opsCheck->herdLinkStatus());

        return Inertia::render('Admin/CommandCenter/Index', [
            'platformStats' => $platformStats,
            'healthSummary' => $healthSummary,
            'currentAccountId' => session('current_account_id'),
            'tenants' => $tenants,
            'recentEvents' => $this->recentLeadEvents(30),
            'recentAlerts' => EventAlertFire::with(['alert:id,name', 'account:id,name'])
                ->orderByDesc('created_at')
                ->limit(15)
                ->get(),
            'opsChecks' => $opsCheck->run(),
            'platformStatus' => app(PlatformStatusService::class)->publicPayload(),
        ]);
    }

    protected function deliveryLogsForAccount(int $accountId): Builder
    {
        return DeliveryLog::query()
            ->join('deliveries', 'deliveries.id', '=', 'delivery_logs.delivery_id')
            ->join('campaigns', 'campaigns.id', '=', 'deliveries.campaign_id')
            ->where('campaigns.account_id', $accountId);
    }

    protected function internalFailuresForAccount(int $accountId, \Carbon\CarbonInterface $date): int
    {
        return (int) DeliveryLogClassifier::scopeInternalFailures(
            $this->deliveryLogsForAccount($accountId)->whereDate('delivery_logs.created_at', $date)
        )->count();
    }

    protected function buyerFailuresForAccount(int $accountId, \Carbon\CarbonInterface $date): int
    {
        $failed = (int) $this->deliveryLogsForAccount($accountId)
            ->whereDate('delivery_logs.created_at', $date)
            ->where('delivery_logs.status', 'failed')
            ->count();

        return max(0, $failed - $this->internalFailuresForAccount($accountId, $date));
    }

    protected function internalFailuresCount(\Carbon\CarbonInterface $date): int
    {
        return (int) DeliveryLogClassifier::scopeInternalFailures(
            DeliveryLog::query()->whereDate('delivery_logs.created_at', $date)
        )->count();
    }

    protected function postSuccessRateForAccount(int $accountId, \Carbon\CarbonInterface $date): ?float
    {
        return $this->postSuccessRate($accountId, $date);
    }

    protected function postSuccessRate(?int $accountId, \Carbon\CarbonInterface $date): ?float
    {
        $query = $accountId
            ? $this->deliveryLogsForAccount($accountId)
            : DeliveryLog::query();

        $posts = (clone $query)
            ->whereDate('delivery_logs.created_at', $date)
            ->whereNotNull('delivery_logs.post_request')
            ->count();

        if ($posts === 0) {
            return null;
        }

        $successes = (clone $query)
            ->whereDate('delivery_logs.created_at', $date)
            ->whereNotNull('delivery_logs.post_request')
            ->where('delivery_logs.status', 'success')
            ->count();

        return round(($successes / $posts) * 100, 1);
    }

    protected function failedJobsCount(): int
    {
        if (! DB::getSchemaBuilder()->hasTable('failed_jobs')) {
            return 0;
        }

        return (int) DB::table('failed_jobs')->count();
    }

    protected function recentLeadEvents(int $limit): array
    {
        return LeadEvent::query()
            ->with(['lead' => fn ($q) => $q->withoutGlobalScopes()->with('account:id,name')])
            ->orderByDesc('created_at')
            ->limit($limit)
            ->get()
            ->map(fn (LeadEvent $e) => [
                'id' => $e->id,
                'event_type' => $e->event_type,
                'message' => $e->message,
                'created_at' => $e->created_at?->toDateTimeString(),
                'lead_id' => $e->lead_id,
                'lead_uuid' => $e->lead?->uuid,
                'tenant' => $e->lead?->account?->name,
            ])
            ->all();
    }

}
