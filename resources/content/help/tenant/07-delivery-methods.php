<?php

return [
    'category' => 'Delivery',
    'slug' => 'delivery-methods',
    'title' => 'Delivery Methods & Ping Tree',
    'summary' => 'Direct post, ping-post, email, SMS, store lead, and tier configuration.',
    'audience' => 'tenant',
    'sort_order' => 70,
    'body' => <<<'MD'
## Overview

**Deliveries** connect buyers to campaigns with a **method** (how data is sent) and **config** (URLs, timeouts, revenue rules). **Distribution configs** (ping trees) define which deliveries run, in what order, and with what filters.

A campaign without an active delivery on an active distribution config will produce **unsold** leads.

## Delivery methods

| Method | Flow | Best for |
|--------|------|----------|
| **Direct POST** | Full lead POST to buyer URL in one step | Simple API buyers |
| **Ping-Post** | Ping partial data → accept + price → post full record | Auction and waterfall buyers |
| **Store Lead** | Internal store; buyer pulls from portal | Buyers without real-time API |
| **Email** | Notification email with lead data | Low-tech buyers |
| **SMS** | Text notification | Urgent callbacks |
| **Email Ping-Post** | Email accept/reject links before full send | Legacy buyers |

## Create a delivery — step by step

1. **Deliveries** → **New** (`/deliveries/create`) or campaign show → **Add delivery**
2. Select **Campaign** and **Buyer**
3. Choose **Method**
4. Configure method-specific settings:

### Ping-Post configuration

| Field | Typical value |
|-------|---------------|
| `ping_url` | Buyer ping endpoint |
| `post_url` | Buyer post endpoint |
| `ping_timeout` | `2000` ms (2 seconds) |
| `post_timeout` | `3000` ms (3 seconds) |
| `success_rule` | JSON path or keyword matching buyer response |
| `revenue_type` | `fixed`, `dynamic`, or `rule` |
| `revenue_amount` | Fixed price if not dynamic |

### Direct POST configuration

| Field | Notes |
|-------|-------|
| `post_url` | Single endpoint |
| `post_timeout` | `5000` ms default |
| `headers` | Custom auth headers if needed |

5. Save delivery
6. Click **Test delivery** (`POST /deliveries/{id}/test`) to fire simulator request

### Built-in simulators (staging)

| Endpoint | Auth | Response |
|----------|------|----------|
| `POST /api/v1/ping` | None | `{ "Success": true, "Cost": 18 }` |
| `POST /api/v1/post` | None | `{ "Success": true, "Approved": true }` |

Use these URLs during QA before buyer endpoints are ready.

## Ping tree (distribution config)

Distribution configs define ordered **tiers**. Each tier contains one or more **deliveries** with priority, filters, and optional parallel ping groups.

### Create distribution — admin UI

1. **Routing / Distribution** → **New** (`/distribution/create`)
2. Or campaign show → **Ping tree** link with `?campaign_id={id}`
3. Set:

| Field | Description |
|-------|-------------|
| **Name** | e.g. `10-Tier Enterprise Ping Tree` |
| **Campaign** | Target campaign |
| **Mode** | Waterfall or auction |
| **is_active** | Only one active config per campaign recommended |

4. Add tiers (1, 2, 3 …)
5. Within each tier, assign deliveries and set **priority** (lower = first)
6. **Activate** config

### Tier structure

```
Tier 1 (premium buyers)
  ├── Delivery A (priority 1) — filters: state=TX
  └── Delivery B (priority 2) — filters: state=CA

Tier 2 (secondary buyers)
  └── Delivery C (priority 1) — no filters

Tier 3 (remainder)
  └── Delivery D — store lead fallback
```

### Tier filters

Restrict which leads reach a delivery within a tier:

| Filter type | Example |
|-------------|---------|
| **State / region** | `state IN [TX, FL]` |
| **Postcode prefix** | `zipcode starts_with SW1` |
| **Numeric range** | `loan_amount >= 5000` |
| **Field equals** | `homeowner = yes` |

Skipped deliveries log status `skipped` — this is normal waterfall behaviour, not an error.

### Auction mode

In auction tiers, eligible deliveries ping **in parallel**. Highest bid above floor price wins. Losers log `outbid`.

| Setting | Effect |
|---------|--------|
| **Parallel group** | Deliveries ping simultaneously |
| **Floor price** | Campaign-level minimum bid |
| **Timeout** | Shortest ping timeout in group wins timing |

## Delivery log outcomes

View at **Logs → Delivery** (`/logs/delivery`):

| Log status | Meaning |
|------------|---------|
| **ping_ok** | Buyer accepted ping with bid |
| **success** | Post completed successfully |
| **skipped** | Filter, cap, or eligibility excluded this delivery |
| **outbid** | Lost auction to higher bid |
| **failed** | HTTP error, timeout, or buyer post rejection |
| **cap_hit** | Buyer or delivery cap reached |

### Inspect a delivery attempt

1. **Logs → Delivery** → click log row (`/logs/delivery/{id}`)
2. Review:

| Section | Content |
|---------|---------|
| **Request** | URL, payload sent, headers |
| **Response** | HTTP status, body snippet |
| **Duration** | Ping/post milliseconds |
| **Revenue** | Parsed bid or fixed amount |

## Revenue configuration

| Type | Behaviour |
|------|-----------|
| **fixed** | Constant revenue per sold lead |
| **dynamic** | Parse bid from ping response (`Cost`, `price`, etc.) |
| **rule** | Expression-based revenue calculation |

Revenue feeds buyer billing (prepay debit) and financial reports.

## Routing Simulator

Dry-run tier decisions without ingesting a lead:

1. Open **Routing Simulator** (`/routing/simulator`)
2. Select campaign and distribution config
3. Paste sample field JSON matching campaign schema
4. Click **Run**
5. Review which tiers and deliveries would fire, and which filters would skip

Use before go-live and after any tier filter change.

## Clone and test utilities

| Action | Path |
|--------|------|
| **Clone delivery** | Delivery show → Clone — duplicates config to new row |
| **Test delivery** | Fires test ping/post to configured URLs |
| **Reprocess lead** | Lead detail → re-run pipeline through current tree |

## Troubleshooting

| Symptom | Likely cause | Fix |
|---------|--------------|-----|
| All leads unsold | No active distribution | Activate config on campaign |
| ping_ok but no success | Post URL wrong or timeout | Check post log; increase timeout |
| Everything skipped | Tier filters too strict | Simulator → review filter match |
| failed on all tiers | Buyer API down | Test URL with curl; contact buyer |
| outbid only in auction | Floor too high | Lower floor or adjust buyer bids |
| Buyer never pinged | Cap or prepay ineligible | Check buyer credit and caps |
| Slow pipeline | Too many tiers / long timeouts | Reduce tiers; tighten ping timeout to 2s |

## Recommended timeouts

| Phase | Baseline |
|-------|----------|
| Ping | 2000 ms |
| Post | 3000 ms |
| Email | N/A (async) |

Increase only if buyer SLA requires — long timeouts delay unsold fallback.

## Tips

- Use **Routing Simulator** before go-live and after every filter change
- Set ping timeout 2s and post timeout 3s as baselines
- Always attach at least one **store lead** or email fallback tier for unsold recovery
- Name deliveries clearly: `{Buyer} — {Method} — {Tier}` aids log debugging
- Clone working deliveries across campaigns instead of retyping URLs
MD,
];
