# 01 - Dashboard

## Purpose

The admin dashboard is the landing page after login. It provides a real-time snapshot of lead volume, conversion, revenue, and pipeline health for the current platform (tenant). Use it to confirm seeded demo data loaded correctly and to jump quickly into operational areas.

---

## Where to Find It

| Item | Location |
|------|----------|
| URL | `https://powerbyexcellence.test/dashboard` |
| Route name | `dashboard` |
| Navigation | Top bar → **Home** |
| Access | Account Admin, Super Admin (after switching platform) |
| Middleware | `auth`, `verified`, `billing.active`, admin role |

**Also on every admin page:** **Live stats bar** (leads, sold, queue, quarantine, revenue) below the header.

---

## Page layout (current)

| Section | Description |
|---------|-------------|
| Header row | Platform overview title + actions (platforms, settings, live ops, new campaign) |
| Tenant context banner | Shown when super admin has no tenant selected, or when tenant is active |
| Partner platforms table | Super admin only - compact tenant list with switch / campaigns link |
| **Today KPI strip** | Compact horizontal stats: leads, sold, unsold, revenue, reject %, quarantine, pending (clickable) |
| Quick link panels | Grouped chips: tenant management, campaigns & leads, operations, finance |
| Charts | 7/14/30-day lead volume + status donut + revenue line chart |
| Recent leads | Paginated table with drill-down to lead detail |

---

## Prerequisites

- `php artisan migrate:fresh --seed` completed
- Logged in as `uk@powerbyexcellence.test` / `password`
- Demo historical data seeder has run (28 days of leads on UK platform)

---

## How to Test (Step-by-Step)

### 1. Login and land on dashboard

1. Navigate to `/login`
2. Enter `uk@powerbyexcellence.test` and `password`
3. Submit the form

**Expected:** Redirect to `/dashboard`. Page title shows platform overview. No billing lock screen.

### 2. Verify live stats bar

Below the top nav, confirm the **Live** strip shows linked metrics (Leads, Sold, Queue, Quarantine, Revenue).

**Expected:** Values refresh periodically. Links navigate to leads, operations, quarantine, finance.

### 3. Verify today KPI strip

Inspect the horizontal **compact stat strip** (not large cards):

| Stat | What to check |
|------|---------------|
| Leads today | Non-zero if leads submitted today |
| Sold today | `sold` status distributed today |
| Unsold today | Received but not sold today |
| Revenue today | Sum for today's sold leads (tenant currency) |
| Reject rate | % rejected today |
| Quarantined | Held leads count |
| Pending | Queue depth |

**Expected:** Clicking a cell filters or navigates to the relevant list.

### 4. Verify charts

1. Find **Lead volume** chart - toggle 7d / 14d / 30d
2. Confirm **Status breakdown** donut for the same period
3. Scroll to **Revenue** chart

**Expected:** Seeded historical data produces visible series. Currency matches platform (GBP for UK).

### 5. Verify recent leads table

1. Scroll to **Recent leads**
2. Click a row

**Expected:** Navigates to `/leads/{id}` detail.

### 6. Test quick links

Use quick-link chip panels or top nav:

| Destination | Route |
|-------------|-------|
| Live Operations | `/operations` |
| Lead Pipeline | `/leads` |
| Deliveries | `/deliveries` (More → Ops, or quick links) |
| Buyers | `/buyers` (More menu) |
| Billing | `/billing` (More menu) |
| Reports | `/reports` (top nav) |

**Expected:** Each page loads without 403/404.

### 7. Super admin tenant table

1. Log in as `admin@powerbyexcellence.test`
2. On Home, confirm **Partner platforms** table lists UK + US tenants
3. Click **Switch** on inactive tenant

**Expected:** Tenant context updates; banner and scoped data reflect selection.

---

## Expected Results (Summary)

- Dashboard loads within 2 seconds on local Herd
- Currency displays in **tenant default** (GBP UK, USD US)
- Charts reflect seeded historical data
- Buyer/supplier portal users **cannot** access `/dashboard` (403 or redirect)

---

## Edge Cases

| Scenario | Expected behaviour |
|----------|-------------------|
| Fresh seed, no leads today | Today strip may show 0; charts still show history |
| Super admin without platform switch | May see all-tenant aggregate - select tenant for buyer/supplier CRUD |
| Billing-locked account | Redirect to `/billing/lock` |
| Queue worker stopped | Pending rises after async API ingest |

---

## Related Docs

- [12-operations-and-logs.md](./12-operations-and-logs.md) - live pipeline monitor
- [05-reports.md](./05-reports.md) - deeper analytics
- [02-campaigns-and-verticals.md](./02-campaigns-and-verticals.md) - campaign configuration
- [../UX_NAVIGATION_AUDIT.md](../UX_NAVIGATION_AUDIT.md) - navigation improvement backlog
