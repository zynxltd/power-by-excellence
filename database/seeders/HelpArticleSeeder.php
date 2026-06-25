<?php

namespace Database\Seeders;

use App\Models\HelpArticle;
use Illuminate\Database\Seeder;

class HelpArticleSeeder extends Seeder
{
    public function run(): void
    {
        $articles = [
            // Tenant guides
            ['Getting Started', 'welcome', 'Welcome to PowerByExcellence', 'Platform overview and first steps.', 'tenant', 'Welcome to your partner platform. Create campaigns, add buyers and deliveries, then ingest leads via API or hosted forms. Run the queue worker for async processing.'],
            ['Getting Started', 'quick-start', 'Quick Start Guide', 'Set up your first campaign in minutes.', 'tenant', '1. Create a campaign with fields and payout. 2. Add buyers with credit. 3. Configure deliveries and ping-tree tiers. 4. Issue supplier API keys. 5. Submit test leads via POST /api/v1/leads.'],
            ['Leads', 'lead-ingest', 'Ingesting Leads via API', 'REST API lead submission guide.', 'tenant', 'POST /api/v1/leads with your supplier API key. Include campaign_reference, sid, optional ssid for sub-affiliate tracking, and lead fields. Use sync:true for immediate processing.'],
            ['Leads', 'lead-validation', 'Lead Validation & Filters', 'How validation and dedupe work.', 'tenant', 'Configure validation_config per campaign: require email/phone, block disposable emails, email/HLR API checks, custom rules. Failed checks can reject or quarantine based on settings.'],
            ['Leads', 'lead-quarantine', 'Lead Quarantine', 'Hold, review and release leads.', 'tenant', 'Leads quarantine for validation failures, unsold retries, or out-of-hours rules. Review in Operations → Quarantine. Release individually or in bulk.'],
            ['Campaigns', 'campaign-setup', 'Campaign Configuration', 'Fields, caps, and sell modes.', 'tenant', 'Campaigns define lead schema, payout, floor price, dedupe config, and ping-tree distribution. Each vertical ships a default field set you can customise.'],
            ['Campaigns', 'api-spec', 'Campaign API Spec', 'Map buyer API fields to your campaign.', 'tenant', 'The API spec documents ping/post parameters for each campaign. Tier and delivery filters use the same field names as your campaign schema and API spec.'],
            ['Campaigns', 'suppression-lists', 'Suppression Lists', 'Upload hashed customer data.', 'tenant', 'Import CSV suppression lists from Imports. Data is SHA-256 hashed. Suppression campaigns index leads without distribution.'],
            ['Delivery', 'delivery-methods', 'Delivery Methods', 'API, ping-post, email, SMS, store.', 'tenant', 'Supported methods: Direct POST, Ping-Post, Email Ping-Post, Email, SMS, Store Lead. Configure endpoints, timeouts, and revenue per delivery.'],
            ['Delivery', 'tier-filters', 'Tier & Delivery Filters', 'Dynamic filters against lead fields.', 'tenant', 'Ping-tree tier entry filters and per-delivery eligibility rules evaluate lead field values (state, zipcode, loan_amount, etc.). Field names must match your campaign schema — the same fields sent in API ingest and documented in the API spec.'],
            ['Delivery', 'email-ping-post', 'Email Ping-Post', 'Monetise unsold leads via email.', 'tenant', 'Email ping-post sends partial non-PII data to buyers with accept/reject links. Ideal for waterfall fallback tiers.'],
            ['Delivery', 'buyer-schedule', 'Buyer Schedules', 'Control when buyers receive leads.', 'tenant', 'Set delivery windows per buyer or delivery. Leads outside hours skip that buyer or quarantine if configured.'],
            ['Suppliers', 'affiliate-tracking', 'Affiliate & Sub-ID Tracking', 'SID, SSID, and postbacks.', 'tenant', 'Suppliers are affiliates. Each source has a SID; sub-affiliates use SSID for granular reporting. Configure rev-share, default postback URLs, and sub-supplier SSIDs under Suppliers.'],
            ['Suppliers', 'supplier-portal', 'Supplier Portal', 'Let affiliates view their leads.', 'tenant', 'Suppliers sign in on your tenant domain at /portal/supplier to view submissions, payouts, and performance.'],
            ['Responders & Bulk', 'auto-responders', 'Auto Responders', 'Automated SMS and email on triggers.', 'tenant', 'Create auto-responders for on_lead_received and on_lead_sold. Use merge tags like {{firstname}} and {{email}}.'],
            ['Import & Export', 'csv-import', 'CSV Import', 'Batch import offline leads.', 'tenant', 'Upload CSV mapped to campaign fields. Each row runs through validation, dedupe, and distribution.'],
            ['Reports & Verify', 'reports', 'Reports & Analytics', 'Profitability by buyer and supplier.', 'tenant', 'Reports show leads, sold count, revenue, top buyers/suppliers, and delivery performance.'],
            ['Reports & Verify', 'event-alerts', 'Event Alerts', 'Never miss cap warnings.', 'tenant', 'Configure alerts for reject rate, delivery success, queue depth. Notifications via email when thresholds breach.'],
            ['Admin', 'api-keys', 'API Keys & Security', 'REST API access and permissions.', 'tenant', 'API keys support scoped permissions and optional IP allowlist. Enable 2FA on employee accounts.'],
            ['Admin', 'webhooks', 'Webhooks', 'Push lead events externally.', 'tenant', 'Outbound webhooks for lead.sold, lead.accepted, etc. Payload includes UUID, campaign, and field data.'],
            ['Admin', 'billing-prepay', 'Buyer Billing & Prepay', 'Credits, locks, and insufficient balance.', 'tenant', 'Top up buyer credits from Billing. When require_buyer_prepay is enabled, buyers without credit are skipped at ping/post.'],
            ['Admin', 'ticketing', 'Support Tickets', 'Get help from our team.', 'tenant', 'Create support tickets from any portal. Track status and conversation history.'],

            // Super-admin / platform guides
            ['Platform Admin', 'command-center', 'Command Center', 'Cross-tenant operations dashboard.', 'admin', 'Command Center shows health, queue depth, pings/posts, and per-tenant stats. Use god mode to open any tenant portal without losing your super admin session. Run php artisan platform:check for infrastructure validation.'],
            ['Platform Admin', 'partner-platforms', 'Partner Platforms (Tenants)', 'Create and manage tenant subdomains.', 'admin', 'Each tenant gets a slug subdomain (e.g. excellence-uk.powerbyexcellence.test). Super admin manages platforms centrally; tenant users sign in only on their subdomain. Link subdomains in Herd: herd link {slug}.powerbyexcellence.'],
            ['Platform Admin', 'god-mode', 'God Mode', 'Browse any tenant as super admin.', 'admin', 'Use Open portal on Partner Platforms to enter god mode — your super admin session is preserved across subdomains. Switch sets tenant context on the central host without impersonation.'],
            ['Platform Admin', 'live-feed', 'Live Feed', 'Real-time cross-tenant activity.', 'admin', 'Live Feed aggregates lead events, deliveries, and platform activity across all tenants. Available under Logs → Live Feed.'],
            ['Platform Admin', 'tenant-billing', 'Platform Billing Overview', 'View billing per tenant context.', 'admin', 'On the central host, switch to a tenant or open god mode before viewing Billing — buyer credits are scoped per partner platform.'],
            ['Platform Admin', 'impersonation', 'Impersonation', 'Login as tenant admin for support.', 'admin', 'Login as admin impersonates the tenant account admin on their subdomain. End impersonation from the banner to return to your super admin session.'],
        ];

        foreach ($articles as [$category, $slug, $title, $summary, $audience, $text]) {
            HelpArticle::updateOrCreate(
                ['slug' => $slug],
                [
                    'category' => $category,
                    'title' => $title,
                    'summary' => $summary,
                    'audience' => $audience,
                    'body' => $this->body($text),
                    'sort_order' => 0,
                    'is_published' => true,
                ]
            );
        }
    }

    protected function body(string $text): string
    {
        return "## Overview\n\n{$text}\n\n## Tips\n\n- Check Live Operations for real-time delivery logs\n- Use the Routing Simulator to dry-run ping-tree decisions\n- Contact support via the ticketing system if you need assistance";
    }
}
