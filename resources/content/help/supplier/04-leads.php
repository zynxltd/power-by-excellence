<?php

return [
    'category' => 'Supplier Portal',
    'slug' => 'supplier-portal-leads',
    'title' => 'Viewing & Filtering Your Leads',
    'summary' => 'Search leads by status, campaign, and date range.',
    'audience' => 'supplier',
    'sort_order' => 40,
    'body' => <<<'MD'
## Overview

The **Leads** page at `/portal/supplier/leads` lists every lead attributed to your supplier account. Use it to investigate individual submissions, diagnose quality issues, and prepare data for CSV export.

Only leads where `supplier_id` matches your account are visible — you never see another affiliate's traffic.

## Navigating to the leads page

1. Sign in at `{subdomain}/login`
2. From the dashboard, click **View all leads** or use navigation to **Leads**
3. URL: `/portal/supplier/leads`

The page shows a filter panel, a data table (25 leads per page), and pagination controls at the bottom.

## Using filters

The **Filters** panel supports five criteria. Set your values, then click **Apply**. Click **Clear** to reset all filters.

### Search

- **Field:** free-text input
- **Matches:** lead UUID (partial match) or email address in lead field data
- **Example:** paste `a3f8c2e1` to find a UUID, or `jane@example.com` to find by email
- Press **Enter** in the search box to apply immediately

### Status

- **Options:** All, `pending`, `processing`, `sold`, `unsold`, `rejected`, `quarantined`, `duplicate`
- **Use case:** filter to `sold` for payout reconciliation, or `quarantined` to spot validation holds

### Campaign

- Dropdown lists campaigns where you have at least one attributed lead
- Shows campaign **name**; internally filtered by `campaign_id`
- Select **All** to include every campaign

### Date range (From / To)

- Filters on **`received_at`** (when the lead entered the platform)
- Use date pickers to set inclusive start and end dates
- **Example:** From `2026-06-01`, To `2026-06-30` for June submissions

### Step-by-step: find all sold leads this week

1. Go to `/portal/supplier/leads`
2. Set **Status** to `sold`
3. Set **From** to Monday's date and **To** to today's date
4. Click **Apply**
5. Review the payout column and note UUIDs for reconciliation

### Step-by-step: diagnose rejected traffic

1. Set **Status** to `rejected`
2. Set **From** / **To** to the period you are investigating
3. Click **Apply**
4. Note campaign and SID patterns in rejected rows
5. Cross-check your API payload against campaign field requirements

## Table columns

| Column | Description |
|--------|-------------|
| **UUID** | Unique lead identifier (truncated in table; full value in CSV export) |
| **Campaign** | Campaign name the lead was submitted to |
| **SID** | Source identifier from your API ingest (`sid` parameter) |
| **Status** | Current lifecycle status (colour-coded badge) |
| **Payout** | Your earnings when sold; `£0.00` for non-sold leads |
| **Received** | Timestamp when the lead was ingested (`received_at`) |

Buyer identity is typically hidden per platform policy. You see your payout on sold leads, not the buyer's purchase price.

## Status guide

| Status | Meaning | Payout? |
|--------|---------|---------|
| **pending** | Received, awaiting processing | No |
| **processing** | Currently in distribution / validation pipeline | No |
| **sold** | Monetised — a buyer accepted the lead | Yes |
| **unsold** | Processed but no buyer accepted | No |
| **rejected** | Failed validation, business rules, or filter checks | No |
| **quarantined** | Held for manual review by the platform operator | No |
| **duplicate** | Matched an existing lead (dedupe rule triggered) | No |

### Status lifecycle (simplified)

```
pending → processing → sold
                     → unsold
                     → rejected
                     → quarantined
                     → duplicate
```

Not every lead passes through every state. Some reject immediately; others quarantine before a final decision.

## Pagination

- Results are paginated at **25 leads per page**
- Use pagination links at the bottom to navigate older submissions
- Filters persist in the URL — bookmark or share a filtered view with your team

## Export CSV from leads page

Click **Export CSV** (top right) to download leads. The export respects your **date filters** (From / To). Set dates before exporting to avoid downloading the full 5,000-lead limit. See the **CSV Export** article for column details.

## Common workflows

### Daily reconciliation

1. Filter **Status** = `sold`, **From** = yesterday, **To** = yesterday
2. Sum payout column mentally or export CSV for spreadsheet totals
3. Compare against postback logs and ad platform spend

### Campaign performance comparison

1. Clear all filters
2. Select **Campaign A**, note sold count and payout over a date range
3. Click **Clear**, repeat for **Campaign B**
4. Compare sold rates to decide where to allocate traffic

### Finding a specific lead

1. Paste the UUID or email into **Search**
2. Click **Apply**
3. Confirm status and payout match your postback notification

## Tips

- Filter **sold** for payout reconciliation; ignore payout values on other statuses (always zero)
- Use **quarantined** to spot validation issues in your data before they scale
- **duplicate** status often means the same consumer submitted twice — review dedupe settings with your account manager
- Date filters use `received_at`, not `distributed_at` — a lead received Monday may sell Tuesday
- SID column helps segment traffic without exporting — look for one SID with unusually high rejection rates

## Troubleshooting

| Issue | Cause | Fix |
|-------|-------|-----|
| **No leads returned** | Date range too narrow, or no traffic yet | Widen dates or clear filters |
| **Expected lead missing** | Wrong SID, wrong supplier API key, or wrong subdomain | Verify API credentials and SID against Sources panel |
| **Status stuck on pending** | Processing backlog or distribution delay | Wait a few minutes; contact account manager if persistent |
| **Payout shows £0 on sold lead** | Financials not yet written | Rare timing issue; refresh page or check billing page |
| **Campaign not in dropdown** | No leads for that campaign under your supplier | Confirm you are posting to the correct `campaign_reference` |
| **Search by phone not working** | Search supports UUID and email only | Use UUID from API response or export CSV |
MD,
];