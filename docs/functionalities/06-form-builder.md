# 06 - Form Builder

## Purpose

The **Form Builder** creates hosted, multi-step lead capture forms linked to campaigns. Forms are published at public URLs (`/forms/{slug}`) and submit leads into the same ingestion pipeline as the REST API. This enables vertical-specific capture pages without custom front-end development.

---

## Where to Find It

| Item | Location |
|------|----------|
| Form admin list | `/forms` |
| Create form | POST `/forms` (from index page) |
| Edit form | `/forms/{id}/edit` |
| Public form | `/forms/{slug}` |
| Public submit | POST `/forms/{slug}` |
| Seeded demo form | `/forms/auto-insurance-quote-uk` |
| Navigation | Sidebar → **Tools** (or Campaigns area depending on layout) |
| Access | Admin for builder; public form requires no login |

---

## Seeded Demo Form

| Property | Value |
|----------|-------|
| Name | Auto Insurance Quote (Multi-Step) |
| Slug | `auto-insurance-quote-uk` |
| Campaign | Auto Insurance (`auto-insurance-uk`) |
| Steps | 3: Your vehicle → About you → Contact & postcode |
| Redirect | `/help` after successful submit |

---

## How to Test (Step-by-Step)

### 1. List forms

1. Log in as `uk@powerbyexcellence.test`
2. Navigate to `/forms`

**Expected:** **Auto Insurance Quote (Multi-Step)** listed. Campaign column shows Auto Insurance vertical. Active status indicated.

### 2. Create a new form

1. Click create / new form on index page
2. Select campaign: **Loans**
3. Name: `Loans Quick Apply`
4. Submit

**Expected:** Redirect to edit page. Success flash: "Form created - add steps and fields."

### 3. Configure multi-step form (edit)

1. On edit page for `Loans Quick Apply`:
2. Enable **Multi-step**
3. Add Step 1: "Loan details" - fields: `loan_amount` (number), `loan_purpose` (select)
4. Add Step 2: "Contact" - fields: `firstname`, `lastname`, `email`, `phone1`, `zipcode`
5. Set redirect URL: `https://powerbyexcellence.test/help`
6. Save

**Expected:** Form saved. Slug auto-generated or editable. Steps render in builder preview if available.

### 4. Test public form - seeded demo

1. Open incognito window (no login)
2. Navigate to `/forms/auto-insurance-quote-uk`

**Expected:** Branded multi-step form. Step 1 shows vehicle fields. Progress indicator if enabled. No login required.

### 5. Complete multi-step submission

1. Step 1 - Vehicle:
   - Vehicle year: `2019`
   - Make: `Ford`
   - Cover type: Comprehensive
2. Step 2 - About you:
   - First name: `Form`
   - Last name: `Tester`
   - Email: `form.tester.unique@demo.test`
3. Step 3 - Contact:
   - Phone: `07700900456`
   - Postcode: `EC2A 4NE`
4. Submit

**Expected:** Success message or redirect to `/help`. Lead created in pipeline. With queue worker running, lead processes to `sold` or `unsold`.

### 6. Verify lead in admin

1. Return to admin session
2. Open `/leads` - filter/search for `form.tester.unique@demo.test`
3. Open lead detail

**Expected:** Lead associated with Auto Insurance campaign. Source/sid from form config. Field data matches form input. Status reflects distribution outcome.

### 7. Test inactive form

1. Edit form → set inactive
2. Visit public URL

**Expected:** 404 or "form unavailable" message. Submission blocked.

### 8. Delete test form

1. Delete `Loans Quick Apply` from admin
2. Confirm public URL no longer accessible

**Expected:** Form removed from list. Slug freed.

---

## Field Types Available

`text`, `email`, `tel`, `number`, `postcode`, `radio`, `select`, `checkbox`, `textarea`, `date`

---

## Expected Results (Summary)

- Public forms work without authentication
- Multi-step navigation validates required fields per step
- Submissions create leads in the linked campaign
- Seeded form demonstrates 3-step Auto Insurance flow
- Redirect URL fires after successful submit
- Form submissions respect campaign dedupe rules

---

## Edge Cases

| Scenario | Expected behaviour |
|----------|-------------------|
| Duplicate email (dedupe window) | Submission rejected or lead status `rejected` |
| Missing required field | Client/server validation error; step blocked |
| Invalid redirect URL on save | Validation error on admin save |
| Custom CSS in config | Applied on public form render |
| Allowed domains restriction | Cross-origin embed blocked if configured |
| Queue worker stopped | Lead stays `pending` after submit |
| Submit to inactive campaign | Lead may reject at validation |

---

## Related Docs

- [02-campaigns-and-verticals.md](./02-campaigns-and-verticals.md) - campaign fields
- [09-api-and-sdk.md](./09-api-and-sdk.md) - alternative ingest path
- [12-operations-and-logs.md](./12-operations-and-logs.md) - verify processing
