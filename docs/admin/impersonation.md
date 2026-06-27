# Impersonation

Impersonation lets admins **act as another user** with that user's exact permissions - for support, QA, and portal troubleshooting.

## Who can impersonate

- **Super admin** - any impersonatable user (tenant staff, buyer portal, supplier portal)
- **Account admin** - users within their tenant only

`User::canBeImpersonatedBy()` enforces the policy.

## Starting impersonation

1. Open **Users** (or buyer/supplier portal user management)
2. Choose **Impersonate** on the target row
3. If actor and target are on different hosts:
   - One-time token stored in `impersonation_handoff:{token}`
   - Redirect to `{tenant}/impersonate/handoff/{token}`
4. Session stores `impersonator_id` (original admin)
5. User is redirected to appropriate portal path:
   - Buyer → `/portal/buyer`
   - Supplier → `/portal/supplier`
   - Staff → `/dashboard`

## Cross-subdomain handoff

Same pattern as god mode:

- 2-minute TTL, single-use cache token
- Target must belong to host tenant (`host_account` vs `user.resolveAccount()`)

## Stopping impersonation

Click **End impersonation** in the layout banner.

- Super admin on tenant host: may hand off back to central host via `impersonation_stop_handoff`
- Session restored to impersonator; `impersonator_id` cleared
- Access log records `impersonation.stop`

## Access logging

`AccessLogService` records:

- `impersonation.start` when impersonation begins (cross-host handoff path)
- `impersonation.stop` when returning to original user

Review in **Logs → Security** / access logs.

## God mode interaction

If session has `god_mode` without `impersonator_id`, **End impersonation** button triggers **endGodMode** instead.

## Best practices

- Inform tenant admins when impersonating their staff in production
- Prefer impersonation over sharing passwords
- Use buyer/supplier impersonation to verify CSV export, billing, and return flows
- Never impersonate to perform billing mutations without explicit approval

## Troubleshooting

| Issue | Cause |
|-------|-------|
| 403 on handoff | Expired token or wrong subdomain |
| Target not found | User deleted or wrong tenant |
| Stuck in impersonation | Clear session or use stop-handoff URL from central host |
