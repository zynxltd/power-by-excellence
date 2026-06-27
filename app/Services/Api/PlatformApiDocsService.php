<?php

namespace App\Services\Api;

class PlatformApiDocsService
{
    /**
     * @return list<array{key: string, method: string, path: string, scope: string|null, summary: string, description: string}>
     */
    public function endpoints(): array
    {
        return [
            [
                'key' => 'ingest',
                'method' => 'POST',
                'path' => '/leads',
                'scope' => 'leads.create',
                'summary' => 'Submit a lead',
                'description' => 'Validates the payload against the campaign API spec, runs dedupe and suppression checks, then queues the lead for distribution. Returns 202 with lead_id and queue_id unless sync=true.',
            ],
            [
                'key' => 'status-lead',
                'method' => 'GET',
                'path' => '/leads/{lead_id}',
                'scope' => 'leads.read',
                'summary' => 'Poll lead status',
                'description' => 'Returns the current pipeline status, revenue, redirect URL (when sold), and reject_reason (when rejected at ingest). Poll every 1–2 seconds until the status is terminal.',
            ],
            [
                'key' => 'status-queue',
                'method' => 'GET',
                'path' => '/leads/queue/{queue_id}',
                'scope' => 'leads.read',
                'summary' => 'Poll by queue ID',
                'description' => 'Same response shape as GET /leads/{lead_id}. Use whichever identifier you stored from the 202 Accepted response.',
            ],
            [
                'key' => 'search',
                'method' => 'POST',
                'path' => '/leads/search',
                'scope' => 'leads.read',
                'summary' => 'Search leads',
                'description' => 'Paginated lead search filtered by campaign_id and status. Useful for reconciliation jobs.',
            ],
            [
                'key' => 'reprocess',
                'method' => 'POST',
                'path' => '/leads/{lead_id}/reprocess',
                'scope' => 'leads.read',
                'summary' => 'Re-queue a lead',
                'description' => 'Pushes the lead back through the processing pipeline. Use after fixing validation or buyer configuration.',
            ],
            [
                'key' => 'import',
                'method' => 'POST',
                'path' => '/leads/import',
                'scope' => 'leads.create',
                'summary' => 'Bulk CSV import',
                'description' => 'Upload leads in bulk via the import API. Same validation rules as single POST /leads.',
            ],
            [
                'key' => 'reports-leads',
                'method' => 'GET',
                'path' => '/reports/leads',
                'scope' => 'reports.read',
                'summary' => 'Lead volume report',
                'description' => 'Aggregated lead counts for a date range.',
            ],
            [
                'key' => 'reports-revenue',
                'method' => 'GET',
                'path' => '/reports/revenue',
                'scope' => 'reports.read',
                'summary' => 'Revenue report',
                'description' => 'Revenue and margin aggregates for a date range.',
            ],
            [
                'key' => 'platform-export',
                'method' => 'GET',
                'path' => '/platform',
                'scope' => 'platform.read',
                'summary' => 'Export platform configuration',
                'description' => 'Full snapshot of campaigns, buyers, suppliers, routing, webhooks, postbacks, and hosted forms. Use when mirroring config into your own admin or BI stack.',
            ],
            [
                'key' => 'platform-campaign',
                'method' => 'GET',
                'path' => '/platform/campaigns/{reference}',
                'scope' => 'platform.read',
                'summary' => 'Export one campaign',
                'description' => 'Campaign schema, deliveries, ping-tree configs, and API spec for a single campaign reference.',
            ],
            [
                'key' => 'quarantine-list',
                'method' => 'GET',
                'path' => '/quarantine',
                'scope' => 'quarantine.manage',
                'summary' => 'List quarantined leads',
                'description' => 'Paginated list of leads held for manual review.',
            ],
            [
                'key' => 'quarantine-release',
                'method' => 'POST',
                'path' => '/quarantine/{lead_id}/release',
                'scope' => 'quarantine.manage',
                'summary' => 'Release from quarantine',
                'description' => 'Re-queue a quarantined lead for distribution.',
            ],
            [
                'key' => 'quarantine-reject',
                'method' => 'POST',
                'path' => '/quarantine/{lead_id}/reject',
                'scope' => 'quarantine.manage',
                'summary' => 'Reject quarantined lead',
                'description' => 'Permanently reject a quarantined lead with an optional reason.',
            ],
            [
                'key' => 'buyer-feedback',
                'method' => 'POST',
                'path' => '/buyers/{buyer_reference}/feedback',
                'scope' => 'buyers.manage',
                'summary' => 'Buyer conversion feedback',
                'description' => 'Report funded, contacted, or returned status for a sold lead. May fire the buyer conversion postback.',
            ],
            [
                'key' => 'buyer-credit',
                'method' => 'POST',
                'path' => '/buyers/{buyer_reference}/credit',
                'scope' => 'buyers.manage',
                'summary' => 'Top up buyer credit',
                'description' => 'Add prepaid credit to a buyer account via API.',
            ],
        ];
    }

    /**
     * @return list<array{permission: string, description: string}>
     */
    public function permissions(): array
    {
        return [
            ['permission' => 'leads.create', 'description' => 'POST new leads and bulk import'],
            ['permission' => 'leads.read', 'description' => 'Poll status, search, and reprocess leads'],
            ['permission' => 'reports.read', 'description' => 'Read aggregated reports endpoints'],
            ['permission' => 'platform.read', 'description' => 'Export platform configuration (campaigns, buyers, routing, integrations)'],
            ['permission' => 'quarantine.manage', 'description' => 'List, release, or reject quarantined leads'],
            ['permission' => 'buyers.manage', 'description' => 'Buyer feedback and credit top-up API'],
            ['permission' => '*', 'description' => 'Full access (administrator keys only)'],
        ];
    }

    /**
     * @return list<array{field: string, type: string, when: string, description: string}>
     */
    public function statusFields(): array
    {
        return [
            [
                'field' => 'status',
                'type' => 'string',
                'when' => 'Always',
                'description' => 'Pipeline state. Terminal values stop polling: sold, unsold, accepted (test mode), rejected, duplicate. In-flight: pending, validating, accepted, distributing, quarantined.',
            ],
            [
                'field' => 'lead_id',
                'type' => 'uuid',
                'when' => 'Always',
                'description' => 'Stable identifier for the lead. Use for status polling and support tickets.',
            ],
            [
                'field' => 'queue_id',
                'type' => 'string',
                'when' => 'Always',
                'description' => 'Short-lived queue reference from the ingest response. Alternative to lead_id for polling.',
            ],
            [
                'field' => 'test_mode',
                'type' => 'boolean',
                'when' => 'When test: true was sent',
                'description' => 'True when the lead was ingested in test mode. No buyer deliveries, postbacks, or credit debits were run.',
            ],
            [
                'field' => 'reject_reason',
                'type' => 'string|null',
                'when' => 'status = rejected',
                'description' => 'Human-readable reason the lead failed platform validation (duplicate email, campaign cap, suppression list, invalid field, inactive campaign, etc.). This is not a buyer ping/post rejection - see Delivery Logs for buyer responses.',
            ],
            [
                'field' => 'buyer_reference',
                'type' => 'string|null',
                'when' => 'status = sold',
                'description' => 'Reference of the buyer who purchased the lead.',
            ],
            [
                'field' => 'revenue',
                'type' => 'number|null',
                'when' => 'status = sold',
                'description' => 'Revenue credited for this lead in the account currency.',
            ],
            [
                'field' => 'currency',
                'type' => 'string',
                'when' => 'Always',
                'description' => 'ISO currency code for revenue (e.g. GBP, USD).',
            ],
            [
                'field' => 'redirect_url',
                'type' => 'string|null',
                'when' => 'status = sold',
                'description' => 'Thank-you or confirmation URL to send the consumer to. Resolved from the winning ping-tree tier redirect_url, then delivery redirect_url, then delivery accept_url.',
            ],
            [
                'field' => 'decline_url',
                'type' => 'string|null',
                'when' => 'status = unsold or quarantined',
                'description' => 'Decline or fallback URL when no buyer accepts after all ping-tree tiers. Set on the ping tree as the decline page at the final step.',
            ],
            [
                'field' => 'received_at',
                'type' => 'ISO 8601',
                'when' => 'Always',
                'description' => 'When the platform first received the lead.',
            ],
            [
                'field' => 'distributed_at',
                'type' => 'ISO 8601|null',
                'when' => 'After distribution completes',
                'description' => 'When the lead finished routing (sold or unsold). Null while still in-flight.',
            ],
        ];
    }

    /**
     * @return list<array{status: string, terminal: bool, description: string}>
     */
    public function leadStatuses(): array
    {
        return [
            ['status' => 'pending', 'terminal' => false, 'description' => 'Queued for background processing.'],
            ['status' => 'validating', 'terminal' => false, 'description' => 'Running field validation, dedupe, and suppression checks.'],
            ['status' => 'accepted', 'terminal' => true, 'description' => 'Validation passed. In test mode this is the final state. In live mode, distribution may still run.'],
            ['status' => 'distributing', 'terminal' => false, 'description' => 'Ping tree / waterfall routing in progress.'],
            ['status' => 'sold', 'terminal' => true, 'description' => 'A buyer accepted the lead. redirect_url and revenue are populated.'],
            ['status' => 'unsold', 'terminal' => true, 'description' => 'No buyer accepted after all tiers. decline_url is populated when configured on the ping tree.'],
            ['status' => 'rejected', 'terminal' => true, 'description' => 'Failed validation before distribution. See reject_reason.'],
            ['status' => 'duplicate', 'terminal' => true, 'description' => 'Matched an existing lead per campaign dedupe rules.'],
            ['status' => 'quarantined', 'terminal' => false, 'description' => 'Held for manual review. May be released or rejected via admin or quarantine API.'],
        ];
    }

    /**
     * @return list<array{title: string, body: string}>
     */
    public function guides(): array
    {
        return [
            [
                'title' => 'Async vs sync ingest',
                'body' => 'By default POST /leads returns 202 Accepted immediately and processes in the background. Pass "sync": true to block until distribution finishes and receive the final status in one response. Use async for production volume; sync for low-latency integrations and debugging.',
            ],
            [
                'title' => 'Test mode',
                'body' => 'Pass "test": true to validate the payload and run dedupe without pinging buyers, firing postbacks, or debiting buyer credit. Test leads end as accepted when validation passes. Omit test or set false for live traffic.',
            ],
            [
                'title' => 'Campaign reference',
                'body' => 'Every ingest request must include campaign_reference (e.g. loans-uk) or campaign_id. Field names and required flags are defined per campaign in the Campaign API Spec - each campaign can have a different schema.',
            ],
            [
                'title' => 'Buyer rejections vs platform rejections',
                'body' => 'reject_reason on the lead API is only for ingest/validation failures. When a buyer rejects a ping or post during routing, the lead may still end unsold - the buyer\'s message is stored in Delivery Logs (ping_response / post_response JSON), not in reject_reason.',
            ],
            [
                'title' => 'Authentication headers',
                'body' => 'Send your API key as Authorization: Bearer {prefix}|{secret} or as X-API-Key: {prefix}|{secret}. Both formats are equivalent. Keys are tenant-scoped - use the hostname shown on this page (not the central admin domain).',
            ],
            [
                'title' => 'Rate limits',
                'body' => 'Excessive requests return HTTP 429. Back off and retry with exponential delay. Contact your account manager if you need higher throughput.',
            ],
        ];
    }

    /**
     * @return list<array{title: string, body: string}>
     */
    public function platformGuides(): array
    {
        return [
            [
                'title' => 'When to use platform export',
                'body' => 'Use GET /platform when you operate your own front-end, CRM, or reporting stack but want PowerByExcellence to run lead routing, ping trees, and buyer delivery. Pull the snapshot on a schedule (e.g. every 15 minutes) to keep your local copy of campaigns, field schemas, buyers, and delivery endpoints in sync.',
            ],
            [
                'title' => 'Own platform scenario',
                'body' => 'Example: you run a white-label partner portal on your domain. Your app stores affiliate users and shows campaign lists from GET /platform. Lead ingest still goes to POST /leads using the campaign_reference and field schema from the export. Status polling uses GET /leads/{lead_id}. Your portal never needs admin UI access - only an API key with platform.read, leads.create, and leads.read.',
            ],
            [
                'title' => 'Partial exports',
                'body' => 'Pass include as a comma-separated list to limit payload size: include=campaigns,buyers or include=suppliers,postbacks. Platform metadata is always returned. Omit include for the full snapshot.',
            ],
            [
                'title' => 'Incremental sync',
                'body' => 'Compare updated_at on campaigns, buyers, and suppliers between polls. For a single campaign refresh, call GET /platform/campaigns/{reference} instead of re-downloading the full export.',
            ],
            [
                'title' => 'Secrets and credentials',
                'body' => 'Webhook signing secrets, API key tokens, and buyer portal passwords are never returned. Webhooks include has_secret: true when a secret is configured. Delivery buyer URLs and field schemas are included so your stack can document integrations.',
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function samplePlatformExport(): array
    {
        return [
            'exported_at' => '2026-06-26T12:00:00+00:00',
            'platform' => [
                'slug' => 'your-platform',
                'name' => 'Your Platform',
                'api_base_url' => 'https://your-platform.powerbyexcellence.test/api/v1',
                'portal_url' => 'https://your-platform.powerbyexcellence.test',
                'default_currency' => 'GBP',
                'timezone' => 'Europe/London',
            ],
            'campaigns' => [
                [
                    'reference' => 'loans-uk',
                    'name' => 'Loans UK',
                    'status' => 'active',
                    'fields' => [
                        ['name' => 'firstname', 'type' => 'string', 'required' => true],
                        ['name' => 'email', 'type' => 'email', 'required' => true],
                    ],
                    'api_spec' => ['version' => '1.0', 'fields' => []],
                    'deliveries' => [
                        ['name' => 'Tier 1 - Acme Buyer', 'tier' => 1, 'method' => 'ping_post'],
                    ],
                    'distribution_configs' => [
                        ['name' => 'Default ping tree', 'is_active' => true],
                    ],
                ],
            ],
            'buyers' => [
                ['reference' => 'acme-buyer', 'name' => 'Acme Buyer', 'status' => 'active', 'credit_balance' => 500.0],
            ],
            'suppliers' => [
                [
                    'reference' => 'main-affiliate',
                    'name' => 'Main Affiliate',
                    'sources' => [['sid' => 'AFF001', 'name' => 'Default source']],
                ],
            ],
        ];
    }
}
