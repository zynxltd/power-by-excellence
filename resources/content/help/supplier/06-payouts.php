<?php

return [
    'category' => 'Supplier Portal',
    'slug' => 'supplier-portal-payouts',
    'title' => 'Payouts & Billing Summary',
    'summary' => 'Lifetime payout, monthly totals, and recent sold lead earnings.',
    'audience' => 'supplier',
    'sort_order' => 60,
    'body' => <<<'MD'
## Overview

The **Payouts & Revenue** page at `/portal/supplier/billing` summarises your affiliate earnings on the platform. It shows lifetime totals, current-month payout, sold lead count, and a table of the 25 most recent sold leads with individual payout amounts.

This page is **read-only** - payout rates and rev-share percentages are configured by the platform administrator, not in the portal.

## Navigating to payouts

1. Sign in at `{subdomain}/login`
2. Click **Payouts** or **Billing** in the navigation menu
3. URL: `/portal/supplier/billing`

The page header reads **Payouts & Revenue** with the description: "Track your lead payouts, sold volume, and revenue earned on the platform."

## Summary cards

Three stat cards appear at the top:

| Card | What it shows | Calculation |
|------|---------------|-------------|
| **Total Payouts** | Lifetime earnings | Sum of all `lead_financials.payout` for your sold leads |
| **This Month** | Current calendar month earnings | Payouts where `distributed_at` falls in the current month and year |
| **Sold Leads** | Total sold count | All leads with status `sold` attributed to your supplier |

All monetary values display in your account currency (**GBP**, **USD**, or **EUR**).

### Example summary

| Card | Value |
|------|-------|
| Total Payouts | £12,450.00 |
| This Month | £1,820.00 |
| Sold Leads | 3,112 |

## How payouts are calculated

Payout per lead is determined when a lead reaches **sold** status:

1. The distribution engine matches your lead to a buyer
2. The platform calculates revenue and cost
3. Your **rev-share percentage** (from `affiliate_settings`) is applied
4. The result is stored in `lead_financials.payout`

You do not see buyer pricing or platform margin - only your payout amount.

### Rev-share configuration (admin-managed)

Your supplier record includes `affiliate_settings` such as:

- `rev_share_percent` - your percentage of campaign payout
- Campaign-specific payout rules may override defaults

Contact your account manager to discuss rate changes - these cannot be edited in the portal.

## Recent Payouts table

Below the summary cards, the **Recent Payouts** panel lists the **25 most recent sold leads**:

| Column | Description |
|--------|-------------|
| **Lead** | UUID (truncated for display) |
| **Payout** | Amount earned on that lead |
| **Sold At** | `distributed_at` timestamp - when the lead was sold |

### Using recent payouts for spot checks

1. Sell a test lead via API
2. Navigate to `/portal/supplier/billing`
3. Confirm the lead UUID appears at the top of Recent Payouts
4. Verify the payout amount matches your expected rev-share

## Reconciling payouts

### Monthly statement comparison

1. Note **This Month** on the billing page on the last day of the month
2. Export sold leads CSV for the same month (see CSV Export article)
3. Sum the `payout` column in your spreadsheet
4. Compare against the monthly statement from your account manager

Small differences may occur due to:

- Timezone boundaries on month-end leads
- Adjustments or returns processed after month close
- Leads received in one month but sold (`distributed_at`) in another

### Sold leads vs payout total

**Sold Leads** count and **Total Payouts** sum are independent metrics:

- 3,000 sold leads at varying payout rates produce different totals than 3,000 × flat rate
- Campaign payout rules may differ - higher-value verticals earn more per lead

## Payout timeline

Understanding when payout appears:

| Event | When | Visible in portal |
|-------|------|-------------------|
| Lead submitted | `received_at` | Leads page (payout = £0) |
| Lead sold | `distributed_at` | Leads page (payout > £0), Billing recent table |
| Dashboard "Payout Today" | Same day as `distributed_at` | Dashboard stats card |
| Billing "This Month" | `distributed_at` in current month | Billing summary card |

Unsold, rejected, quarantined, and duplicate leads **never** generate payout.

## Common workflows

### Verify a specific lead's earnings

1. Go to `/portal/supplier/leads`
2. Search by UUID
3. Check **Status** = `sold` and **Payout** column
4. Cross-reference on `/portal/supplier/billing` in Recent Payouts

### Track month-to-date earnings

1. Open `/portal/supplier/billing` daily or weekly
2. Monitor the **This Month** card
3. Compare trend against dashboard 7-day payout chart on `/portal/supplier`

### Investigate missing payout

1. Find the lead on `/portal/supplier/leads`
2. If status is `unsold` or `rejected` - no payout is expected
3. If status is `sold` but payout is £0 - refresh the page; contact account manager if persistent
4. If lead is not visible - verify API key, SID, and supplier attribution

## Tips

- Compare portal totals with monthly statements from your account manager
- Unsold leads do not generate payout - optimise traffic quality to improve sold rate
- **This Month** uses `distributed_at`, not `received_at` - a lead received last month but sold this month counts toward this month
- Payout currency is set at the tenant account level - you cannot switch currency in the portal
- Recent Payouts shows only 25 rows; use CSV export for complete sold-lead history

## Troubleshooting

| Issue | Cause | Fix |
|-------|-------|-----|
| **Total Payouts is £0** | No sold leads yet | Submit test traffic; confirm leads reach `sold` status |
| **This Month lower than expected** | Leads sold late in prior month | Check `distributed_at` on individual leads |
| **Sold count doesn't match CSV** | CSV filters on `received_at`; billing counts all sold | Align date logic when comparing |
| **Payout amount unexpected** | Rev-share or campaign rule change | Discuss rates with account manager |
| **Recent Payouts empty** | No sold leads on record | Verify distribution is working for your campaigns |
| **Currency symbol wrong** | Account default currency setting | Confirm with platform operator (GBP/USD/EUR) |
MD,
];