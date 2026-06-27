<?php

return [
    'category' => 'Buyer Portal',
    'slug' => 'buyer-portal-webhooks',
    'title' => 'Webhooks & Outbound Events',
    'summary' => 'Register HTTPS endpoints, approval workflow, and sample lead.sold payloads.',
    'audience' => 'buyer',
    'sort_order' => 55,
    'body' => <<<'MD'
## Overview

When leads are **sold to your buyer account**, the platform can push JSON to your HTTPS endpoints in real time. Configure webhooks on `/portal/buyer/integrations`, submit drafts for tenant approval, and point your CRM or middleware at the approved URLs.

This is separate from **ping/post delivery** (how buyers receive live lead posts) and from the **feedback API** (how you report conversions back to the platform).

## Configure a webhook

1. Sign in and open `/portal/buyer/integrations`
2. Scroll to **Your webhooks**
3. Enter a **Name**, **Webhook URL** (HTTPS), and tick the **Events** you need (typically `lead.sold`)
4. Click **Save draft**
5. Click **Submit for approval** — live traffic does not fire until a platform administrator approves the request
6. After approval, the webhook appears under **Active webhooks**

You can edit or resubmit rejected drafts. To remove an approved webhook, use **Request removal** and wait for administrator action.

## Authentication & security

- Webhook URLs must be **HTTPS** in production
- The platform POSTs JSON to your endpoint; validate signatures or IP allowlists if your security team requires them (ask your account manager for tenant policy)
- Do not expose API keys in webhook URLs

## Event types

| Event | When it fires |
|-------|---------------|
| `lead.sold` | A lead is sold and attributed to your buyer |

Additional event types may be enabled by your platform administrator. Only tick events your integration handles.

## Sample payload (`lead.sold`)

```json
{
  "event": "lead.sold",
  "lead_id": 12345,
  "lead_uuid": "00000000-0000-0000-0000-000000000000",
  "campaign_id": 1,
  "buyer_id": 42,
  "buyer_name": "Example Buyer Ltd",
  "buyer_reference": "buyer-042",
  "status": "sold",
  "revenue": 42.50,
  "payout": null,
  "field_data": {
    "firstname": "Jane",
    "lastname": "Example",
    "email": "jane@example.com"
  },
  "received_at": "2026-06-24T10:00:00+00:00",
  "distributed_at": "2026-06-24T10:02:00+00:00"
}
```

Field availability depends on campaign configuration and privacy rules. Treat `lead_uuid` as the stable identifier for dedupe in your CRM.

## Feedback API (inbound to platform)

To report outcomes **to** the platform from your CRM, use the REST feedback endpoint (requires an API key with `buyers.manage`):

```bash
curl -X POST 'https://your-tenant.test/api/v1/buyers/buyer-042/feedback' \
  -H 'Authorization: Bearer your_prefix|your_secret' \
  -H 'Content-Type: application/json' \
  -d '{
    "lead_uuid": "00000000-0000-0000-0000-000000000000",
    "status": "contacted",
    "converted": false,
    "notes": "Reached consumer — follow-up scheduled"
  }'
```

| Field | Required | Values |
|-------|----------|--------|
| `lead_uuid` | Yes | UUID from My Leads or CSV export |
| `status` | Yes | `contacted`, `converted`, or `invalid` |
| `converted` | No | `true` when funded / sold |
| `notes` | No | Free text |

Returns are **portal-only** — use the form on `/portal/buyer/leads`. See **Feedback, Conversions & Returns** for portal workflows.

## CSV export (pull model)

Download purchased leads without webhooks:

```
GET /portal/buyer/leads/download?from_date=2026-06-01&to_date=2026-06-30
```

Optional query params: `uuids[]`, `supplier_id`, `sid`. Requires portal login. See **Downloading Leads (CSV)** for full detail.

## Pull vs push

| Approach | Best for |
|----------|----------|
| **Webhooks** | Real-time CRM ingest when a lead sells |
| **CSV export** | Batch imports, finance reconciliation, offline analysis |
| **Feedback API** | Automated conversion reporting from your CRM |

## Troubleshooting

| Symptom | Likely cause | Resolution |
|---------|--------------|------------|
| Webhook never fires | Draft not approved | Submit for approval; confirm status is **Live** |
| HTTP 4xx from your server | URL or handler error | Test endpoint with a manual POST; check logs |
| Duplicate events | Retries or multiple webhooks | Dedupe on `lead_uuid` in your consumer |
| Missing fields in payload | Campaign privacy rules | Ask account manager which fields are included |
| Need historical data | Webhooks are forward-looking | Use CSV export for backfill |
MD,
];
