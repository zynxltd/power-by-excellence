# Tenant Billing (Super Admin)

When operating in **god mode** or with a switched `current_account_id`, super admins use the same tenant **Finance** and **Billing** screens as account admins — scoped to the active tenant.

## Finance dashboard

**Route:** `/finance` (tenant host)

Shows revenue, margin, buyer spend, and supplier payouts for the managed tenant. Requires valid tenant session; cross-tenant access redirects with an error flash (see `EnsureTenantAccess`).

## Buyer credits

**Billing → Credit buyer**

- Adds `buyer_transactions` ledger entries
- Updates `credit_balance` on the buyer record
- Respects `require_buyer_prepay` platform setting

Super admins use this during onboarding and make-good adjustments.

## Super admin workflow

1. **Visit** tenant from Partner Platforms (god mode)
2. Open **Finance** or **Billing**
3. Perform credit top-ups or review transactions
4. **Exit god mode** when finished

## Testing

`tests/Feature/GodModeTest::test_super_admin_billing_works_with_switched_tenant` verifies billing routes work with god-mode session context.

## Tips

- Always confirm `current_account_id` matches the tenant you intend (banner + dashboard subtitle)
- Buyer portal billing view mirrors the same transaction table — impersonate a buyer to verify their view after top-up
- Multi-currency: check buyer `currency` vs account `default_currency` before crediting
