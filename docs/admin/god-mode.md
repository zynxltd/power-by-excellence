# God Mode

God mode lets a **super admin** operate inside a tenant's admin UI as themselves (not impersonating a tenant user), with full cross-tenant privileges on that subdomain.

## When to use

- Debugging tenant-specific routing, billing, or campaign config
- Verifying branding and portal behaviour on the real hostname
- Support investigations where you need admin menus, not a buyer/supplier view

## Starting god mode

### From central host

1. Go to **Partner Platforms** (`/accounts`) or **Command Center**
2. Click **Visit** on a tenant
3. Browser redirects to `{tenant}/god-mode/handoff/{token}`
4. Token is consumed; you are logged in as super admin on the tenant host
5. Session contains `god_mode: true` and `current_account_id`

### From tenant host (rare)

If already on the correct subdomain, visit sets session and redirects to `/dashboard` without handoff.

## Handoff security

| Property | Value |
|----------|-------|
| Token storage | Cache key `god_mode_handoff:{token}` |
| TTL | 2 minutes |
| Single use | `Cache::pull` on consume |
| Validation | Super admin ID + account must match host tenant |

Invalid or expired tokens return **403**.

## UI indicators

- Layout shows **Exit god mode** (not "End impersonation")
- Dashboard subtitle reflects managed tenant name
- `HandleInertiaRequests` exposes `auth.godMode` to Vue

## Ending god mode

### From tenant subdomain

Click **Exit god mode**:

- If on tenant host: may redirect to central host via `god_mode_stop_handoff` token
- Clears `god_mode` and `current_account_id`
- Returns to Command Center on central host

### Session domain

For handoffs to preserve login across subdomains, set:

```env
SESSION_DOMAIN=.powerbyexcellence.test
```

(local) or the production parent domain.

## God mode vs impersonation

| | God mode | Impersonation |
|---|----------|---------------|
| **Identity** | Super admin | Target user |
| **Permissions** | Super admin + tenant context | Exactly target user's role |
| **Use case** | Platform ops on tenant | Reproduce buyer/supplier/staff issue |
| **Banner** | Exit god mode | End impersonation |

## Tips

- Do not confuse god mode with **account switch** on central host — switch alone does not fix subdomain session issues
- Cross-tenant bookmarks while in god mode can trigger tenant access redirects; always exit before switching partners
