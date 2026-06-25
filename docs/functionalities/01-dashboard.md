# 01 — Dashboard

## Purpose

The admin dashboard is the landing page after login. It provides a real-time snapshot of lead volume, conversion, revenue, and pipeline health for the current platform (tenant). Use it to confirm seeded demo data loaded correctly and to jump quickly into operational areas via quick links.

---

## Where to Find It

| Item | Location |
|------|----------|
| URL | `https://powerbyexcellence.test/dashboard` |
| Route name | `dashboard` |
| Navigation | Sidebar → **Dashboard** (top item) |
| Access | Account Admin, Super Admin (after switching platform) |
| Middleware | `auth`, `verified`, `billing.active`, admin role |

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

### 2. Verify stat cards

Inspect the top summary cards:

| Stat | What to check |
|------|---------------|
| Leads today | Non-zero if you submitted leads today; may be 0 on fresh morning |
| Sold today | Count of leads with `sold` status distributed today |
| Unsold today | Leads received but not sold today |
| Revenue today | Sum of financial revenue for today's sold leads (GBP £) |
| Reject rate | Percentage of today's leads rejected |
| Quarantined | Count of leads in quarantine status |
| Pending | Leads awaiting queue processing |

**Expected:** After seeding, historical data populates charts even if "today" counts are low. Quarantined and pending may show small non-zero values from seed data.

### 3. Verify charts

1. Scroll to the **7-day trend** chart section
2. Confirm labels show day names (Mon, Tue, etc.)
3. Toggle or hover series if tooltips are available: leads, sold, revenue

**Expected:** Three data series with values across the last 7 days. Seeded historical leads produce visible bars/lines.

### 4. Verify status breakdown

1. Find the **30-day status breakdown** chart or table
2. Confirm statuses appear: `sold`, `rejected`, `unsold`, `quarantined`, `accepted`, `pending`

**Expected:** `sold` is the largest segment (~62% of historical seed). Other statuses present in smaller proportions.

### 5. Verify recent leads table

1. Scroll to **Recent leads**
2. Confirm columns: status, campaign, buyer, received time
3. Click a lead row if linked

**Expected:** Up to 10 most recent leads listed. Auto Insurance campaign leads appear frequently. Clicking navigates to `/leads/{id}` detail.

### 6. Test quick links

Click each quick link and confirm navigation:

| Link | Destination |
|------|-------------|
| Live Operations | `/operations` |
| Lead Pipeline | `/leads` |
| Deliveries | `/deliveries` |
| Buyers | `/buyers` |
| Suppliers | `/suppliers` |
| Integrations | `/integrations` |
| Billing | `/billing` |
| Import Data | `/imports` |

**Expected:** Each page loads without 403/404. Sidebar highlights correct section.

### 7. Theme and accent (optional)

1. Go to `/profile` → Appearance
2. Switch to dark mode and change accent colour
3. Return to `/dashboard`

**Expected:** Dashboard respects theme and accent. Preference persists on reload.

---

## Expected Results (Summary)

- Dashboard loads within 2 seconds on local Herd
- Currency displays as **GBP** for UK platform
- Charts reflect seeded 28-day historical data
- Recent leads match data in `/leads` index
- Quick links route to correct admin modules
- Buyer/supplier portal users **cannot** access `/dashboard` (403 or redirect)

---

## Edge Cases

| Scenario | Expected behaviour |
|----------|-------------------|
| Fresh seed, no leads submitted today | "Today" stats may be 0; 7-day chart still shows historical data |
| Super admin without platform switch | May see empty or wrong tenant data — switch at `/accounts` first |
| Billing-locked account | Redirect to `/billing/lock` before dashboard loads |
| Unverified email user | Redirect to email verification notice |
| US admin (`us@powerbyexcellence.test`) | Dashboard shows US platform data (1 solar campaign, USD) |
| Queue worker stopped | Pending count may rise after async API ingest; sold count won't update until worker runs |

---

## Related Docs

- [12-operations-and-logs.md](./12-operations-and-logs.md) — live pipeline monitor
- [05-reports.md](./05-reports.md) — deeper analytics
- [02-campaigns-and-verticals.md](./02-campaigns-and-verticals.md) — campaign configuration
