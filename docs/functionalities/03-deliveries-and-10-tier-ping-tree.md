# 03 - Deliveries & 10-Tier Ping Tree

## Purpose

**Deliveries** define how leads reach buyers: HTTP ping-post, direct post, store in-platform, email, or SMS. Each delivery has a **tier** number (1–10) used for ping-tree routing and performance reporting. The seeded **Auto Insurance** campaign includes a full **10-tier enterprise ping tree** with one delivery per tier and varied routing modes. This doc covers the 6-step delivery wizard, delivery testing, and verifying 10-tier performance data.

---

## Where to Find It

| Item | Location |
|------|----------|
| Delivery list | `/deliveries` |
| Create delivery | `/deliveries/create` |
| Edit delivery | `/deliveries/{id}/edit` |
| Delivery detail | `/deliveries/{id}` |
| Test delivery | POST `/deliveries/{id}/test` (button on detail page) |
| Clone delivery | POST `/deliveries/{id}/clone` |
| 10-tier report | `/reports` → "10-tier ping tree performance" panel |
| Navigation | Sidebar → **Tools** → **Automation** → Deliveries |
| Access | Account Admin |

---

## Delivery Methods

| Method | Description |
|--------|-------------|
| `store_lead` | Assign lead to buyer in-platform (buyer portal) |
| `direct_post` | Single HTTP POST with full lead data |
| `ping_post` | Two-phase: ping (partial fields) then post (full data) |
| `email_ping_post` | Email-based ping-post variant |
| `email` | Send lead details via email template |
| `sms` | Send SMS template (logs in demo) |

---

## Seeded 10-Tier Structure (Auto Insurance)

After `migrate:fresh --seed`, the **Auto Insurance** campaign has 10 tier deliveries plus originals:

| Tier | Name pattern | Method | Routing mode in ping tree |
|------|-------------|--------|---------------------------|
| 1–3 | Tier N - Parallel auction | Ping-post | `parallel_auction` |
| 4 | Tier 4 - Waterfall | Ping-post | `waterfall` |
| 5 | Tier 5 - Parallel auction | Ping-post | `parallel_auction` |
| 6 | Tier 6 - Weighted | Ping-post | `weighted` |
| 7 | Tier 7 - Waterfall | Ping-post | `waterfall` |
| 8 | Tier 8 - Parallel auction | Ping-post | `parallel_auction` |
| 9 | Tier 9 - Sequential ping | Ping-post | `sequential_ping` |
| 10 | Tier 10 - Waterfall | Store lead | `waterfall` (fallback) |

Active distribution config: **10-Tier Enterprise Ping Tree** (replaces inactive Hybrid Ping Tree).

---

## How to Test (Step-by-Step)

### 1. List and filter deliveries

1. Navigate to `/deliveries`
2. Use filters: vertical, campaign, method, status, buyer, search

**Expected:** Many deliveries for Auto Insurance campaign. Filter by vertical **Auto Insurance** narrows list. Status badges show active/inactive.

### 2. Review a ping-post delivery (Tier 1)

1. Open **Tier 1 - Parallel auction** delivery
2. Confirm ping URL points to `/api/v1/ping` and post URL to `/api/v1/post`
3. Check revenue type: **Dynamic** with `Cost` field
4. Note `bid_hint` for auction demos
5. Confirm **Advanced distribution only** is checked

**Expected:** Detail page shows ping-tree links (tier 1, config name). Analytics section may show attempt/success counts from seed.

### 3. Walk through the 6-step delivery wizard (create)

1. Go to `/deliveries/create`
2. **Step 1 - Basics:** name, campaign, buyer, status active
3. **Step 2 - Method:** select **Ping Post**
4. **Step 3 - Settings:** enter simulator URLs (`/api/v1/ping`, `/api/v1/post`)
5. **Step 4 - Routing:** set tier `3`, priority `30`, check advanced distribution only
6. **Step 5 - Pricing:** select Dynamic, revenue field `Cost`
7. **Step 6 - Caps:** optional daily cap `50`
8. Submit

**Expected:** Delivery created. Appears in list. Can be added to a ping tree tier in `/distribution`.

### 4. Test a delivery

1. Open any active ping-post delivery
2. Click **Test delivery** button
3. Review test result in flash or response panel

**Expected:** Test fires ping (and post if ping succeeds). Success or structured failure message. No real buyer endpoint needed when using built-in simulators.

### 5. Clone a delivery

1. On delivery detail, click **Clone**
2. Confirm new delivery created with copied config

**Expected:** Clone appears in list with "(copy)" or similar naming. Independent ID; edits don't affect original.

### 6. Verify 10-tier performance in Reports

1. Navigate to `/reports`
2. Scroll to **10-tier ping tree performance** panel
3. Review per-tier: attempts, wins, outbid, rejections

**Expected:** Tiers 1–10 listed with non-zero attempt counts from historical seed. Tier 10 (store lead) shows successes as fallback wins.

### 7. End-to-end sync API lead

Submit a unique sync lead to `auto-insurance-uk` (see [09-api-and-sdk.md](./09-api-and-sdk.md)), then check `/operations` delivery logs.

**Expected:** Lead status `sold`. Multiple tier log entries (outbid/skipped then success). Revenue recorded.

---

## Expected Results (Summary)

- 6-step wizard covers basics, method, config, routing, pricing, caps
- Tier field (1–10) aligns with ping-tree groups and reports
- Ping-post deliveries use built-in simulators in demo
- 10-tier enterprise config is active on Auto Insurance
- Delivery test button validates connectivity without ingesting a lead
- Reports tier summary matches delivery `tier` column

---

## Edge Cases

| Scenario | Expected behaviour |
|----------|-------------------|
| Inactive delivery | Skipped during distribution |
| Insufficient buyer credit | Delivery skipped with `insufficient_credit` reason |
| Ping below floor price | Tier fails; cascade to next tier |
| All 10 tiers fail | Lead status `unsold` or `quarantined` |
| Delivery not in ping tree group | Ignored when advanced distribution enabled |
| `advanced_distribution_only` unchecked | Delivery also eligible for standard waterfall |
| Direct post timeout | Logged as `failed` with duration_ms |
| Duplicate email (dedupe) | Lead rejected before any delivery attempted |

---

## Related Docs

- [04-distribution-ping-tree.md](./04-distribution-ping-tree.md) - tier group configuration
- [05-reports.md](./05-reports.md) - tier performance analytics
- [09-api-and-sdk.md](./09-api-and-sdk.md) - lead ingest
