<?php

return [
    'category' => 'Automation',
    'slug' => 'automation-responders',
    'title' => 'Auto Responders, Sequences & Alerts',
    'summary' => 'Event-driven email/SMS, bulk SMS, and operational alerts.',
    'audience' => 'tenant',
    'sort_order' => 130,
    'body' => <<<'MD'
## Overview

**Automation** covers three related capabilities: immediate **auto responders** when lead events fire, multi-step **sequences** for delayed follow-up, and **event alerts** that notify your team when platform metrics cross thresholds. Use responders for consumer-facing confirmation messages; use alerts for internal operations monitoring.

All automation respects campaign scope - configure per campaign so messaging and triggers match each vertical's compliance requirements.

---

## Auto responders

Auto responders fire a single message when a lead event occurs.

### Supported events

| Trigger | Typical use |
|---------|-------------|
| Lead received | "We got your application" confirmation |
| Lead sold | Notify consumer their info was shared with a partner |
| Lead unsold | Re-engagement offer or alternate path |

### Configuration

1. Navigate to **Automation → Auto Responders**.
2. Select **campaign** and **trigger event**.
3. Choose **channel**: email or SMS.
4. Write the template body using `{{field}}` macros - e.g. `Hi {{firstname}}, we received your request.`
5. Set optional **delay** (minutes) before sending - useful to batch rapid duplicate events.
6. Save and test with a sandbox lead.

### Step-by-step: create a "lead received" email

1. **Automation → Auto Responders → New**.
2. Campaign: your active vertical (e.g. Auto Insurance UK).
3. Trigger: `on_lead_received`.
4. Channel: **email**.
5. Subject: `Thanks {{firstname}} - application received`.
6. Body: include `{{email}}` and `{{phone1}}` only if needed for compliance; avoid sensitive data in SMS.
7. Delay: `0` minutes for immediate send.
8. Ingest a test lead and confirm the email arrives within one minute.

### Template macros

Any campaign field name works as a macro: `{{firstname}}`, `{{state}}`, `{{loan_amount}}`. If the field is empty on the lead, the macro renders blank - write templates that still read naturally.

---

## Sequences

Sequences chain multiple steps with delays between them - ideal for nurture flows on unsold leads.

### Step-by-step: build a 3-step unsold sequence

1. Go to **Automation → Sequences → New**.
2. Select **campaign** and trigger: `on_lead_unsold` (or `on_lead_sold` for post-sale follow-up).
3. **Step 1**: SMS - "Still interested? Reply YES" - delay `0` minutes.
4. **Step 2**: Email - detailed offer with `{{firstname}}` - delay `60` minutes after step 1.
5. **Step 3**: SMS - final reminder - delay `1440` minutes (24 hours) after step 2.
6. Save and enable the sequence.
7. Test by ingesting a lead that will not sell (pause all buyers temporarily, or use a test campaign).

### Sequence behaviour

- Steps execute in order - step 2 does not fire until step 1's delay elapses.
- If the lead status changes (e.g. sold on retry), remaining steps may cancel depending on config.
- Each step supports independent channel and template.

### Example: sold lead follow-up

| Step | Channel | Delay | Message |
|------|---------|-------|---------|
| 1 | Email | 0 min | Confirmation with reference number |
| 2 | SMS | 30 min | "A partner will call you shortly" |
| 3 | Email | 24 hr | Satisfaction survey link |

---

## Bulk SMS

Bulk SMS re-engages existing leads matching filters - not real-time event driven.

### When to use

- Re-contact unsold leads from last 7 days with `has_phone` true
- Promote a new buyer or offer to a segment
- Compliance-permitted follow-up in your vertical

### Step-by-step: send bulk SMS

1. Navigate to **Automation → Bulk SMS**.
2. Select **campaign**.
3. Set filters:
   - Status: `unsold`, `rejected`, etc.
   - Age: leads created in last N days
   - `has_phone`: true (required for SMS)
4. Write message with field macros: `Hi {{firstname}}, still looking for auto insurance?`
5. Preview recipient count - confirm it matches expectations.
6. Schedule or send immediately.
7. Monitor delivery report for failures (invalid numbers, carrier blocks).

### Bulk SMS cautions

- Verify TCPA/consent requirements for your vertical before sending.
- Start with a small filter (e.g. last 24 hours, 50 leads) before blasting thousands.
- Invalid phone numbers fail silently or log errors - clean data first.

---

## Event alerts

Event alerts monitor platform metrics and notify your team when thresholds are breached.

### Available metrics

| Metric | Example use |
|--------|-------------|
| `delivery_success_rate_24h` | Buyer endpoint outage |
| `reject_rate_24h` | Validation rule too strict or bad traffic |
| `pending_queue` | Worker backlog |
| `lead_volume_24h` | Unexpected traffic spike or drop |

### Notification channels

- Email
- SMS
- Webhook (POST to your ops endpoint)
- Slack (via webhook URL)

### Step-by-step: alert on low delivery success

1. **Automation → Event Alerts → New**.
2. Metric: `delivery_success_rate_24h`.
3. Condition: less than `85`% (adjust to your baseline).
4. Channel: email to ops team + Slack webhook.
5. Cooldown: 60 minutes (avoid alert storms during brief blips).
6. Save.

### Step-by-step: alert on queue backlog

1. Metric: `pending_queue`.
2. Condition: greater than `100`.
3. Channel: SMS to on-call number.
4. Save - verify workers are running when this fires.

---

## Troubleshooting

### Auto responder not sending

- Confirm responder is **enabled** and campaign matches the ingested lead.
- Check SMS provider config - use `log` driver in development (messages write to log, not sent).
- Verify lead has required field for channel (email for email, phone for SMS).
- Check delay setting - message may be queued for future delivery.

### Sequence stops after step 1

- Step 2 delay may not have elapsed yet - check `delay_minutes`.
- Lead status may have changed, cancelling remaining steps.
- SMS step may fail on invalid phone - check automation logs.

### Bulk SMS recipient count is zero

- Filters too restrictive - broaden status or age window.
- `has_phone` filter excludes leads without normalised phone numbers.
- Campaign has no matching leads in the selected period.

### Alert fatigue (too many notifications)

- Raise thresholds to match normal variance - buyer skips and outbids are expected in ping trees.
- Increase cooldown period between repeat alerts.
- Use different thresholds for business hours vs overnight if supported.

### SMS works in dev but not production

- Development uses `log` SMS provider - switch to live provider (Twilio, etc.) in production config.
- Confirm sender ID and account credits on the SMS provider.

---

## Tips

- Use `log` SMS provider in development - messages appear in application logs, not sent to real numbers.
- Keep alert thresholds realistic - buyer skips and outbids are normal in ping trees.
- Test sequences with a dedicated test campaign before enabling on live traffic.
- Avoid sensitive PII in SMS templates - email can carry more detail.
- Pair event alerts with Delivery logs investigation workflow for faster root cause.
MD,
];
