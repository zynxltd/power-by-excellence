<?php

return [
    'category' => 'Integrations',
    'slug' => 'api-keys',
    'title' => 'API Keys, Webhooks & Security',
    'summary' => 'REST permissions, outbound webhooks, 2FA, and IP allowlists.',
    'audience' => 'tenant',
    'sort_order' => 110,
    'body' => <<<'MD'
## Overview

API keys let suppliers and integrations ingest leads programmatically. Webhooks push events out to your systems when leads change state. Together they form the integration layer - but they also expand your attack surface. This guide covers creating and scoping keys, configuring webhooks, hardening staff accounts, and operational security practices.

All API keys are tenant-scoped: a key created on your platform cannot read or write another account's data.

---

## API keys

Navigate to **Integrations → API Keys** (or **Settings → API Keys**).

### Permissions (scopes)

Each key carries scoped permissions. Common scopes:

| Scope | Allows |
|-------|--------|
| `leads:write` | Ingest leads via `POST /api/v1/leads` |
| `leads:read` | Query lead status and details |
| `reports:read` | Access report endpoints |

Grant the minimum scopes required. A read-only reporting integration does not need `leads:write`.

### IP allowlist

Optionally restrict a key to specific source IPs. When enabled, requests from other IPs receive `403 Forbidden` even with a valid key. Use this for fixed-egress affiliate servers or your own ETL infrastructure.

### Supplier linkage

Keys can be linked to a **supplier** record. When linked, ingested leads automatically attribute `supplier_id` - no need for affiliates to pass supplier reference in every request.

### Step-by-step: create a production API key

1. Go to **Integrations → API Keys → New**.
2. Enter a descriptive name (e.g. `Acme Media - production ingest`).
3. Select permissions: typically `leads:write` for ingest-only affiliates.
4. Link to the **supplier** record if this key belongs to a specific affiliate.
5. Add IP allowlist entries if the affiliate provides static egress IPs.
6. Click **Create** and copy the key immediately - it is shown only once.
7. Send the key to the affiliate securely (password manager share link, not email body).
8. Ask them to test with a single lead before bulk traffic.

### Step-by-step: rotate a compromised key

1. Create a **new** key with the same permissions and supplier linkage.
2. Provide the new key to the affiliate and confirm their next ingest succeeds.
3. **Revoke** or delete the old key.
4. Review **Access logs** for suspicious activity on the old key's window.
5. Document the rotation date for compliance records.

### Example: staging vs production keys

| Environment | Key name | IP allowlist | Supplier |
|-------------|----------|--------------|----------|
| Staging | `Acme - staging` | Your office IP | Acme (staging supplier) |
| Production | `Acme - prod` | Affiliate server IPs | Acme |

Never reuse production keys in staging - staging errors can pollute live campaigns.

---

## Webhooks

Navigate to **Integrations → Webhooks**.

Webhooks deliver HTTP POST payloads to your endpoint when platform events occur.

### Available events

| Event | Fires when |
|-------|------------|
| `lead.received` | Lead passes initial validation |
| `lead.sold` | Lead sold to a buyer |
| `lead.accepted` | Buyer accepted post |
| `lead.rejected` | Lead rejected (validation or distribution) |

Payloads include the lead UUID and field data (respecting your configured field visibility).

### Step-by-step: configure a webhook

1. Go to **Integrations → Webhooks → New**.
2. Enter your endpoint URL (must be HTTPS in production).
3. Select events to subscribe to - start with `lead.sold` if you only need sold notifications.
4. Optionally set a signing secret for HMAC verification of inbound payloads.
5. Save and use the **Test** button to send a sample payload.
6. Confirm your endpoint returns `2xx` within the timeout window.
7. Monitor webhook delivery logs for retries and failures.

### Example: verifying webhook signatures

Your endpoint should:

1. Read the raw request body (before JSON parsing).
2. Compute HMAC-SHA256 using your signing secret.
3. Compare to the signature header sent by the platform.
4. Reject requests with mismatched signatures before processing.

### Webhook troubleshooting

| Symptom | Likely cause |
|---------|--------------|
| No payloads received | URL wrong, firewall blocking, or event not subscribed |
| Intermittent failures | Endpoint timeout - respond with `200` quickly and process async |
| Duplicate events | Your endpoint retried; use lead UUID for idempotency |
| 401/403 on your side | Auth middleware on your endpoint rejecting platform IPs |

---

## Security

### Two-factor authentication (2FA)

Enable **2FA** on all staff accounts:

1. Each user opens **Profile → Security**.
2. Scan the TOTP QR code with an authenticator app.
3. Save recovery codes in a secure location.
4. Enforce 2FA for admin roles via platform policy if available.

Staff accounts have full tenant access - a compromised password without 2FA exposes all campaigns, buyers, and billing.

### Access logs and security logs

Review periodically:

- **Access logs** - who logged in, from which IP, when
- **Security logs** - failed logins, API key auth failures, permission denials

Set a monthly calendar reminder to scan for unfamiliar IPs or brute-force patterns.

### API key hygiene

- Rotate keys when an affiliate offboards or changes technical teams.
- Never commit API keys to git, Slack, or ticket systems.
- Use separate keys per environment (staging vs production).
- Revoke unused keys - old keys are a common breach vector.

### Portal user security

Buyer and supplier portal users have limited scope but still see sensitive data:

- Use strong passwords and rotate when team members leave.
- One portal user per buyer/supplier org is typical; create additional users for teams as needed.
- Direct portal users to Help Centre articles rather than sharing admin credentials.

---

## Support tickets

Navigate to **Support** to manage tickets from tenants and portal users.

### Workflow

1. Portal users or staff open a ticket with a subject and description.
2. Assignees receive notification (email if configured).
3. Reply in-thread - the requester sees updates in their portal or email.
4. Close the ticket when resolved; reopen if the issue recurs.

Use Support for affiliate integration questions, buyer billing disputes, and portal access issues - keep API keys and passwords out of ticket bodies.

---

## Troubleshooting

### API returns 401 Unauthorized

- Confirm the `Authorization: Bearer <key>` header is present.
- Check the key has not been revoked.
- Verify the request hits the correct tenant domain (keys are not portable across accounts).

### API returns 403 Forbidden

- Key may lack the required scope (e.g. `reports:read` for report endpoints).
- IP allowlist may block the request source - check affiliate egress IP.
- Campaign may be inactive - ingest rejects at pipeline start.

### Webhook payloads missing fields

- Field visibility may be restricted on the webhook config.
- Sold events include buyer reference; received events may omit post-sale fields.

### Affiliate says "key stopped working" after nothing changed

- Check if key was accidentally revoked during a cleanup.
- Confirm their server IP has not changed (if allowlist enabled).
- Review Access logs for `401` spikes starting at a specific timestamp.

---

## Tips

- Never commit API keys to git - use environment variables or secret managers.
- Use separate keys per environment (staging vs production).
- Rotate API keys on affiliate offboarding - do not leave dormant keys active.
- Subscribe webhooks to the minimum events needed to reduce endpoint load.
- Enable 2FA on every staff account before inviting additional admins.
MD,
];
