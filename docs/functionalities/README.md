# PowerByExcellence - Manual QA Sense-Check Guide

> **Purpose:** Master checklist for tomorrow's hands-on QA session. Each linked document walks through one functional area with step-by-step tests, expected results, and edge cases.

---

## Before You Start

Run a clean demo environment:

```bash
composer install
cp .env.example .env   # if first time
php artisan key:generate
php artisan migrate:fresh --seed
npm install && npm run build
```

**Terminal 2 (required for async lead processing):**

```bash
php artisan queue:work
```

Without the queue worker, API leads submitted without `"sync": true` will remain in `pending` status.

**Base URL:** `https://powerbyexcellence.test` (Laravel Herd) or `http://127.0.0.1:8000` if using `php artisan serve`.

---

## Demo Login Credentials

| Role | Email | Password | Notes |
|------|-------|----------|-------|
| **Account Admin (UK)** | `uk@powerbyexcellence.test` | `password` | Primary QA account - Excellence Leads UK |
| Account Admin (US) | `us@powerbyexcellence.test` | `password` | Partner Solar US tenant |
| Super Admin | `admin@powerbyexcellence.test` | `password` | Switch platform at `/accounts` before tenant CRUD |
| Buyer Portal (UK) | `buyer-portal@excellence-uk.test` | `password` | Redirects to `/portal/buyer` |
| Supplier Portal (UK) | `supplier-portal@excellence-uk.test` | `password` | Redirects to `/portal/supplier` |

API keys are printed once in the terminal when you run `php artisan migrate:fresh --seed`. Copy the **Admin API** and **Supplier API** tokens immediately.

**Seeded references (UK):** campaigns `auto-insurance-uk`, `loans-uk`, `mortgage-uk`, `payday-loans-uk`, `solar-uk` · buyers `buyer-primary`, `buyer-secondary` · supplier SID `google_search`

---

## QA Checklist

| # | Area | Doc | Route(s) | Status |
|---|------|-----|----------|--------|
| 1 | Dashboard & overview stats | [01-dashboard.md](./01-dashboard.md) | `/dashboard` | ☐ |
| 2 | Campaigns & verticals | [02-campaigns-and-verticals.md](./02-campaigns-and-verticals.md) | `/campaigns` | ☐ |
| 3 | Deliveries & 10-tier ping tree | [03-deliveries-and-10-tier-ping-tree.md](./03-deliveries-and-10-tier-ping-tree.md) | `/deliveries` | ☐ |
| 4 | Distribution ping tree config | [04-distribution-ping-tree.md](./04-distribution-ping-tree.md) | `/distribution` | ☐ |
| 5 | Reports & analytics | [05-reports.md](./05-reports.md) | `/reports` | ☐ |
| 6 | Hosted form builder | [06-form-builder.md](./06-form-builder.md) | `/forms`, `/forms/{slug}` | ☐ |
| 7 | Buyers & billing | [07-buyers-and-billing.md](./07-buyers-and-billing.md) | `/buyers`, `/billing` | ☐ |
| 8 | Suppliers & portals | [08-suppliers-and-portals.md](./08-suppliers-and-portals.md) | `/suppliers`, `/portal/*` | ☐ |
| 9 | REST API & SDK | [09-api-and-sdk.md](./09-api-and-sdk.md) | `/api/v1/*`, `/sdk/pbe-leads.js` | ☐ |
| 10 | Postbacks & webhooks | [10-postbacks-webhooks.md](./10-postbacks-webhooks.md) | `/postbacks`, `/webhooks` | ☐ |
| 11 | Automation & alerts | [11-automation.md](./11-automation.md) | `/automation` | ☐ |
| 12 | Operations & audit logs | [12-operations-and-logs.md](./12-operations-and-logs.md) | `/operations`, `/logs/*` | ☐ |
| 13 | Marketing site | [13-marketing-site-pricing-blog.md](./13-marketing-site-pricing-blog.md) | `/`, `/pricing`, `/blog` | ☐ |

---

## Recommended Test Order

1. **Setup** - migrate, seed, start queue worker, log in as UK admin
2. **Dashboard & Reports** - confirm seeded historical data is visible
3. **Campaigns → Deliveries → Distribution** - trace the lead routing stack
4. **API ingest** - submit a sync lead, verify in Operations and Lead Pipeline
5. **Form builder** - submit hosted form, confirm lead appears
6. **Portals** - log in as buyer and supplier, verify isolation
7. **Integrations** - webhooks, postbacks, automation
8. **Marketing** - public pages (no login required)

---

## Quick Smoke Test (15 minutes)

If time is limited, run this minimum path:

1. Log in → `/dashboard` - stats and charts load
2. `/campaigns` → open **Auto Insurance** - fields, deliveries, ping tree linked
3. `/reports` - 28-day charts and 10-tier table populated
4. `POST /api/v1/leads` with `sync: true` - lead sells, appears in `/operations`
5. `/forms/auto-insurance-quote-uk` - multi-step form submits
6. Log out → log in as `buyer-portal@excellence-uk.test` - sold leads visible
7. Visit `/` and `/pricing` - marketing pages render

---

## Known Demo Limitations

- Billing uses credit ledger + admin top-up; Stripe integration is config UI - full Checkout in buyer portal is pending
- SMS delivery and bulk SMS log to platform log unless provider configured
- Async leads require `php artisan queue:work`
- User admin supports create/delete; no edit form for existing users
- Registration is disabled; admins create users at `/users`
- Buyers and Billing are under top nav **More** (see [UX_NAVIGATION_AUDIT.md](../UX_NAVIGATION_AUDIT.md) for planned IA changes)

---

## Support & Help

- In-app help centre: `/help`
- Support tickets: `/support` (all roles), `/support/manage` (admin)
- Implementation inventory: [`../IMPLEMENTATION_STATUS.md`](../IMPLEMENTATION_STATUS.md)
- UX / navigation audit: [`../UX_NAVIGATION_AUDIT.md`](../UX_NAVIGATION_AUDIT.md)
