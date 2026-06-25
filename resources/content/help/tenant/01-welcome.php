<?php

return [
    'category' => 'Partner Platform',
    'slug' => 'welcome',
    'title' => 'Welcome to Your Partner Platform',
    'summary' => 'What PowerByExcellence provides on your dedicated subdomain.',
    'audience' => 'tenant',
    'sort_order' => 10,
    'body' => <<<'MD'
## Overview

PowerByExcellence gives you a **white-label lead distribution platform** on your own subdomain (e.g. `excellence-uk.powerbyexcellence.test`). You operate campaigns, buyers, suppliers, routing, billing, and reporting — independent of other partners on the network.

Unlike a shared SaaS login, each partner platform is a **fully isolated tenant**. Your buyers, suppliers, leads, financials, and branding live inside your account boundary. Super-admin network tools run only on the central marketing domain — your day-to-day work happens entirely on your tenant URL.

### What you can do on day one

| Capability | Description |
|------------|-------------|
| **Ingest leads** | REST API, hosted forms, CSV import |
| **Route & sell** | Ping trees, direct post, auctions, store lead |
| **Manage partners** | Buyers with credit; suppliers with SIDs and postbacks |
| **Operate** | Live queue, quarantine, delivery logs, reprocessing |
| **Report** | Revenue, margin, buyer/supplier performance |
| **Self-service** | Buyer and supplier portals on your domain |

## Core modules

| Module | Purpose | Admin path |
|--------|---------|------------|
| **Campaigns** | Lead schema, validation, caps, verticals | `/campaigns` |
| **Buyers** | Purchasers with credit, deliveries, portal access | `/buyers` |
| **Suppliers** | Affiliates with API keys, SIDs, rev-share | `/suppliers` |
| **Deliveries** | Buyer connection methods per campaign | `/deliveries` |
| **Routing / Ping Tree** | Tiered distribution, ping-post, auctions | `/distribution` |
| **Operations** | Live queue, quarantine, delivery logs | `/operations`, `/leads` |
| **Reports & Finance** | Revenue, margin, buyer/supplier performance | `/reports`, `/finance` |
| **Billing** | Buyer credits, top-ups, transaction ledger | `/billing` |
| **Portals** | Self-service for buyers and suppliers | `/portal/buyer`, `/portal/supplier` |
| **Integrations** | API keys, validation services, Stripe | `/api-keys`, `/integrations` |

### How modules connect

A typical lead journey:

1. **Supplier** posts via API using a **SID** (source ID) and **campaign reference**
2. **Campaign** validates fields, runs dedupe, checks caps
3. **Distribution config** (ping tree) walks tiers and pings **deliveries**
4. Winning buyer receives the lead; **financials** record revenue, payout, and margin
5. **Postbacks** notify the supplier; portals update for both parties

## First login — step by step

### 1. Open your tenant URL

1. Navigate to `https://{your-slug}.{base-domain}/login` — **not** the central marketing site
2. Confirm the login page shows your **brand name** and logo (once branding is configured)
3. Bookmark this URL for your team — admin sessions do not work cross-tenant

### 2. Sign in as Platform Administrator

1. Use the **Platform Administrator** credentials provided at onboarding
2. After login you land on **Dashboard** (`/dashboard`) with today's lead stats, revenue, and quick links
3. If you see a billing lock screen, resolve account billing status before other modules unlock

### 3. Complete platform settings

1. Go to **Settings** (sidebar → **Platform** → **Settings**, or `/settings`)
2. Review and set:
   - **Default currency** (GBP, USD, CAD, EUR)
   - **Timezone** and **country**
   - **require_buyer_prepay** if buyers must maintain credit before pings
   - **billing_alert_emails** for low-balance notifications
3. Click **Save**

### 4. Configure branding

1. Open **Branding** (`/settings/branding` or via Settings)
2. Upload **logo** and **favicon**
3. Set **brand_name** — shown on login, portals, and hosted forms
4. Save and refresh login page to verify

### 5. Review seeded data

Most tenants ship with demo campaigns, buyers, and suppliers:

1. **Campaigns** → `/campaigns` — confirm verticals and references (e.g. `auto-insurance-uk`)
2. **Buyers** → `/buyers` — check credit balance and linked deliveries
3. **Suppliers** → `/suppliers` — verify sources (SIDs) and API keys at `/api-keys`
4. Open one campaign's **show page** and confirm fields, deliveries, and active distribution config

## Navigation guide

| Sidebar section | Key pages |
|-----------------|-----------|
| **Dashboard** | Overview stats, quick actions |
| **Campaigns** | List, create, API spec, validation |
| **Buyers / Suppliers** | Partner CRUD, portal users |
| **Operations** | Leads pipeline, quarantine, live stats |
| **Logs** | Delivery logs, API request logs, change log |
| **Reports** | Revenue, performance breakdowns |
| **Billing / Finance** | Credits, transactions, reconciliation |
| **Tools** | API keys, routing simulator, integrations |
| **Help** | This Help Centre |

The **Tenant Hub** panel on campaign and buyer pages provides contextual shortcuts (ping tree, API spec, add delivery, lead pipeline).

## Architecture

### Tenant isolation

- All records are scoped by `account_id`
- UK admins cannot authenticate on the Canada subdomain — sessions and data are separate
- API keys are tenant-bound; a UK key cannot post to a US campaign

### Async processing

- Default ingest is **async**: API returns `202` with `queue_id`; workers process via Redis/Horizon
- Use `sync: true` only for staging and low-volume testing — production should queue
- Run `php artisan queue:work` or **Horizon** in production; without workers, leads stay `pending`

### Multi-currency

- Set `default_currency` per account in Platform settings
- Campaigns inherit or override currency for reporting
- Buyer `currency` column matters when you have multi-currency buyers

### Local development (Herd)

```bash
php artisan platform:link-tenants
```

Links tenant subdomains locally. Ensure `SESSION_DOMAIN=.powerbyexcellence.test` if testing god-mode handoff across subdomains.

## Onboarding checklist

Use this before going live:

1. Branding and platform settings saved
2. At least one **active** campaign with required fields defined
3. Buyer created, credit topped up (if prepay enabled)
4. Delivery configured and attached to distribution tier
5. Supplier with valid **SID** and API key (`leads.create` permission)
6. Test lead ingested with `sync: true` in staging
7. Delivery log shows `ping_ok` → `success` (or expected `skipped` waterfall)
8. Queue workers running in production
9. Postback URL tested (if affiliates need conversion tracking)
10. Portal users issued for buyers/suppliers who need self-service

## Troubleshooting

| Symptom | Likely cause | Fix |
|---------|--------------|-----|
| Login works on central domain but not tenant | Wrong hostname | Use `{slug}.{base-domain}/login` |
| Leads stuck `pending` | Queue not running | Start Horizon / `queue:work` |
| 403 on admin pages | Billing lock or staff module restriction | Check `/billing/lock`; verify user role and `allowed_modules` |
| Empty campaign list | Wrong tenant login | Confirm you are on correct subdomain |
| Branding not showing | Cache or missing save | Re-save branding; hard-refresh login |
| API 401 | Missing or revoked key | Regenerate at `/api-keys` |

## Tips

- Run `php artisan queue:work` (or Horizon) in production — async ingest depends on it
- Link local subdomains: `php artisan platform:link-tenants` on Herd
- Use **Routing Simulator** (`/routing/simulator`) before enabling new ping trees
- Use **Help Centre** for buyers/suppliers; internal super-admin guides live in `docs/admin/` only
- Check **API request logs** (`/logs/api`) when debugging supplier integration issues
MD,
];
