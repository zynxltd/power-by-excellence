<?php

return [
    'category' => 'Suppliers',
    'slug' => 'supplier-portal',
    'title' => 'Enabling the Supplier Portal',
    'summary' => 'Provision supplier portal users and what affiliates can see.',
    'audience' => 'tenant',
    'sort_order' => 95,
    'body' => <<<'MD'
## Overview

The **Supplier Portal** gives affiliates read access to their performance data - lead counts, payout summaries, and CSV exports - without granting admin rights to your platform. This reduces support tickets ("how many leads did I send this week?") and builds trust through transparent reporting.

Portal users log in at `{your-tenant-domain}/login` and access `/portal/supplier`. They see only leads where `supplier_id` matches their linked account.

---

## When to enable the portal

| Scenario | Portal benefit |
|----------|----------------|
| Volume affiliates | Self-serve lead and payout reporting |
| API-integrated partners | Confirm ingest is working without admin requests |
| Payout disputes | Shared source of truth for lead counts |
| Onboarding new affiliates | Immediate visibility after first test lead |

Affiliates without portal access can still ingest via API key - the portal is for visibility, not delivery.

---

## Setup

### Step-by-step: create portal access from supplier record

1. Navigate to **Suppliers** and open the affiliate's supplier record.
2. Click **Edit**.
3. Scroll to **Portal access** section.
4. Enter user details:
   - **Email** - login username
   - **Name** - display name
5. Set a password or enable **Send credentials** to email a welcome link on save.
6. Confirm `supplier_id` linkage on the user record (applied automatically via supplier form).
7. Save the supplier record.
8. Send the affiliate their login URL: `{tenant}/login`.
9. Ask them to verify dashboard shows data after their next API ingest.

### Alternative: create via Users admin

1. Go to **Users → New**.
2. Role: `supplier_portal`.
3. Link `supplier_id` to the correct supplier record.
4. Set email, name, and password.
5. Optionally send credentials email.

### Linking API keys and portal users

Best practice: link the affiliate's **API key** to the same `supplier_id` as their portal user. Leads ingested via API automatically attribute to that supplier - portal dashboard reflects the same data.

| Component | Linkage |
|-----------|---------|
| API key | `supplier_id` on key record |
| Portal user | `supplier_id` on user record |
| Leads | `supplier_id` set at ingest |

### Example: onboarding Acme Media

1. Create supplier record: name `Acme Media`, reference `acme-media`.
2. Create API key with `leads:write`, linked to Acme supplier.
3. Create portal user with affiliate's ops email, linked to same supplier.
4. Send API docs + portal login in onboarding email.
5. Affiliate sends test lead via API.
6. Affiliate logs into portal and confirms lead appears on dashboard.

---

## Affiliate experience

After login at `/portal/supplier`:

### Dashboard

- Lead volume charts (daily/weekly trends)
- Sold vs rejected breakdown
- Payout summary for the period

### Leads list

- All leads where `supplier_id` matches their account
- Filter by date, status, campaign
- View individual lead status (sold, rejected, quarantined)

### CSV export

- Export filtered lead list for internal reporting
- Columns include status, campaign, timestamps - not buyer-identifying data unless configured

### Payout summary

- Aggregated payout figures based on campaign payout rules
- Mirrors admin supplier reporting for that affiliate's scope

### What affiliates cannot see

- Other suppliers' leads or performance
- Buyer names, bids, or ping tree details (unless explicitly exposed)
- Campaign configuration, delivery URLs, or floor prices
- Platform-wide reports or admin settings

---

## Data scope and isolation

Portal users only see leads where `supplier_id` matches their linked supplier record.

### How attribution works

| Ingest method | supplier_id source |
|---------------|-------------------|
| API key linked to supplier | Automatic from key |
| API with supplier reference param | Resolved to supplier record |
| CSV import | Set during import mapping |
| Unattributed ingest | No supplier_id - invisible in portal |

### Multi-campaign affiliates

One supplier record can feed multiple campaigns. Portal shows all leads attributed to that supplier across campaigns - affiliates filter by campaign in the leads list.

---

## Managing access lifecycle

### New affiliate team member

1. Create additional portal user with same `supplier_id`.
2. Send credentials securely.
3. Both users see identical supplier-scoped data.

### Affiliate offboarding

1. Revoke API keys linked to the supplier.
2. Disable or delete portal users.
3. Set supplier status to inactive.
4. Retain lead history for compliance and payout reconciliation.

### Password rotation

- Affiliate uses **Forgot password** on login page, or
- Admin resets from **Users → Edit** when teams change
- Rotate passwords when affiliate teams change - do not share one login across companies

### Wrong tenant login

Portal users are tenant-scoped. Affiliates working with multiple networks need separate logins per tenant domain - credentials from one platform do not work on another.

---

## Troubleshooting

### Affiliate sees empty dashboard

- Confirm leads have `supplier_id` set - unattributed leads are invisible.
- Check API key is linked to the correct supplier.
- Verify affiliate ingested to an active campaign on this tenant.
- Date filter may exclude recent leads - broaden the range.

### Lead count mismatch (portal vs affiliate's tracker)

- Affiliate tracker may count attempts; portal counts accepted leads.
- Rejected and quarantined leads appear with those statuses - not counted as "delivered."
- Timezone differences on date boundaries - align on UTC vs local.
- Dedupe rejections reduce counted leads vs raw POST attempts.

### Portal user cannot log in

- Confirm correct tenant domain.
- Check user is active and role is `supplier_portal`.
- Verify `supplier_id` is set - users without supplier linkage cannot access portal routes.

### Affiliate sees another company's data

- Critical misconfiguration: wrong `supplier_id` on user record.
- Disable user immediately, correct linkage, rotate password.
- Audit recent portal access in access logs.

### API ingest works but portal empty

- API key may not be linked to supplier - leads ingest without `supplier_id`.
- Edit API key, set supplier linkage, future leads will appear (past leads remain unattributed).

---

## Tips

- Point affiliates to Help Centre → **Supplier Portal** category for self-service guides.
- Rotate passwords when affiliate teams change.
- Link API keys and portal users to the same `supplier_id` for consistent attribution.
- Include portal login URL in affiliate onboarding checklist alongside API credentials.
- Review unattributed leads monthly - leads without `supplier_id` indicate key misconfiguration.
MD,
];