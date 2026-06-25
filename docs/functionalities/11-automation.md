# 11 — Automation

## Purpose

The **Automation** hub centralises remarketing and monitoring tools: multi-step **automation sequences** (email/SMS follow-ups on lead events), **bulk SMS campaigns**, and **event alerts** (threshold-based notifications). These features support nurture flows and operational alerting without external marketing automation platforms.

---

## Where to Find It

| Item | Location |
|------|----------|
| Automation hub | `/automation` |
| Create sequence | POST `/automation/sequences` |
| Delete sequence | DELETE `/automation/sequences/{id}` |
| Create bulk SMS | POST `/automation/bulk-sms` |
| Send bulk SMS | POST `/automation/bulk-sms/{id}/send` |
| Create alert | POST `/automation/alerts` |
| Delete alert | DELETE `/automation/alerts/{id}` |
| Auto-responders | `/features/auto-responders` |
| Navigation | Sidebar → **Tools** → **Automation** |
| Access | Account Admin |

---

## Automation Components

| Component | Trigger / use |
|-----------|-----------------|
| Sequences | `on_lead_received`, `on_lead_sold`, `on_lead_unsold` |
| Sequence steps | Email or SMS channel with delay (minutes) |
| Bulk SMS | One-off SMS blast to filtered leads |
| Event alerts | Metric threshold (e.g. reject rate > 20%) |
| Auto-responders | Separate feature page for instant responses |

---

## How to Test (Step-by-Step)

### 1. Load automation hub

1. Log in as `uk@powerbyexcellence.test`
2. Navigate to `/automation`

**Expected:** Three sections: Sequences, Bulk SMS campaigns, Event alerts. Campaign dropdown available. May be empty on fresh seed.

### 2. Create automation sequence

1. Click create sequence (or use form on page)
2. Configure:
   - Name: `QA Sold Follow-up`
   - Campaign: Auto Insurance (optional — blank for all)
   - Trigger: `on_lead_sold`
3. Add steps:
   - Step 1: delay 0 min, channel email, subject/body config
   - Step 2: delay 60 min, channel SMS, message template
4. Save

**Expected:** Sequence appears in list with steps expanded. Status: active. Success flash.

### 3. Trigger sequence

1. Submit sync API lead that sells on Auto Insurance
2. Check Laravel log (`storage/logs/laravel.log`) for email/SMS dispatch

**Expected:** Sequence triggered on sold event. Email sent via mail driver (log/array in demo). SMS logged to platform log (no live provider in demo).

### 4. Delete sequence

1. Delete `QA Sold Follow-up`
2. Confirm

**Expected:** Sequence removed. Subsequent lead sales do not trigger it.

### 5–9. Bulk SMS, alerts, and auto-responders

Create and send a bulk SMS campaign (verify in logs). Add a scheduled bulk SMS and a threshold event alert (`reject_rate_24h > 15`). Test auto-responders at `/features/auto-responders` for instant `on_lead_received` responses. Delete test records after verification.

---

## Expected Results (Summary)

- Sequences support multi-step delayed follow-up
- Bulk SMS creates and sends (logs in demo environment)
- Event alerts configurable with metric/operator/threshold
- Campaign scoping limits automation to specific verticals
- SMS does not send via real provider in demo — verify via logs
- Automation does not block lead processing (async side effects)

---

## Edge Cases

| Scenario | Expected behaviour |
|----------|-------------------|
| Sequence with 0 steps | Validation error on create |
| Bulk SMS to 0 matching leads | Sends with 0 recipients; no error |
| Alert with invalid email | Validation error |
| Duplicate trigger sequences | Both fire independently |
| Unsold trigger on 10-tier exhaust | Sequence fires if configured |
| Delete campaign with sequences | Orphan handling — verify cascade |
| Very short delay (0 min) | Step fires immediately after trigger |

---

## Related Docs

- [09-api-and-sdk.md](./09-api-and-sdk.md) — trigger lead events
- [06-form-builder.md](./06-form-builder.md) — capture leads for nurture
- [12-operations-and-logs.md](./12-operations-and-logs.md) — monitor pipeline health
