# 07 — Buyers & Billing

## Purpose

**Buyers** (advertisers) purchase leads from the platform. PowerByExcellence tracks buyer credit balances, transaction ledgers, volume caps, and prepay enforcement. **Billing** provides admin tools to top up buyer credit and review platform-wide financial activity. When a lead sells, the buyer's credit is debited by the revenue amount.

---

## Where to Find It

| Item | Location |
|------|----------|
| Buyer list | `/buyers` |
| Create/edit buyer | `/buyers/create`, `/buyers/{id}/edit` |
| Billing overview | `/billing` |
| Per-buyer billing | `/billing/{buyer_id}` |
| Top-up credit | POST `/billing/{buyer_id}/top-up` |
| Billing lock screen | `/billing/lock` |
| Account settings (prepay) | `/settings` |
| API buyer credit | `POST /api/v1/buyers/{id}/credit` |
| Navigation | Sidebar → **Buyers**; Sidebar → **Account** → Billing |
| Access | Account Admin |

---

## Seeded Buyers (UK)

| Reference | Name | Initial credit (via ledger) |
|-----------|------|----------------------------|
| `buyer-primary` | Primary Buyer | £500 top-up |
| `buyer-secondary` | Secondary Buyer | £250 top-up |

Historical seeder adds weekly top-ups and lead purchase debits.

---

## How to Test (Step-by-Step)

### 1. List buyers

1. Log in as `uk@powerbyexcellence.test`
2. Navigate to `/buyers`

**Expected:** Primary Buyer and Secondary Buyer listed. Credit balances visible. References shown.

### 2. Create a buyer

1. Click **New Buyer**
2. Fill in:
   - Reference: `buyer-qa-test`
   - Name: `QA Test Buyer`
   - Email: `qa-buyer@demo.test`
   - Daily cap: `25`
3. Save

**Expected:** Buyer appears in list. Credit balance £0.00.

### 3. Edit buyer

1. Open QA Test Buyer → Edit
2. Change daily cap to `50`
3. Save

**Expected:** Updated cap saved. Success flash.

### 4. Billing overview

1. Navigate to `/billing`
2. Review summary cards: total credit across buyers, buyer count, transactions today
3. Review recent transactions list

**Expected:** Total credit reflects sum of buyer balances. Recent transactions show seed top-ups and lead debits. Currency: GBP.

### 5. Per-buyer billing and top-up

1. Click **Primary Buyer** from billing overview (or `/billing/{id}`)
2. Review transaction history (paginated)
3. Enter top-up amount: `100.00`
4. Description: `QA manual top-up`
5. Submit top-up

**Expected:** Credit balance increases by £100. New `credit` transaction in ledger with `balance_after`. Success flash.

### 6. Verify debit on lead sale

1. Note Primary Buyer balance
2. Submit sync API lead that sells to Primary Buyer (see [09-api-and-sdk.md](./09-api-and-sdk.md))
3. Refresh billing page

**Expected:** Balance decreased by lead revenue amount. New `debit` transaction: "Lead purchase".

### 7. Insufficient credit scenario

1. Edit QA Test Buyer — set credit to `0` via billing (no top-up)
2. Create delivery assigned to QA Test Buyer on a test campaign
3. Submit lead that routes only to that delivery

**Expected:** Delivery skipped with `insufficient_credit`. Lead may cascade to next tier or become unsold.

### 8. Prepay enforcement (settings)

1. Navigate to `/settings`
2. Enable **Require buyer prepay**
3. Save
4. Attempt lead sale to buyer with zero credit

**Expected:** Buyers without credit cannot receive leads. Disable setting after test.

### 9. Billing lock (optional)

1. If account billing lock triggered (overdue invoice simulation in seed), user sees `/billing/lock`
2. Admin can unlock via billing unlock action

**Expected:** Lock screen blocks admin routes except billing unlock. Portal users also blocked.

### 10. Delete test buyer

1. Delete `buyer-qa-test`
2. Confirm

**Expected:** Buyer removed. Associated deliveries may need reassignment.

---

## Expected Results (Summary)

- Credit ledger is source of truth (use `/billing` top-up, not direct DB edits)
- Each sold lead creates a debit transaction
- Top-ups create credit transactions with running balance
- Buyer caps enforced during distribution
- Billing page shows cross-buyer summary and per-buyer detail
- No Stripe/card payment in demo — manual top-up only

---

## Edge Cases

| Scenario | Expected behaviour |
|----------|-------------------|
| Top-up negative amount | Validation error |
| Buyer with no deliveries | Credit unchanged; no debits |
| Direct edit of credit_balance on buyer form | May bypass ledger — prefer billing top-up |
| Deleted buyer with past leads | Historical leads retain buyer reference |
| Multi-currency (US platform) | USD displayed for US buyers |
| require_buyer_prepay enabled | Zero-balance buyers skipped |
| Return/refund | Buyer portal return flow; verify ledger if implemented |

---

## Related Docs

- [08-suppliers-and-portals.md](./08-suppliers-and-portals.md) — buyer portal view
- [03-deliveries-and-10-tier-ping-tree.md](./03-deliveries-and-10-tier-ping-tree.md) — buyer-delivery assignment
- [09-api-and-sdk.md](./09-api-and-sdk.md) — API credit endpoint
