<?php

return [
    'category' => 'Campaigns',
    'slug' => 'campaign-setup',
    'title' => 'Campaign Configuration',
    'summary' => 'Fields, caps, sell modes, verticals, and campaign lifecycle.',
    'audience' => 'tenant',
    'sort_order' => 60,
    'body' => <<<'MD'
## Overview

Campaigns define **what** you sell: field schema, pricing, validation, dedupe, caps, and distribution attachment. Every lead ingested via API, hosted form, or import must target an active campaign by `campaign_reference`.

Think of a campaign as the contract between your suppliers (what data they send) and your buyers (what data you ping/post).

## Key settings

| Setting | Purpose | Locked after first lead? |
|---------|---------|--------------------------|
| **reference** | Unique slug used in API ingest (`auto-insurance-uk`) | Often yes |
| **vertical** | Vertical catalog - seeds default fields and floor prices | Varies |
| **currency** | Financial reporting currency | Often yes |
| **payout** | Default supplier payout basis | No |
| **floor_price** | Minimum acceptable buyer bid in ping trees | No |
| **sell_mode** | `exclusive`, `shared`, etc. | No |
| **bidding_mode** | Waterfall vs real-time auction | No |
| **caps** | Daily/monthly lead limits | No |
| **use_advanced_distribution** | Enable multi-tier ping tree | No |

## Create a campaign - step by step

1. Navigate to **Campaigns** → **New** (`/campaigns/create`)
2. Complete the form:

| Field | Guidance |
|-------|----------|
| **Name** | Internal display name (e.g. `Auto Insurance UK`) |
| **Reference** | API slug - lowercase, hyphens, no spaces |
| **Vertical** | Select from catalog - auto-creates field template |
| **Country** | Drives phone/postcode validation rules |
| **Currency** | GBP, USD, CAD, EUR |
| **Floor price** | Minimum ping bid you'll accept |
| **Payout** | Default amount owed to supplier on sold lead |
| **Bidding mode** | Waterfall (first accept wins) or auction (highest bid) |

3. Submit - redirect to campaign **show page**
4. Confirm vertical fields appeared (e.g. `vehicle_year` for Auto Insurance)
5. Set status to **Active**

## Campaign fields

Fields define the ingest schema and ping-post parameter mapping.

### Field types

| Type | Validation |
|------|------------|
| `text` | Free text |
| `email` | RFC format |
| `phone` | libphonenumber for campaign country |
| `postcode` | Country-specific postcode/ZIP rules |
| `select` | Enumerated options |
| `number` | Numeric, optional min/max |

### Admin UI - manage fields

Fields are auto-seeded from the vertical on create. To customise:

1. Campaign show page → **Fields** section
2. Add field:

| Property | Notes |
|----------|-------|
| **Name** | API key (e.g. `loan_amount`) - match buyer contracts exactly |
| **Label** | Display label for forms |
| **Type** | See table above |
| **Required** | API 422 if missing |
| **Ping field** | Included in ping request (not full post) |
| **Sort order** | Form and API spec display order |

3. Save - changes apply to new ingests immediately

### Ping fields vs post fields

| Category | Sent when | Examples |
|----------|-----------|----------|
| **Ping fields** | Ping request only | `zipcode`, `state`, `loan_amount` |
| **All fields** | Full post after ping accept | Includes PII: name, email, phone |

Buyers often bid on partial data - mark only non-PII fields as ping fields unless buyer contract requires otherwise.

## Caps

Limit volume to protect buyer contracts and margin.

| Cap type | Scope |
|----------|-------|
| **Daily** | Resets midnight account timezone |
| **Monthly** | Calendar month |
| **Hourly** | Rolling or clock-hour |
| **Total** | Lifetime campaign cap |

### Configure caps

1. Campaign edit form → **Caps** section
2. Set limits per period
3. When cap hit, new ingests receive `rejected` with cap reason

Monitor cap usage on campaign show page stats (**leads today**, etc.).

## API spec

**Campaign → API Spec** (`/campaigns/{id}/api-spec`) documents the integration contract.

### Admin workflow

1. Open API Spec from campaign show page
2. Review auto-generated field list and example JSON
3. Optional actions:

| Action | Purpose |
|--------|---------|
| **Load vertical template** | Reset to vertical defaults |
| **Load premade template** | Industry-standard buyer mapping |
| **Apply to form** | Sync hosted form fields from spec |

4. Customise ping/post parameter names if buyer API uses different keys
5. Save - share URL or export with integration partners

Tier filters in distribution use the **same field names** as campaign fields.

## Sell modes and bidding

| Mode | Behaviour |
|------|-----------|
| **Exclusive** | One buyer wins; lead not re-sold |
| **Shared** | Multiple buyers may purchase (if configured) |
| **Waterfall** | Tiers tried in order until sold |
| **Real-time auction** | Parallel pings; highest bid wins |

Match `bidding_mode` on campaign to distribution config mode. Mismatch causes unexpected `unsold` results.

## Campaign lifecycle

| Status | Ingest behaviour |
|--------|------------------|
| **Draft** | Typically rejects or hidden from suppliers |
| **Active** | Accepts ingest |
| **Paused** | Rejects new ingest at pipeline start |
| **Archived** | Historical only - no new leads |

### Pause a campaign

1. Campaign edit → set status **Paused**
2. Save - API returns rejection for new posts
3. In-flight queued leads may still process

### Delete

Deletion is blocked if leads exist - archive instead.

## Seeded reference campaigns (UK demo)

| Name | Reference | Vertical |
|------|-----------|----------|
| Auto Insurance | `auto-insurance-uk` | Auto Insurance |
| Loans | `loans-uk` | Loans |
| Mortgage | `mortgage-uk` | Mortgage |
| Payday Loans | `payday-loans-uk` | Payday Loans |
| Solar | `solar-uk` | Solar |

Use these as templates - open show page and inspect fields, deliveries, and distribution.

## Validation panel

On campaign show page:

1. Toggle `require_email`, `require_phone`, `block_disposable_email`
2. Save validation - see **Validation, Dedupe & Quarantine** help article

## Troubleshooting

| Symptom | Cause | Fix |
|---------|-------|-----|
| 422 on ingest | Missing required field | API Spec → check required flags |
| All leads unsold | Campaign paused or no distribution | Activate campaign and ping tree |
| Wrong fields on ping | Ping flags incorrect | Mark correct fields as ping fields |
| Can't edit reference | Locked after first lead | Create new campaign; migrate suppliers |
| Floor price too high | No bids above floor | Lower floor or negotiate buyer contracts |
| Cap rejections | Daily cap hit | Raise cap or wait for reset |

## Tips

- Clone seeded campaigns as starting points before building from scratch
- Match field names to buyer contracts **exactly** - renaming after go-live breaks mappings
- Set floor price slightly below expected buyer bids to avoid unnecessary `unsold`
- Enable `use_advanced_distribution` for any campaign with more than one buyer tier
- Review **leads today** on show page daily during launch week
MD,
];
