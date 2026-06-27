# 04 - Distribution Ping Tree

## Purpose

The **Distribution Ping Tree** is PowerByExcellence's advanced routing engine. While individual deliveries define *how* to reach a buyer, distribution configs define *which* deliveries run, in *which tier order*, and with *which routing mode* (waterfall, parallel auction, weighted, round-robin, sequential ping). When a campaign has advanced distribution enabled, leads flow through tier groups until sold or exhausted.

---

## Where to Find It

| Item | Location |
|------|----------|
| Ping tree list | `/distribution` |
| Create config | `/distribution/create` |
| Edit config | `/distribution/{id}/edit` |
| Visual tree view | `/distribution/{id}` (show page) |
| Routing simulator | `/routing/simulator` |
| Campaign link | `/campaigns/{id}` → Distribution configs section |
| Navigation | Sidebar → **Tools** → **Automation** → Ping Tree |
| Access | Account Admin |

---

## Routing Modes

| Mode | Behaviour |
|------|-----------|
| `waterfall` | Try deliveries in priority order until one sells |
| `parallel_auction` | Ping all deliveries simultaneously; highest bid ≥ floor wins |
| `sequential_ping` | Ping deliveries one-by-one in priority order |
| `weighted` | Random selection weighted by delivery weight |
| `round_robin` | Rotate fairly between deliveries |

---

## Seeded Configs (Auto Insurance)

| Config name | Active | Tiers |
|-------------|--------|-------|
| 10-Tier Enterprise Ping Tree | Yes | 10 groups (tiers 1–10) |
| Hybrid Ping Tree | No | 2 groups (auction + waterfall fallback) |

---

## How to Test (Step-by-Step)

### 1. List distribution configs

1. Navigate to `/distribution`
2. Review list: name, campaign, tier count, active status

**Expected:** At least two configs for Auto Insurance. **10-Tier Enterprise Ping Tree** marked active. Tier badges show mode per group (parallel_auction, waterfall, etc.).

### 2. Visual tree view

1. Click **10-Tier Enterprise Ping Tree** to open show page
2. Scroll through visual tier diagram

**Expected:** 10 tier cards stacked vertically. Each shows: tier number, name, routing mode, floor price, assigned delivery node(s). Arrow or connector between tiers. Bottom shows "No tier accepts → unsold" outcome.

### 3. Edit ping tree - add a tier

1. Click **Edit** on Hybrid Ping Tree (inactive)
2. Click **Add tier**
3. Configure:
   - Name: `Tier 3 - Test Waterfall`
   - Mode: Waterfall
   - Floor price: `8`
   - Deliveries: select **Store Lead (Fallback)**
4. Save

**Expected:** Config updated. Show page reflects new tier. Success flash message.

### 4. Remove a tier

1. On edit form with 3+ tiers, click **Remove tier** on the last tier
2. Save

**Expected:** Tier removed from config. Minimum one tier required.

### 5. Switch active config

1. Edit **Hybrid Ping Tree** → set active
2. Edit **10-Tier Enterprise** → set inactive
3. Submit a sync API lead to Auto Insurance
4. Observe routing in `/operations`

**Expected:** Lead routes through 2-tier hybrid (auction then store fallback) instead of 10 tiers. **Restore 10-tier as active after test.**

### 6. Create new ping tree for another campaign

1. `/distribution/create`
2. Select campaign: **Loans**
3. Name: `Loans Standard Tree`
4. Add Tier 1: parallel_auction, assign ping-post deliveries
5. Add Tier 2: waterfall, assign store lead delivery
6. Mark active, save

**Expected:** New config appears in list. Visible on Loans campaign show page.

### 7. Routing simulator

1. Navigate to `/routing/simulator`
2. Select campaign: Auto Insurance
3. Enter sample lead fields (zipcode, vehicle_year)
4. Click **Run simulation**

**Expected:** Simulator shows predicted tier path and delivery outcomes without creating a real lead. Useful for demo walkthrough.

### 8. Delete distribution config

1. Delete the test Loans config created in step 6
2. Confirm

**Expected:** Config removed. Campaign show page no longer lists it.

---

## Expected Results (Summary)

- Visual show page renders all tiers with delivery nodes and floor prices
- Only one config should be active per campaign at a time (verify behaviour when multiple active)
- Tier order is top-to-bottom: tier 1 attempted first
- Parallel auction tier pings all assigned deliveries before picking winner
- Campaign must have `use_advanced_distribution` enabled for ping tree to run
- Routing simulator provides dry-run without side effects

---

## Edge Cases

| Scenario | Expected behaviour |
|----------|-------------------|
| Empty delivery_ids on a tier | Tier skipped or error on save |
| Floor price higher than all bids | Tier fails; cascade continues |
| Single delivery in auction tier | That delivery wins if ping succeeds |
| Tier 10 store lead fallback | Catches leads no buyer bid on |
| Config inactive | Campaign falls back to standard priority routing |
| Delivery in tier but inactive status | Skipped during execution |
| Weighted mode with one delivery | Always selects that delivery |
| Edit config while leads processing | In-flight leads use config at processing time |

---

## Related Docs

- [03-deliveries-and-10-tier-ping-tree.md](./03-deliveries-and-10-tier-ping-tree.md) - delivery setup per tier
- [02-campaigns-and-verticals.md](./02-campaigns-and-verticals.md) - enable advanced distribution
- [12-operations-and-logs.md](./12-operations-and-logs.md) - live delivery logs
