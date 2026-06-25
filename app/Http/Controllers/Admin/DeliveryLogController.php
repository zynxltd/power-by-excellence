<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Campaign;
use App\Models\Buyer;
use App\Models\Delivery;
use App\Models\DeliveryLog;
use App\Support\Tenancy\AccountContext;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DeliveryLogController extends Controller
{
    public function index(Request $request): Response
    {
        $days = (int) $request->input('days', 7);
        $days = in_array($days, [1, 7, 14, 28], true) ? $days : 7;
        $since = $request->filled('date_from')
            ? \Carbon\Carbon::parse($request->input('date_from'))->startOfDay()
            : now()->subDays($days)->startOfDay();
        $until = $request->filled('date_to')
            ? \Carbon\Carbon::parse($request->input('date_to'))->endOfDay()
            : now()->endOfDay();

        $query = DeliveryLog::query()
            ->with(['lead.campaign', 'delivery', 'buyer'])
            ->whereBetween('delivery_logs.created_at', [$since, $until])
            ->orderByDesc('delivery_logs.created_at');

        if ($accountId = $request->input('account_id')) {
            $query->whereHas('delivery.campaign', fn ($q) => $q->withoutGlobalScopes()->where('account_id', (int) $accountId));
        } else {
            $query->forCurrentAccount();
        }

        if ($status = $request->input('status')) {
            $query->where('delivery_logs.status', $status);
        }

        if ($method = $request->input('method')) {
            if ($method === 'ping-post') {
                $query->whereNotNull('ping_request');
            } elseif ($method === 'direct') {
                $query->whereNull('ping_request');
            }
        }

        if ($deliveryId = $request->input('delivery_id')) {
            $query->where('delivery_logs.delivery_id', $deliveryId);
        }

        if ($buyerId = $request->input('buyer_id')) {
            $query->where('delivery_logs.buyer_id', $buyerId);
        }

        if ($tier = $request->input('tier')) {
            $query->whereHas('delivery', fn ($q) => $q->where('tier', $tier));
        }

        if ($campaignId = $request->input('campaign_id')) {
            $query->whereHas('delivery', fn ($q) => $q->where('campaign_id', $campaignId));
        }

        if ($search = $request->input('q')) {
            $query->whereNotNull('delivery_logs.post_request');
        }

        if ($request->boolean('has_ping')) {
            $query->whereNotNull('delivery_logs.ping_request');
        }

        if ($search = $request->input('q')) {
            $query->whereHas('lead', fn ($q) => $q->where('uuid', 'like', "%{$search}%"));
        }

        $base = clone $query;
        $stats = [
            'total' => (clone $base)->count(),
            'success' => (clone $base)->where('status', 'success')->count(),
            'failed' => (clone $base)->where('status', 'failed')->count(),
            'skipped' => (clone $base)->where('status', 'skipped')->count(),
            'outbid' => (clone $base)->where('status', 'outbid')->count(),
            'avg_ms' => (int) round((clone $base)->avg('duration_ms') ?? 0),
        ];

        $logs = $query->paginate(50)->withQueryString()->through(fn (DeliveryLog $log) => [
            'id' => $log->id,
            'status' => $log->status,
            'delivery' => $log->delivery?->name,
            'delivery_id' => $log->delivery_id,
            'buyer' => $log->buyer?->name,
            'lead_id' => $log->lead_id,
            'lead_uuid' => $log->lead?->uuid,
            'campaign' => $log->lead?->campaign?->name,
            'method' => $log->ping_request ? 'ping-post' : 'direct',
            'revenue' => $log->revenue,
            'duration_ms' => $log->duration_ms,
            'http_status' => $log->http_status,
            'skipped_reason' => $log->skipped_reason,
            'created_at' => $log->created_at?->toDateTimeString(),
        ]);

        return Inertia::render('Admin/DeliveryLogs/Index', [
            'logs' => $logs,
            'stats' => $stats,
            'filters' => [
                'days' => $days,
                'status' => $request->input('status'),
                'method' => $request->input('method'),
                'delivery_id' => $request->input('delivery_id'),
                'buyer_id' => $request->input('buyer_id'),
                'tier' => $request->input('tier'),
                'campaign_id' => $request->input('campaign_id'),
                'account_id' => $request->input('account_id'),
                'has_post' => $request->boolean('has_post') ? '1' : null,
                'has_ping' => $request->boolean('has_ping') ? '1' : null,
                'date_from' => $request->input('date_from'),
                'date_to' => $request->input('date_to'),
                'q' => $request->input('q'),
            ],
            'deliveries' => Delivery::orderBy('name')->get(['id', 'name']),
            'buyers' => Buyer::orderBy('name')->get(['id', 'name']),
            'statusOptions' => ['success', 'failed', 'skipped', 'outbid', 'ping_ok'],
        ]);
    }

    public function show(DeliveryLog $deliveryLog): Response
    {
        $this->ensureLogAccessible($deliveryLog);

        $deliveryLog->load(['lead.campaign', 'lead.financials', 'delivery', 'buyer']);

        return Inertia::render('Admin/DeliveryLogs/Show', [
            'log' => [
                'id' => $deliveryLog->id,
                'status' => $deliveryLog->status,
                'skipped_reason' => $deliveryLog->skipped_reason,
                'revenue' => $deliveryLog->revenue,
                'duration_ms' => $deliveryLog->duration_ms,
                'http_status' => $deliveryLog->http_status,
                'method' => $deliveryLog->ping_request ? 'ping-post' : 'direct',
                'created_at' => $deliveryLog->created_at?->toDateTimeString(),
                'ping_request' => $deliveryLog->ping_request,
                'ping_response' => $deliveryLog->ping_response,
                'post_request' => $deliveryLog->post_request,
                'post_response' => $deliveryLog->post_response,
                'delivery' => $deliveryLog->delivery?->only(['id', 'name', 'method', 'tier']),
                'buyer' => $deliveryLog->buyer?->only(['id', 'name']),
                'lead' => $deliveryLog->lead ? [
                    'id' => $deliveryLog->lead->id,
                    'uuid' => $deliveryLog->lead->uuid,
                    'status' => $deliveryLog->lead->status->value ?? $deliveryLog->lead->status,
                    'campaign' => $deliveryLog->lead->campaign?->name,
                    'revenue' => $deliveryLog->lead->financials?->revenue,
                ] : null,
            ],
        ]);
    }

    protected function ensureLogAccessible(DeliveryLog $deliveryLog): void
    {
        if (! $accountId = AccountContext::id()) {
            return;
        }

        $campaignAccountId = Campaign::withoutGlobalScopes()
            ->whereIn('id', function ($q) use ($deliveryLog) {
                $q->select('campaign_id')
                    ->from('deliveries')
                    ->where('id', $deliveryLog->delivery_id);
            })
            ->value('account_id');

        abort_unless($campaignAccountId && (int) $campaignAccountId === (int) $accountId, 404);
    }
}
