# 12 - Operations & Logs

## Purpose

**Live Operations** is the real-time monitoring console for lead processing and delivery execution. **Audit logs** track user access, configuration changes, and security events. Together they provide operational visibility for QA demos and troubleshooting distribution issues.

---

## Where to Find It

| Item | Location |
|------|----------|
| Live Operations | `/operations` |
| Lead pipeline | `/leads`, `/leads/{id}` |
| Quarantine queue | `/quarantine` |
| Delivery logs | `/logs/delivery` |
| API logs | `/logs/api` |
| Access logs | `/logs/access` |
| Change logs | `/logs/changes` |
| Security logs | `/logs/security` |
| CSV import | `/imports` |
| Routing simulator | `/routing/simulator` |
| Navigation | Top bar → **Ops** (Live operations, Lead pipeline, Quarantine, Deliveries, …) |
| Access | Account Admin |

---

## Operations Dashboard Sections

| Section | Content |
|---------|---------|
| KPI strip | Leads today, sold, unsold, rejected, in queue, quarantined, ping-posts, revenue (compact horizontal row) |
| Top campaigns | Compact strip - leads/sold per campaign (links to filtered pipeline) |
| Queue breakdown | Pending, processing, accepted, quarantined |
| Hourly chart | Ingest volume by hour |
| Recent leads | Paginated with drill-down |
| Delivery preview | Recent delivery attempts |

---

## How to Test (Step-by-Step)

### Live Operations

#### 1. Load operations page

1. Log in as `uk@powerbyexcellence.test`
2. Navigate to `/operations`

**Expected:** Compact KPI strip populated from seed data. Ping-posts today > 0 if historical logs exist.

#### 2. Review recent leads feed

1. Scroll to recent leads table
2. Confirm mix of statuses: sold, rejected, unsold, quarantined, pending
3. Click a lead UUID link

**Expected:** Navigates to `/leads/{id}` detail. Timestamps in platform timezone (Europe/London for UK).

#### 3. Review delivery logs

1. Scroll to delivery logs table
2. Check columns: status, delivery name, buyer, lead UUID, method, revenue, duration_ms
3. Paginate to page 2

**Expected:** Ping-post entries show method `ping-post`. Statuses include `success`, `outbid`, `skipped`, `failed`. Duration in milliseconds. Pagination works (25 per page).

#### 4. Live update after API ingest

1. Keep `/operations` open
2. Submit sync API lead (see [09-api-and-sdk.md](./09-api-and-sdk.md))
3. Refresh page

**Expected:** New lead at top of recent leads. New delivery log entries for tiers attempted. Sold lead shows buyer name and revenue.

---

### Lead Pipeline

#### 5. Lead list and filters

1. Navigate to `/leads`
2. Filter by status: `sold`
3. Filter by campaign: Auto Insurance
4. Search by email

**Expected:** Filtered results match criteria. Pagination for large seed dataset.

#### 6. Lead detail page

1. Open a sold lead
2. Review: field data, financials (revenue, payout, margin), events timeline, delivery logs
3. Review linked buyer and supplier

**Expected:** Event timeline shows processing steps. Delivery logs match operations page. Financials in GBP.

#### 7. Quarantine release

1. Find a quarantined lead (filter status `quarantined`)
2. Click **Release from quarantine**
3. Ensure queue worker is running

**Expected:** Lead re-queued for processing. Status changes to `pending` then final state. Success flash.

#### 8. Quarantine reject

1. Find another quarantined lead
2. Click **Reject**

**Expected:** Status changes to `rejected`. Lead not reprocessed.

---

### Audit Logs & Import

Review `/logs/access` (login entries), `/logs/changes` (config edits), and `/logs/security` (failed auth). Upload a CSV at `/imports` and confirm leads queue after worker runs. Use `/routing/simulator` for dry-run tier prediction.

---

## Expected Results (Summary)

- Operations page is the go-to during live demos
- Delivery logs expose ping request/response detail on lead show page
- Quarantine release/reject works from lead detail (no separate quarantine admin page)
- Access logs confirm user activity for compliance demos
- Import bulk-loads leads through same pipeline as API
- All logs scoped to current platform tenant

---

## Edge Cases

| Scenario | Expected behaviour |
|----------|-------------------|
| No delivery logs for lead | Lead rejected before distribution |
| Pending lead stuck | Queue worker not running |
| Release quarantine without worker | Lead stays pending |
| Very long 10-tier cascade | Many log entries per lead; duration_ms varies |
| Cross-tenant lead UUID | 404 on lead detail |
| Empty import CSV | Validation error |
| Operations page performance | Pagination prevents timeout on large log tables |

---

## Related Docs

- [01-dashboard.md](./01-dashboard.md) - summary stats
- [03-deliveries-and-10-tier-ping-tree.md](./03-deliveries-and-10-tier-ping-tree.md) - delivery log context
- [09-api-and-sdk.md](./09-api-and-sdk.md) - generate live traffic
