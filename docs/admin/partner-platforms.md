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

Super admins provision new partner platforms from the **central host** (`powerbyexcellence.test` in local dev).

### UI flow

1. Sign in as **super admin** on the central host.
2. Open **Partner Platforms** (`/accounts`).
3. Click **New platform** (`/accounts/create`).
4. Complete the form:
   - **Platform name** — display label for the tenant.
   - **Subdomain slug** — becomes `{slug}.{base_domain}` (e.g. `acme-leads.powerbyexcellence.test`).
   - **Custom domain** (optional) — override the default subdomain hostname.
   - **Country, currency, timezone** — defaults for new campaigns and billing.
   - **Account admin** — first tenant user (`account_admin` role) who can configure buyers, suppliers, and campaigns.
5. Submit. The platform appears on the partner list immediately.
6. **Local dev:** run `herd link {slug}.{base_domain}` or `php artisan platform:link-tenants` so the subdomain resolves.
7. Use **Open portal ↗** or **Visit** to enter god mode on the new tenant.

The provisioner creates only the `Account` record and one `account_admin` user — no demo buyers, suppliers, or campaigns. Configure those after visiting the tenant portal.

### API / code

| Route | Method | Purpose |
|-------|--------|---------|
| `accounts.create` | GET | Create form |
| `accounts.store` | POST | Provision tenant |

Service: `App\Services\Platform\TenantProvisioner`.

### Required data

| Field | Purpose |
|-------|---------|
| `name` | Platform display name |
| `slug` | Subdomain segment (unique, lowercase) |
| `domain` | Optional custom hostname |
| `default_currency` | GBP, USD, CAD, EUR, etc. |
| `default_country` | 2-letter ISO code |
| `timezone` | PHP timezone identifier |
| `admin_name`, `admin_email`, `admin_password` | First tenant admin |

Reserved slugs (`api`, `admin`, `www`, etc.) are rejected.

### Demo / seed data

Seeded demo tenants come from `database/seeders/PlatformSeeder.php` and `config/tenant_platforms.php` with full buyers, suppliers, and campaigns. Use the UI flow above for real onboarding without demo data.

## DNS & environments

| Environment | Pattern |
|-------------|---------|
| Local (Herd) | `{slug}.powerbyexcellence.test` |
| Production | Custom domain or `{slug}.powerbyexcellence.com` |

`TenantResolver::portalUrl()` builds correct URLs for visit/handoff links.

## Tips

- Use **Command Center** for at-a-glance health before visiting a troubled tenant
- Document which slug maps to which commercial partner for support handoffs
