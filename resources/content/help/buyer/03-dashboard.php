<?php

return [
    'category' => 'Buyer Portal',
    'slug' => 'buyer-portal-dashboard',
    'title' => 'Buyer Dashboard & Charts',
    'summary' => 'Understand credit balance, daily stats, 7-day performance charts, and the recent leads panel.',
    'audience' => 'buyer',
    'sort_order' => 30,
    'body' => <<<'MD'
## Overview

The buyer dashboard at `/portal/buyer` is your home screen immediately after login. It gives a single-page snapshot of purchasing activity, credit position, and short-term trends for your buyer account — without running reports or exporting data.

The page header shows **Buyer Dashboard** with the subtitle *Credit & lead performance for {your buyer name}*. Two quick-action buttons sit in the hero: **Download CSV** and **View all leads**.

## Navigating to the dashboard

1. Sign in at your partner `/login`
2. You are redirected automatically to `/portal/buyer`
3. To return later, click **Dashboard** in the sidebar or open `/portal/buyer` directly

If you bookmark one portal page, make it the dashboard — it links to every other buyer feature.

## Key metrics (stat cards)

Three stat cards appear in a row below the hero banner:

| Metric | UI label | Meaning |
|--------|----------|---------|
| Credit Balance | **Credit Balance** | Prepay credits remaining on your buyer account (`buyer.credit_balance`) |
| Daily volume | **Leads Today** | Count of leads sold to you since midnight in the **platform timezone** |
| Lifetime volume | **Total Leads Purchased** | All-time sold count where you were the winning buyer |

### Reading the numbers

- **Credit Balance** displays with your account currency symbol (e.g. £, $, €). A balance of `0.00` with prepay enabled means you will be skipped in distribution until topped up.
- **Leads Today** resets at midnight platform time, not UTC or your local office timezone.
- **Total Leads Purchased** includes every historical sale; it does not decrease if leads are returned (returns affect credit via billing, not this counter).

## Charts — last 7 days

Two bar charts sit side by side (stacked on mobile):

### Leads Purchased — Last 7 Days

- **X-axis:** Weekday labels (Mon, Tue, …) for the past seven calendar days including today
- **Y-axis:** Count of leads with `distributed_at` on each day
- **Colour:** Indigo bars

Use this chart to spot volume drops (supplier issues, campaign pauses) or spikes (new traffic sources).

### Spend — Last 7 Days ({currency})

- **X-axis:** Same seven days as the leads chart
- **Y-axis:** Sum of `lead_financials.revenue` for leads sold to you on each day
- **Colour:** Emerald/green bars
- **Title** includes your platform default currency (GBP, USD, CAD, EUR, etc.)

Compare leads vs spend to calculate effective **cost per lead (CPL)** for each day: `spend ÷ leads`. A rising CPL with flat volume may indicate higher-priced campaigns winning more often.

## Recent Purchased Leads table

The bottom panel lists your **10 most recent** purchases in a table:

| Column | Content |
|--------|---------|
| Lead | First 12 characters of UUID + ellipsis (display only) |
| Status | Lead status (typically `sold` for delivered inventory) |
| Cost | Revenue charged to your account for that lead |
| Date | `distributed_at` — when the lead was delivered to you |

This table is read-only. For search, filters, feedback, or returns, click **View all leads** or go to `/portal/buyer/leads`.

## Quick actions from the dashboard

1. **Download CSV** — opens `/portal/buyer/leads/download` and triggers a browser download of `leads.csv` (up to 5,000 most recent leads; no date filter applied from dashboard alone)
2. **View all leads** — navigates to `/portal/buyer/leads` with full filter and pagination

To export a specific date range, go to **My Leads**, set **From** / **To** filters, click **Apply**, then use **Export CSV** (filters pass through to the download URL).

## Example scenarios

### Spotting a credit crunch before pings fail

David sees **Leads Today** at 85 and **Credit Balance** at £120. His average CPL from the spend chart is £8. He estimates less than two days of credit remain and emails finance to request a top-up before the weekend dialler shift.

### Investigating a quiet Tuesday

Emma notices the **Leads Purchased** chart shows zero on Tuesday while Monday and Wednesday are normal. She checks with her account manager whether the campaign was paused or her buyer node was capped — the dashboard confirms it is not a portal display issue because **Leads Today** also reflects the gap.

### Quick sanity check after go-live

After a new buyer integration goes live, Raj refreshes `/portal/buyer` every few minutes. He expects **Leads Today** to increment and new rows in **Recent Purchased Leads** with status `sold` and non-zero **Cost**. Once confirmed, he opens **My Leads** for full field data.

## Tips

- Compare **Leads Today** with the same weekday on the 7-day chart to detect anomalies early
- Low credit with high-volume days may mean missed pings tomorrow — request top-ups proactively via your account manager
- Charts always cover exactly seven days ending today; they do not respect custom date ranges
- Refresh the page (or revisit `/portal/buyer`) for near-real-time stats — there is no auto-refresh interval
- Use **Billing** (`/portal/buyer/billing`) alongside the dashboard when reconciling spend figures
- The truncated UUID in Recent Leads is for quick identification — copy the full UUID from **My Leads**

## Troubleshooting

| Symptom | Likely cause | What to do |
|---------|--------------|------------|
| All stat cards show zero | New account or no sales yet | Confirm live traffic; check with admin that buyer node is active |
| **Leads Today** seems wrong vs dialler | Timezone mismatch | Remember stats use platform timezone, not yours |
| Credit Balance does not match billing | Recent transaction not loaded | Open `/portal/buyer/billing` for authoritative ledger |
| Charts empty but stats non-zero | Edge case on first day of account | Wait until multiple days of history exist |
| **403 — Buyer account not linked to this user.** | User not linked to buyer | Contact administrator |
| Recent leads missing expected record | Lead sold to different buyer ID | Verify delivery logs with admin; search UUID on **My Leads** |
| Spend chart shows £0 with leads sold | Financials not yet written | Rare timing issue; refresh; escalate if persistent |
MD,
];
