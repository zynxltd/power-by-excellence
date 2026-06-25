<?php

return [
    'category' => 'Buyer Portal',
    'slug' => 'buyer-portal-billing',
    'title' => 'Billing, Credits & Transactions',
    'summary' => 'How prepay credits work, reading the transaction ledger, and what happens when balance runs low.',
    'audience' => 'buyer',
    'sort_order' => 70,
    'body' => <<<'MD'
## Overview

The **Billing** page at `/portal/buyer/billing` (alias `/portal/buyer/transactions`) shows your current credit balance, whether prepay is required on your platform, and a paginated ledger of every debit and credit affecting your buyer account.

Use this page alongside the dashboard **Credit Balance** stat when reconciling spend with lead purchases or preparing top-up requests.

## Opening the billing page

1. Sign in to the buyer portal
2. Click **Billing** in the sidebar or navigate to `/portal/buyer/billing`
3. Review the three stat cards at the top, then scroll to **Transaction History**

The page header reads **Credits & Billing** with description *View your credit balance, prepay status, and transaction history.*

## What you see on screen

### Stat cards

| Card | Values | Meaning |
|------|--------|---------|
| **Current Balance** | Formatted currency amount | Live `buyer.credit_balance` |
| **Prepay Mode** | `Required` or `Optional` | Driven by platform setting `require_buyer_prepay` |
| **Currency** | GBP, USD, CAD, EUR, etc. | Your buyer/account default currency |

### Prepay notice banner

When **Prepay Mode** is **Required**, an info panel explains:

> Your account uses **prepay billing**. Leads are charged against your credit balance when sold. Contact your platform administrator to top up credit.

Buyer portal users cannot add credit themselves — top-ups are performed by administrators from the admin console.

### Transaction History table

| Column | Description |
|--------|-------------|
| **Date** | When the transaction was recorded (`created_at`) |
| **Type** | `debit` or `credit` (capitalised in UI) |
| **Amount** | Negative (red) for debits, positive (green) for credits |
| **Balance** | Running `balance_after` after this transaction |
| **Description** | Human-readable context — often includes lead UUID for purchases |

Transactions paginate at **25 per page**, newest first.

## Prepay mode explained

When the platform has **`require_buyer_prepay`** enabled in account settings:

1. Each sold lead **debits** your `credit_balance` by the lead **revenue** amount at sale time
2. Before you are pinged or posted to, the engine checks whether balance ≥ lead price
3. If balance is **insufficient**, your buyer node is **skipped** in the ping tree — you lose auctions without error emails to consumers
4. Administrators add credit via **Billing** in the admin console (manual top-up, return approval, promotions)

When prepay is **disabled** (**Prepay Mode: Optional**), you may be invoiced separately off-platform. The portal still shows transaction history for transparency, but distribution may not block on balance — confirm terms with your account manager.

## Transaction types

| Type | Direction | Typical causes |
|------|-----------|----------------|
| **debit** | Decreases balance | Lead purchase, adjustment charge, correction |
| **credit** | Increases balance | Manual top-up, approved return, promotional credit, reversal |

Each row includes **`balance_after`** so you can reconcile running totals without mental arithmetic. Match a debit **Description** containing a UUID to the same UUID on `/portal/buyer/leads`.

## Low balance behaviour

| Stage | What happens |
|-------|--------------|
| Balance adequate | Normal ping/post participation |
| Balance low | Platform may email `billing_alert_emails` and/or per-buyer alert contacts |
| Balance insufficient | Skipped in distribution until topped up |
| Account suspended | Separate from credit — admin suspension blocks login entirely |

Low-balance alert thresholds are configured by your platform operator — not in the buyer portal UI.

## Requesting a top-up

1. Note **Current Balance** and recent daily **Spend** from `/portal/buyer` charts
2. Estimate days of credit remaining: `balance ÷ average daily spend`
3. Email or ticket your account manager with buyer name, requested amount, and urgency
4. After admin posts credit, refresh `/portal/buyer/billing` — a **credit** row appears with updated **Balance**
5. Confirm pings resume if you were skipped (check **Leads Today** on dashboard)

## Reconciling with leads and CSV

1. Pick a date range on **My Leads** and sum **Revenue** column
2. On **Billing**, filter mentally by the same dates in **Transaction History** (debits for purchases)
3. Export CSV for the same range and sum `revenue` column
4. Totals should align — small timing differences may occur if sales straddle midnight platform time

## Example scenarios

### Weekend coverage

A payday lender runs campaigns 24/7. Friday afternoon, balance is £400 and daily spend averages £350. Ops requests £2,000 top-up before the weekend so Monday morning pings are not skipped.

### Investigating an unexpected debit

Finance spots a £45 debit with unfamiliar description. They copy the UUID from **Description**, search it on **My Leads**, and confirm a **sold** lead on that date with matching **Revenue**. CPL was higher because a premium campaign won the auction.

### Return credit posted

Buyer submitted a return that administrators approved. A **credit** transaction appears with positive amount and description referencing the return. **Current Balance** increases; **Balance** column on that row shows the new running total.

### Prepay off but tracking spend

**Prepay Mode** shows **Optional**. Buyer still uses **Transaction History** monthly to verify lead counts against the invoice their account manager sends — portal is the operational source of truth for per-lead charges.

## Tips

- Request top-ups **before weekends and holidays** if you run always-on campaigns
- Match transaction **Date** with CSV `received_at` / `distributed_at` for accounting cutoffs
- **Currency** is set per buyer/account — confirm with your account manager if you operate multi-currency brands
- Watch **Prepay Mode** after contract changes — switching to required prepay changes ping behaviour immediately
- Screenshot or export billing history before disputes — descriptions contain UUID audit trails
- Dashboard **Credit Balance** and billing **Current Balance** should always match — if not, hard-refresh the page

## Troubleshooting

| Symptom | Likely cause | Resolution |
|---------|--------------|------------|
| Skipped in ping tree, no leads | Insufficient credit (prepay required) | Top up via account manager; verify **Prepay Mode** |
| Unexpected debit | Legitimate lead purchase | Match UUID in **Description** to **My Leads** |
| Balance unchanged after return approved | Credit not yet posted | Refresh billing; contact admin if pending > SLA |
| **Prepay Mode: Optional** but still debited | Transparency ledger | Debits may still log; distribution may not block |
| No transactions listed | New account or no activity yet | Confirm sales occurred; check date range |
| Amount sign confusing | UI colour coding | Red = debit (negative amount); green = credit |
| Alert email not received | Wrong billing contact on file | Ask admin to verify `billing_alert_emails` settings |
| Cannot self-serve top-up | By design | Only administrators credit buyer balances |
| Balance mismatch vs dashboard | Stale page cache | Hard refresh `/portal/buyer` and `/portal/buyer/billing` |
| **403** on billing page | User not linked to buyer | Administrator must link portal user to buyer profile |
MD,
];
