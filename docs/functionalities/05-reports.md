# 05 - Reports

## Purpose

The Reports dashboard provides admin analytics across lead volume, conversion, revenue, buyer/supplier performance, delivery outcomes, and **10-tier ping tree** statistics. It complements the high-level dashboard with a 28-day (configurable) analytical view and tables suitable for demo presentations and QA validation of seeded historical data.

---

## Where to Find It

| Item | Location |
|------|----------|
| Admin reports UI | `/reports` |
| API - lead report | `GET /api/v1/reports/leads` |
| API - revenue report | `GET /api/v1/reports/revenue` |
| Navigation | Top bar → **Reports** |
| Access | Account Admin (UI); API requires `reports.read` permission |

---

## Report Sections

| Section | Data shown |
|---------|------------|
| KPI strips | Volume, economics (EPL/EPC/CPA), rates, lead status, delivery outcomes - compact horizontal rows (8 columns) |
| Trend charts | Daily leads, sold, rejected, revenue over selected period |
| Top buyers | Lead count and revenue by buyer (top 10) |
| Top suppliers | Lead count and payout by supplier (top 10) |
| Delivery performance | Per-delivery attempts, successes, outbid, rejections, revenue |
| 10-tier ping tree | Per-tier attempts, wins, outbid, rejections |
| Distribution outcomes | Breakdown by delivery log status |

---

## How to Test (Step-by-Step)

### 1. Load reports with default period

1. Log in as `uk@powerbyexcellence.test`
2. Navigate to `/reports`

**Expected:** Page loads with multiple **compact KPI strips** at the top. Default period: 28 days. Values non-zero from seeded historical data. Currency matches active tenant.

### 2. Change date range

1. Select **7 days** from period filter (if available)
2. Wait for page reload

**Expected:** Charts and tables recalculate. Totals decrease vs 28-day view. Labels show 7 day names.

Repeat for 14 and 30 day options.

**Expected:** Only valid periods (7, 14, 28, 30) accepted. Invalid values default to 28.

### 3. Verify trend charts

1. Inspect the multi-series chart: leads, sold, rejected, revenue
2. Hover tooltips on data points

**Expected:** 28 data points for default period. Sold line correlates with revenue bars. Rejected shows ~12% of leads from seed weights.

### 4. Verify top buyers table

1. Scroll to **Top buyers**
2. Confirm **Primary Buyer** and **Secondary Buyer** appear
3. Check lead counts and revenue sums

**Expected:** Primary buyer typically leads on revenue. Currency in GBP (£).

### 5. Verify top suppliers table

1. Scroll to **Top suppliers**
2. Confirm **Main Supplier** listed with payout totals

**Expected:** Non-zero payout reflecting sold lead financials.

### 6. Verify delivery performance

1. Review **Delivery performance** table
2. Sort mentally by tier column
3. Confirm ping-post and store_lead methods appear

**Expected:** Tier 1–10 deliveries from Auto Insurance campaign listed. Columns: attempts, successes, outbid, rejections, revenue. Methods match delivery configuration.

### 7. Verify 10-tier ping tree panel

1. Locate **10-tier ping tree performance** panel
2. Review each tier row (1 through 10)

**Expected:**
- All 10 tiers present
- Attempts > 0 from historical seed
- Lower tiers show higher outbid counts (leads cascade down)
- Tier 10 shows wins from store-lead fallback
- Panel title/description references Auto Insurance campaign

### 8. Verify distribution outcome breakdown

1. Find status breakdown: `success`, `outbid`, `failed`, `skipped`, etc.

**Expected:** `outbid` is significant (auction losers). `success` present. Totals align with delivery log seed.

### 9. API reports (optional)

```bash
curl "https://powerbyexcellence.test/api/v1/reports/leads?days=7" \
  -H "Authorization: Bearer YOUR_ADMIN_API_KEY"

curl "https://powerbyexcellence.test/api/v1/reports/revenue?days=28" \
  -H "Authorization: Bearer YOUR_ADMIN_API_KEY"
```

**Expected:** JSON response with aggregated data. 401 without valid API key. 403 without `reports.read` permission.

### 10. Cross-check with dashboard

1. Open `/dashboard` in another tab
2. Compare today's sold count with reports summary

**Expected:** Today's figures consistent between dashboard and reports (same underlying queries).

---

## Expected Results (Summary)

- Reports populate immediately after seed (no manual lead submission needed)
- 10-tier panel is the primary demo artifact for enterprise routing
- Period filter correctly scopes all widgets
- Buyer/supplier tables respect tenant isolation
- API endpoints mirror UI aggregates for integrations

---

## Edge Cases

| Scenario | Expected behaviour |
|----------|-------------------|
| US platform admin | Reports show only US solar campaign data |
| No leads in period | Charts flat at zero; conversion shows 0% |
| Campaign with no deliveries | Delivery performance empty for that campaign |
| Fresh seed without DemoHistoricalDataSeeder | 10-tier panel may be empty - ensure full seed ran |
| Invalid `days` query param | Defaults to 28 |
| Sold leads without financials | Revenue may undercount - verify seed integrity |

---

## Related Docs

- [01-dashboard.md](./01-dashboard.md) - high-level overview
- [03-deliveries-and-10-tier-ping-tree.md](./03-deliveries-and-10-tier-ping-tree.md) - tier delivery setup
- [09-api-and-sdk.md](./09-api-and-sdk.md) - API report endpoints
