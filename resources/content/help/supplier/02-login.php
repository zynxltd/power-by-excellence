<?php

return [
    'category' => 'Supplier Portal',
    'slug' => 'supplier-portal-login',
    'title' => 'Signing In & Getting Access',
    'summary' => 'How supplier portal accounts are provisioned and how to sign in securely.',
    'audience' => 'supplier',
    'sort_order' => 20,
    'body' => <<<'MD'
## Overview

Supplier portal access is **provisioned by the platform administrator**, not self-registered. Your portal user is linked to a **Supplier** record, which in turn owns one or more **Sources (SIDs)** for API ingest tracking.

This article covers how accounts are created, how to sign in, and how portal access differs from API authentication.

## How access is provisioned

Your platform administrator sets up portal access in one of two ways:

### Option A - From the Supplier record

1. Admin navigates to **Suppliers → Edit** your supplier profile
2. Opens the **Portal access** section
3. Creates a user with email and password (or generates a temporary password)
4. Optionally clicks **Send credentials** to email login details

### Option B - From Users

1. Admin navigates to **Users → New**
2. Sets role to `supplier_portal`
3. Links the user to your `supplier_id`
4. Saves and shares credentials securely

You should receive:

- Your **tenant subdomain URL** (e.g. `https://excellence-uk.powerbyexcellence.test`)
- A **portal email address** and **password**
- Separately, your **API key** and **SID** values for lead submission (if applicable)

## Signing in step by step

1. Open your browser and navigate to `{your-subdomain}/login`
   - Example: `https://excellence-uk.powerbyexcellence.test/login`
2. Enter your **supplier portal email** and **password**
3. Click **Log in**
4. You are redirected to `/portal/supplier` (the Supplier Dashboard)

### First login checklist

- Confirm the dashboard header shows your **supplier name**
- Verify the **Your Sources (SID)** panel lists your expected source identifiers
- Navigate to `/portal/supplier/leads` and confirm recent test leads appear (if you have submitted any)

## API vs portal

| Method | Credential | Purpose |
|--------|------------|---------|
| **REST API** | API key in `Authorization` header | Real-time lead submission (`POST /api/v1/leads`) |
| **Supplier portal** | Email + password | Reporting, exports, payout visibility |

You need **both** if you ingest leads programmatically but reconcile earnings manually.

### Example: API ingest (separate from portal login)

```json
POST /api/v1/leads
Authorization: Bearer {your_api_key}

{
  "campaign_reference": "loans_uk_v1",
  "sid": "google_ppc",
  "ssid": "partner_42",
  "fields": {
    "firstname": "Jane",
    "lastname": "Smith",
    "email": "jane@example.com",
    "phone1": "07700900123"
  }
}
```

Portal login does **not** replace API authentication. You cannot submit leads through the portal UI.

## Password and account security

### Best practices

- Store API keys in a secrets manager or environment variable - they are typically shown only once at creation
- Use a unique, strong password for portal access
- Request **separate portal users** per team member when your account manager supports it
- Do not share credentials across sub-affiliates; use **SSID** tracking instead (see tracking article)

### Password reset

1. On the login page, click **Forgot your password?**
2. Enter your portal email address
3. Check your inbox for the reset link (also check spam)
4. Set a new password and sign in again at `/login`

If password reset fails, your email may not match a portal user record - contact your account manager.

## Multi-user and team access

When multiple people on your team need portal visibility:

1. Ask your account manager to create additional `supplier_portal` users linked to the same `supplier_id`
2. Each user gets their own email and password
3. All users see the same supplier-scoped data (leads, payouts, sources)

Rotating passwords when team members leave is recommended - ask the admin to reset or deactivate old accounts.

## Tips

- Bookmark `{subdomain}/login` - do not attempt to sign in on the central marketing domain
- If you also have a buyer account on another tenant, credentials are **not interchangeable** across subdomains
- Keep API keys out of browser bookmarks, shared documents, and client-side code
- After receiving credentials, submit a test lead via API and confirm it appears on `/portal/supplier/leads` within seconds

## Troubleshooting

| Problem | Likely cause | What to do |
|---------|--------------|------------|
| **Invalid credentials** | Wrong subdomain or typo in email | Confirm URL and email with your account manager |
| **Redirected to buyer portal** | User has buyer role, not supplier | Request a `supplier_portal` user linked to your supplier |
| **403 after login** | User not linked to a Supplier record | Admin must set `supplier_id` on your user profile |
| **Email verification required** | Tenant enforces verified emails | Click the verification link sent to your inbox |
| **No leads visible after login** | No API traffic yet, or wrong SID | Submit a test lead and verify SID matches a configured Source |
| **Cannot find API key in portal** | API keys are admin-managed | Request your key from the platform administrator - it is not displayed in the supplier portal |
MD,
];
