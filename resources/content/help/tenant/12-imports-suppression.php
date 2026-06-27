<?php

return [
    'category' => 'Data & Compliance',
    'slug' => 'csv-import-suppression',
    'title' => 'CSV Import & Suppression Lists',
    'summary' => 'Bulk lead imports, suppression hashes, and compliance blocking.',
    'audience' => 'tenant',
    'sort_order' => 120,
    'body' => <<<'MD'
## Overview

**Imports** let you bulk-load leads from CSV when affiliates cannot integrate via API, or when you need to backfill historical data. **Suppression lists** maintain hashed opt-out and do-not-contact values that block matching leads at validation - essential for TCPA, GDPR, and affiliate compliance.

Both features respect the same campaign validation, dedupe, and quarantine rules as API ingest. A suppressed email rejected during import behaves the same as one rejected during live API traffic.

---

## Lead CSV import

### When to use CSV import

- Affiliate provides a one-time batch file instead of real-time API integration
- Migrating leads from a legacy system into an active campaign
- Testing campaign validation with realistic data before go-live

### Step-by-step: import leads from CSV

1. Navigate to **Features → Imports** or **Leads → Import**.
2. Select the target **campaign** - field mapping depends on that campaign's schema.
3. Upload your CSV file (UTF-8 encoding recommended).
4. **Map columns** - match each CSV header to a campaign field (`email`, `phone1`, `state`, etc.).
5. Review the mapping summary - unmapped required fields will cause row failures.
6. Submit the import - rows queue for validation and processing.
7. Monitor **import status** on the imports list page.
8. Download or review **error rows** for failures - each row shows the rejection reason.

### What happens to each row

Imported rows pass through the same pipeline as API leads:

1. **Field validation** - required fields, format checks (email, phone, postcode)
2. **Suppression check** - hashed values compared against `suppression_hashes`
3. **Dedupe check** - per campaign `dedupe_config` (email, phone, composite keys)
4. **Quarantine rules** - suspicious patterns held for review
5. **Distribution** - valid leads enter the ping tree

### Example: mapping a loan leads file

| CSV column | Campaign field | Notes |
|------------|----------------|-------|
| `Email Address` | `email` | Required |
| `Mobile` | `phone1` | Normalised to E.164 where possible |
| `Postcode` | `zipcode` | Must match field name exactly |
| `Loan Amount` | `loan_amount` | Numeric validation applies |

### Import status values

| Status | Meaning |
|--------|---------|
| Pending | Queued, not yet processed |
| Processing | Currently validating and routing |
| Completed | All rows processed (some may have failed) |
| Failed | File-level error (bad format, missing campaign) |

---

## Suppression lists

Suppression prevents specific contacts from entering your system - opt-outs, litigation lists, or affiliate-provided DNC files.

### How hashing works

1. You upload a single-column CSV of raw values (emails, phone numbers).
2. The platform normalises each value (lowercase email, strip phone formatting).
3. A one-way hash is stored in `suppression_hashes` - raw values are not retained.
4. At validation time, incoming lead field values are hashed and compared.
5. Matches reject with a clear suppression reason in the lead record.

**Important:** hashing is one-way. You cannot export or recover raw suppressed values from stored hashes. Keep your source opt-out files archived separately.

### Step-by-step: upload a suppression list

1. Go to **Features → Imports** (or **Imports → New**).
2. Set import type to **suppression** (not lead import).
3. Select the **campaign** to scope the list.
4. Choose the **field name** the values match: `email`, `phone1`, `phone2`, etc.
5. Upload a single-column CSV - one value per row, no header required (or with a header row that is skipped).
6. Submit and wait for processing to complete.
7. Verify by ingesting a test lead with a known suppressed value - it should reject immediately.

### Example: email opt-out list

```
user@example.com
optout@affiliate.com
another@domain.org
```

- Type: **suppression**
- Campaign: `auto-insurance-uk`
- Field: `email`

A lead with `email = user@example.com` will be rejected at validation with a suppression reason.

### Scoping suppression

| Scope | When to use |
|-------|-------------|
| Per campaign | Different verticals have different opt-out obligations |
| Per field | Email opt-outs vs phone DNC are separate lists |
| Refresh regularly | Affiliates send updated opt-out files monthly |

---

## Best practices

### Before large uploads

1. Test with a **10-row file** containing mix of valid, invalid, and suppressed values.
2. Confirm error reporting shows expected rejection reasons.
3. Scale to full file only after test pass.

### Compliance pairing

- Use **suppression lists** for known opt-outs (affiliate DNC, litigation lists).
- Use **dedupe_config** on campaigns to block duplicate submissions within a time window.
- Use **quarantine rules** for suspicious patterns (velocity, geo mismatch).
- Together these three layers cover most compliance requirements.

### File format tips

- UTF-8 encoding - avoid Excel's default encoding on non-ASCII names.
- Phone numbers: include country code where possible for reliable normalisation.
- Emails: lowercase in source file is fine; platform normalises regardless.
- Remove duplicate rows in source file to speed processing (platform dedupes hashes, but clean files are easier to audit).

### Refresh cadence

- Request updated opt-out files from affiliates monthly or per contract terms.
- Re-upload suppression CSV - new hashes are added; existing hashes remain.
- Document upload dates for audit trails.

---

## Troubleshooting

### Import shows many validation errors

- Check column mapping - a phone column mapped to `email` fails every row.
- Confirm field names match campaign schema exactly (`zipcode` not `zip`).
- Review required field flags on campaign - unmapped required fields reject rows.

### Suppression not blocking expected leads

- Verify suppression was uploaded to the **same campaign** the lead ingests to.
- Confirm the **field name** matches (suppressed `email` will not block matching `phone1`).
- Check normalisation - `+1 (555) 123-4567` and `5551234567` should match if uploaded in comparable format.
- Test with a fresh lead ingest, not a CSV re-import of the same row (dedupe may fire first).

### Import stuck in Processing

- Large files take time - check queue worker health on self-hosted deployments.
- Look for campaign-level issues (inactive campaign rejects all rows at pipeline start).

### Cannot recover suppressed values

- This is by design - hashes are one-way for privacy compliance.
- Maintain your source opt-out CSV files externally for audit and re-upload needs.

### Duplicate leads after import

- CSV import does not bypass dedupe - if dedupe window expired, duplicates may pass.
- Tighten `dedupe_config` window or add values to suppression list.

---

## Tips

- Test with a 10-row file before uploading thousands of rows.
- Pair suppression with **dedupe_config** on campaigns for full compliance coverage.
- Scope suppression per campaign when verticals have different opt-out obligations.
- Archive source opt-out files - you cannot export raw values from stored hashes.
- Review import error rows immediately - a systematic mapping error wastes an entire batch.
MD,
];
