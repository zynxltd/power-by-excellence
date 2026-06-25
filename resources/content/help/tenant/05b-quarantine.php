<?php

return [
    'category' => 'Leads',
    'slug' => 'lead-quarantine',
    'title' => 'Quarantine Operations',
    'summary' => 'Review, release, reject, and bulk-manage quarantined leads with full workflow guidance.',
    'audience' => 'tenant',
    'sort_order' => 55,
    'body' => <<<'MD'
## Overview

**Quarantine** temporarily holds leads that should not distribute immediately — for example validation uncertainty, manual review policies, suspicious patterns, or unsold leads held for retry. Quarantined leads have status `quarantined` and do not enter the ping tree until released.

Think of quarantine as a **parking bay** between ingest and distribution. Operators review the queue, fix data if needed, then release (re-process) or reject (terminal).

## Where to find quarantined leads

| Location | Path | Best for |
|----------|------|----------|
| **Quarantine workbench** | `/quarantine` | Bulk review, release, reject |
| **Live operations** | `/operations` | Queue depth + link to quarantine count |
| **Lead pipeline** | `/leads?status=quarantined` | Search/filter alongside other statuses |
| **Lead detail** | `/leads/{id}` | Single-lead review with events and field data |

The live stats bar (green **LIVE** strip on admin pages) shows **Quarantine** count — click it to jump to filtered leads.

## Why leads enter quarantine

| Reason | Typical source |
|--------|----------------|
| Validation policy | Campaign rules flag lead for review |
| Unsold retry hold | Distribution engine holds unsold for repost window |
| Manual API quarantine | `POST /api/v1/quarantine` or admin action |
| Expired hold policy | Awaiting auto-release timer |

Check the lead **Events** tab on detail view for `lead.quarantined` and the message column for the exact reason.

## Step-by-step: release a lead

1. Go to **Operations → Quarantine** (`/quarantine`)
2. Use filters (campaign, date, search) to find the lead
3. Click the row to open **Lead detail**, or select checkbox for bulk
4. Click **Release & repost** — lead status returns to `pending` and `ProcessLeadJob` queues distribution
5. Confirm **queue worker** is running (`php artisan queue:work` or Horizon) — without it, released leads stay pending
6. Refresh lead detail — status should move to `processing` then `sold` or `unsold`

## Step-by-step: reject a lead

1. Open lead from quarantine list
2. Click **Reject** (or bulk reject from quarantine index)
3. Enter **reject reason** — visible in lead events and supplier-facing logs where applicable
4. Lead moves to terminal `rejected` status — no further distribution

Use reject for confirmed bad data, duplicates you chose not to merge, or compliance blocks.

## Bulk operations

On `/quarantine`:

1. Select multiple rows via checkboxes
2. Choose **Bulk release** or **Bulk reject**
3. Confirm — each lead processes independently in the queue

Bulk release is useful after fixing a campaign validation rule that wrongly quarantined a batch.

## API access

| Method | Endpoint | Purpose |
|--------|----------|---------|
| GET | `/api/v1/quarantine` | List quarantined leads (API key with `quarantine.manage`) |
| POST | `/api/v1/quarantine/{uuid}/release` | Release single lead |
| POST | `/api/v1/quarantine/{uuid}/reject` | Reject with reason body |

## Automation

Scheduled command `quarantine:process-expired` runs every **15 minutes** (see `routes/console.php`). Leads past `quarantined_until` auto-release or auto-reject per campaign/account policy.

## Monitoring

- Watch **Quarantine** stat on dashboard and live stats bar
- Set **Event alert** on `quarantined_count` threshold in **Automation → Event Alerts**
- Sudden spikes often mean a validation rule change or buyer outage causing unsold holds

## Troubleshooting

| Symptom | Likely cause | Fix |
|---------|--------------|-----|
| Released lead stays `pending` | Queue worker not running | Start `queue:work` or Horizon |
| Released lead immediately re-quarantines | Validation still failing | Fix field data or relax `validation_config` |
| Cannot find lead in quarantine | Wrong tenant subdomain | Sign in on correct partner platform |
| Bulk release partial success | Some leads invalid for release | Check individual lead events |
| Count not decreasing | Stale browser cache | Hard refresh; check `/live-stats` JSON |

## Tips

- Document quarantine reasons when rejecting — helps supplier disputes
- After rule changes, bulk-release a test batch of 5 leads before releasing hundreds
- Pair quarantine review with **Delivery logs** when leads were unsold before hold
- Use **Lead pipeline → Quarantine** filter for CSV export of held inventory
MD,
];
