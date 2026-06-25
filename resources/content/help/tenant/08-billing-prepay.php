<?php

return [
    'category' => 'Buyers',
    'slug' => 'billing-prepay',
    'title' => 'Buyer Billing & Prepay',
    'summary' => 'Credits, locks, spend caps, and insufficient balance behaviour.',
    'audience' => 'tenant',
    'sort_order' => 80,
    'body' => <<<'MD'
## Overview

**Billing** manages buyer `credit_balance` and the immutable **transaction ledger** (`buyer_transactions`). When prepay is enabled, buyers must maintain sufficient credit before ping eligibility — unsold leads due to insufficient balance are preventable with proper monitoring.

Access billing at **Billing** (`/billing`) in the admin sidebar.

## Prepay model

### Enable prepay

1. Go to **Settings** (`/settings`)
2. Enable **require_buyer_prepay** (platform setting)
3. Save

When active:

| Event | Behaviour |
|-------|-----------|
| **Ping time** | `BuyerEligibilityService` checks credit ≥ expected revenue |
| **Sold lead** | Buyer `credit_balance` debited by revenue amount |
| **Insufficient credit** | Buyer skipped at ping — logs `skipped` with eligibility reason |
| **Portal** | Buyer sees balance and transaction history at `/portal/buyer/billing` |

When prepay is **disabled**, buyers are not blocked by balance — useful for invoiced/post-pay relationships.

## Top up buyer credit — step by step

1. Navigate to **Billing** (`/billing`)
2. Click buyer name or open **Billing → {buyer}** (`/billing/{id}`)
3. Review current `credit_balance` and recent transactions
4. Click **Top up** / **Credit buyer**
5. Enter:

| Field | Example |
|-------|---------|
| **Amount** | `1000.00` |
| **Description** | `Monthly prepay — March` |
| **Reference** | Optional invoice number |

6. Submit — creates `buyer_transactions` row type `credit`
7. Confirm new balance on buyer show page and billing detail

### API top-up (admin key)

Keys with `buyers.manage` permission:

```bash
curl -X POST "https://your-tenant.test/api/v1/buyers/{id}/credit" \
  -H "Authorization: Bearer ADMIN_API_KEY" \
  -H "Content-Type: application/json" \
  -d '{
    "amount": 500.00,
    "description": "API top-up"
  }'
```

## Transaction ledger

Each financial movement creates a ledger row:

| Type | Direction | Trigger |
|------|-----------|---------|
| `credit` | + | Admin top-up, Stripe payment |
| `debit` | − | Sold lead revenue |
| `adjustment` | ± | Manual correction |
| `refund` | + | Returned lead credit |

### View transactions

1. **Billing → {buyer}** — paginated transaction list
2. **Export** (`/billing/{buyer}/export`) — CSV for accounting
3. **Billing export all** (`/billing-export`) — all buyers

### Reconcile with finance

1. Open **Finance** (`/finance`) for account-level revenue summary
2. Compare period revenue to sum of buyer debits
3. Investigate mismatches in **Delivery logs** (sold but not debited = config bug)

## Spend caps

Per-buyer `caps` JSON supports volume and **spend** limits tracked in `cap_counters`.

| Cap | Scope |
|-----|-------|
| **Daily volume** | Max leads per day |
| **Daily spend** | Max revenue debited per day |
| **Monthly spend** | Calendar month debit cap |
| **Hourly** | Short-window protection |

### Configure buyer caps

1. **Buyers** → edit buyer (`/buyers/{id}/edit`)
2. **Caps** section — set limits per period
3. Save

When cap hit, buyer is ineligible at ping time — same as insufficient credit (logs `skipped`).

## Stripe integration (optional)

If Stripe is configured at `/integrations/stripe`:

1. Buyers can self-serve top-up in portal
2. Webhook credits balance on successful payment
3. Admin sees Stripe-linked transactions in ledger

Test mode keys should be used in staging only.

## Billing lock

Accounts can enter restricted billing states:

| Status | Effect |
|--------|--------|
| **locked** | Most admin actions blocked except billing pages |
| **past_due** | Warning state — may progress to locked |

### Locked account experience

1. Admin users redirect to `/billing/lock`
2. Resolve outstanding invoice or platform fee
3. Super admin or billing unlock at **Billing → Unlock account**

Non-billing routes return middleware block until resolved.

## Low-credit alerts

| Setting | Location |
|---------|----------|
| `billing_alert_emails` | Platform settings — account-wide recipients |
| Per-buyer threshold | Buyer edit form — notify when balance below X |

Alerts fire on debit that crosses threshold — not on every lead.

## Buyer portal billing view

Buyers with portal access see:

| Page | Path | Content |
|------|------|---------|
| Balance | `/portal/buyer/billing` | Current credit |
| Transactions | `/portal/buyer/transactions` | Ledger history |
| Top-up | `/portal/buyer/billing` | Stripe button if enabled |

Buyers cannot edit caps or see other buyers' data.

## Troubleshooting

| Symptom | Cause | Fix |
|---------|-------|-----|
| Buyer never pinged | Zero credit (prepay on) | Top up buyer |
| Debit didn't happen | Prepay off or sold status missing | Check lead financials |
| Balance wrong | Manual adjustment needed | Add adjustment transaction with note |
| Portal shows 0 leads but debits exist | Different buyer link | Verify portal user → buyer association |
| Locked out of admin | Account billing_status | `/billing/lock` → resolve → unlock |
| Stripe top-up not crediting | Webhook misconfigured | Check `/integrations/stripe` logs |

## Monthly reconciliation checklist

1. Export all buyer transactions for the month
2. Compare to **Finance** revenue report
3. Match sold lead count per buyer to debit row count
4. Investigate returns/refunds — ensure credit issued
5. Top up buyers proactively before month-start campaigns

## Tips

- Reconcile **Finance** view with buyer transactions monthly
- Set buyer `currency` column when multi-currency buyers exist
- Enable low-credit alerts at 20% of typical monthly spend
- Document top-up SLA for buyers (e.g. same-business-day manual credit)
- Use **adjustment** type sparingly — always add description for audit trail
MD,
];
