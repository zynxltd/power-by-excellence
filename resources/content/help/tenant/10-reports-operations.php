<?php

return [
    'category' => 'Reports & Operations',
    'slug' => 'reports',
    'title' => 'Reports, Operations & Live Stats',
    'summary' => 'Analytics, delivery logs, and real-time monitoring.',
    'audience' => 'tenant',
    'sort_order' => 100,
    'body' => <<<'MD'
## Overview

Reports and Operations give you two complementary views of platform health: **historical analytics** for revenue and performance trends, and **live operations** for what is happening right now. Use Reports when reconciling buyer spend, supplier payouts, or tier performance over a week or month. Use Operations when a buyer reports missed leads, queue depth looks high, or you need to confirm the pipeline is processing.

Both areas respect tenant isolation - you only see data for campaigns, buyers, and suppliers on your account.

---

## Reports

Navigate to **Reports** (`/reports`) from the sidebar.

### What you see

| Section | Purpose |
|---------|---------|
| Summary cards | Total leads, sold, rejected, revenue, conversion %, outbid count, ping rejections |
| Trend charts | Daily leads, sold, rejected, and revenue over the selected period |
| Top buyers | Lead count and revenue by buyer (top 10) |
| Top suppliers | Lead count and payout by supplier (top 10) |
| Delivery performance | Per-delivery attempts, successes, outbid, rejections, revenue |
| Ping tree tiers | Per-tier attempts, wins, outbid, rejections |
| Distribution outcomes | Breakdown by delivery log status |

### Step-by-step: run a period report

1. Open **Reports** from the sidebar.
2. Select a campaign if the page filters by campaign (the demo seeds **Auto Insurance** as the 10-tier example).
3. Choose a date range: **7**, **14**, **28**, or **30** days. Invalid values default to 28.
4. Review summary cards first - conversion % and outbid totals flag routing issues quickly.
5. Scroll to **Top buyers** and **Top suppliers** to spot concentration risk (one buyer taking 80% of volume).
6. Open **Delivery performance** to see which delivery methods or buyers have high rejection rates.
7. Export CSV from tables where the export button is available - useful for finance reconciliation.

### Example: diagnosing a revenue drop

A buyer says they stopped receiving leads three days ago:

1. Set Reports to **7 days** and compare daily revenue trend - confirm the drop aligns with their report.
2. Check **Delivery performance** for that buyer's delivery row - look for rising `rejections` or `outbid`.
3. Cross-reference **Logs → Delivery** (see below) filtered to that buyer for the last 48 hours.
4. If rejections spiked, the issue is likely buyer-side (ping reject, post reject). If `outbid` rose, check tier floor prices and competing buyers.

---

## Operations (Live Ops)

Navigate to **Operations** (`/operations`) for real-time monitoring.

### What you see

- **Queue depth** - how many leads are waiting to be processed
- **Processing badge** - whether workers are actively consuming the queue
- **Today's counts** - pings, posts, failures for the current day
- **Pipeline poll** - recent activity refreshes automatically

### Step-by-step: morning health check

1. Open **Operations** at the start of the business day.
2. Confirm queue depth is near zero or trending down - a steadily rising queue suggests worker backlog.
3. Check today's **failures** count. A spike after a deploy or buyer URL change is a red flag.
4. Click through to **Delivery logs** if failures are non-zero.
5. Compare today's ping/post ratio to a normal day - a sudden drop in posts with steady pings may indicate buyer post URL issues.

### When to use Operations vs Reports

| Question | Use |
|----------|-----|
| "How did we do last month?" | Reports |
| "Is anything broken right now?" | Operations |
| "Why did this specific lead fail?" | Delivery logs |
| "Which buyer won the most last week?" | Reports → Top buyers |

---

## Delivery logs

Navigate to **Logs → Delivery** (`/logs/delivery`).

Every ping, post, and direct delivery attempt is recorded with:

- **Status** - success, failed, skipped, outbid, etc.
- **Duration** - milliseconds for HTTP round-trips
- **HTTP payload** - ping/post request and response bodies (for debugging integrations)
- **skipped_reason** - why a delivery was not attempted or did not complete

### Filters available

- Buyer, campaign, delivery, tier
- Status (success, failed, skipped, outbid)
- Method (ping-post vs direct)
- Date range (preset 1/7/14/28 days or custom from/to)

### Step-by-step: trace a single lead

1. Open **Logs → Delivery**.
2. Filter by **campaign** and narrow the date to when the lead arrived.
3. Search or scroll to the lead's delivery attempts - a lead may generate multiple log rows (one per tier/buyer).
4. Click a row to expand ping/post payloads.
5. Read `skipped_reason` if status is `skipped` or `failed`:
   - `eligibility_rules` - lead did not pass tier or delivery filters
   - `outside_schedule` - buyer schedule blocked the attempt
   - `ping_rejected` / `post_rejected` - buyer returned a negative response
   - `auction_lost` / `outbid` - another buyer won the parallel auction
   - `missing_ping_url` / `missing_post_url` - delivery config incomplete
   - `timeout` - buyer endpoint did not respond in time

### Example: buyer says "we never got pinged"

1. Filter logs by that **buyer** and the lead's date.
2. If no rows exist - the lead never reached that delivery (check tier filters, caps, or prepay balance).
3. If rows show `skipped` + `eligibility_rules` - review tier entry filters and delivery eligibility rules.
4. If rows show `skipped` + `outside_schedule` - check buyer schedule and timezone.
5. If rows show `failed` + `missing_ping_url` - edit the delivery and confirm ping URL is set.

---

## Event alerts

Configure threshold-based alerts so you are notified before small issues become outages.

### Common metrics

| Metric | When to alert |
|--------|---------------|
| `delivery_success_rate_24h` | Buyer endpoint down or misconfigured |
| `reject_rate_24h` | Validation rules too strict or bad affiliate traffic |
| `pending_queue` | Worker backlog or ingest spike |

### Step-by-step: set up a delivery success alert

1. Go to **Automation → Event Alerts** (or **Settings → Alerts** depending on your menu).
2. Create a new alert with metric `delivery_success_rate_24h`.
3. Set threshold below your normal baseline (e.g. alert if success rate drops below 85%).
4. Choose notification channel: email, SMS, webhook, or Slack.
5. Save and test by temporarily pointing a test delivery at a bad URL - confirm you receive the alert.

---

## Dashboard

The tenant **Dashboard** (`/dashboard`) is your daily snapshot:

- **7 / 14 / 30 day** lead and revenue charts
- **Status donut** - sold, rejected, quarantined, pending breakdown
- **Stat cards** with drill-down links to leads, reports, or logs

Use the Dashboard for a quick glance; use Reports for deeper analysis and Delivery logs for individual lead forensics.

---

## Troubleshooting

### Reports show zero data

- Confirm the date range includes days when leads were ingested.
- Check that the selected campaign has active deliveries and received leads in that period.
- Seeded demo data may only cover specific date windows - ingest a test lead and re-check.

### Operations queue keeps growing

- Verify queue workers are running (`php artisan queue:work` in self-hosted setups).
- Check for a buyer endpoint causing widespread timeouts (filter delivery logs by `timeout`).
- Look for a campaign validation change rejecting all inbound leads at pipeline start.

### High `outbid` in reports but buyers complain about no leads

- In parallel auction tiers, only the highest bidder wins - losers show `outbid`, not `failed`.
- Lower floor prices or reorder tiers so preferred buyers run in waterfall mode.
- Check buyer prepay balance - buyers without credit are skipped silently at ping time.

### "Failed" in logs does not mean platform outage

| skipped_reason | Meaning |
|----------------|---------|
| `ping_rejected` | Buyer declined the ping (normal in competitive trees) |
| `post_rejected` | Buyer accepted ping but rejected the post |
| `auction_lost` | Lost to a higher bid - expected behaviour |
| `eligibility_rules` | Your filter config excluded the lead |
| `exception` / `timeout` | Investigate - likely config or buyer endpoint issue |

---

## Tips

- Export CSV from reports tables for monthly finance reconciliation with buyer transactions.
- Bookmark a Delivery logs filter preset (buyer + 7 days) for your highest-value buyer.
- Distinguish buyer reject vs internal error before escalating to engineering - `skipped_reason` tells you which.
- Compare Reports tier breakdown with Distribution config when tuning floor prices.
MD,
];
