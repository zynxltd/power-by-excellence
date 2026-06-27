<?php

return [
    'category' => 'Buyers',
    'slug' => 'buyer-portal-admin',
    'title' => 'Enabling the Buyer Portal',
    'summary' => 'Create buyer portal logins and manage purchaser self-service.',
    'audience' => 'tenant',
    'sort_order' => 85,
    'body' => <<<'MD'
## Overview

The **Buyer Portal** gives purchasers self-service access to leads sold to them, credit balances, and conversion feedback - without granting admin rights to your platform. As a tenant admin, you provision portal users, link them to buyer records, and manage access when buyer teams change.

Portal users log in at `{your-tenant-domain}/login` and land on `/portal/buyer`. They see only data scoped to their linked `buyer_id`.

---

## When to enable the portal

| Scenario | Portal benefit |
|----------|----------------|
| Prepay buyers | Self-serve credit balance and transaction history |
| High-volume purchasers | CSV export and lead search without admin tickets |
| Quality feedback loops | Conversion and return requests directly in portal |
| Multi-user buyer teams | Separate logins per team member |

Buyers without portal access still receive leads via ping/post - the portal is for visibility and self-service, not delivery.

---

## Setup

### Step-by-step: create a buyer portal user

1. Navigate to **Buyers** and open the buyer record (or create a new buyer first).
2. Click **Edit**.
3. Scroll to the **Portal access** section.
4. Enter portal user details:
   - **Email** - login username (must be unique on your tenant)
   - **Name** - display name for the user
5. Set role to `buyer_portal` (applied automatically when creating via buyer form).
6. Confirm `buyer_id` is linked to this buyer record.
7. Optionally check **Send credentials** to email a welcome message with password setup link on save.
8. Save the buyer record.
9. Ask the buyer to log in at `{tenant}/login` and confirm they see their dashboard.

### Alternative: create via Users admin

1. Go to **Users → New**.
2. Role: `buyer_portal`.
3. Link `buyer_id` to the correct buyer record.
4. Set email and name; send credentials manually or via welcome email.

### Multiple users per buyer

One portal user per buyer organisation is typical, but you can create multiple users linked to the same `buyer_id` for teams (sales, finance, compliance). Each user sees the same buyer-scoped data.

### Portal language

European platforms often deliver leads to purchasers in different countries. Set the default buyer portal language under **Platform settings → Buyer portal language**. Override per buyer on the buyer form (**Portal access → Portal language**) when a purchaser's team needs German, French, Spanish, or another supported locale.

Supported languages: English, Deutsch, Français, Español, Italiano, Nederlands, Polski, Português.

### Example: onboarding a new prepay buyer

1. Create buyer record with name, reference, and currency.
2. Enable prepay in **Platform settings** if not already active.
3. **Billing → Credit buyer** with initial top-up amount.
4. Create portal user with buyer's ops email.
5. Send credentials and link to Help Centre → **Buyer Portal** articles.
6. Buyer logs in, confirms credit balance matches top-up.

---

## Buyer portal capabilities

### Credit balance and transactions

- View current `credit_balance`
- Transaction ledger: debits (lead purchases), credits (top-ups), refunds
- Mirrors admin **Billing** view for that buyer - same `buyer_transactions` table

### Lead search and export

- Search leads sold to their buyer (`sold_to_buyer_id`)
- Filter by date, status, campaign
- **CSV export** for CRM import or internal reporting

### Conversion feedback and returns

- Submit conversion outcome on sold leads (contacted, converted, bad lead)
- Request lead returns per your return policy
- Reduces back-and-forth email with your ops team

### What portal users cannot do

- View other buyers' leads or balances
- Edit campaigns, deliveries, or distribution config
- Access supplier data or platform-wide reports
- Create API keys or manage webhooks

---

## Finance integration

Portal **billing** reflects the same `buyer_transactions` ledger as admin **Billing**.

| Action | Admin location | Portal visibility |
|--------|----------------|-------------------|
| Top-up credit | Billing → Credit buyer | Appears in transaction list |
| Lead debit | Automatic on sold lead | Debit row with lead reference |
| Refund | Billing → Refund | Credit row in portal |
| Low balance alert | Billing alert config | Buyer may receive email notification |

Reconcile monthly: export transactions from admin **Finance** view and compare to buyer's portal export.

---

## Managing access lifecycle

### New team member at buyer org

1. Create additional portal user linked to same `buyer_id`.
2. Send credentials via secure channel.
3. No need to share existing user's password.

### Team member leaves

1. **Users → Edit** - disable or delete the portal user.
2. Do not delete the buyer record - only the user login.

### Buyer offboarding

1. Disable portal users.
2. Pause or archive buyer deliveries.
3. Set buyer status to inactive.
4. Retain transaction history for finance records.

### Password reset

- Buyer uses **Forgot password** on login page, or
- Admin resets password from **Users → Edit** and resends credentials

---

## Troubleshooting

### Buyer cannot log in

- Confirm they use the correct **tenant domain** - portal users are not portable across accounts.
- Check user status is active (not disabled).
- Verify `buyer_id` is set on the user record - users without buyer linkage cannot access portal routes.
- Check buyer `status` - locked or inactive buyers may block portal features.

### Portal shows zero leads

- Confirm leads are actually **sold** to this buyer (`sold_to_buyer_id` matches).
- Check date filters - default view may show only recent leads.
- Verify portal user `buyer_id` matches the buyer receiving deliveries.

### Credit balance mismatch

- Admin and portal read the same ledger - mismatch suggests caching or wrong buyer linked.
- Refresh the page; check admin **Billing** for the same buyer ID.
- Look for pending transactions not yet committed.

### "Send credentials" email not received

- Check spam folder.
- Verify email address on user record.
- Resend from **Users → Edit** or manually set password.

### Buyer sees another org's data

- Critical: check `buyer_id` on user record - data leak if mislinked.
- Each portal user must link to exactly one buyer.
- Report immediately and rotate passwords if misconfiguration confirmed.

---

## Tips

- One portal user per buyer org is typical; create multiple for teams if needed.
- Direct buyers to Help Centre → **Buyer Portal** category for self-service how-to articles.
- Enable prepay and top up credit before sending portal credentials - empty balance frustrates first login.
- Use buyer `reference` in internal docs to map portal users to contracts.
- Rotate passwords when buyer teams change - do not share a single login across departments.
MD,
];