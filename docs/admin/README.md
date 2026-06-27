# Super Admin Documentation

Internal guides for **PowerByExcellence platform operators** (super admins). These documents are **not** published to the public Help Centre at `/help`.

Each partner platform is **fully self-serviced** by its tenant admin (campaigns, buyers, suppliers, branding, billing). Super admins provision tenants and monitor network health — day-to-day operations stay with the partner.

| Guide | Description |
|-------|-------------|
| [Command Center](./command-center.md) | Cross-tenant health, delivery metrics, ops checks |
| [Partner Platforms](./partner-platforms.md) | Tenant list, creating platforms, switching context, visiting subdomains |
| [God Mode](./god-mode.md) | Cross-subdomain super-admin sessions |
| [Impersonation](./impersonation.md) | Acting as tenant staff, buyers, or suppliers |
| [Live Feed](./live-feed.md) | Real-time platform-wide lead event stream |
| [Tenant Billing](./tenant-billing.md) | Finance and billing while managing a tenant |

**Product UX (all roles):** [UX & Navigation Audit](../UX_NAVIGATION_AUDIT.md) - friction analysis and IA recommendations.

## Audience

- **Super admin** users (`isSuperAdmin()`)
- Routes on the **central host** (`powerbyexcellence.test` / production apex) where noted
- Tenant staff should use the [Help Centre](/help) (`tenant`, `buyer`, `supplier` audiences)

## Security

- God mode and impersonation handoffs use **single-use cache tokens** (2-minute TTL)
- All impersonation starts are recorded in **access logs**
- Never share god-mode handoff URLs; they authenticate the super admin on the tenant subdomain

## Related code

| Area | Location |
|------|----------|
| Command Center | `app/Http/Controllers/Admin/CommandCenterController.php` |
| Accounts / visit | `app/Http/Controllers/Admin/AccountController.php` |
| God mode handoff | `app/Http/Controllers/Admin/GodModeHandoffController.php` |
| Impersonation | `app/Http/Controllers/Admin/ImpersonationController.php` |
| Help (public only) | `app/Http/Controllers/HelpController.php`, `resources/content/help/` |
