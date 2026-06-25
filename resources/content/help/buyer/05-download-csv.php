<?php

return [
    'category' => 'Buyer Portal',
    'slug' => 'buyer-portal-csv-export',
    'title' => 'Downloading Leads (CSV)',
    'summary' => 'Export purchased leads for CRM or dialler import, with date filters and a 5,000-row limit per download.',
    'audience' => 'buyer',
    'sort_order' => 50,
    'body' => <<<'MD'
## Overview

CSV export lets you pull lead batches into Excel, Google Sheets, HubSpot, Salesforce, or your dialler **without using the API**. Downloads are scoped strictly to leads **sold to your buyer account** on your tenant — you cannot access other buyers' data.

The export endpoint is `/portal/buyer/leads/download`. The browser saves a file named `leads.csv`.

## When to use CSV vs the on-screen table

| Use CSV when… | Use My Leads table when… |
|---------------|--------------------------|
| Importing hundreds+ rows into a CRM | Checking a handful of records interactively |
| Sharing a file with finance or ops | Submitting feedback or returns |
| Need standard columns in one file | Filtering and paging through live inventory |
| Building offline reports in Excel | Copying a single UUID quickly |

## How to export — basic (no date filter)

1. Sign in to the buyer portal
2. From **Buyer Dashboard** (`/portal/buyer`), click **Download CSV** in the hero banner  
   **OR** from **My Leads** (`/portal/buyer/leads`), click **Export CSV** in the page header
3. Your browser downloads `leads.csv` immediately
4. Open the file in your spreadsheet tool (see encoding tips below)

This path exports up to **5,000** of your **most recent** leads by `distributed_at`, with no date restriction.

## How to export — with date range

1. Open `/portal/buyer/leads`
2. In the **Filters** panel, set **From** and/or **To** dates
3. Click **Apply** — confirm the table shows the expected date range
4. Click **Export CSV** in the page header (filters are passed to the download URL automatically)
5. Verify row count in the spreadsheet matches your expectations

Example URL shape after filtering:

```
/portal/buyer/leads/download?from_date=2026-06-01&to_date=2026-06-30
```

You can bookmark or script this URL pattern for scheduled manual pulls (authentication still required).

## CSV columns

The header row is fixed:

```
uuid,firstname,lastname,email,phone1,zipcode,status,revenue,received_at
```

| Column | Description |
|--------|-------------|
| `uuid` | Unique lead identifier — map as external dedupe key in CRM |
| `firstname` | First name from campaign field data |
| `lastname` | Last name from campaign field data |
| `email` | Email address |
| `phone1` | Primary phone |
| `zipcode` | Postal / ZIP code |
| `status` | Lead status (typically `sold` for your inventory) |
| `revenue` | Cost charged to your buyer account for this lead |
| `received_at` | Timestamp when the lead was received by the platform |

Vertical-specific fields beyond this set are **not** included in the standard CSV — use API access or ask your administrator about extended export options if you need custom schema fields.

## Limits and ordering

| Rule | Detail |
|------|--------|
| Maximum rows | **5,000** per download |
| Sort order | Most recent `distributed_at` first |
| Scope | Only `sold_to_buyer_id` = your buyer |
| Tenant isolation | Cannot cross partner platforms |
| Authentication | Must be signed in as Buyer Portal user |

If you have more than 5,000 leads in a date range, run multiple exports with narrower date windows (e.g. weekly batches).

## CRM import tips

1. Map `uuid` as your **unique external ID** to prevent duplicate imports on re-export
2. Treat `revenue` as **cost** (debit to your account), not supplier payout
3. Parse `received_at` as ISO datetime for timezone-aware reporting
4. Import `status` for filtering — most workflows only import `sold` rows
5. Normalise phone numbers in your CRM ETL if diallers require E.164 format
6. Keep a copy of each export file for audit — filenames are always `leads.csv` (rename after download if needed)

### Excel on Windows

Open via **Data → Get Data → From Text/CSV** and choose **UTF-8** encoding to avoid garbled accents in names or addresses.

### Google Sheets

**File → Import → Upload** and select **Comma** separator with UTF-8 detection.

## Example scenarios

### Nightly dialler feed

Every evening at 6 PM, ops exports the day’s leads: **From** = today, **To** = today, **Apply**, **Export CSV**. The dialler team imports `uuid` and `phone1` into the outbound queue. UUID dedupe prevents re-dialling leads from an earlier export.

### Month-end finance reconciliation

Finance requests all June sales. Ops filters `2026-06-01` to `2026-06-30`, exports CSV, and sums the `revenue` column. They cross-check the total against **Spend** on the dashboard chart and `/portal/buyer/billing` debits.

### Partial UUID lookup failed in UI

A CRM stores full UUIDs but the portal table truncates display. Ops pastes the full UUID into **Search** on **My Leads**, confirms the row, then exports with a tight date filter to pull the complete CSV line for that record.

## Tips

- Always **Apply** date filters on **My Leads** before **Export CSV** — the dashboard **Download CSV** button does not include date filters
- Rename downloaded files immediately (`leads-2026-06-25.csv`) — the server always names the file `leads.csv`
- For recurring exports, document the filter URL your team uses so results stay consistent
- Compare `revenue` in CSV against the **Revenue** column in **My Leads** — they use the same source
- If importing into strict schemas, validate empty `email` or `phone1` rows against campaign optional-field rules
- Large exports may take a few seconds — wait for the browser download to complete before clicking again

## Troubleshooting

| Symptom | Likely cause | Resolution |
|---------|--------------|------------|
| **Empty file** (header only) | No leads in date range | Widen **From** / **To**; confirm sales exist on **My Leads** |
| Fewer rows than expected | 5,000 cap or filters | Narrow date range into multiple exports; check filters |
| Garbled characters (Ã©, etc.) | Wrong encoding in Excel | Open as UTF-8 via Data → From Text/CSV |
| Duplicate rows in CRM | Re-imported without UUID key | Dedupe on `uuid` in import mapping |
| Missing custom fields | Standard CSV schema only | Request API or extended export from administrator |
| Download prompts login page | Session expired | Sign in again at `/login`, retry export |
| **403** or access denied | Wrong role or buyer link | Confirm Buyer Portal user with linked buyer |
| Revenue shows `0` | Financials edge case | Cross-check row on **My Leads**; escalate if wrong |
| Dates off by one day | Spreadsheet timezone | Import `received_at` as datetime, not date-only |
MD,
];
