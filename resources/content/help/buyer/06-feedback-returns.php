<?php

return [
    'category' => 'Buyer Portal',
    'slug' => 'buyer-portal-feedback-returns',
    'title' => 'Feedback, Conversions & Returns',
    'summary' => 'Report lead outcomes, flag conversions, and submit return requests for quality disputes.',
    'audience' => 'buyer',
    'sort_order' => 60,
    'body' => <<<'MD'
## Overview

After purchasing leads, you report outcomes back to the platform from `/portal/buyer/leads`. This powers conversion tracking, supplier quality analytics, and optional postbacks to data sources when platform policy allows.

There are two distinct actions:

1. **Conversion feedback** — tell the platform what happened after you worked the lead (contacted, converted, invalid, etc.)
2. **Return request** — dispute lead quality and ask for administrator review (and possible credit)

Returns are **not** instant refunds. Feedback is **not** a billing adjustment. Each flow has its own form at the bottom of the **My Leads** page.

## Where to find the forms

1. Sign in and open `/portal/buyer/leads`
2. Scroll past the **Lead Inventory** table and pagination
3. Left panel: **Submit Feedback**
4. Right panel: **Return Lead**

Both forms require the full **Lead UUID** from your inventory (search the table or export CSV to obtain it).

## Conversion feedback

### What feedback is used for

- Internal conversion reporting and buyer performance metrics
- Supplier postbacks when configured (`BuyerConversionService`)
- Account manager visibility into funnel outcomes

Feedback does **not** automatically credit your account or reverse a lead charge.

### Submitting feedback step by step

1. Locate the lead on **My Leads** and copy its **UUID** (search by email or name if needed)
2. In **Submit Feedback**, paste the UUID into **Lead UUID**
3. Select **Status** from the dropdown:
   - `contacted` — you reached the consumer but no sale yet
   - `converted` — sale or funded outcome (pair with checkbox below)
   - `invalid` — lead data failed validation on contact (wrong number, fake details, etc.)
4. Optionally tick **Converted** for funded/sale events (boolean flag used in reporting)
5. Add **Notes** — free text for your account manager (optional but recommended for edge cases)
6. Click **Submit Feedback**
7. On success, you see a confirmation: **Feedback submitted.** The form resets

### Feedback fields reference

| Field | Required | Notes |
|-------|----------|-------|
| `lead_uuid` | Yes | Must match a lead sold to your buyer |
| `status` | Yes | `contacted`, `converted`, or `invalid` |
| `converted` | No | Checkbox; set true for funded/sale events |
| `notes` | No | Free text context |

## Lead returns

### What returns are used for

When a lead fails quality standards — wrong number, duplicate in your system, fraud, out-of-geo — you submit a **return request** for tenant staff review. Approved returns may credit your account per platform policy.

### Submitting a return step by step

1. Confirm the lead is in your inventory (`sold` to your buyer)
2. In **Return Lead**, paste the **Lead UUID**
3. Enter **Return Reason** — factual explanation (required, max **500 characters**)
4. Click **Submit Return**
5. On success: **Return submitted for review.** Status in the system is **pending** until staff acts

Good reasons are specific: *"Phone number disconnected — auto message on dial"* beats *"Bad lead"*.

### Return fields reference

| Field | Required | Notes |
|-------|----------|-------|
| `lead_uuid` | Yes | Must match a lead sold to your buyer only |
| `reason` | Yes | Max 500 characters; shown to reviewers |

## API vs portal

Administrators can also record feedback via the REST API (`POST /api/v1/buyers/{id}/feedback`) for automated CRM webhooks. Portal forms POST to:

- `POST /portal/buyer/feedback`
- `POST /portal/buyer/returns`

Use whichever channel your integration supports; duplicate feedback on the same lead may depend on platform dedupe rules — ask your account manager if you run both.

## Example scenarios

### Marking a funded loan

A lender contacts a lead, completes underwriting, and funds the loan. Ops copies the UUID from **My Leads**, submits feedback with **Status** `converted`, ticks **Converted**, and notes the fund date. Supplier postback fires if configured, and conversion rate updates in platform reports.

### Invalid wrong-number

Dialler marks the phone as invalid after three attempts. QA submits feedback with **Status** `invalid` and notes *"Number not in service per carrier message."* This is feedback only — no automatic refund.

### Return for duplicate

CRM flags the UUID as duplicate of a lead purchased last month. Buyer submits a **Return Lead** with reason citing the original UUID and purchase date. Administrator reviews, approves, and credits the account if policy allows duplicates within the return window.

### Contacted but not converted

Call centre reached the consumer; no sale. Agent submits **Status** `contacted` without **Converted** checked. Reporting distinguishes contact rate from conversion rate.

## Tips

- Submit feedback **promptly** — downstream reporting often uses same-day aggregates
- Returns are **not** automatic refunds — await administrator approval and check `/portal/buyer/billing` for credits
- Keep return reasons **factual and specific** for faster resolution (include dialer dispositions, timestamps)
- Use **invalid** feedback for reporting bad data; use **returns** when you seek financial remedy
- Copy UUID from CSV export if the table truncation makes copy-paste awkward
- One return per quality dispute — do not spam duplicate requests for the same UUID
- Notes in feedback help account managers defend or improve supplier relationships

## Troubleshooting

| Symptom | Likely cause | Resolution |
|---------|--------------|------------|
| **404 Not Found** on submit | UUID wrong or not sold to you | Verify UUID on **My Leads**; only your inventory qualifies |
| Validation error on `lead_uuid` | Empty or malformed UUID | Paste full UUID from export or search result |
| Validation error on `reason` | Missing or over 500 chars | Shorten reason; keep under limit |
| **Feedback submitted.** but reports unchanged | Aggregation delay | Allow up to one hour for reporting jobs |
| Return still **pending** after days | Awaiting staff review | Contact account manager with UUID and date submitted |
| Cannot find lead for return | Lead on different buyer or tenant | Confirm sale on your buyer ID only |
| Form errors after session timeout | Auth expired | Sign in at `/login` and retry |
| Converted checkbox ignored | Status not `converted` | Align status with checkbox for consistent reporting |
| Duplicate feedback rejected | Platform dedupe | Check with admin whether updates are allowed |
MD,
];
