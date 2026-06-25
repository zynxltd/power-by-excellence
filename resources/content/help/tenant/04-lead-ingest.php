<?php

return [
    'category' => 'Leads',
    'slug' => 'lead-ingest',
    'title' => 'Ingesting Leads via API',
    'summary' => 'REST API authentication, payload structure, and sync vs async processing.',
    'audience' => 'tenant',
    'sort_order' => 40,
    'body' => <<<'MD'
## Overview

The primary ingest endpoint is:

```
POST /api/v1/leads
```

Suppliers and integrations POST JSON payloads authenticated with a **Bearer API key**. The platform validates the campaign, maps fields to the campaign schema, records the lead, and either queues or immediately processes it through the pipeline.

**Base URL:** `https://{your-tenant-domain}/api/v1`

## Authentication

Pass the supplier or administrator API key in the `Authorization` header:

```
Authorization: Bearer {api_key}
Content-Type: application/json
Accept: application/json
```

### API key types

| Type | Scope | Typical permissions |
|------|-------|---------------------|
| **Administrator** | Account-wide | `*` or custom set |
| **Supplier** | Locked to one supplier | `leads.create`, `leads.read` |

Keys are managed at **API Keys** (`/api-keys`). Additional controls:

- **IP allowlist** — reject requests from unknown IPs
- **Revocation** — instant 401 on revoked keys

### Permission reference

| Permission | Endpoints |
|------------|-----------|
| `leads.create` | `POST /leads`, `POST /leads/import` |
| `leads.read` | `GET /leads/{uuid}`, `GET /leads/queue/{id}`, `POST /leads/search` |
| `quarantine.manage` | `GET /quarantine`, release/reject actions |
| `*` | All endpoints |

## Request payload

### Top-level fields

| Field | Required | Description |
|-------|----------|-------------|
| `campaign_reference` | Yes* | Campaign slug (e.g. `auto-insurance-uk`) |
| `campaign_id` | Yes* | Numeric ID — alternative to reference |
| `sid` | Recommended | Source ID for affiliate tracking |
| `ssid` | No | Sub-source ID (requires supplier config) |
| `fields` | Yes | Object matching campaign schema |
| `sync` | No | `true` = process inline; default = async queue |
| `metadata` | No | Arbitrary JSON stored on lead |
| `received_at` | No | Override ingest timestamp (ISO 8601) |

*Provide `campaign_reference` **or** `campaign_id`, not neither.

### Field mapping

Campaign fields can be sent either nested in `fields` or at the top level (legacy flat format). Preferred:

```json
{
  "campaign_reference": "loans-uk",
  "sid": "google_search",
  "fields": {
    "firstname": "Jane",
    "lastname": "Doe",
    "email": "jane@example.com",
    "phone1": "07700900123",
    "zipcode": "SW1A 1AA",
    "loan_amount": "10000",
    "loan_purpose": "debt_consolidation"
  }
}
```

### Tracking parameters

Optional passthrough keys for affiliate reporting:

| Field | Purpose |
|-------|---------|
| `c1`–`c5` | Custom tracking slots |
| `optin_url` | Landing page URL |
| `ip_address` | Consumer IP (fraud/geo checks) |
| `consent_text` | GDPR/consent audit |

## Sync vs async

| Mode | HTTP | Behaviour | When to use |
|------|------|-----------|-------------|
| **Default (async)** | `202 Accepted` | Returns `queue_id`; worker processes lead | Production volume |
| **`sync: true`** | `200 OK` | Full pipeline inline in request | Staging, low volume, live buyer redirect |

### Async response

```json
{
  "status": "queued",
  "queue_id": "q_8f3a2b1c",
  "lead_id": "ld_9x7y6z"
}
```

Poll until complete:

```bash
GET /api/v1/leads/queue/{queue_id}
Authorization: Bearer {api_key}
```

### Sync response (sold example)

```json
{
  "status": "sold",
  "lead_id": "ld_9x7y6z",
  "buyer_reference": "primary-buyer",
  "revenue": 18.50,
  "payout": 5.00,
  "redirect_url": "https://buyer.com/thank-you?ref=..."
}
```

**Warning:** `sync: true` on campaigns with deep ping trees risks HTTP timeouts. Use async in production.

## Additional API endpoints

| Method | Path | Purpose |
|--------|------|---------|
| `GET` | `/leads/{uuid}` | Full lead detail with financials and delivery logs |
| `GET` | `/leads/queue/{queueId}` | Poll async status |
| `POST` | `/leads/search` | Paginated lead search |
| `POST` | `/leads/{uuid}/reprocess` | Re-queue lead through pipeline |
| `POST` | `/leads/import` | CSV bulk import |

### Reprocess example

```bash
curl -X POST "https://your-tenant.test/api/v1/leads/{uuid}/reprocess" \
  -H "Authorization: Bearer YOUR_API_KEY"
```

Use when a lead was **unsold** due to temporary buyer outage and you want to retry distribution.

## Hosted forms

Alternative to direct API integration:

1. **Forms** → **New** (`/forms`)
2. Link form to campaign; configure steps and styling
3. Public URL: `/forms/{slug}` on your tenant domain
4. Submissions flow through the same validation and pipeline as API ingest

Hidden fields can pass `sid`, `ssid`, and UTM parameters. Use **Campaign → API Spec → Apply to form** to sync field definitions.

## CSV import

Batch ingest for call-centre uploads or legacy data:

1. **Imports** (via integrations or imports module)
2. Upload CSV with column headers matching campaign field names
3. Map `sid` and `campaign_reference` in import config
4. Import runs asynchronously with progress and error report

Imports respect the same validation, dedupe, and quarantine rules as API ingest.

## Admin debugging

### API Spec page

1. Open campaign → **API Spec** (`/campaigns/{id}/api-spec`)
2. Review required fields, ping field mapping, and example payload
3. Load vertical template or premade template for buyer contract alignment

### API request logs

1. **Logs** → **API** (`/logs/api`)
2. Filter by status code, API key, or date
3. Inspect request body and response for 422 validation failures

## Error responses

| HTTP | Meaning |
|------|---------|
| `401` | Missing, invalid, or revoked API key |
| `403` | Key not permitted for this action or tenant |
| `404` | Unknown `campaign_reference` |
| `422` | Validation failed — see `errors` object for field messages |
| `400` | Malformed JSON body |

### 422 example

```json
{
  "message": "The given data was invalid.",
  "errors": {
    "fields.email": ["The email field is required."],
    "fields.phone1": ["Invalid phone number format."]
  }
}
```

## Troubleshooting

| Symptom | Likely cause | Fix |
|---------|--------------|-----|
| 401 Unauthorized | Bad token or wrong header format | `Bearer {token}` with space; regenerate key |
| 422 on email/phone | Schema mismatch | Compare to API Spec; check required flags |
| 202 but never completes | Queue worker down | Run Horizon / `queue:work` |
| Lead rejected as duplicate | Dedupe window hit | Check campaign dedupe settings; use new test email |
| Unknown campaign | Typo in reference | Copy reference from campaign show page |
| Supplier 403 | Key scoped to different supplier | Use supplier key or admin key |
| Slow sync timeout | Deep ping tree | Switch to async ingest |

## Tips

- Map buyer API fields using **Campaign → API Spec** — share this page with integration partners
- Log sample payloads in **API request logs** for debugging supplier issues
- Use built-in ping/post simulators (`/api/v1/ping`, `/api/v1/post`) during development
- Load `/sdk/pbe-leads.js` for browser or Node ESM integrations
- Document `campaign_reference` and `sid` in supplier onboarding packs
MD,
];
