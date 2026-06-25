# 08 — Suppliers & Portals

## Purpose

**Suppliers** (publishers) send leads into the platform via API, hosted forms, or imports. Each supplier has **sources** identified by a **SID** (source ID) used in lead ingest. **Buyer** and **Supplier portals** give external partners a self-service view of leads, billing, and payouts without admin access.

---

## Where to Find It

### Admin — Suppliers

| Item | Location |
|------|----------|
| Supplier list | `/suppliers` |
| Create/edit | `/suppliers/create`, `/suppliers/{id}/edit` |
| Navigation | Sidebar → **Suppliers** |

### Buyer Portal

| Item | Location |
|------|----------|
| Dashboard | `/portal/buyer` |
| My leads | `/portal/buyer/leads` |
| CSV download | `/portal/buyer/leads/download` |
| Transactions | `/portal/buyer/transactions` |
| Billing | `/portal/buyer/billing` |
| Feedback / returns | POST `/portal/buyer/feedback`, `/portal/buyer/returns` |
| Login | `buyer-portal@excellence-uk.test` / `password` |

### Supplier Portal

| Item | Location |
|------|----------|
| Dashboard | `/portal/supplier` |
| Submitted leads | `/portal/supplier/leads` |
| Payouts | `/portal/supplier/billing` |
| Login | `supplier-portal@excellence-uk.test` / `password` |

---

## Seeded Data (UK)

| Entity | Reference / SID |
|--------|-----------------|
| Supplier | `supplier-main` (Main Supplier) |
| Source SID | `google_search` |
| Buyer portal user | `buyer-portal@excellence-uk.test` → Primary Buyer |
| Supplier portal user | `supplier-portal@excellence-uk.test` → Main Supplier |

---

## How to Test (Step-by-Step)

### Admin: Suppliers

1. List suppliers at `/suppliers` — confirm Main Supplier with SID `google_search`
2. Create test supplier `supplier-qa` with source SID `qa_traffic`, then delete after tests

### Buyer Portal

3. Log in as `buyer-portal@excellence-uk.test` — lands on `/portal/buyer`
4. Review dashboard stats and 7-day charts (Primary Buyer scope only)
5. Open `/portal/buyer/leads` — submit feedback/return if UI available
6. Open billing, export CSV, then try `/dashboard` — expect 403 or redirect

### Supplier Portal

7. Log in as `supplier-portal@excellence-uk.test` — lands on `/portal/supplier`
8. Review dashboard, leads table, and payout billing — all scoped to Main Supplier
9. Navigate to `/portal/buyer` — expect blocked

---

## Expected Results (Summary)

- Suppliers manage traffic sources via SID
- Buyer portal shows purchased leads and credit ledger
- Supplier portal shows submitted leads and payout earnings
- Portal roles are strictly isolated from admin and each other
- Portal data respects multi-tenant boundaries (UK vs US)

---

## Edge Cases

| Scenario | Expected behaviour |
|----------|-------------------|
| Portal user without buyer/supplier link | 403 on portal dashboard |
| Admin user hits portal URL | Redirect based on role or 403 |
| Buyer returns a lead | Return recorded; verify admin lead detail |
| Supplier API key scoped | Can only ingest to own supplier's traffic |
| US portal users | Only US platform data visible |
| Empty lead history | Tables show empty state, not error |
| CSV download with no leads | Empty file or headers only |

---

## Related Docs

- [07-buyers-and-billing.md](./07-buyers-and-billing.md) — admin billing and top-up
- [09-api-and-sdk.md](./09-api-and-sdk.md) — supplier API ingest with SID
- [10-postbacks-webhooks.md](./10-postbacks-webhooks.md) — supplier postbacks
