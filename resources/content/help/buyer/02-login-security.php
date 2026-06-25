<?php

return [
    'category' => 'Buyer Portal',
    'slug' => 'buyer-portal-login',
    'title' => 'Signing In & Account Security',
    'summary' => 'How to log in on your partner subdomain, reset your password, and keep your buyer account secure.',
    'audience' => 'buyer',
    'sort_order' => 20,
    'body' => <<<'MD'
## Overview

Buyer portal accounts are created by your platform administrator — there is no self-registration. When your buyer profile is set up, an administrator links a **Buyer Portal** user to your buyer record and provides credentials by email or a secure handoff.

Your login is scoped to a single **tenant subdomain** (partner platform). The same email/password will not work on the central marketing site, super-admin console, or another partner's domain. This isolation protects lead data and billing across brands.

## Before you sign in

Confirm you have:

1. The correct **partner URL** (e.g. `https://excellence-uk.powerbyexcellence.test/login`)
2. A **Buyer Portal** role user — not staff admin or supplier credentials
3. An **active** account (not suspended by your administrator)

## Signing in step by step

1. Open your partner platform URL in a modern browser (Chrome, Firefox, Safari, or Edge)
2. Navigate to `/login` on that subdomain — e.g. `https://insurance-ca.powerbyexcellence.test/login`
3. Enter the **email** address on your user record
4. Enter your **password** (case-sensitive)
5. Optionally tick **Remember me** on a trusted personal device only
6. Click **Log in**
7. On success, you are redirected to **Buyer Dashboard** at `/portal/buyer`

The login page shows your partner branding (logo, colours) when configured by the platform operator. You should not see the generic central marketing site unless your administrator explicitly uses that domain.

## What you see after login

| UI element | Location | Purpose |
|------------|----------|---------|
| Buyer Dashboard hero | `/portal/buyer` | Welcome banner with buyer name |
| Sidebar navigation | Left rail | Dashboard, My Leads, Billing, Profile |
| Credit Balance stat | Dashboard | Current prepay credits |
| Account menu | Top right | Profile, sign out |

Super-admin and tenant **staff** accounts use different login flows and land on `/admin` — they cannot use buyer credentials to access `/portal/buyer` unless impersonation is used by support staff.

## Password reset

If you forget your password:

1. On `/login`, click **Forgot password?**
2. Enter your registered email address
3. Submit the form — a reset link is sent to that inbox
4. Open the email and follow the link (links expire after a set period)
5. Enter a new password twice and save
6. Return to `/login` on the **same subdomain** and sign in

If you do not receive the email within a few minutes, check spam/junk folders. Still nothing? Ask your platform administrator to confirm the email on your user record matches what you typed, and that the account is not suspended.

## Session & subdomain rules

- **Sessions are domain-scoped** — cookies set on `excellence-uk.powerbyexcellence.test` do not apply to `solar-us.powerbyexcellence.test`
- **One tenant per login** — if you purchase leads on multiple brands, each requires a separate user and login on that brand's subdomain
- **Sign out on shared PCs** — use the account menu **Log out** when finished
- **Verified email** may be required — if prompted to verify email after first login, complete verification before accessing portal routes

## Account security best practices

1. **Do not share credentials** — request separate portal users for each team member so audit trails stay accurate
2. **Use strong passwords** — mix length with numbers and symbols; avoid reusing passwords from other systems
3. **Report suspicious access** — if you see leads you did not purchase or unknown sessions, contact your administrator immediately
4. **Avoid public Wi‑Fi** for portal access when handling PII; use VPN if your policy requires it
5. **Two-factor authentication** — if your platform enables 2FA for portal users in future policy, enable it when offered

## Example scenarios

### Wrong bookmark

Tom bookmarked `https://powerbyexcellence.test/login` (central domain). He enters valid buyer credentials but sees: **"Partner platforms sign in at https://excellence-uk.powerbyexcellence.test/login"**. He updates his bookmark to the partner URL and signs in successfully.

### New team member

A call-centre agent needs read access to leads. Instead of sharing the manager's password, the manager asks the platform admin to create a second **Buyer Portal** user linked to the same buyer ID. Each agent signs in with their own email for accountability.

### Suspended during contract review

During a billing dispute, an administrator suspends the portal user. On login, the user sees: **"This account has been suspended. Contact your platform administrator."** No portal pages load until the admin reactivates the account.

## Tips

- Save the exact login URL from your welcome email — do not guess the subdomain
- After password reset, always return to your partner `/login`, not the central site
- If your organisation uses IP allowlists (configured by admin), connect from an approved network or VPN
- Clear browser cookies for the subdomain only if you get stuck in a redirect loop after a domain change
- Use **Profile** (`/profile`) to update your display name or password when self-service is enabled

## Troubleshooting

| Error or symptom | Meaning | Resolution |
|------------------|---------|------------|
| **These credentials do not match our records.** | Wrong email/password | Reset password or confirm credentials with admin |
| **This account has been suspended. Contact your platform administrator.** | `is_suspended` flag on user | Contact admin to reactivate |
| **This account is not registered on {Brand Name}.** | User belongs to a different tenant | Use the subdomain from your buyer agreement |
| **Your account is not registered on this platform domain.** | Middleware blocked cross-tenant access | Sign out; log in on correct subdomain |
| **Partner platforms sign in at …** | Buyer tried central host login | Use the partner URL shown in the message |
| **Too many login attempts.** | Rate limit (5 attempts) | Wait the stated minutes, then retry |
| Redirect to `/admin` instead of portal | User has staff role, not Buyer Portal | Ask admin to create or fix your portal user role |
| **403 — Buyer account not linked to this user.** | User missing buyer link | Admin must attach `buyer_id` to your user |
| Password reset link invalid | Expired or already used | Request a fresh reset from `/login` |
MD,
];
