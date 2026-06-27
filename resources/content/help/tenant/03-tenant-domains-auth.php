<?php

return [
    'category' => 'Partner Platform',
    'slug' => 'tenant-domains-auth',
    'title' => 'Tenant Domains, Login & Users',
    'summary' => 'How subdomains, sessions, and roles work for your platform.',
    'audience' => 'tenant',
    'sort_order' => 30,
    'body' => <<<'MD'
## Overview

Each partner platform runs on a dedicated hostname: `{slug}.{base_domain}` (e.g. `excellence-uk.powerbyexcellence.test`) or a custom domain stored in `accounts.domain`. Your team, buyers, and suppliers all authenticate on **your** hostname - never on another partner's subdomain.

Understanding hostname rules prevents login confusion, session issues, and accidental cross-tenant access attempts.

## Hostname model

| Host type | Example | Who uses it |
|-----------|---------|-------------|
| **Central / marketing** | `powerbyexcellence.test` | Super admin, public marketing, blog, central help |
| **Tenant subdomain** | `excellence-uk.powerbyexcellence.test` | Account admin, staff, buyer portal, supplier portal |
| **Custom domain** | `leads.yourbrand.com` | Same as tenant - CNAME to platform |

### Who signs in where

| Role | Host | Login path |
|------|------|------------|
| **Super admin** | Central domain only | `/login` on `powerbyexcellence.test` |
| **Account admin / staff** | Tenant subdomain only | `/login` on your tenant URL |
| **Buyer portal** | Tenant subdomain | `/login` → redirects to `/portal/buyer` |
| **Supplier portal** | Tenant subdomain | `/login` → redirects to `/portal/supplier` |

**Cross-tenant access is blocked** - UK admins cannot authenticate on the Canada subdomain. Credentials are scoped to the account that created them.

## User roles

| Role | Code | Access |
|------|------|--------|
| **Platform administrator** | `account_admin` | Full tenant admin - all modules |
| **Staff** | `staff` | Module-restricted via `allowed_modules` JSON |
| **Buyer portal** | `buyer_portal` | `/portal/buyer` only |
| **Supplier portal** | `supplier_portal` | `/portal/supplier` only |

### Staff module restrictions

Staff users can be limited to specific areas (e.g. operations and reports only):

1. Go to **Users** → **New** or edit existing user (`/users`)
2. Set role to **Staff**
3. Select **Allowed modules** checkboxes (campaigns, buyers, operations, billing, etc.)
4. Save - user sees only permitted sidebar items

Use this for call-centre teams, finance-only users, or external contractors.

## Managing users - step by step

### Invite an admin or staff member

1. Navigate to **Users** (`/users`)
2. Click **New user**
3. Fill in:

| Field | Notes |
|-------|-------|
| **Name** | Display name |
| **Email** | Login identifier - must be unique per tenant |
| **Role** | `account_admin` or `staff` |
| **Password** | Set initial password; user can reset later |
| **Allowed modules** | Staff only - leave empty for full staff access if configured |

4. Save - user can log in immediately at your tenant `/login`

### Create buyer portal access

1. Open **Buyers** → edit buyer (`/buyers/{id}/edit`)
2. Scroll to **Portal access** section
3. Enter portal email, name, and password
4. Save - buyer logs in at tenant `/login` and lands on `/portal/buyer`

Alternatively create a user with `buyer_portal` role linked to the buyer record.

### Create supplier portal access

1. Open **Suppliers** → edit supplier
2. Complete **Portal access** step in the supplier wizard
3. Supplier logs in and sees only their submitted leads and payouts at `/portal/supplier`

## Branding

Branding controls what partners see before they enter the app.

### Configure branding

1. Go to **Settings** → **Branding** (`/settings/branding`)
2. Set:

| Setting | Effect |
|---------|--------|
| **brand_name** | Page titles, login heading, portal header |
| **logo** | Login page and admin header |
| **favicon** | Browser tab icon |

3. Save and open `/login` in a private window to verify

Branding applies to login, buyer portal, supplier portal, and hosted forms on your tenant domain.

## Sessions and environment

### SESSION_DOMAIN

For cross-subdomain sessions (e.g. super-admin **god mode handoff** from central to tenant), set in `.env`:

```
SESSION_DOMAIN=.powerbyexcellence.test
```

Without a leading dot, cookies are host-only and will not transfer between `powerbyexcellence.test` and `excellence-uk.powerbyexcellence.test`.

### God mode handoff

Super admins on the central domain can impersonate tenant context via a one-time handoff token (`/god-mode/handoff/{token}`). This is for platform support - not for day-to-day tenant admin work.

## Security practices

| Practice | Why |
|----------|-----|
| Issue portal credentials from buyer/supplier forms | Ensures correct partner linkage |
| Use staff roles for limited access | Reduces risk of accidental config changes |
| Revoke API keys when suppliers churn | Keys at `/api-keys` - revoke, don't just delete supplier |
| Review **Security log** (`/logs/security`) | Failed logins, suspicious access |
| Enable IP allowlists on production API keys | Restricts ingest to known supplier IPs |

## Troubleshooting

| Symptom | Cause | Resolution |
|---------|-------|------------|
| "Invalid credentials" on tenant URL | User exists on different tenant | Create user on correct subdomain |
| Portal user sees 403 on dashboard | User not linked to buyer/supplier | Re-save portal section on partner record |
| Admin redirected to billing lock | Account `billing_status` locked | Resolve at `/billing/lock` |
| Session lost between subdomains | `SESSION_DOMAIN` not set | Add leading-dot domain in `.env` |
| Staff sees empty sidebar | No modules allowed | Edit user → check `allowed_modules` |
| Buyer can access admin routes | Wrong role assigned | Must be `buyer_portal`, not `account_admin` |

## Tips

- Issue buyer/supplier credentials from their respective admin forms - linkage is automatic
- Use **Users** to invite staff with limited modules for call-centre teams
- Tell partners to bookmark **your** tenant URL, not the central marketing site
- Custom domains require DNS CNAME setup - contact platform support if provided
- Check **Access log** (`/logs/access`) to audit who logged in and when
MD,
];
