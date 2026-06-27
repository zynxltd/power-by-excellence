<?php

return [
    'category' => 'Delivery',
    'slug' => 'tier-filters',
    'title' => 'Tier & Delivery Filters',
    'summary' => 'Eligibility rules on tiers and deliveries using lead field values.',
    'audience' => 'tenant',
    'sort_order' => 75,
    'body' => <<<'MD'
## Overview

Filters control which leads reach which buyers during distribution. There are three layers - **tier entry filters**, **delivery eligibility rules**, and **location filters** - evaluated at different points in the ping tree. Misconfigured filters are the most common cause of "buyer never got pinged" support tickets; understanding the evaluation order saves hours of debugging.

All filters use the same **rule engine** syntax as campaign validation. Field names must match your campaign schema and API spec exactly.

---

## How filters are evaluated

When a lead enters distribution:

1. **Tier entry filters** on the distribution group - if the lead fails, the entire tier is skipped (no deliveries in that tier are attempted).
2. For each delivery in the tier, **delivery eligibility rules** are checked - failure skips only that delivery.
3. **Location filter** on the delivery (`location_filter.states`) provides geo gating.
4. Buyer-level checks follow: schedule, caps, prepay balance, billing lock.

A lead can pass tier filters but fail delivery filters - the tier runs, but individual buyers are skipped.

---

## Tier entry filters

Configured on the **Distribution** tier group (Ping Tree builder).

### Purpose

Restrict which leads enter a tier before any buyer in that tier is evaluated. Use tier filters for broad segmentation: state groups, loan amount bands, credit score tiers.

### Step-by-step: add a state tier filter

1. Navigate to **Distribution** and edit the campaign's ping tree config.
2. Select the target tier (e.g. Tier 1 - Premium buyers).
3. Scroll to **Tier entry filters**.
4. Add a condition:
   - Field: `state`
   - Operator: `in`
   - Value: `TX, CA, FL` (or select from dropdown if campaign fields are loaded)
5. Save the distribution config.
6. Test with **Routing Simulator** using leads in and out of those states.

### Example: two-tier state split

| Tier | Entry filter | Buyers |
|------|--------------|--------|
| Tier 1 | `state` in `['TX','CA']` | Premium buyers with higher bids |
| Tier 2 | (no filter - catch-all) | Fallback buyers for all other states |

Leads from `NY` skip Tier 1 entirely and waterfall to Tier 2.

### Empty filters

If tier entry filters are empty, all leads enter the tier (subject to per-delivery rules and buyer eligibility).

---

## Delivery eligibility rules

Configured on each **Delivery** record (Deliveries → Edit).

### Purpose

Fine-grained gating per buyer. Use when buyers in the same tier accept different lead profiles - e.g. one buyer wants `loan_amount >= 10000`, another wants `loan_amount < 10000`.

### Step-by-step: add delivery eligibility

1. Go to **Deliveries** and edit the buyer's delivery.
2. Find **Eligibility rules** section.
3. Add conditions using the rule editor:
   - Field: `loan_amount`
   - Operator: `gte` (greater than or equal)
   - Value: `10000`
4. Save the delivery.
5. Run Routing Simulator with a lead at `loan_amount = 5000` - delivery should show ineligible.
6. Repeat with `loan_amount = 15000` - delivery should be eligible.

### Rule operators

| Operator | Meaning | Example |
|----------|---------|---------|
| `eq` / `=` | Equals | `state` eq `TX` |
| `neq` / `!=` | Not equals | `status` neq `test` |
| `gt`, `gte`, `lt`, `lte` | Numeric comparison | `loan_amount` gte `5000` |
| `in` | Value in list | `state` in `['TX','CA','FL']` |
| `not_in` | Value not in list | `state` not_in `['AK','HI']` |
| `contains` | Substring match | `email` contains `@gmail.com` |
| `regex` | Pattern match | `zipcode` regex `^75` |
| `exists` | Field has a value | `phone1` exists |
| `empty` | Field is blank | `phone2` empty |

### Combining conditions

- Default group operator is **AND** - all conditions must pass.
- Use nested groups with `operator: or` for alternative conditions (e.g. `state` is `TX` OR `loan_amount` gte `20000`).

### Example: AND vs OR

**AND** (all must match):
```
state in ['TX', 'CA']  AND  loan_amount gte 10000
```
Lead must be in TX or CA **and** have loan amount ≥ 10000.

**OR** (any can match):
```
state eq 'TX'  OR  loan_amount gte 50000
```
Lead passes if in Texas **or** has a high loan amount.

---

## Location filter

A delivery-level shortcut for geo gating via `location_filter.states` array.

### Configuration

On the delivery record, set:

```json
{
  "states": ["TX", "CA", "FL"]
}
```

Only leads with `state` matching one of the listed values will attempt this delivery. Functionally similar to an eligibility rule but stored separately for clarity.

### Tier filter vs location filter

| Layer | Scope | Use when |
|-------|-------|----------|
| Tier entry filter | Entire tier | Segment traffic before any buyer sees it |
| Location filter | Single delivery | One buyer in a tier serves specific states |
| Eligibility rules | Single delivery | Non-geo criteria (amount, score, source) |

---

## Field names

Field names in filters must match campaign schema and API spec **exactly**.

### Common mismatches

| Wrong | Correct | Result of mismatch |
|-------|---------|-------------------|
| `zip` | `zipcode` | Filter never matches - all leads skip |
| `phone` | `phone1` | Phone-based rules silently fail |
| `State` | `state` | Case-sensitive mismatch |
| `loanAmount` | `loan_amount` | API sends snake_case |

### Step-by-step: verify field names

1. Open **Campaign → Fields** and note exact `name` values.
2. Open **Campaign → API Spec** - tier filters use the same names.
3. Ingest a test lead via API and inspect the lead record's stored fields.
4. Compare stored field keys to your filter config.

---

## Routing Simulator

Always test filters in **Routing Simulator** before going live.

### Step-by-step: simulate a lead

1. Navigate to **Distribution → Simulator** (or campaign routing simulator).
2. Select campaign and enter test field values (`state`, `loan_amount`, etc.).
3. Run simulation - each tier and delivery shows **eligible** or **ineligible** with reasons.
4. Adjust filters and re-run until routing matches expectations.

### What simulator shows

- Tier-level: whether lead enters the tier (tier entry filters)
- Delivery-level: eligibility, schedule, caps, prepay - with skip reasons
- Overall: whether the lead would sell

---

## Troubleshooting

### Buyer never pinged - filter issue checklist

1. Check **tier entry filters** - lead may not enter the tier at all.
2. Check **delivery eligibility rules** - lead enters tier but buyer skipped.
3. Check **location_filter.states** - geo mismatch.
4. Filter delivery logs by buyer - look for `skipped_reason: eligibility_rules`.
5. Run Routing Simulator with the lead's actual field values.

### All leads skipping a delivery after config change

- New rule too restrictive - test with `exists` / `empty` operators accidentally blocking everything.
- Field name typo - `zip` instead of `zipcode` means `in` checks always fail.
- Numeric operator on text field - `loan_amount` gte `10000` fails if value is stored as non-numeric string.

### Tier runs but every delivery in tier skipped

- Tier entry filters passed (tier was entered).
- Each delivery has its own eligibility - check each delivery's rules individually.
- Buyer schedule or prepay may skip after eligibility passes - check `outside_schedule` or billing skip reasons in logs.

### Filters work in simulator but not live

- Simulator uses the field values you enter - live lead may have different normalised values (phone format, state abbreviation).
- Lead may be quarantined or rejected before distribution - filters never evaluated.
- Delivery status may be `paused` - simulator may still show eligibility but live skips inactive deliveries.

### `skipped_reason: eligibility_rules` in delivery logs

This confirms a filter blocked the attempt. Cross-reference the lead's field values against the delivery's `eligibility_rules` and tier entry filters. Use Routing Simulator with the same values to identify which rule failed.

---

## Tips

- Test edge cases in **Routing Simulator** before enabling on live traffic.
- Log `skipped_reason: eligibility_rules` in delivery logs is your primary debugging signal.
- Match field names to campaign schema exactly - `zipcode` vs `zip` breaks filters silently.
- Use tier filters for broad segmentation; delivery rules for per-buyer nuance.
- Document filter logic per buyer in internal runbooks - complex AND/OR groups are hard to reverse-engineer later.
MD,
];