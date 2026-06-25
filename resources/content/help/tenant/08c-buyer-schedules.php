<?php

return [
    'category' => 'Buyers',
    'slug' => 'buyer-schedules',
    'title' => 'Buyer Schedules & Availability',
    'summary' => 'Time windows when buyers accept pings and posts.',
    'audience' => 'tenant',
    'sort_order' => 82,
    'body' => <<<'MD'
## Overview

**Buyer schedules** restrict delivery to configured hours — essential when buyers operate call centres with fixed opening times or serve specific timezones. Outside scheduled windows, deliveries to that buyer are skipped with reason `outside_schedule`, and the lead may waterfall to the next eligible buyer in the tier.

Schedules are evaluated at **ping time** by `ScheduleService`. An empty or disabled schedule means 24/7 availability.

---

## When to use schedules

| Scenario | Schedule approach |
|----------|-------------------|
| Call centre 9–5 Mon–Fri | Per-day windows with buyer timezone |
| UK buyer, US traffic | Explicit `Europe/London` timezone |
| Lunch break exclusion | Two windows per day (morning + afternoon) |
| 24/7 buyer | Leave schedule empty or set `"day": "all"` |

Schedules apply at the **buyer** level and can also be set per **delivery** — buyer schedule is the most common configuration.

---

## Configuration

Schedules are stored as JSON on the buyer record (`schedule` column) or delivery record.

### Advanced format (recommended)

Per-day windows with explicit timezone:

```json
{
  "timezone": "America/Toronto",
  "windows": [
    { "day": "mon", "start": "09:00", "end": "17:00" },
    { "day": "tue", "start": "09:00", "end": "17:00" },
    { "day": "wed", "start": "09:00", "end": "17:00" },
    { "day": "thu", "start": "09:00", "end": "17:00" },
    { "day": "fri", "start": "09:00", "end": "17:00" }
  ]
}
```

### 24/7 within a time range

Use `"day": "all"` to apply start/end across every day:

```json
{
  "timezone": "Europe/London",
  "windows": [
    { "day": "all", "start": "08:00", "end": "20:00" }
  ]
}
```

### Simple format (legacy)

Single start/end applied every day:

```json
{
  "enabled": true,
  "timezone": "Europe/London",
  "start": "09:00",
  "end": "17:00"
}
```

The platform normalises this to the windows format internally.

### Day name values

Accepted day values: `mon`, `tue`, `wed`, `thu`, `fri`, `sat`, `sun`, `monday`, `tuesday`, … `all`.

Matching is case-insensitive.

---

## Step-by-step: configure a buyer schedule

1. Navigate to **Buyers → Edit** the target buyer.
2. Find the **Schedule** section (JSON editor or visual form depending on your UI).
3. Set `timezone` to the buyer's operating timezone — **do not assume server timezone**.
4. Add windows for each operating day with `start` and `end` in `HH:MM` 24-hour format.
5. Save the buyer record.
6. Open **Routing Simulator** and test with a lead at a time inside and outside the window.
7. Confirm delivery logs show `outside_schedule` when expected.

### Example: US Eastern call centre

Buyer operates 8 AM – 6 PM Eastern, Monday through Saturday:

```json
{
  "timezone": "America/New_York",
  "windows": [
    { "day": "mon", "start": "08:00", "end": "18:00" },
    { "day": "tue", "start": "08:00", "end": "18:00" },
    { "day": "wed", "start": "08:00", "end": "18:00" },
    { "day": "thu", "start": "08:00", "end": "18:00" },
    { "day": "fri", "start": "08:00", "end": "18:00" },
    { "day": "sat", "start": "09:00", "end": "14:00" }
  ]
}
```

Sunday is omitted — leads arriving Sunday skip this buyer entirely.

### Example: split shift (lunch break)

```json
{
  "timezone": "Europe/London",
  "windows": [
    { "day": "mon", "start": "09:00", "end": "12:00" },
    { "day": "mon", "start": "13:00", "end": "17:00" }
  ]
}
```

Repeat windows for each day as needed.

---

## Behaviour at runtime

### Inside schedule

- Buyer is eligible for ping/post (subject to filters, caps, prepay).
- Normal distribution flow proceeds.

### Outside schedule

- Delivery skipped with `skipped_reason: outside_schedule`.
- Lead continues to next delivery in waterfall tier, or next tier if no eligible buyers remain.
- Lead may sell to a different buyer — not lost unless entire tree is unavailable.

### Disabled schedule

Set `"enabled": false` or leave schedule empty/null — buyer accepts leads 24/7.

### Evaluation timing

Schedule is checked at **ping time**, not at lead ingest time. A lead ingested at 11 PM may queue and ping at 9 AM when the buyer opens — if workers process immediately, it may still hit an closed buyer. Consider ingest timing for time-sensitive verticals.

---

## Buyer timezone vs server timezone

**Always set buyer `timezone` explicitly.**

| Mistake | Consequence |
|---------|-------------|
| Omit timezone | Falls back to `app.timezone` (server default) |
| Wrong timezone | Buyer pinged at 3 AM local or skipped during business hours |
| DST not considered | Carbon handles DST in named timezones — use IANA names like `America/New_York`, not fixed offsets |

### Step-by-step: verify timezone

1. Note buyer's stated operating hours and timezone.
2. Configure schedule with IANA timezone string.
3. At a known UTC moment, check `ScheduleService` behaviour via Routing Simulator.
4. Compare with buyer's local clock.

---

## Routing Simulator testing

### Step-by-step: test midnight boundary

1. Open **Routing Simulator** for the campaign using this buyer.
2. Enter valid lead field values.
3. Note current time in buyer's timezone.
4. If inside window — delivery should show eligible.
5. Temporarily adjust a window end to one minute ago, re-save buyer, re-simulate — should show `outside_schedule`.
6. Restore correct schedule after test.

### Cross-midnight windows

For overnight windows (e.g. 22:00 – 06:00), use two windows or `"day": "all"` with start > end carefully — the current engine compares `HH:MM` strings within the same calendar day. Overnight spans may need custom handling; test thoroughly.

---

## Troubleshooting

### Buyer complains about leads at night

- Check schedule windows — missing `timezone` may use server TZ.
- Verify `"day": "all"` is not set with 24-hour range unintentionally.
- Filter delivery logs by buyer and `outside_schedule` — if no rows, leads may be routing to a different buyer.

### Buyer gets no leads during business hours

- Timezone likely wrong — buyer in `America/Chicago` but schedule set to `Europe/London`.
- Window `start`/`end` reversed or typo (`17:00` start, `09:00` end).
- Day name mismatch — lead arrives Tuesday but only Monday configured.
- Buyer may be skipped for other reasons (eligibility, prepay) — check full `skipped_reason` in logs.

### `outside_schedule` in logs but buyer insists they were open

- Compare log timestamp (UTC) converted to buyer timezone against window.
- Daylight saving transition days may shift effective hours — use IANA timezone names.
- Delivery-level schedule may override buyer schedule — check both records.

### Schedule change not taking effect

- Confirm buyer record saved successfully.
- Cached delivery config is rare — retry with fresh lead ingest.
- Check if delivery has its own `schedule` JSON overriding buyer defaults.

### Leads queue overnight then skip buyer at open

- Worker processed lead at ingest time (overnight) when buyer was closed.
- Lead may have already waterfall-sold to another buyer.
- For time-sensitive routing, consider tier ordering so 24/7 buyers catch overflow.

---

## Tips

- Set buyer `timezone` explicitly — do not assume server TZ.
- Test with **Routing Simulator** across midnight boundaries and DST transition dates.
- Document buyer operating hours in the buyer record notes for support reference.
- Pair schedules with tier waterfall so closed buyers fall through to 24/7 partners.
- Review delivery logs weekly for `outside_schedule` volume — high counts may mean schedule misconfiguration or traffic arriving outside expected hours.
MD,
];