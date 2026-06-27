<?php

return [
    'category' => 'Partner Platform',
    'slug' => 'quick-start',
    'title' => 'Quick Start Guide',
    'summary' => 'Launch your first campaign and sell a test lead in under 30 minutes.',
    'audience' => 'tenant',
    'sort_order' => 20,
    'body' => <<<'MD'
## Overview

This guide walks through a **minimal production-ready setup**: one campaign, one buyer, one supplier, distribution routing, and a test ingest. Follow the steps in order - each builds on the previous.

**Time estimate:** 20–30 minutes if you have admin access and queue workers running.

**Prerequisites:**

- Logged in on your tenant subdomain as **account_admin**
- Queue worker or Horizon running (`php artisan queue:work`)
- For sync testing only: workers optional if you pass `"sync": true`

## Step 1 - Create a campaign

### Admin UI

1. Navigate to **Campaigns** → **New** (`/campaigns/create`)
2. Fill in the form:

| Field | Example value |
|-------|---------------|
| **Name** | `QA Quick Start` |
| **Reference** | `qa-quick-start` |
| **Vertical** | Loans (or your target vertical) |
| **Country / Currency** | GB / GBP |
| **Floor price** | `10.00` |
| **Payout** | `5.00` |
| **Bidding mode** | Real-time auction (if available) |
| **Advanced distribution** | Enabled |

3. Click **Create campaign**
4. On the campaign **show page**, confirm vertical fields were auto-seeded (e.g. `loan_amount`, `loan_purpose` for Loans)
5. Set status to **Active** if not already active

### Verify fields

1. Scroll to **Campaign fields** on the show page
2. Ensure these exist and are marked **required** where needed:
   - `firstname`, `lastname`, `email`, `phone1`, `zipcode`
3. Note which fields are **ping fields** - these are sent during ping-post, not the full post

## Step 2 - Create a buyer

### Admin UI

1. Go to **Buyers** → **New** (`/buyers/create`)
2. Enter:

| Field | Example |
|-------|---------|
| **Name** | `QA Primary Buyer` |
| **Reference** | `qa-primary-buyer` |
| **Email** | `buyer-qa@yourdomain.test` |
| **Status** | Active |

3. Save and open the buyer **show page**

### Billing (if prepay enabled)

1. If **Platform settings** has `require_buyer_prepay` enabled:
   - Go to **Billing** → select the buyer → **Top up**
   - Add credit (e.g. `500.00`) with description `QA setup`
2. Confirm `credit_balance` shows on buyer detail

### Optional: buyer portal user

1. On buyer edit form, add **Portal access** email and password
2. Buyer can later log in at `/portal/buyer`

## Step 3 - Add a delivery

1. From campaign show page, click **Add delivery** (or **Deliveries** → **New** with `?campaign_id={id}`)
2. Configure:

| Setting | Recommended for QA |
|---------|-------------------|
| **Buyer** | QA Primary Buyer |
| **Method** | Ping-Post (or Direct POST for simpler test) |
| **Name** | `QA Ping-Post` |
| **Ping URL** | `https://your-tenant.test/api/v1/ping` (built-in simulator) |
| **Post URL** | `https://your-tenant.test/api/v1/post` (built-in simulator) |
| **Revenue type** | Fixed |
| **Revenue amount** | `15.00` |
| **Ping timeout** | `2000` ms |
| **Post timeout** | `3000` ms |

3. Save delivery
4. Optional: click **Test delivery** on delivery show page to fire a dry-run ping/post

Built-in simulators (`POST /api/v1/ping` and `POST /api/v1/post`) require **no authentication** and return success responses for staging.

## Step 4 - Create supplier and API key

### Supplier

1. **Suppliers** → **New** (`/suppliers/create`)
2. Complete wizard steps:

| Step | Action |
|------|--------|
| **Basics** | Reference `qa-supplier`, name `QA Supplier` |
| **Affiliate** | Set `rev_share_percent` if needed; optional `default_postback_url` |
| **Traffic sources** | Add SID `test_source`, name `Test Source` |
| **Portal access** | Optional portal user |

3. Save supplier

### API key

1. Go to **API Keys** (`/api-keys`)
2. Click **New API key**
3. Configure:

| Field | Value |
|-------|-------|
| **Name** | `QA Supplier Key` |
| **Type** | Supplier (scoped to QA Supplier) |
| **Permissions** | `leads.create`, `leads.read` |

4. **Copy the token immediately** - it is shown only once
5. Optional: set IP allowlist for production keys

## Step 5 - Configure distribution

1. Open **Routing / Distribution** (`/distribution/create?campaign_id={id}`) or from campaign show → **Ping tree**
2. Create a distribution config:

| Setting | Value |
|---------|-------|
| **Name** | `QA Single Tier` |
| **Campaign** | QA Quick Start |
| **Mode** | Waterfall or auction (match campaign bidding mode) |

3. Add **Tier 1**:
   - Assign **QA Ping-Post** delivery
   - Set priority `1`
   - Leave tier filters empty for first test (accept all leads)
4. **Activate** the distribution config
5. Deactivate any conflicting configs on the same campaign

### Optional: tier filters

If testing geo routing, add a filter on `zipcode` or `state` - see the **Tier Filters** help article.

## Step 6 - Test ingest

### cURL example (sync - immediate result)

```bash
curl -X POST "https://your-tenant.test/api/v1/leads" \
  -H "Authorization: Bearer YOUR_API_KEY" \
  -H "Content-Type: application/json" \
  -d '{
    "campaign_reference": "qa-quick-start",
    "sid": "test_source",
    "sync": true,
    "fields": {
      "firstname": "Test",
      "lastname": "Lead",
      "email": "test@example.com",
      "phone1": "07700900123",
      "zipcode": "SW1A 1AA",
      "loan_amount": "5000"
    }
  }'
```

### Async example (production pattern)

Omit `sync` - expect HTTP **202**:

```json
{
  "status": "queued",
  "queue_id": "q_abc123",
  "lead_id": "ld_xyz789"
}
```

Poll status:

```bash
curl "https://your-tenant.test/api/v1/leads/queue/q_abc123" \
  -H "Authorization: Bearer YOUR_API_KEY"
```

### JavaScript SDK

```javascript
import { createClient } from '/sdk/pbe-leads.js';

const pbe = createClient({
  apiKey: 'YOUR_API_KEY',
  baseUrl: 'https://your-tenant.test/api/v1',
});

const result = await pbe.ingestLead({
  campaign_reference: 'qa-quick-start',
  sid: 'test_source',
  sync: true,
  fields: {
    firstname: 'Test',
    lastname: 'Lead',
    email: 'test@example.com',
    phone1: '07700900123',
    zipcode: 'SW1A 1AA',
  },
});
```

## Step 7 - Verify results

### Leads pipeline

1. Open **Leads** (`/leads`) or **Operations** (`/operations`)
2. Find your test lead by email or UUID
3. Expected final status: **sold** (simulator accepts ping and post) or **unsold** if no buyer matched

| Status | Meaning |
|--------|---------|
| `sold` | Buyer won; financials recorded |
| `unsold` | No buyer accepted after full waterfall |
| `rejected` | Validation or dedupe failed |
| `quarantined` | Held for manual review |
| `pending` | Still queued - check workers |

### Delivery logs

1. **Logs** → **Delivery** (`/logs/delivery`)
2. Filter by campaign or lead UUID
3. Expect sequence: `ping_ok` → `success` for ping-post simulators

### Reports and finance

1. **Reports** (`/reports`) - revenue and margin for today
2. **Finance** (`/finance`) - account-level summary
3. If prepay enabled: **Billing** → buyer transactions show debit for sold lead

## Troubleshooting

| Problem | Check |
|---------|-------|
| HTTP 401 | API key missing, revoked, or wrong tenant |
| HTTP 422 | Required field missing - compare payload to **Campaign → API Spec** |
| HTTP 403 | Supplier key posting outside its scope |
| Lead stays `pending` | Queue worker not running |
| Lead `unsold` | Distribution inactive, delivery filtered, or buyer ineligible (no credit) |
| Ping `failed` | Ping URL unreachable; timeout too low; buyer API down |
| Unknown SID | Create source in supplier form - unknown SIDs may still ingest but skew reporting |

## Tips

- Use **Routing Simulator** (`/routing/simulator`) before go-live - paste sample field JSON and preview tier decisions
- Start with `sync: true` in **staging only** - production should use async ingest at volume
- Clone this setup from seeded UK data (`auto-insurance-uk`) if you want a working reference before building from scratch
- Document your `campaign_reference` and `sid` values for suppliers - they are required on every API post
MD,
];
