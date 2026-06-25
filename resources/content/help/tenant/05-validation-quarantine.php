<?php

return [
    'category' => 'Leads',
    'slug' => 'lead-validation',
    'title' => 'Validation, Dedupe & Quarantine',
    'summary' => 'How leads are validated, deduplicated, and held for review.',
    'audience' => 'tenant',
    'sort_order' => 50,
    'body' => <<<'MD'
## Overview

Every lead passes through the **LeadPipeline** before distribution:

```
Ingest → Campaign active check → Validation → Dedupe → Caps → Distribution → Financials
```

Failures at validation or dedupe may **reject** the lead immediately or **quarantine** it for manual review, depending on campaign and platform settings. Understanding this flow helps you diagnose high reject rates and configure appropriate hold policies.

## Validation

### Per-campaign validation_config

Configure on the campaign **show page** → **Validation** panel (PATCH `/campaigns/{id}/validation`):

| Setting | Effect |
|---------|--------|
| `require_email` | Email must be present and valid format |
| `require_phone` | Phone must be present and valid (libphonenumber) |
| `block_disposable_email` | Reject known disposable domains |
| Custom rules | Expression engine for field comparisons |

### Admin UI — update validation

1. Open **Campaigns** → select campaign (`/campaigns/{id}`)
2. Locate **Validation** section on show page
3. Toggle settings:

| Toggle | Recommended for |
|--------|-----------------|
| Require email | All consumer lead campaigns |
| Require phone | Call-verified or SMS follow-up flows |
| Block disposable | Reduce fraud on high-CPL verticals |

4. Click **Save validation**
5. Post a test lead with invalid data to confirm rejection

### Third-party validation services

When integrated via **Integrations → Validation** (`/integrations/validation`):

| Service | Purpose | Typical outcome |
|---------|---------|-----------------|
| **HLR** | Mobile number active/reachable | Reject or quarantine dead numbers |
| **Email verification** | Deliverability, MX check | Reject risky addresses |
| **IP fraud** | Geo mismatch vs postcode | Quarantine suspicious leads |
| **PAF (UK)** | Postal address validation | Reject invalid postcodes |

1. Configure provider credentials at `/integrations/validation`
2. Click **Test connection** to verify
3. Enable per-campaign or account-wide as supported

### Custom rule engine

Advanced campaigns support expressions such as:

- `age >= 21`
- `state IN [TX, CA, FL]`
- `loan_amount >= 5000`

Rules evaluate after basic field validation. Failed rules may reject or quarantine based on rule severity.

## Dedupe

Dedupe prevents paying twice for the same consumer within a configurable window.

### Typical dedupe keys

| Key type | Fields hashed |
|----------|---------------|
| **Email** | Normalised email address |
| **Phone** | Normalised E.164 phone |
| **Composite** | Email + phone + campaign |
| **Cross-campaign** | Email across multiple campaigns (account setting) |

### Configure dedupe

1. Campaign show page → **Dedupe** settings (or campaign edit form)
2. Set:

| Setting | Description |
|---------|-------------|
| **Window** | Hours/days to look back (e.g. 30 days) |
| **Scope** | This campaign only vs account-wide |
| **Keys** | Which fields trigger duplicate match |

3. Save

### Duplicate behaviour

| Outcome | Lead status | Supplier paid? |
|---------|-------------|----------------|
| Hard reject | `rejected` / `duplicate` | No |
| Soft hold | `quarantined` | No until released |

## Quarantine

Quarantine holds leads that need human review before entering (or re-entering) distribution.

### When leads are quarantined

| Reason | Example |
|--------|---------|
| **Validation borderline** | HLR returned "unknown" |
| **Out of hours** | Arrived outside buyer schedule |
| **Unsold retry** | Held for second-pass distribution |
| **Manual rule** | Custom expression flagged for review |
| **Admin action** | Operator placed lead on hold |

### Admin UI — quarantine queue

1. Navigate to **Operations → Quarantine** (`/quarantine`)
2. Review list columns:

| Column | Meaning |
|--------|---------|
| **Lead** | UUID, name, email |
| **Reason** | `validation`, `out_of_hours`, `unsold`, etc. |
| **Held until** | Auto-expiry timestamp |
| **Campaign** | Source campaign |

3. Open lead detail for full field data and validation messages

### Quarantine actions

| Action | Effect |
|--------|--------|
| **Release** | Re-enter pipeline from current stage |
| **Reject** | Terminal status with reason code |
| **Extend** | Push `quarantined_until` forward |
| **Bulk release** | Release selected rows |
| **Bulk reject** | Reject selected with shared reason |

#### Release a single lead

1. Quarantine list → click lead row
2. Review **quarantine_message** in metadata
3. Click **Release**
4. Lead re-queues through pipeline — check **Leads** for new status

#### Bulk operations

1. Select multiple rows via checkboxes
2. Choose **Bulk release** or **Bulk reject**
3. Confirm — operation runs asynchronously for large batches

### Scheduled expiry

Command `quarantine:process-expired` runs every 15 minutes (scheduler). Expired holds are auto-released or auto-rejected per campaign policy.

### API quarantine management

Keys with `quarantine.manage` permission:

```bash
# List quarantined leads
GET /api/v1/quarantine

# Release
POST /api/v1/quarantine/{uuid}/release

# Reject
POST /api/v1/quarantine/{uuid}/reject
```

## Pipeline status reference

| Status | Stage failed / outcome |
|--------|------------------------|
| `pending` | Queued, not yet processed |
| `processing` | Pipeline running |
| `quarantined` | Held for review |
| `rejected` | Terminal — validation, dedupe, or admin |
| `duplicate` | Dedupe match |
| `sold` | Successfully distributed |
| `unsold` | No buyer accepted |

## Troubleshooting

| Symptom | Check | Action |
|---------|-------|--------|
| High reject rate | Supplier data quality | Sample 50 leads; check email/phone formats |
| Sudden spike in quarantine | HLR integration | Review `/integrations/validation` logs |
| Leads released but still unsold | Distribution inactive | Activate ping tree; check buyer credit |
| Duplicate on first submit | Window too wide / cross-campaign | Narrow dedupe scope |
| Quarantine not expiring | Scheduler not running | Verify `schedule:run` cron |
| Validation too strict | Recent toggle change | Compare reject reasons in lead metadata |

## Reporting quarantine depth

1. **Dashboard** — quarantined count links to filtered lead list
2. **Operations** (`/operations`) — live stats include quarantine depth
3. Monitor daily — growing queue indicates supplier or validation misconfiguration

## Tips

- High reject rate? Check supplier data quality first before loosening validation
- Use quarantine for manual review of borderline HLR results — better than auto-rejecting good leads
- Document quarantine reasons when rejecting — suppliers see status in portal
- Pair **block_disposable_email** with supplier education on email capture
- Test dedupe with the same email twice in staging before go-live
MD,
];
