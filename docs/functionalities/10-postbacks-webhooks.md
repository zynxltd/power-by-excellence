# 10 - Postbacks & Webhooks

## Purpose

PowerByExcellence supports two outbound notification mechanisms:

- **Webhooks** - JSON POST payloads to external systems on platform events (lead sold, rejected, etc.). Managed under Integrations.
- **Postbacks** - Affiliate-style tracking pixels and URL callbacks (GET or POST) scoped to suppliers and campaigns. Includes audit log of fired postbacks.

Both fire during lead lifecycle events via `WebhookDispatcher` and `PostbackDispatcher`.

---

## Where to Find It

| Item | Location |
|------|----------|
| Webhooks list | `/webhooks` |
| Create/delete webhook | POST `/webhooks`, DELETE `/webhooks/{id}` |
| Postbacks manager | `/postbacks` |
| Create/update/delete postback | POST/PUT/DELETE `/postbacks` |
| Integrations hub | `/integrations` |
| Navigation | Sidebar → **Tools** → **Integration** → Webhooks; Postbacks via integrations or direct URL |
| Access | Account Admin |

---

## Event Types

### Webhook events (typical)

- `lead.sold`
- `lead.rejected`
- `lead.unsold`
- `lead.accepted`

### Postback events

- `lead.accepted`
- `lead.sold`
- `lead.rejected`
- `lead.unsold`
- `delivery.success`

Postback URLs support merge tags for lead fields (e.g. `[email]`, `[uuid]`).

---

## How to Test (Step-by-Step)

### Webhooks

#### 1. List webhooks

1. Log in as `uk@powerbyexcellence.test`
2. Navigate to `/webhooks` (or `/integrations` → Webhooks)

**Expected:** Empty list or any previously created webhooks. Create form visible.

#### 2. Create webhook

1. Click create / new webhook
2. Configure:
   - URL: `https://webhook.site/YOUR-UNIQUE-ID` (use webhook.site for testing)
   - Events: `lead.sold`, `lead.resold` (or all available)
   - Active: yes
3. Save

**Expected:** Webhook listed. Success flash.

#### 3. Trigger webhook via lead sale

1. Submit sync API lead that sells (see [09-api-and-sdk.md](./09-api-and-sdk.md))
2. Check webhook.site (or your endpoint) for incoming POST

**Expected:** JSON payload received containing lead UUID, campaign reference, status, field data, revenue. `Content-Type: application/json`.

#### 4. Delete webhook

1. Delete the test webhook
2. Submit another lead

**Expected:** No outbound call to deleted endpoint.

---

### Postbacks

#### 5. List postbacks

1. Navigate to `/postbacks`

**Expected:** Postback list (may be empty on fresh seed). Recent logs panel at bottom. Supplier and campaign dropdowns in create form.

#### 6. Create GET postback

1. Create postback:
   - Name: `QA Supplier Sold Pixel`
   - URL: `https://webhook.site/YOUR-ID?click_id=[sid]&status=sold&email=[email]`
   - Method: GET
   - Events: `lead.sold`
   - Supplier: Main Supplier
   - Campaign: Auto Insurance (optional scope)
   - Active: yes
2. Save

**Expected:** Postback appears in list with event badges. Log count: 0.

#### 7. Trigger postback

1. Submit sync API lead with `sid: google_search` that sells on Auto Insurance
2. Return to `/postbacks` → recent logs

**Expected:** New log entry: success status, lead UUID, postback name. webhook.site shows GET request with interpolated `sid` and `email`.

#### 7–11. POST postback, edit, deactivate, delete

Repeat create/trigger cycle for a POST postback on `lead.accepted`. Edit to add `lead.unsold`, deactivate, then delete. Confirm logs update accordingly and inactive postbacks stop firing.

---

## Expected Results (Summary)

- Webhooks send JSON POST on subscribed events
- Postbacks support GET and POST with field interpolation
- Postback manager shows audit log of recent fires
- Scoping by supplier/campaign limits which leads trigger postbacks
- Both respect tenant isolation - only fire for current platform's leads
- Failed outbound calls logged (verify in postback logs or delivery logs)

---

## Edge Cases

| Scenario | Expected behaviour |
|----------|-------------------|
| Invalid webhook URL | Logged failure; lead processing still completes |
| Webhook timeout | Non-blocking; lead sale not rolled back |
| Postback URL with unknown merge tag | Tag left literal or stripped |
| Postback scoped to wrong supplier | Does not fire for other suppliers' leads |
| Multiple postbacks on same event | All active postbacks fire |
| Webhook edit | Not supported - delete and recreate |
| HTTPS required | HTTP URLs may work in demo but blocked in production config |
| Quarantined lead | May fire `lead.accepted` but not `lead.sold` |

---

## Related Docs

- [09-api-and-sdk.md](./09-api-and-sdk.md) - trigger events via ingest
- [08-suppliers-and-portals.md](./08-suppliers-and-portals.md) - supplier scoping
- [12-operations-and-logs.md](./12-operations-and-logs.md) - delivery log correlation
