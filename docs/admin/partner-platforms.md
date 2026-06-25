# Partner Platforms

**Route:** `/accounts` (central host, super admin only)

Partner platforms are **tenant accounts** — isolated lead distribution instances on dedicated subdomains (e.g. `excellence-uk.powerbyexcellence.test`).

## Listing

The index shows every `Account` with:

- Brand name and slug
- Resolved domain (`accounts.domain` or `{slug}.{base_domain}`)
- Counts: campaigns, leads, buyers, suppliers
- Primary admin user (first `account_admin` or `staff`)

## Switching context (central host)

**Switch** sets `session('current_account_id')` without leaving the central host. Use this when:

- Browsing super-admin notification tools scoped to one tenant
- Preparing to open tenant-scoped admin routes that read session context

This does **not** log you in on the tenant subdomain — data may still be filtered incorrectly for subdomain-only features.

## Visiting a tenant (recommended)

**Visit** is the correct way to manage a tenant:

1. Sets `current_account_id` and `god_mode` in session
2. On central host: redirects to tenant subdomain via one-time handoff token
3. On tenant host already: redirects to tenant dashboard

See [God Mode](./god-mode.md).

## Tenant isolation

- All operational data is scoped by `account_id`
- Buyers, suppliers, campaigns, and leads never cross tenants
- Portal users authenticate only on their tenant hostname

## Creating tenants

New accounts are provisioned via seeders (demo) or platform onboarding (production). Each needs:

| Field | Purpose |
|-------|---------|
| `slug` | Subdomain segment |
| `domain` | Optional custom hostname |
| `default_currency` | GBP, USD, CAD, EUR |
| Admin user | `account_admin` role linked to account |

Run `php artisan platform:link-tenants` locally (Herd) so subdomains resolve.

## DNS & environments

| Environment | Pattern |
|-------------|---------|
| Local (Herd) | `{slug}.powerbyexcellence.test` |
| Production | Custom domain or `{slug}.powerbyexcellence.com` |

`TenantResolver::portalUrl()` builds correct URLs for visit/handoff links.

## Tips

- Use **Command Center** for at-a-glance health before visiting a troubled tenant
- Document which slug maps to which commercial partner for support handoffs
