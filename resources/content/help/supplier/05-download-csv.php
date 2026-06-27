<?php

return [
    'category' => 'Supplier Portal',
    'slug' => 'supplier-portal-csv-export',
    'title' => 'CSV Export for Suppliers',
    'summary' => 'Download lead and payout data for affiliate reconciliation.',
    'audience' => 'supplier',
    'sort_order' => 50,
    'body' => <<<'MD'
## Overview

The supplier portal lets you download lead data as a CSV file for spreadsheet analysis, sub-affiliate reconciliation, and accounting. Exports are scoped to **your supplier only** - you cannot access other affiliates' data.

**Download URL:** `/portal/supplier/leads/download`
**Filename:** `supplier-leads.csv`
**Row limit:** 5,000 leads per download (most recent first)

## How to export step by step

### From the Leads page (recommended)

1. Sign in and navigate to `/portal/supplier/leads`
2. Set **From** and **To** date filters to the period you need
   - Example: first and last day of the month for monthly reconciliation
3. Click **Apply** to preview the filtered range in the table
4. Click **Export CSV** (top right button)
5. Your browser downloads `supplier-leads.csv`

### Direct URL access

You can also trigger a download by visiting:

```
/portal/supplier/leads/download?from_date=2026-06-01&to_date=2026-06-30
```

Replace dates with your desired range. You must be signed in - unauthenticated requests redirect to login.

## What filters apply to export

| Filter | Applied to CSV? |
|--------|-----------------|
| **From date** | Yes |
| **To date** | Yes |
| Status | No (export includes all statuses in date range) |
| Campaign | No |
| Search (UUID/email) | No |

**Important:** Only **date filters** narrow the CSV. If you need sold leads only, download the full date range and filter the `status` column in Excel or Google Sheets.

Leads are ordered by `received_at` descending (newest first) and capped at 5,000 rows.

## CSV columns

| Column | Description | Example |
|--------|-------------|---------|
| `uuid` | Unique lead identifier | `a3f8c2e1-7b4d-4e9a-9c1f-2d8e6f0a1b3c` |
| `campaign` | Campaign reference code | `loans_uk_v1` |
| `status` | Lead status | `sold` |
| `firstname` | Lead first name | `Jane` |
| `lastname` | Lead last name | `Smith` |
| `email` | Lead email address | `jane@example.com` |
| `phone` | Primary phone (`phone1` field) | `07700900123` |
| `payout` | Your earnings (0 for non-sold) | `4.50` |
| `received_at` | Ingest timestamp | `2026-06-15 14:32:01` |
| `distributed_at` | Sold/distribution timestamp | `2026-06-15 14:32:04` |

### Example CSV rows

```csv
uuid,campaign,status,firstname,lastname,email,phone,payout,received_at,distributed_at
a3f8c2e1-...,loans_uk_v1,sold,Jane,Smith,jane@example.com,07700900123,4.50,2026-06-15 14:32:01,2026-06-15 14:32:04
b7d2a9f3-...,loans_uk_v1,rejected,John,Doe,john@example.com,07700900456,0,2026-06-15 15:10:22,
```

Note: `distributed_at` is empty for leads that never sold.

## Common use cases

### Monthly payout reconciliation

1. Set **From** = `2026-06-01`, **To** = `2026-06-30`
2. Export CSV
3. In Excel: `=SUMIF(H:H,">0")` on the payout column (column H)
4. Compare total against `/portal/supplier/billing` → **This Month**

### Sub-affiliate reporting

If you pass `ssid` in API ingest, cross-reference SSID from your internal logs with UUIDs in the CSV. Match `uuid` to postback callbacks for per-partner attribution.

### CRM upload

Import `firstname`, `lastname`, `email`, `phone` columns into your CRM for follow-up on specific campaigns. Only import leads you are permitted to re-contact under your data agreement.

### Sold-rate analysis

1. Export a week's data
2. Pivot by `campaign` and `status`
3. Calculate sold rate = count of `sold` ÷ total rows per campaign

## Working with large date ranges

If your date range contains more than 5,000 leads:

1. Split into smaller windows (e.g. weekly instead of monthly)
2. Export each window separately
3. Merge files in your spreadsheet tool

The export always returns the **5,000 most recent** leads within the date range.

## Tips

- Match `uuid` to postback logs for conversion attribution
- `payout` is `0` for unsold, rejected, quarantined, and duplicate leads
- `campaign` shows the reference code (not display name) - consistent with API `campaign_reference`
- Set date filters **before** clicking Export CSV to avoid downloading unneeded rows
- Store exports securely - they contain PII (email, phone, name)

## Troubleshooting

| Issue | Cause | Fix |
|-------|-------|-----|
| **Empty CSV (header only)** | No leads in date range | Widen From/To dates or clear filters |
| **Fewer rows than expected** | 5,000 row cap reached | Narrow date range into smaller exports |
| **Status/campaign filter ignored** | Export only respects dates | Filter status/campaign in spreadsheet after download |
| **Download redirects to login** | Session expired | Sign in again at `/login` and retry |
| **Special characters garbled** | Spreadsheet encoding issue | Open as UTF-8 in Excel (Data → From Text/CSV) |
| **Payout doesn't match billing page** | Billing uses `distributed_at`; export filters on `received_at` | Align date logic - sold leads may span different received dates |
MD,
];