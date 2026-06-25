# PowerByExcellence — Lead Distribution Platform

Laravel 13 + Vue 3 (Inertia) lead generation and distribution platform for real-time ping-tree routing and buyer management.

## Features

- **Multi-tenant partner platforms** — Each `Account` is an isolated partner with own campaigns, buyers, suppliers, leads
- **Lead ingest API** — REST JSON with Admin / Supplier API keys
- **Validation & deduplication** — Field rules, email/phone dedupe, suppression hashes
- **Distribution engine** — Waterfall, ping-post, parallel auction, weighted, round-robin, hybrid groups (ping tree)
- **Deliveries** — Direct post, ping-post, store lead with full audit logs
- **Caps** — Campaign, buyer, delivery caps (hourly/daily/weekly/monthly/total)
- **Admin UI** — Vue dashboard for campaigns, deliveries, buyers, suppliers, leads
- **Error logging** — `PlatformLogger` → DB (`system_error_logs`) + `storage/logs/platform.log`
- **Webhooks** — Outbound events on lead sold/unsold

## Quick Start

```bash
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate:fresh --seed
npm install && npm run build
php artisan serve
# In another terminal:
php artisan queue:work
```

Visit `http://powerbyexcellence.test` (Herd) or `http://localhost:8000`

### Demo Logins

| Role | Email | Password |
|------|-------|----------|
| Super Admin | admin@powerbyexcellence.test | password |
| UK Platform Admin | uk@powerbyexcellence.test | password |
| US Platform Admin | us@powerbyexcellence.test | password |

API keys are printed when you run `php artisan db:seed`.

## API Usage

```bash
# Ingest lead (async)
curl -X POST http://localhost:8000/api/v1/leads \
  -H "Authorization: Bearer YOUR_API_KEY" \
  -H "Content-Type: application/json" \
  -d '{
    "campaign_reference": "auto-insurance-uk",
    "firstname": "Jane",
    "lastname": "Doe",
    "email": "jane@example.com",
    "phone1": "07700900123",
    "zipcode": "SW1A 1AA"
  }'

# Live buyer response (sync)
curl -X POST ... -d '{ ..., "sync": true }'

# Poll queue
curl http://localhost:8000/api/v1/leads/queue/{queue_id} \
  -H "Authorization: Bearer YOUR_API_KEY"
```

## Architecture

See [docs/IMPLEMENTATION_STATUS.md](docs/IMPLEMENTATION_STATUS.md) for full demo accounts, routes, and API examples.

```
app/
├── Enums/           # LeadStatus, DeliveryMethod, RoutingMode
├── Http/
│   ├── Controllers/Admin/   # Inertia Vue admin
│   └── Controllers/Api/     # REST API
├── Jobs/ProcessLeadJob.php
├── Models/          # Account, Campaign, Lead, Delivery, Buyer, Supplier...
├── Services/
│   ├── Caps/
│   ├── Delivery/    # DeliveryExecutor, TagInterpolator
│   ├── Distribution/# DistributionEngine, WebhookDispatcher
│   ├── Leads/       # LeadPipeline, LeadIngestService, DedupeService
│   └── Logging/     # PlatformLogger
└── Support/Tenancy/ # AccountContext
```

## Tests

```bash
php artisan test
```

## Implementation Phases

| Phase | Status |
|-------|--------|
| 1 — Foundation (ingest, validation, dedupe, pipeline) | ✅ |
| 2 — Distribution (ping-post, ping tree, caps) | ✅ |
| 3 — Advanced routing (hybrid, weighted, round-robin) | ✅ |
| 4 — Buyers/suppliers, financials, multi-tenant admin | ✅ |
| 5 — Buyer/supplier portals, Stripe, CSV import | ✅ |
| 6 — Facebook/Google integrations, form builder | 🔲 Planned |
