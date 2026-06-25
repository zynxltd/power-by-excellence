<?php

return [
    'category' => 'Supplier Portal',
    'slug' => 'supplier-portal-dashboard',
    'title' => 'Supplier Dashboard & Performance',
    'summary' => 'Leads received, sold rate, payout charts, and source breakdown.',
    'audience' => 'supplier',
    'sort_order' => 30,
    'body' => <<<'MD'
## Overview

The **Supplier Dashboard** at `/portal/supplier` is your at-a-glance performance hub. It shows real-time counts for today, trend charts for the last seven days, and a list of your configured **Sources (SIDs)**.

Use the dashboard for daily health checks before diving into detailed lead filters or CSV exports.

## Navigating to the dashboard

1. Sign in at `{subdomain}/login`
2. You land on `/portal/supplier` automatically after login
3. To return later, click **Dashboard** in the navigation or visit `/portal/supplier` directly

The page header displays **Supplier Dashboard** with your supplier name (e.g. "Lead submissions & payouts for Acme Media Ltd").

## Stats cards (today)

Three cards appear at the top of the dashboard:

| Card | What it measures | Notes |
|------|------------------|-------|
| **Leads Submitted Today** | Leads received with your `supplier_id` today | Based on `received_at` timestamp |
| **Sold Today** | Leads that reached **sold** status today | Based on `distributed_at` timestamp |
| **Payout Today** | Sum of payout from sold leads today | Shown in your account currency (GBP, USD, EUR) |

### How to interpret today's numbers

- **High submissions, low sold** — Traffic is arriving but not monetising; check lead quality, campaign fit, or filter match rates on `/portal/supplier/leads`
- **Sold without matching submissions** — Rare; usually reflects leads received on a prior day that sold today (distribution delay)
- **Payout lower than expected** — Payout is your rev-share, not buyer revenue; rates are set by the platform administrator

### Example

| Metric | Value | Interpretation |
|--------|-------|----------------|
| Leads Submitted Today | 120 | 120 API posts attributed to your supplier today |
| Sold Today | 48 | 40% sold rate today (48 ÷ 120) |
| Payout Today | £192.00 | Average ~£4.00 payout per sold lead |

## Charts (last 7 days)

Two bar charts sit below the stats cards:

### Leads Submitted — Last 7 Days

- **Blue bars** — leads received per day (`received_at`)
- **Green bars** — leads sold per day (`distributed_at`, status = sold)
- X-axis labels show weekday abbreviations (Mon, Tue, Wed…)

Compare blue vs green height to spot days with strong or weak conversion. A widening gap (many blue, few green) signals quality or routing issues.

### Payout — Last 7 Days

- **Cyan bars** — total payout per day from sold leads
- Useful for correlating earnings with traffic volume and sold rate

### Reading chart trends

1. **Steady parallel bars** — Consistent sold rate; healthy traffic
2. **Submission spike, flat sold** — Possible bad traffic source or campaign mismatch
3. **Payout dip despite high sold** — Lower-value leads or campaign payout rule change; check with account manager

## Your Sources (SID) panel

The bottom panel lists every **Source** configured under your supplier account.

Each row shows:

- **SID** — the source identifier you pass in API ingest (e.g. `google_ppc`, `email_blast`)
- **Name** — human-readable label set by the administrator

### Why Sources matter

- Every API post should include a valid `sid` matching one of these Sources
- Sources let you segment PPC, email, display, and sub-affiliate traffic in reporting
- An empty panel means no Sources are configured — contact your account manager before scaling spend

### Example SID setup

| SID | Name | Typical use |
|-----|------|-------------|
| `google_ppc` | Google Ads | Paid search campaigns |
| `bing_ppc` | Microsoft Ads | Bing/UET traffic |
| `partner_network` | Sub-affiliates | SSID-tracked downstream partners |

## Quick actions from the dashboard

- Click **View all leads** (top right) to open `/portal/supplier/leads` with the full filterable table
- Use navigation to reach **Payouts** (`/portal/supplier/billing`) for lifetime earnings

## Daily monitoring workflow

1. Open `/portal/supplier` each morning
2. Compare **Leads Submitted Today** and **Sold Today** against your internal traffic logs
3. Scan the 7-day chart for anomalies (sudden drops or spikes)
4. If sold rate drops, filter leads by `rejected` or `quarantined` on the Leads page
5. Confirm all active traffic sources appear in the **Your Sources (SID)** panel

## Tips

- Sold rate = sold ÷ received — benchmark within the same campaign vertical, not across unrelated offers
- Payout is your **rev-share**, not buyer revenue or platform margin
- Charts use the platform's timezone for "today" boundaries — ask your account manager if unsure
- Dashboard data updates on each page load; refresh to see the latest counts after a traffic burst
- Sub-affiliate performance is tracked via **SSID** at ingest time — the Sources panel shows SIDs, not individual SSIDs (use CSV export for SSID-level reconciliation if your setup supports it)

## Troubleshooting

| Issue | Cause | Fix |
|-------|-------|-----|
| **All stats show zero** | No leads attributed to your supplier yet | Submit a test lead via API; confirm SID matches a configured Source |
| **Submissions show but sold is zero** | Leads failing validation or no buyer match | Filter `/portal/supplier/leads` by `rejected`, `unsold`, or `quarantined` |
| **Sources panel empty** | Admin has not created Sources | Request SID setup under your supplier profile |
| **Payout currency unexpected** | Account default currency differs from expectation | Shown in tenant account currency (GBP/USD/EUR) |
| **Chart shows gap on one day** | Platform outage or paused campaigns | Cross-check with account manager; review that day's leads individually |
MD,
];
