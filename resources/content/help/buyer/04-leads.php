<?php

return [
    'category' => 'Buyer Portal',
    'slug' => 'buyer-portal-leads',
    'title' => 'Browsing & Searching Your Leads',
    'summary' => 'Filter, search, paginate, and interpret every lead record sold to your buyer account.',
    'audience' => 'buyer',
    'sort_order' => 40,
    'body' => <<<'MD'
## Overview

The **My Leads** page at `/portal/buyer/leads` lists every lead sold to your buyer account. This is your primary inventory view - richer than the dashboard's recent table and the right place to search, filter, export, and submit feedback or returns.

Each row includes campaign reference, contact fields from `field_data`, status, revenue (cost to you), and the distribution timestamp.

## Opening the leads page

1. Sign in and click **My Leads** in the sidebar, or navigate to `/portal/buyer/leads`
2. The page header shows **My Leads** with description *View purchased leads, filter inventory, and submit feedback or returns.*
3. Use **Export CSV** in the header to download with current filters applied

## Using filters

A **Filters** panel sits above the lead table with five controls:

| Filter | UI control | Behaviour |
|--------|------------|-----------|
| **Search** | Text input | Matches UUID, email, first name, or last name (partial match) |
| **Status** | Dropdown | sold, pending, processing, unsold, rejected - or **All** |
| **Campaign** | Dropdown | Limits to campaigns you have purchased from (name shown) |
| **From** | Date picker | `distributed_at` on or after this date |
| **To** | Date picker | `distributed_at` on or before this date |

### Applying filters step by step

1. Enter search text and/or select status, campaign, and dates
2. Click **Apply** - the table reloads with filtered results
3. Notice the URL updates with query parameters (e.g. `?search=smith&from_date=2026-06-01`) - share this link with teammates
4. Click **Clear** to reset all filters and show full inventory
5. Press **Enter** in the Search box to apply without clicking **Apply**

Results paginate at **25 leads per page**. Use pagination controls at the bottom of the table to move between pages. Filters persist across pages.

## Lead inventory table columns

| Column | Description |
|--------|-------------|
| **UUID** | Unique lead identifier (truncated in UI - use search to find full value) |
| **Campaign** | Campaign `reference` code |
| **Name** | `firstname` + `lastname` from field data |
| **Email** | Email address from field data |
| **Status** | Colour-coded status badge |
| **Revenue** | Amount charged to your account for this lead |
| **Date** | `distributed_at` - when you received the lead |

Additional field data (phone, zipcode, vertical attributes) is stored on the record but not shown in every column - use CSV export for full standard fields or ask your administrator about API access for complete schemas.

## Lead field data

Field names match the **campaign schema** configured by the platform. Common fields include:

- `firstname`, `lastname`, `email`, `phone1`, `zipcode`
- Vertical-specific: `vehicle_year`, `loan_amount`, `coverage_type`, etc.

Empty cells usually mean the field was optional on the form or not passed by the supplier - not a portal bug.

## Status meanings

| Status | Meaning for buyers |
|--------|-------------------|
| **sold** | Successfully delivered to you; you were the winning buyer - primary inventory status |
| **pending** | Awaiting processing (uncommon in buyer view after distribution completes) |
| **processing** | In flight through the pipeline |
| **unsold** | Did not result in a sale to any buyer |
| **rejected** | Failed validation, caps, or buyer rejection rules |

Most rows in your inventory should be **sold**. Other statuses may appear if you filter broadly or if records were visible during edge-case pipeline states.

## Feedback and returns on the same page

Below the inventory table, two forms appear side by side:

- **Submit Feedback** - report contact/conversion outcomes (see the Feedback article)
- **Return Lead** - request a quality return with reason (see the Returns article)

Both require the full **Lead UUID** from your inventory.

## Example scenarios

### Finding one lead by email

A consumer calls claiming they submitted a form an hour ago. Support searches the email in **Search**, clicks **Apply**, and finds the matching **sold** row with UUID and **Date**. They confirm delivery time matches the caller's story.

### Campaign-specific QA

During a new auto-insurance campaign launch, QA filters **Campaign** to that campaign only and reviews the first 50 **sold** leads over two days. They verify `vehicle_year` and state fields populate correctly before approving full budget.

### Sharing a filtered view with finance

Finance needs June purchases only. Ops sets **From** `2026-06-01`, **To** `2026-06-30`, clicks **Apply**, and copies the browser URL. Finance opens the same filtered list without re-entering criteria.

## Tips

- Search by **UUID** when reconciling with CRM imports - it is the canonical dedupe key
- Use **Campaign** filter when testing a new buyer integration or isolating CPL by product
- Date filters align to **platform timezone**, not UTC or your local office
- Combine **Status = sold** with date range for accounting-aligned exports
- After applying filters, use **Export CSV** so the download matches what you see
- UUIDs in the table are truncated - search by partial UUID or export for the full value

## Troubleshooting

| Symptom | Likely cause | Resolution |
|---------|--------------|------------|
| Lead missing from list | Not sold to your buyer ID | Ask admin to check delivery logs for your buyer reference |
| Search returns nothing | Typo, or lead on different buyer | Try email only; verify buyer ID with account manager |
| Wrong date range results | Timezone interpretation | `distributed_at` uses platform midnight boundaries |
| Empty email or phone in row | Optional campaign field | Check campaign schema; review CSV export |
| Pagination "lost" my filter | Navigated without query string | Re-apply filters; use shared URL with parameters |
| **404** on feedback/return submit | UUID not found or not yours | UUID must match a lead **sold to your buyer** only |
| All campaigns dropdown empty | No purchases yet | Deliveries populate campaign list over time |
| Status shows unsold but I was charged | Rare data inconsistency | Cross-check UUID in `/portal/buyer/billing` transactions |
MD,
];
