# 09 — API & SDK

## Purpose

The **REST API** (`/api/v1`) is the primary machine interface for lead ingest, status polling, search, reprocessing, reports, quarantine management, and buyer operations. **SDKs** (JavaScript and PHP) wrap common API calls. All API requests authenticate via bearer token (API key) and are scoped to the key's platform (tenant).

---

## Where to Find It

| Item | Location |
|------|----------|
| API base | `https://powerbyexcellence.test/api/v1` |
| API key admin | `/api-keys` |
| Integrations hub | `/integrations` |
| JavaScript SDK | `/sdk/pbe-leads.js` |
| PHP SDK | `sdk/php/PbeClient.php` (repo) |
| Built-in ping simulator | `POST /api/v1/ping` |
| Built-in post simulator | `POST /api/v1/post` |
| Navigation | Sidebar → **Tools** → **Integration** → API Keys |

---

## API Key Permissions

| Permission | Endpoints |
|------------|-----------|
| `leads.create` | `POST /leads`, `POST /leads/import` |
| `leads.read` | `GET /leads/{uuid}`, `GET /leads/queue/{id}`, `POST /leads/search`, `POST /leads/{uuid}/reprocess` |
| `reports.read` | `GET /reports/leads`, `GET /reports/revenue` |
| `platform.read` | `GET /platform`, `GET /platform/campaigns/{reference}` |
| `quarantine.manage` | `GET /quarantine`, `POST /quarantine/{uuid}/release\|reject` |
| `buyers.manage` | `POST /buyers/{id}/feedback`, `POST /buyers/{id}/credit` |
| `*` (admin key) | All of the above |

Keys are printed once when running `php artisan migrate:fresh --seed`.

---

## How to Test (Step-by-Step)

### 1. Generate API key (admin UI)

1. Log in as `uk@powerbyexcellence.test`
2. Navigate to `/api-keys`
3. Create key: name `QA Test Key`, type Administrator, permissions `*`
4. Copy token immediately

**Expected:** Token shown once. Key appears in list (token masked). Revoke button available.

### 2–4. Sync, async, and queue polling

**Sync:** POST `/leads` with `"sync": true` — expect HTTP 200 and final status in response.

**Async:** Omit `sync` — expect HTTP 202 with `queue_id`. Poll `GET /leads/queue/{queueId}` after `php artisan queue:work` runs.

**Show:** `GET /leads/{uuid}` returns financials, buyer, and delivery logs.

Example sync payload fields: `campaign_reference`, `sid`, `firstname`, `lastname`, `email`, `phone1`, `zipcode`, plus vertical fields.

### 5–7. Search, simulators, SDK

Use `POST /leads/search` and `POST /leads/{uuid}/reprocess` for paginated search and re-queue.

Ping simulator: `POST /api/v1/ping` with `{ "floor": 10, "bid_hint": 18 }` → `{ "Success": true, "Cost": 18 }`.

Post simulator: `POST /api/v1/post` → `{ "Success": true, "Approved": true }`. No auth required.

Load `/sdk/pbe-leads.js` as ESM, call `createClient({ apiKey, baseUrl: '/api/v1' }).ingestLead({...})` with `sync: true`.

### 9. Platform export (own portal / external stack)

1. Create administrator API key (includes `platform.read` via `*` permission)
2. `GET /api/v1/platform` with bearer token
3. Expect JSON with `platform`, `campaigns`, `buyers`, `suppliers`, `webhooks`, `postbacks`, `forms`
4. Optional: `GET /api/v1/platform?include=campaigns,buyers` for partial sync
5. Optional: `GET /api/v1/platform/campaigns/loans-uk` for one campaign

**Use case:** Partner runs their own white-label portal. They poll platform export to mirror campaign schemas and buyer routing locally, then POST leads via `/leads` and poll status — no admin UI login required.

### 8. Auth failure and revoke

1. Request without `Authorization` header → 401
2. Revoke key in `/api-keys`, retry → 401
3. Supplier key posting to another tenant's campaign → 403, no lead created

---

## Expected Results (Summary)

- Sync ingest returns final status in one request (< 3s typical)
- Async ingest requires queue worker
- API keys scoped to single platform (tenant)
- Supplier keys limited to `leads.create` and `leads.read`
- Built-in ping/post simulators power demo deliveries
- SDK available at `/sdk/pbe-leads.js` for browser/ESM use

---

## Edge Cases

| Scenario | Expected behaviour |
|----------|-------------------|
| Missing required campaign fields | 422 validation error |
| Duplicate email within dedupe window | `status: rejected` |
| Invalid campaign_reference | 404 or validation error |
| Malformed JSON | 400 bad request |
| sync:true on slow campaign | HTTP timeout risk on 10-tier tree |
| Import endpoint | `POST /leads/import` accepts CSV bulk |
| Quarantine API | List and release/reject quarantined leads |

---

## Related Docs

- [03-deliveries-and-10-tier-ping-tree.md](./03-deliveries-and-10-tier-ping-tree.md) — ping-post flow
- [06-form-builder.md](./06-form-builder.md) — alternative ingest
- [10-postbacks-webhooks.md](./10-postbacks-webhooks.md) — outbound notifications
