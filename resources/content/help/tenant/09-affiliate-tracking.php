<?php

return [
    'category' => 'Suppliers',
    'slug' => 'affiliate-tracking',
    'title' => 'Affiliates, SIDs & Postbacks',
    'summary' => 'Supplier settings, sub-ID tracking, and conversion postbacks.',
    'audience' => 'tenant',
    'sort_order' => 90,
    'body' => <<<'MD'
## Overview

**Suppliers** are affiliates — publishers, call centres, or ad networks that send leads into your platform. Each supplier has one or more **Sources** identified by a **SID** (source ID). Optional **sub-suppliers** (SSID) provide second-tier attribution for landing pages, sub-brokers, or A/B tests.

Tracking is enforced at ingest (`sid`, `ssid` on API payload) and on outbound **postbacks** when lead status changes.

## Supplier hierarchy

```
Supplier (affiliate entity)
  └── Source (SID) — e.g. google_ppc
        └── Sub-supplier (SSID) — e.g. landing_page_b
```

| Level | Identifier | Example |
|-------|------------|---------|
| Supplier | `reference` | `supplier-main` |
| Source | `sid` | `google_search` |
| Sub-supplier | `ssid` | `partner_12` |

## Create a supplier — step by step

1. **Suppliers** → **New** (`/suppliers/create`)
2. Wizard steps:

### Step 1 — Basics

| Field | Example |
|-------|---------|
| **Reference** | `acme-media` |
| **Name** | `Acme Media Ltd` |
| **Status** | Active |

### Step 2 — Affiliate settings

| Field | Purpose |
|-------|---------|
| `rev_share_percent` | Override default payout calculation |
| `default_postback_url` | Fired on configured events if no per-source URL |
| `enable_sub_suppliers` | Allow `ssid` on ingest |
| `tracking_params` | Passthrough query keys (e.g. `c1`–`c5`) |

### Step 3 — Traffic sources

Add at least one source:

| Field | Example |
|-------|---------|
| **SID** | `google_ppc` |
| **Name** | `Google PPC` |
| **Payout override** | Optional per-source payout |

Add **sub-suppliers** under a source if `enable_sub_suppliers` is on:

| SSID | Name |
|------|------|
| `lp_variant_a` | Landing page A |
| `lp_variant_b` | Landing page B |

### Step 4 — Portal access

Optional: create `supplier_portal` user for self-service reporting.

3. Save supplier

## API ingest with tracking

```bash
curl -X POST "https://your-tenant.test/api/v1/leads" \
  -H "Authorization: Bearer SUPPLIER_API_KEY" \
  -H "Content-Type: application/json" \
  -d '{
    "campaign_reference": "loans-uk",
    "sid": "google_ppc",
    "ssid": "partner_12",
    "c1": "everflow_click_id_99887",
    "fields": {
      "firstname": "Jane",
      "lastname": "Doe",
      "email": "jane@example.com",
      "phone1": "07700900123",
      "zipcode": "SW1A 1AA"
    }
  }'
```

### Tracking field reference

| Field | Required | Description |
|-------|----------|-------------|
| `sid` | Strongly recommended | Must match a source on the supplier |
| `ssid` | If sub-suppliers enabled | Second-tier attribution |
| `c1`–`c5` | No | Custom slots — echoed in postbacks |
| `optin_url` | No | Consumer opt-in page URL |
| `ip_address` | No | Consumer IP for fraud checks |

Supplier-scoped API keys can only ingest for their linked supplier.

## Issue supplier API key

1. **API Keys** → **New** (`/api-keys`)
2. Type: **Supplier** → select supplier
3. Permissions: `leads.create`, `leads.read`
4. Copy token — provide to affiliate integration team
5. Optional: IP allowlist for production

## Postbacks

`PostbackDispatcher` sends HTTP notifications when lead lifecycle events occur. Configure at **Postbacks** (`/postbacks`).

### Available events

| Event | When fired |
|-------|------------|
| `lead.accepted` | Lead passed validation |
| `lead.sold` | Lead sold to buyer |
| `lead.rejected` | Lead rejected |
| `lead.unsold` | No buyer accepted |
| `lead.contacted` | Buyer feedback — contacted |
| `lead.converted` | Buyer feedback — conversion |
| `lead.funded` | Buyer feedback — funded |
| `lead.returned` | Buyer return processed |
| `delivery.success` | Delivery post succeeded |

### Create a postback — admin UI

1. **Postbacks** → create new
2. Configure:

| Field | Example |
|-------|---------|
| **Name** | `Acme — sold notification` |
| **URL** | `https://affiliate.com/postback?click_id={c1}&payout={payout}` |
| **Method** | `GET` or `POST` |
| **Events** | `lead.sold`, `lead.rejected` |
| **Supplier** | Optional — scope to one affiliate |
| **Campaign** | Optional — scope to one campaign |
| **Active** | Yes |

3. Save

### URL macros

Postback URLs support field substitution from lead data:

| Macro | Value |
|-------|-------|
| `{lead_id}` / `{lead_uuid}` | Lead UUID |
| `{status}` | Current lead status |
| `{revenue}` | Revenue amount |
| `{payout}` | Supplier payout |
| `{sid}` | Source ID |
| `{ssid}` | Sub-source ID |
| `{campaign_reference}` | Campaign slug |
| `{c1}`–`{c5}` | Custom tracking values |
| Any field name | e.g. `{email}`, `{zipcode}` |

### Postback payload (POST method)

POST postbacks send JSON body merging all lead fields plus:

```json
{
  "lead_id": "ld_abc123",
  "lead_uuid": "ld_abc123",
  "campaign_reference": "loans-uk",
  "status": "sold",
  "event": "lead.sold",
  "revenue": 18.50,
  "payout": 5.00,
  "sid": "google_ppc",
  "ssid": "partner_12"
}
```

### Postback logs

1. **Postbacks** index — recent log strip
2. Each log row: URL fired, HTTP status, duration, response snippet
3. Failed postbacks show `failed` — affiliate should retry from their side or fix URL

## Rev-share and payout

| Setting | Level | Effect |
|---------|-------|--------|
| Campaign payout | Campaign | Default supplier payment on sold |
| `rev_share_percent` | Supplier affiliate settings | % of revenue instead of fixed |
| `payout_override` | Source (SID) | Per-source fixed override |

Payout timing depends on campaign `payout_supplier_on`:

| Value | Supplier paid when |
|-------|-------------------|
| `system_accept` | Lead accepted by platform |
| `buyer_delivery_accept` | Buyer post succeeds |

## Supplier portal

Create **supplier_portal** users so affiliates self-serve:

| Page | Path | Content |
|------|------|---------|
| Dashboard | `/portal/supplier` | Submits, sold rate, earnings |
| Leads | `/portal/supplier/leads` | Submitted lead list with status |
| Billing | `/portal/supplier/billing` | Payout summary |

Portal data is scoped strictly to the linked supplier — no cross-affiliate visiblity.

See **Supplier Portal** help category for portal-user documentation.

## Reporting by SID/SSID

1. **Reports** (`/reports`) — filter by supplier, source, campaign
2. Compare SID performance: sold rate, revenue, margin
3. Unknown or missing SIDs appear as blank — fix supplier onboarding

## Troubleshooting

| Symptom | Cause | Fix |
|---------|-------|-----|
| Postback not firing | Event not selected or postback inactive | Edit postback → check events + active |
| Postback failed (HTTP 4xx) | Affiliate URL wrong | Test URL with curl; check macros |
| Unknown SID in reports | Source not created | Add SID to supplier sources |
| SSID ignored | `enable_sub_suppliers` off | Enable in affiliate settings |
| 403 on ingest | Wrong supplier API key | Reissue key scoped to supplier |
| Payout mismatch | Override or rev-share config | Check source `payout_override` and `rev_share_percent` |
| Portal empty | Portal user not linked | Re-save portal section on supplier |

## Affiliate onboarding pack

Provide affiliates:

1. Tenant API base URL
2. Supplier API key (once)
3. Valid `sid` values table
4. `campaign_reference` list they may post to
5. Postback URL template with macros
6. API Spec link for required fields
7. Sample curl request

## Tips

- Validate SID exists before go-live — unknown SIDs may still ingest but skew reporting
- Document postback macros for affiliates — most use `GET` with query params
- Use unique SIDs per traffic channel (PPC, native, email) for clean reporting
- Scope postbacks per supplier when URLs differ — avoids firing wrong affiliate
- Monitor postback log failure rate — chronic failures mean affiliate integration drift
MD,
];
