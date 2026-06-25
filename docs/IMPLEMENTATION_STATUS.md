# PowerByExcellence — Implementation Status

> **Last updated:** 25 June 2026  
> **Stack:** Laravel 13 · Vue 3 · Inertia · SQLite/MySQL · Laravel Queues  
> **Tests:** 235 passing (`php artisan test`)

This document is the **live inventory** of what is built today. For the full LeadByte specification, see [`LEADBYTE_REPLICA_DEV_DOC.md`](./LEADBYTE_REPLICA_DEV_DOC.md). For navigation UX recommendations, see [`UX_NAVIGATION_AUDIT.md`](./UX_NAVIGATION_AUDIT.md).

---

## Quick Start

```bash
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate:fresh --seed
npm install && npm run build

# Terminal 1 — web
php artisan serve   # or use Herd: powerbyexcellence.test

# Terminal 2 — REQUIRED for async lead processing
php artisan queue:work
```

> **Important:** Without `php artisan queue:work`, async API leads (`POST /api/v1/leads` without `"sync": true`) will sit in the `jobs` table and never process.

---

## Demo Accounts

All passwords: **`password`**

| Email | Role | Platform |
|-------|------|----------|
| `admin@powerbyexcellence.test` | Super Admin | All (switch at `/accounts`) |
| `uk@powerbyexcellence.test` | Account Admin | Excellence Leads UK |
| `us@powerbyexcellence.test` | Account Admin | Partner Solar US |
| `buyer-portal@excellence-uk.test` | Buyer Portal | UK |
| `supplier-portal@excellence-uk.test` | Supplier Portal | UK |
| `buyer-portal@partner-solar-us.test` | Buyer Portal | US |
| `supplier-portal@partner-solar-us.test` | Supplier Portal | US |

**Seeded references**

| Platform | Campaign | Buyers | Supplier SID |
|----------|----------|--------|--------------|
| UK | `auto-insurance-uk`, `loans-uk`, `mortgage-uk`, `payday-loans-uk`, `solar-uk` | `buyer-primary`, `buyer-secondary` | `google_search` |
| US | `auto-insurance-us`, `loans-us`, `mortgage-us`, `payday-loans-us`, `solar-us` | `buyer-primary`, `buyer-secondary` | `google_search` |

API keys are printed once when you run `php artisan db:seed` — copy immediately.

---

## How Laravel Job Queues Work Here

### Yes — the platform uses Laravel queues

| Component | File | Role |
|-----------|------|------|
| Job class | `app/Jobs/ProcessLeadJob.php` | Implements `ShouldQueue`; runs `LeadPipeline` |
| Default driver | `config/queue.php` | `database` (jobs stored in `jobs` table) |
| Dispatch points | `LeadController`, `CsvImportService`, `QuarantineController` | Queue lead after ingest |

### Async vs sync ingest

```
POST /api/v1/leads
        │
        ├── "sync": true  → LeadPipeline::process() runs immediately in the HTTP request
        │                   Returns final status (sold/unsold/rejected) in response
        │
        └── default (async) → ProcessLeadJob::dispatch($leadId)
                              Returns 202 { status: "queued", queue_id, lead_id }
                              Worker picks up job → sets AccountContext → runs pipeline
```

**Poll async status:** `GET /api/v1/leads/queue/{queueId}`

### Job behaviour

- **Retries:** 3 attempts, 5s backoff
- **Tenancy:** Job loads lead `withoutGlobalScopes()`, sets `AccountContext` from lead's campaign account before processing
- **Failure:** Lead marked `rejected` with reason `Queue processing failed`

### Other queue usage

- CSV import dispatches one `ProcessLeadJob` per imported row
- Quarantine release re-dispatches processing job
- Reprocess API: `POST /api/v1/leads/{uuid}/reprocess`

---

## How Ping-Post & Ping-Tree Work

### Ping-post (single delivery)

A **delivery** with `method: ping_post` runs a two-step HTTP flow in `DeliveryExecutor`:

```
1. PING  — partial fields only (campaign fields marked ping_field=true)
           POST → ping_url (timeout: config.ping_timeout, default 5s)
           Logged to delivery_logs.ping_request / ping_response

2. Evaluate ping response
           - matchesPingSuccess() checks Success + floor price (Cost >= campaign floor)
           - If rejected → delivery skipped, try next in routing

3. POST  — full lead fields + ping response interpolation
           POST → post_url (timeout: config.timeout, default 10s)
           Logged to delivery_logs.post_request / post_response

4. Revenue from RevenueCalculator (fixed / dynamic Cost field / rule-based)
```

**Built-in simulators for testing** (no auth required):

- `POST /api/v1/ping` → `{ Success: true, Cost: 15, PingID: "ping_..." }`
- `POST /api/v1/post` → `{ Success: true, Approved: true }`

Seeded UK delivery **"Ping Post — Primary Buyer"** points at these URLs.

### Ping-tree (advanced distribution)

When a campaign has `use_advanced_distribution: true`, `DistributionEngine` reads the active `DistributionConfig` and processes **tiered groups** in order:

```
Campaign (advanced mode)
    └── DistributionConfig (e.g. "Hybrid Ping Tree")
            └── groups[] — each tier has:
                    name, mode, floor_price?, delivery_ids[]
                    mode → RoutingMode enum:
                        waterfall        — priority order until sold
                        parallel_auction — ping all deliveries, highest bid ≥ floor wins
                        sequential_ping  — same as waterfall for ping deliveries
                        weighted         — random pick by delivery.weight
                        round_robin      — rotate fairly between deliveries
```

**Flow per lead:**

```
LeadPipeline (validated, deduped)
    → DistributionEngine::distribute()
        → distributeAdvanced() OR distributeStandard()
            → For each tier group:
                → Filter deliveries by delivery_ids, caps, credit, rules
                → Run mode-specific logic
                → If sold & exclusive → finalizeSold (financials, buyer charge, webhooks)
            → If no tier sells → finalizeUnsold (or quarantine)
```

**Admin UI:**

| Page | URL | Purpose |
|------|-----|---------|
| Ping Tree list | `/distribution` | View/edit tier configs per campaign |
| Ping Tree form | `/distribution/create`, `/distribution/{id}/edit` | Visual tier builder |
| Live Operations | `/operations` | KPIs, recent leads, delivery preview (links to full logs) |
| Delivery Logs | `/logs/delivery` | Filterable ping-post/direct audit trail with drill-down |
| Campaign show | `/campaigns/{id}` | Shows linked ping-tree configs |
| Deliveries | `/deliveries` | Per-buyer delivery methods (ping_post, direct_post, etc.) |

**Seeded example (UK):**

- Tier 1: `parallel_auction`, floor £10
- Tier 2: `waterfall` fallback
- Both tiers reference the Store Lead delivery (demo simplification)

---

## How Multi-Tenancy Works

### Concept

Each **Account** is an isolated partner platform (tenant). All business data belongs to one account.

### AccountContext (request-scoped)

`app/Support/Tenancy/AccountContext.php` holds the current tenant ID for the duration of a request or job.

**Set by middleware:**

| Middleware | When | How |
|------------|------|-----|
| `SetAccountFromUser` | Web (auth) | From `user.account_id`, or buyer/supplier's account, or super-admin session `current_account_id` |
| `AuthenticateApiKey` | API | From API key's `account_id` |

### BelongsToAccount trait

Models using `BelongsToAccount` (Campaign, Lead, Buyer, Supplier, ApiKey, Webhook, etc.):

1. **Global scope** — all queries auto-filter `WHERE account_id = AccountContext::id()`
2. **Auto-fill** — new records get `account_id` from context on create

### Isolation guarantees

- Admin user on UK platform only sees UK campaigns, leads, buyers
- API key scoped to one account — cannot ingest to another tenant's campaigns
- `ProcessLeadJob` explicitly sets context from lead's campaign account
- Super admin switches tenant at `/accounts` → session stores `current_account_id`

### Portal users

- Buyer/supplier portal users link via `buyer_id` / `supplier_id`
- `User::buyer()` / `supplier()` load **without global scopes** (fixes cross-scope lookup)
- `SetAccountFromUser` resolves account from buyer/supplier when `account_id` absent

### Test coverage

- `MultiTenancyTest` — scoped campaign queries
- `LeadIngestApiTest::test_multi_tenant_isolation` — cannot post to another account's campaign
- `PlatformModulesTest` — UK and US admins isolated

---

## TOC §1–20 Implementation Map

| § | Topic | Status | Notes |
|---|-------|--------|-------|
| 1 | Platform Overview | ✅ | Marketing homepage, role-based portals |
| 2 | Domain Model | ✅ Partial | Core entities; no Tracking Pixels, Responders UI |
| 3 | System Architecture | ✅ | Laravel + Vue Inertia + queue workers |
| 4 | Multi-Vertical | ✅ Partial | `vertical_id` on campaigns; no template picker UI |
| 5 | Lead Ingestion | ✅ | REST API, CSV import, sync/async |
| 6 | Validation & Filtering | ✅ Partial | Field validation, RuleEngine; no HLR/email service integrations |
| 7 | Dedupe & Suppression | ✅ | Email/phone dedupe, suppression hash |
| 8 | Distribution / Ping Tree | ✅ | Engine + admin UI at `/distribution` |
| 9 | Deliveries | ✅ | Guided 8-step form; all 5 methods (+ email ping-post) |
| 10 | Caps, Financials, Billing | ✅ Partial | Volume + **campaign revenue budget** caps; billing UI; Stripe config UI |
| 11 | Quarantine & Retry | ✅ | API + admin queue at `/quarantine` (release, reject, bulk) |
| 12 | Portals | ✅ | Buyer + supplier with billing sections |
| 13 | Reporting & Webhooks | ✅ | Admin `/reports` UI + API reports + webhook CRUD |
| 14 | REST API | ✅ | Full v1 surface documented below |
| 15 | Database Schema | ✅ | Migrations in `database/migrations/` |
| 16 | Event Flow / State Machine | ✅ | LeadStatus enum, lead_events audit |
| 17 | Security & Compliance | ✅ Partial | API keys, RBAC; no 2FA, domain lock |
| 18 | Implementation Phases | ✅ | Phases 1–5 complete, 6 pending |
| 19 | Tech Stack | ✅ | Laravel 13, Vue 3, Inertia, Tailwind |
| 20 | Vertical Field Templates | 🔲 | Fields seeded per campaign; no template library UI |

---

## Admin CRUD Status

| Resource | List | Create | Read/Show | Update | Delete | Tested |
|----------|------|--------|-----------|--------|--------|--------|
| Campaigns | ✅ | ✅ | ✅ show page | ✅ | ✅ | `AdminCrudTest` |
| Deliveries | ✅ | ✅ | — | ✅ | ✅ | `AdminCrudTest` |
| Distribution (Ping Tree) | ✅ | ✅ | — | ✅ | ✅ | `DistributionCrudTest` |
| Buyers | ✅ | ✅ | — | ✅ | ✅ | `AdminCrudTest` |
| Suppliers | ✅ | ✅ | — | ✅ | ✅ | `AdminCrudTest` |
| Leads | ✅ | — (API only) | ✅ detail | — | — | `AdminCrudTest` filters |
| Webhooks | ✅ | ✅ | — | — | ✅ | `AdminCrudTest` |
| API Keys | ✅ | ✅ | — | — | ✅ | `AdminCrudTest` |
| Users | ✅ | ✅ | — | — | ✅ | `AdminCrudTest` |
| Imports | ✅ | ✅ upload | — | — | — | Route health |
| Billing | ✅ | — | ✅ per buyer | top-up ✅ | — | `DistributionCrudTest` |
| Finance | ✅ | — | — | — | — | Route health |
| Reports | ✅ | — | — | — | — | Route health |
| Quarantine | ✅ | — | — | release/reject ✅ | — | Feature tests |
| Settings | ✅ | — | — | ✅ | — | `CampaignValidationTest` |
| Branding | ✅ | — | — | ✅ upload | — | `AdminCrudTest` |
| Accounts (super) | ✅ switch | — | — | — | — | Route health |
| Command Center (super) | ✅ | — | — | — | — | Central host |
| Profile | ✅ | — | — | ✅ name/email/avatar | — | `ProfileTest` |
| Profile preferences | — | — | — | ✅ theme/accent | — | `ProfilePreferencesTest` |

**Not implemented as CRUD:**

- Lead create/edit in admin (by design — ingest via API/import)
- User edit form (create + delete only)
- Webhook edit (create + delete only)

---

## Delivery Methods & Pricing (§9)

| Method | Backend | Admin Form | Seeded Demo |
|--------|---------|------------|-------------|
| `store_lead` | ✅ | ✅ guided | ✅ active |
| `direct_post` | ✅ HTTP | ✅ URL, timeout | ✅ secondary buyer |
| `ping_post` | ✅ two-phase | ✅ ping/post URLs, timeouts | ✅ → `/api/ping` |
| `email` | ✅ Mail::raw | ✅ to/subject/body templates | ✅ inactive |
| `sms` | ✅ log only | ✅ to/message | — |

| Pricing model | Service | UI | Tested |
|---------------|---------|-----|--------|
| `fixed` | `RevenueCalculator` | ✅ | ✅ |
| `dynamic` | From buyer `Cost` field | ✅ | ✅ |
| `rule_based` | Field match rules | ✅ | ✅ |

| Routing mode | Engine | Ping-tree UI | Delivery form |
|--------------|--------|--------------|---------------|
| waterfall | ✅ | ✅ tier mode | ✅ |
| parallel_auction | ✅ | ✅ tier mode | — |
| sequential_ping | ✅ | ✅ tier mode | — |
| weighted | ✅ | ✅ tier mode | ✅ weight field |
| round_robin | ✅ | ✅ tier mode | ✅ routing_mode |
| hybrid | ✅ rule groups | ✅ | — |

---

## URL Map (Complete)

### Public

| URL | Description |
|-----|-------------|
| `/` | Marketing homepage (features, ping-tree, billing sections) |
| `/login` | Auth (all roles) |
| `POST /demo-request` | Book a demo form |

### Admin (`auth` + `admin` role)

| URL | Description |
|-----|-------------|
| `/dashboard` | Today stats strip, charts, tenant table (super admin), quick links |
| `/command-center` | Super admin: cross-tenant health, ops checks, tenant table (central host) |
| `/operations` | Live KPI strip, queue, hourly chart, top campaigns |
| `/campaigns` | Campaign CRUD + show + API spec |
| `/distribution` | Ping-tree config CRUD |
| `/deliveries` | Delivery CRUD + test button |
| `/buyers` | Buyer CRUD + billing link |
| `/suppliers` | Supplier + SID CRUD |
| `/leads` | Lead pipeline (filters, live processing) + detail tabs |
| `/quarantine` | Held leads queue — release, reject, bulk actions |
| `/billing` | Credit pool + per-buyer ledger |
| `/billing/{buyer}` | Top-up + transaction history |
| `/finance` | Revenue, payout, margin roll-up |
| `/reports` | Analytics KPI strips, charts, breakdown tables |
| `/logs/delivery` | Delivery attempt audit (ping/post) |
| `/logs/api` | Ingest API request log |
| `/logs/access` | Admin sign-in log |
| `/logs/changes` | Config change audit |
| `/logs/security` | Security events |
| `/imports` | CSV bulk import |
| `/integrations` | Third-party connectors |
| `/automation` | Sequences, bulk SMS, event alerts |
| `/routing/simulator` | Tier routing dry-run |
| `/webhooks` | Outbound webhook config |
| `/postbacks` | Affiliate conversion pixels |
| `/api-keys` | API key generate/revoke |
| `/forms` | Hosted form builder |
| `/users` | Portal user create/delete |
| `/settings` | Platform name, timezone, country, currency, prepay toggle |
| `/branding` | Logo upload, brand name |
| `/accounts` | Super admin: list + switch platform |
| `/profile` | Account info, appearance, password, 2FA |
| `/help`, `/support` | Help centre + tickets |

### Buyer Portal

| URL | Description |
|-----|-------------|
| `/portal/buyer` | Dashboard + charts |
| `/portal/buyer/leads` | Sold leads + feedback/returns |
| `/portal/buyer/billing` | Credit balance + transactions |
| `/portal/buyer/leads/download` | CSV export |

### Supplier Portal

| URL | Description |
|-----|-------------|
| `/portal/supplier` | Dashboard + SID stats |
| `/portal/supplier/leads` | Submitted leads |
| `/portal/supplier/billing` | Payout summary + recent revenue |

### REST API (`/api/v1/`)

| Method | Endpoint | Permission |
|--------|----------|------------|
| POST | `/leads` | `leads.create` |
| POST | `/leads/import` | `leads.create` |
| GET | `/leads/{uuid}` | `leads.read` |
| GET | `/leads/queue/{queueId}` | `leads.read` |
| POST | `/leads/search` | `leads.read` |
| POST | `/leads/{uuid}/reprocess` | `leads.read` |
| GET | `/reports/leads` | `reports.read` |
| GET | `/reports/revenue` | `reports.read` |
| GET | `/quarantine` | `quarantine.manage` |
| POST | `/quarantine/{uuid}/release` | `quarantine.manage` |
| POST | `/quarantine/{uuid}/reject` | `quarantine.manage` |
| POST | `/buyers/{id}/feedback` | `buyers.manage` |
| POST | `/buyers/{id}/credit` | `buyers.manage` |
| POST | `/ping` | Public simulator |
| POST | `/post` | Public simulator |

---

## Navigation Structure (Top Bar)

Admin uses a **compact horizontal top nav** (`AdminTopNav.vue`), not a sidebar.

### Admin — primary nav

| Item | Contents |
|------|----------|
| **Home** | `/dashboard` |
| **Campaigns** ▾ | All campaigns, Form builder |
| **Ops** ▾ | Live operations, Lead pipeline, Quarantine · Deliveries, Ping tree, Routing simulator, Automation |
| **Reports** | `/reports` |
| **More** ▾ | `NavHubMenu` — tenant shortcuts (buyers, suppliers, finance, logs, integrations, settings, help, …) |
| *(super, no tenant)* | Command Center, Partner platforms |

**Also visible:** Live stats bar (leads/sold/queue/quarantine/revenue), tenant switcher (super admin), notifications, theme toggle.

**Campaign context:** `CampaignWorkflowNav` on campaign-scoped pages (show, edit, API spec, leads, deliveries, distribution, operations).

**Buyer/supplier entity pages:** `ManagementHubNav` (overview, edit, billing).

### Buyer / supplier portals

| Item | Route |
|------|-------|
| Dashboard | `/portal/buyer` or `/portal/supplier` |
| My Leads | `…/leads` |
| Billing / Payouts | `…/billing` |

### UX audit (recommendations)

See **[`UX_NAVIGATION_AUDIT.md`](./UX_NAVIGATION_AUDIT.md)** for friction analysis and proposed IA (promote Buyers/Billing, go-live checklist, plain-English labels, unified logs).

---

## UI / UX Features

| Feature | Status |
|---------|--------|
| Light / dark theme | ✅ global + per-user saved |
| Accent colours (6 options) | ✅ Profile → Appearance |
| Whitelabel logo | ✅ `/branding` |
| Compact KPI strips | ✅ `CompactStatStrip` on dashboard, ops, reports, finance, entity pages |
| Slim panels & page headers | ✅ reduced padding (`Panel`, `PageHeader`, layout) |
| Tenant-scoped currency | ✅ `useMoneyFormat` — platform/buyer/campaign currency |
| Campaign revenue budget caps | ✅ daily/monthly spend cap on campaign + `BuyerEligibilityService` |
| Dashboard charts | ✅ admin, buyer, supplier |
| Form validation summaries | ✅ admin forms |
| Flash success/error messages | ✅ all layouts |
| Delivery wizard | ✅ 8-step form with method guides |
| Campaign wizard | ✅ 4-step create/edit (identity, pricing, routing, caps & budget) |
| Delete account on profile | ❌ removed by design |

---

## Test Suite (235 tests)

```bash
php artisan test
```

| Test file | Covers |
|-----------|--------|
| `LeadIngestApiTest` | Async queue, sync sell, dedupe, API auth, tenant isolation |
| `LeadProcessingTimeoutTest` | HTTP timeouts, duration logging, sync perf <3s |
| `MultiTenancyTest` | Account scoped queries |
| `PlatformModulesTest` | All major routes, seeded data integrity |
| `AdminCrudTest` | Campaign, buyer, supplier, delivery, webhook, api-key, user, branding |
| `DistributionCrudTest` | Ping-tree CRUD, billing top-up ledger |
| `BuyerEligibilityFeatureTest` | Credit, caps, **campaign spend cap** |
| `RevenueCalculatorTest` | Fixed, dynamic, rule-based pricing |
| `BuyerBillingTest` | Prepay credit charge |
| `CampaignValidationTest` | Campaign + settings validation |
| `RouteHealthTest` | Auth redirects, portal access |
| `PortalAccessTest` | Role-based portal gates |
| `ProfileTest` / `ProfilePreferencesTest` | Profile CRUD, theme/accent |
| `DemoRequestTest` | Homepage demo form |
| Auth tests | Login, password reset, verification, 2FA |
| `RuleEngineTest` | Distribution eligibility rules |

---

## Architecture Diagram

```
                         ┌─────────────────────────────────────┐
                         │  POST /api/v1/leads               │
                         │  (API key → AccountContext)         │
                         └──────────────┬──────────────────────┘
                                        │
                    ┌───────────────────┴───────────────────┐
                    │ sync:true                           │ async (default)
                    ▼                                     ▼
            LeadPipeline (inline)              ProcessLeadJob (queue)
                    │                                     │
                    └───────────────────┬───────────────────┘
                                        ▼
                              LeadPipeline::process()
                    ┌───────────────────┼───────────────────┐
                    ▼                   ▼                   ▼
              Validate            Dedupe            Suppression
                    └───────────────────┬───────────────────┘
                                        ▼
                           DistributionEngine
                    ┌───────────────────┼───────────────────┐
                    │ Standard          │ Advanced (ping-tree)
                    │ priority waterfall│ DistributionConfig tiers
                    └───────────────────┬───────────────────┘
                                        ▼
                           DeliveryExecutor per delivery
                    ┌─────────┬─────────┬─────────┬─────────┐
                    ▼         ▼         ▼         ▼         ▼
              DirectPost PingPost StoreLead  Email      SMS
                    │         │         │         │         │
                    └─────────┴─────────┴─────────┴─────────┘
                                        ▼
                    finalizeSold / finalizeUnsold
                    (LeadFinancial, BuyerBilling, Webhooks)
```

---

## Seeded Demo Data (after `migrate:fresh --seed`)

Per platform:

- 1 campaign (advanced distribution enabled)
- 2 buyers with ledger top-ups (£500 / £250)
- 1 supplier + 1 source (`google_search`)
- 4 deliveries: Store, Ping Post, Direct API, Email
- 1 DistributionConfig (Hybrid Ping Tree, 2 tiers)
- 5 sold demo leads + financials
- 1 pending lead in queue
- Admin + supplier API keys
- Portal users for buyer and supplier

---

## Phase Summary

### Phases 1–5 — Complete ✅ (tested)

Foundation, distribution engine, advanced routing, network/financials, portals, admin tools, billing UI, ping-tree UI, operations monitor, guided deliveries, user theme preferences, help centre, ticketing, automation/remarketing, form builder, security logs, table pagination, buyer prepay enforcement.

### Phase 6 — Partial ✅ / 🔲

| Feature | Status |
|---------|--------|
| Postback Manager | ✅ `/postbacks` — pixels, supplier/campaign scope, audit log |
| JavaScript SDK | ✅ `/sdk/pbe-leads.js` |
| PHP SDK | ✅ `sdk/php/PbeClient.php` |
| Hosted form builder | ✅ `/forms` |
| Bulk SMS + email campaigns | ✅ `/automation` — Twilio/SendGrid/Mailgun/Postmark/Resend providers |
| Admin Command Center | ✅ `/command-center` (super admin) — cross-tenant pings/posts, health, events |
| Delivery schedules (advanced) | ✅ Per-delivery window editor on delivery form |
| Billing pagination | ✅ `/billing` buyers + transactions paginated |
| Lead detail UX | ✅ Tabs, prev/next nav, processing time, delivery log links |
| 2FA enable/disable | ✅ Profile — recovery codes (TOTP app login challenge 🔲) |
| Event alerts | ✅ Webhook, Slack, SMS, email + fire history |
| Help centre + ticketing | ✅ `/help`, `/support` |
| Email ping-post delivery | ✅ `DeliveryMethod::EmailPingPost` |
| Stripe card payments | ✅ `/integrations/stripe` — keys, webhook URL, buyer self-serve toggle |
| Facebook / Google / TikTok sync | ✅ `/integrations/lead-sources/{provider}` — webhook + ingest endpoints |
| Custom portal domains | 🔲 |
| FTP / scheduled CSV exports | 🔲 |
| Full 2FA TOTP login challenge | 🔲 (enable/disable + recovery codes ✅) |
| 2-step auth delivery method | 🔲 |
| Campaign transfer delivery | 🔲 |
| Custom report builder UI | 🔲 |

---

## Known Limitations

1. **Queue worker required** — async leads need `php artisan queue:work`
2. **Stripe** — configuration UI + webhook stub; full Checkout flow in buyer portal is next step
3. **Lead source sync** — webhook ingest accepts payloads; field mapping to campaigns is manual/demo queue
4. **Parallel auction** — pings all buyers then picks winner; no distributed lock
5. **Super admin on tenant** — god mode via “Open portal”; Command Center / Partner Platforms are central-host only
6. **Buyer form** — includes Advanced tab (pricing, caps, geo, auto top-up thresholds)
7. **Users** — renamed from Employees; super admin hidden on partner platform user lists
8. **Demo data** — `DemoHistoricalDataSeeder` seeds 30 days of leads per tenant (`migrate:fresh --seed`)

---

## Key File Reference

| Path | Purpose |
|------|---------|
| `app/Jobs/ProcessLeadJob.php` | Queued lead processing |
| `app/Services/Leads/LeadPipeline.php` | Validate → dedupe → distribute |
| `app/Services/Distribution/DistributionEngine.php` | Ping-tree + routing modes |
| `app/Services/Delivery/DeliveryExecutor.php` | Ping-post, direct post, email, SMS |
| `app/Services/Billing/RevenueCalculator.php` | Fixed / dynamic / rule-based pricing |
| `app/Services/Billing/BuyerBillingService.php` | Credit ledger |
| `app/Models/Concerns/BelongsToAccount.php` | Tenant global scope |
| `app/Support/Tenancy/AccountContext.php` | Current tenant ID |
| `app/Http/Middleware/SetAccountFromUser.php` | Web tenancy |
| `app/Http/Middleware/AuthenticateApiKey.php` | API tenancy |
| `database/seeders/PlatformSeeder.php` | Demo data |
| `resources/js/Components/UI/AdminTopNav.vue` | Top navigation |
| `resources/js/Components/UI/NavHubMenu.vue` | More → platform shortcuts |
| `resources/js/Components/UI/CampaignWorkflowNav.vue` | In-campaign step nav |
| `resources/js/Components/UI/CompactStatStrip.vue` | Compact horizontal KPI rows |
| `app/Support/Admin/TenantHub.php` | More menu link sections |
| `resources/js/Pages/Admin/Deliveries/Form.vue` | Guided delivery wizard (8 steps) |
| `resources/js/Pages/Admin/Distribution/*` | Ping-tree UI |
| `resources/js/Pages/Admin/CommandCenter/Index.vue` | Super-admin ops dashboard |
| `app/Services/Distribution/WebhookDispatcher.php` | Outbound JSON webhooks |
| `app/Services/Postbacks/PostbackDispatcher.php` | Affiliate pixels / GET postbacks |
| `sdk/javascript/pbe-leads.js` | Browser/ESM lead ingest SDK |
| `sdk/php/PbeClient.php` | PHP API client |

---

## Suggested Next Build Order

### UX / navigation (see [`UX_NAVIGATION_AUDIT.md`](./UX_NAVIGATION_AUDIT.md))

1. Campaign **go-live checklist** on show page
2. Promote **Buyers** + **Billing** to top nav; slim **More** menu
3. Plain-English nav labels (Today, Held leads, Buyer connections)
4. Unified **logs** hub with lead UUID search
5. Delivery **quick setup** path + test CTA

### Product / integrations

1. Stripe Checkout in buyer portal (charge + ledger credit)
2. Lead source field mapping UI + automatic campaign ingest
3. FTP / scheduled CSV exports
4. Full 2FA TOTP login challenge
5. 2-step auth delivery method
6. User edit form in admin
