<?php

return [
    'category' => 'Supplier Portal',
    'slug' => 'supplier-tracking-sids',
    'title' => 'SID, SSID & Postback Tracking',
    'summary' => 'How affiliate IDs flow from ingest to reporting and postbacks.',
    'audience' => 'supplier',
    'sort_order' => 70,
    'body' => <<<'MD'
## Overview

PowerByExcellence tracks affiliate hierarchy using **SID** (Source ID) and **SSID** (Sub-Supplier ID). These identifiers flow from API ingest through distribution, portal reporting, and conversion postbacks.

Correct tracking setup is essential for accurate performance segmentation and sub-affiliate reconciliation.

## Key concepts

| Term | Meaning | Who sets it |
|------|---------|-------------|
| **SID** | Source identifier - maps to a Source record under your supplier | Platform admin creates Sources; you pass `sid` in API |
| **SSID** | Sub-supplier / sub-affiliate token for downstream tracking | You pass `ssid` in API; admin may enable `enable_sub_suppliers` |
| **Source** | Database record linking a SID to your supplier profile | Visible on dashboard **Your Sources (SID)** panel |
| **Postback** | HTTP callback fired on conversion events (e.g. sold) | Admin configures `default_postback_url` on your supplier |

## SID - source tracking

Every lead you submit should include a `sid` value matching one of your configured Sources.

### Viewing your SIDs

1. Sign in and open `/portal/supplier`
2. Scroll to **Your Sources (SID)** panel
3. Each row shows the SID code and display name

### Example Source setup

| SID | Name | Traffic type |
|-----|------|--------------|
| `google_ppc` | Google Ads | Paid search |
| `facebook_social` | Facebook Lead Ads | Social |
| `partner_network` | Sub-affiliate network | Downstream partners |

### Why consistent SIDs matter

- Split reporting by traffic channel in the portal and CSV exports
- Identify which source has high rejection or low sold rates
- Avoid "unknown SID" leads that ingest but skew analytics

**Tip:** Use lowercase, underscore-separated names (`google_ppc` not `Google PPC`) for consistency.

## SSID - sub-affiliate tracking

If you work with sub-affiliates or downstream partners, pass an **SSID** per partner on each lead:

```json
{
  "campaign_reference": "loans_uk_v1",
  "sid": "partner_network",
  "ssid": "partner_42",
  "fields": {
    "firstname": "Jane",
    "lastname": "Smith",
    "email": "jane@example.com",
    "phone1": "07700900123"
  }
}
```

### When to use SSID

- You operate a partner network and need per-partner reporting
- You buy traffic from multiple sub-sources under one SID
- You reconcile payouts to sub-affiliates based on conversion data

### SSID in portal reporting

- The dashboard Sources panel shows SIDs, not individual SSIDs
- The Leads table shows **SID** per row
- For SSID-level analysis, cross-reference your internal SSID logs with exported CSV UUIDs and postback data

Your account manager must enable `enable_sub_suppliers` on your supplier profile for full SSID support.

## API ingest parameters

When posting leads via `POST /api/v1/leads`, include:

| Parameter | Required | Description |
|-----------|----------|-------------|
| `campaign_reference` | Yes | Target campaign code |
| `sid` | Yes | Your source identifier |
| `ssid` | No | Sub-affiliate token |
| `fields` | Yes | Lead data (firstname, email, phone, etc.) |

### Full example request

```json
POST /api/v1/leads
Authorization: Bearer {your_api_key}
Content-Type: application/json

{
  "campaign_reference": "loans_uk_v1",
  "sid": "google_ppc",
  "ssid": "partner_42",
  "fields": {
    "firstname": "Jane",
    "lastname": "Smith",
    "email": "jane@example.com",
    "phone1": "07700900123"
  }
}
```

### Response UUID

The API returns a lead UUID. Store this UUID - it links your internal tracking, portal rows, CSV exports, and postback callbacks.

## Postbacks

When a lead **sells**, the platform may fire an HTTP request to your configured **postback URL** with conversion macros (e.g. payout amount, UUID, status).

### Configuration

Postback URLs are set by the platform administrator:

- **Default postback** - `default_postback_url` on your supplier `affiliate_settings`
- **Per-source postback** - override on individual Source records

You cannot edit postback URLs in the supplier portal. Request changes from your account manager.

### Typical postback flow

1. You submit a lead via API with `sid` and optional `ssid`
2. Lead processes through validation and distribution
3. Lead reaches **sold** status
4. Platform fires GET/POST to your postback URL with macros
5. Your system records the conversion and credits the sub-affiliate

### Testing postbacks

1. Submit a test lead via API with a known `ssid`
2. Wait for the lead to sell (or ask admin to confirm test buyer routing)
3. Check your postback receiver logs for the callback
4. Match the UUID in the postback to `/portal/supplier/leads`
5. Scale traffic only after postbacks fire reliably

## Portal reporting by SID

### Dashboard

- `/portal/supplier` → **Your Sources (SID)** lists all configured sources
- 7-day charts aggregate all SIDs - use Leads filters for per-source analysis

### Leads page

1. Go to `/portal/supplier/leads`
2. Review the **SID** column per row
3. Filter by campaign + date, then scan SID distribution manually
4. For deeper analysis, export CSV and pivot by SID in a spreadsheet

### CSV export

Export from `/portal/supplier/leads` includes `campaign` and `status` but not `sid` or `ssid` in the CSV columns. For SID-level CSV analysis, use the on-screen Leads table or match UUIDs from CSV against your ingest logs where you recorded the SID.

## Tracking parameters

Your supplier may have `tracking_params` configured - passthrough query keys appended to postback URLs (e.g. `click_id`, `sub_id`). Ask your account manager for the macro reference document.

## End-to-end tracking workflow

1. **Setup** - Admin creates Sources (SIDs) under your supplier; you receive API key
2. **Ingest** - Every API post includes `sid` and optional `ssid`
3. **Monitor** - Check `/portal/supplier/leads` for status and SID column
4. **Convert** - Sold leads trigger postbacks to your URL
5. **Reconcile** - Match postback UUIDs to CSV exports and internal sub-affiliate reports
6. **Optimise** - Pause SIDs with low sold rates; scale SIDs with strong performance

## Tips

- Consistent SID naming avoids split reporting across typo variants
- Test postbacks with a sold test lead before scaling spend
- Store the API response UUID on every submission for end-to-end traceability
- Document your SSID naming convention for sub-affiliates (e.g. `partner_{id}`)
- See the tenant help article **Affiliates, SIDs & Postbacks** for administrator configuration detail
- Unknown SIDs may still ingest but will not map cleanly to your Sources panel

## Troubleshooting

| Issue | Cause | Fix |
|-------|-------|-----|
| **SID not in Sources panel** | Admin has not created the Source | Request SID setup before sending traffic |
| **All leads show same SID** | Hardcoded `sid` in your integration | Pass dynamic `sid` per traffic source |
| **Postback not firing** | URL not configured or lead did not sell | Confirm sold status; verify URL with account manager |
| **Postback fires but wrong data** | Macro mismatch | Request postback macro documentation from admin |
| **SSID not tracking** | `enable_sub_suppliers` disabled | Ask admin to enable on your supplier profile |
| **Duplicate reporting across SIDs** | Typo variants (`google_ppc` vs `google-ppc`) | Standardise SID values across integrations |
MD,
];
