# PowerByExcellence - 4-Week End-to-End Test Schedule

> **Purpose:** Structured manual + automated QA over **20 working days**, up to **6 hours/day** (~120 hours total).  
> **Audience:** QA lead, product owner, or engineer doing pre-launch hardening.  
> **Companion docs:** [`functionalities/README.md`](./functionalities/README.md) · [`IMPLEMENTATION_STATUS.md`](./IMPLEMENTATION_STATUS.md)

---

## Goals

1. Exercise every major user journey **admin → campaign → ingest → distribute → portal → billing → reporting**.
2. Deliberately break things - edge cases, tenancy boundaries, billing locks, caps, timeouts.
3. Run the automated suite daily; file defects with repro steps before moving on.
4. End week 4 with a **sign-off checklist** and prioritized bug backlog.

---

## Daily rhythm (6 hours)

| Block | Time | Activity |
|-------|------|----------|
| **Setup & automation** | 0:00–0:30 | Fresh env check, `php artisan test`, note failures |
| **Core E2E** | 0:30–3:30 | Follow that day’s primary scenarios (hands-on) |
| **Edge cases** | 3:30–5:30 | Deliberate failure / boundary tests |
| **Wrap-up** | 5:30–6:00 | Log defects, update daily checklist, note blockers |

---

## Environment (run once, re-verify weekly)

```bash
composer install
cp .env.example .env   # if needed
php artisan key:generate
php artisan migrate:fresh --seed
npm install && npm run build
```

**Terminal 2 (always running during API/async tests):**

```bash
php artisan queue:work
```

**Optional Terminal 3 (Horizon, if Redis configured):**

```bash
php artisan horizon
```

### Hosts & accounts

| Host | Role |
|------|------|
| `powerbyexcellence.test` | Central - super admin, marketing, Command Center |
| `excellence-uk.powerbyexcellence.test` | UK tenant admin + portals |
| `partner-solar-us.powerbyexcellence.test` | US tenant (currency/timezone checks) |

**Password for all demo users:** `password`

| Email | Role |
|-------|------|
| `admin@powerbyexcellence.test` | Super Admin |
| `uk@powerbyexcellence.test` | UK Account Admin |
| `us@powerbyexcellence.test` | US Account Admin |
| `buyer-portal@excellence-uk.test` | UK Buyer Portal |
| `supplier-portal@excellence-uk.test` | UK Supplier Portal |

Copy **Admin API** and **Supplier API** keys from seed output on day 1.

### Defect log template

Create `docs/qa/DEFECT_LOG.md` (or use Linear/Jira) with:

```
ID | Date | Area | Severity | Steps | Expected | Actual | Screenshot/URL
```

**Severity:** P0 (data loss / security / billing wrong) · P1 (core flow broken) · P2 (workaround exists) · P3 (cosmetic)

---

## Focus areas (dedicated half-days)

Use these as add-ons to the weekly plan or swap into any Friday regression slot.

### A - Tenant platform lock (`/accounts/billing`)

**Super admin (central host only)**

- [ ] Open **Tenant billing** index - overdue/locked count banner accurate
- [ ] Review **Platform lock impact** matrix (Active / Past due / Locked)
- [ ] Edit tenant → set **Past due** + past due date → save
- [ ] Verify tenant admin: dashboard may load but **processing blocked**; API ingest returns **402**
- [ ] Set status **Locked** + lock reason → save (or quick **Lock platform** button)
- [ ] Tenant admin redirected to `/billing/lock` - reason visible
- [ ] API ingest **402**; lead does not process
- [ ] **Unlock platform** → full access restored; ingest works again
- [ ] Compare **past due** vs **locked**: ingest suspended only on locked (not past due)

### B - SMS & email auto responders (`/features/auto-responders`)

- [ ] From **Automation** → **SMS & email responders** shortcut works
- [ ] Create **email** responder: trigger `on_lead_sold`, campaign-scoped, tags `{{firstname}}`
- [ ] Create **SMS** responder: trigger `on_lead_received`, `phone1` field
- [ ] Sell a test lead (sync API) → check lead event log for `auto_responder.sent`
- [ ] Missing phone on SMS responder → no crash, no send
- [ ] Duplicate responder → clone from existing
- [ ] Delete responder → no longer fires on next sold lead
- [ ] Provider `log` in dev - message in platform log; Twilio if configured

### C - Help Centre overhaul (`/help`)

- [ ] Index: audience filter chips (Platform / Buyer / Supplier / All)
- [ ] **Learning paths** show correct article titles and links
- [ ] **Popular articles** cards load
- [ ] Search filters articles client-side
- [ ] Article show: **On this page** TOC jumps to headings
- [ ] Sidebar **In this section** highlights current article
- [ ] Prev/next navigation within category works
- [ ] Buyer portal user sees only buyer + all articles (tenant article → 404)
- [ ] Mobile: TOC sidebar stacks below content

---

## Week 1 - Foundation, tenancy & campaign stack

**Theme:** Can we log in, switch tenants, configure campaigns, and trust isolation?

### Day 1 - Environment & smoke (Mon)

**Core (3h)**

- [ ] Run full suite: `php artisan test` - record baseline pass count
- [ ] Route health: hit `/`, `/login`, `/dashboard`, `/pricing`, `/help`, `/status`
- [ ] UK admin login on tenant subdomain → dashboard loads, stats non-empty
- [ ] Super admin on central host → `/accounts` lists UK + US platforms
- [ ] Switch to UK tenant → campaign list matches seeded data
- [ ] Quick smoke from [`functionalities/README.md`](./functionalities/README.md#quick-smoke-test-15-minutes)

**Edge cases (2h)**

- [ ] Login on **wrong subdomain** (UK user on US host) - expect clear error, no data leak
- [ ] `/register` returns 404 (registration disabled)
- [ ] Access `/dashboard` logged out → redirect login
- [ ] Stop queue worker → submit async API lead → stays queued; restart worker → processes
- [ ] Submit sync lead (`"sync": true`) with worker **stopped** - should still complete

**Deliverable:** Baseline test count + smoke pass/fail list.

---

### Day 2 - Auth, users & profile (Tue)

**Core (3h)**

- [ ] Password reset flow (`/forgot-password` → email/log → reset)
- [ ] Email verification prompt for unverified user (create user without `email_verified_at`)
- [ ] Profile: update name, theme, accent colour - persists on reload
- [ ] 2FA enable/disable + recovery codes (profile)
- [ ] Users CRUD at `/users` - create staff with module restrictions
- [ ] Email credentials to new portal user - mail received (or logged in dev)
- [ ] Suspend user → cannot login; reactivate works

**Edge cases (2h)**

- [ ] Staff user with **one module** denied - e.g. no `reports` → `/reports` blocked
- [ ] Admin cannot suspend self
- [ ] Super admin hidden on tenant user list
- [ ] Change email on profile → verification reset (if MustVerifyEmail enabled)
- [ ] Buyer portal user hitting `/dashboard` → redirect to buyer portal
- [ ] Session on central host as non-super-admin → redirect to tenant portal URL

**Reference:** [`functionalities/08-suppliers-and-portals.md`](./functionalities/08-suppliers-and-portals.md)

---

### Day 3 - Accounts, branding & settings (Wed)

**Core (3h)**

- [ ] Super admin: Partner Platforms `/accounts` - create/edit tenant
- [ ] Tenant billing `/accounts/billing` - rent, due date, lock/unlock
- [ ] Revenue projection panel - adjust client mix, setup fee, margins recalc
- [ ] Branding: logo, colours, portal name - visible on tenant login + portals
- [ ] Account settings: timezone, currency, fraud provider, prepay toggle
- [ ] API keys: create, revoke, permission scopes

**Edge cases (2h)**

- [ ] **Billing lock** tenant → admin redirected to `/billing/lock`; API ingest returns 402
- [ ] Unlock billing → ingest resumes
- [ ] Super admin Settings/Branding without tenant switch - guard + toast (not silent fail)
- [ ] API key without `leads.create` → POST `/api/v1/leads` 403
- [ ] Revoked key → 401 on next request
- [ ] Cross-tenant API key on wrong host - rejected

**Reference:** [`IMPLEMENTATION_STATUS.md`](./IMPLEMENTATION_STATUS.md) · `TenantBillingFunctionalityTest`

---

### Day 4 - Campaigns & verticals (Thu)

**Core (3h)**

- [ ] List/create/edit campaign - reference, vertical, caps
- [ ] Campaign show: fields tab, validation config, workflow nav links
- [ ] Vertical templates: load premade fields for insurance, loans, solar
- [ ] Campaign API spec editor - lock, apply to form, export JSON
- [ ] Suppression campaign type - ingest stores without distribution
- [ ] Campaign caps: set daily cap low → next lead rejected with reason
- [ ] Duplicate campaign reference - validation error

**Edge cases (2h)**

- [ ] Inactive campaign → ingest rejected
- [ ] Required field missing → validation fail / quarantine (per config)
- [ ] Invalid phone/email formats - check error messages in lead log
- [ ] Campaign with **no active delivery** → unsold outcome
- [ ] Edit campaign fields while leads in flight - no crash; new leads use new schema
- [ ] UK vs US campaign - currency on financials matches tenant default

**Reference:** [`functionalities/02-campaigns-and-verticals.md`](./functionalities/02-campaigns-and-verticals.md)

---

### Day 5 - Week 1 regression & mobile pass (Fri)

**Core (3h)**

- [ ] Re-run week 1 scenarios at **375px width** (phone) - admin nav drawer, tables scroll
- [ ] PageHeader actions wrap; Operations equal-height panels
- [ ] Help centre `/help` - tenant articles load; search if available
- [ ] Support ticket: create as tenant user → super admin `/support/manage` → reply → resolve → email + in-app notification
- [ ] Re-run `php artisan test --filter=AccountFunctionalityTest,CampaignFunctionalityTest,SettingsFunctionalityTest,TenantAuthTest`

**Edge cases (2h)**

- [ ] God mode handoff: super admin → open UK portal → banner visible → stop god mode
- [ ] Impersonate buyer/supplier user → restricted to their data only
- [ ] Access log entries for login failures and impersonation

**Deliverable:** Week 1 defect triage; P0/P1 must be assigned before week 2.

---

## Week 2 - Lead pipeline, deliveries & distribution

**Theme:** Leads ingest, validate, route, sell - including ping tree edge cases.

### Day 6 - API ingest & queue (Mon)

**Core (3h)**

- [ ] POST `/api/v1/leads` sync - sold lead, financials, delivery log
- [ ] POST async - 202 + `queue_id`; poll `/api/v1/leads/queue/{id}` until terminal
- [ ] GET lead by UUID - status, buyer, revenue fields
- [ ] Search leads API - filters by date, campaign, status
- [ ] Reprocess unsold lead via API
- [ ] JavaScript SDK `/sdk/pbe-leads.js` - browser ingest from test HTML page
- [ ] PHP SDK smoke (`sdk/php/PbeClient.php`)

**Edge cases (2h)**

- [ ] Duplicate email/phone within dedupe window → rejected / flagged
- [ ] Suppression list hit → rejected with reason
- [ ] Invalid `campaign_reference` → 404/422
- [ ] Malformed JSON / missing auth header → 400/401
- [ ] Large payload (max fields) - no timeout under 30s
- [ ] Concurrent 10 sync ingests - no duplicate sales to same buyer over cap
- [ ] API request log at `/logs/api` - request/response captured, secrets redacted

**Reference:** [`functionalities/09-api-and-sdk.md`](./functionalities/09-api-and-sdk.md)

---

### Day 7 - Validation, fraud & quarantine (Tue)

**Core (3h)**

- [ ] Campaign validation rules: require phone, zip, custom regex
- [ ] Quarantine on validation fail - lead held, not distributed
- [ ] Quarantine unsold - config on campaign
- [ ] Admin quarantine UI: release → re-queues job; reject → terminal
- [ ] Bulk release / bulk reject
- [ ] Fraud protection: enable IPQS (or demo provider) - risky email/phone scores
- [ ] Validation integration page - test connection button

**Edge cases (2h)**

- [ ] Quarantine expiry job (`quarantine:process-expired`) - auto-release or reject per config
- [ ] Release quarantined lead when campaign **inactive** - expected behaviour documented
- [ ] Lead scoring 100 with unchecked fraud checks - UI shows “Not checked” not “Passed”
- [ ] Supplier cap exceeded → reject before distribution
- [ ] IP velocity / duplicate from same IP - if configured

**Reference:** [`functionalities/05-reports.md`](./functionalities/05-reports.md) (quarantine counts) · `ValidationQuarantineTest`

---

### Day 8 - Deliveries - all methods (Wed)

**Core (3h)**

- [ ] Create delivery via 8-step wizard for each method:
  - Direct POST
  - Ping-Post (mock buyer URLs `/api/v1/mock/buyers/{tier}/ping|post`)
  - Store Lead
  - Email
  - Email Ping-Post
  - SMS (log provider)
- [ ] Delivery test button - ping/post logged
- [ ] Clone delivery - config copied
- [ ] Delivery schedules - outside window → skipped
- [ ] Eligibility rules - geo, field filters reject before HTTP

**Edge cases (2h)**

- [ ] Ping timeout (set 1s, slow mock) → skip to next delivery
- [ ] Ping success but **Cost below floor** → rejected
- [ ] Post failure after ping accept - lead unsold, log shows post error
- [ ] Inactive delivery - skipped in tree
- [ ] Email delivery - multiple recipients, invalid email skipped
- [ ] Tag interpolation typos - graceful error in delivery log

**Reference:** [`functionalities/03-deliveries-and-10-tier-ping-tree.md`](./functionalities/03-deliveries-and-10-tier-ping-tree.md)

---

### Day 9 - Distribution & ping tree (Thu)

**Core (3h)**

- [ ] Standard waterfall - priority order respected
- [ ] Advanced distribution - enable on campaign
- [ ] Ping tree builder: 10 tiers, drag reorder, lock tier
- [ ] Routing modes: sequential, parallel auction, hybrid
- [ ] Tier entry filters - lead must match to enter tier
- [ ] Tier redirect URL - sold lead redirects buyer to custom URL
- [ ] **Decline URL** - final tier all reject → unsold + decline redirect/API field
- [ ] Routing simulator - dry run with sample lead fields
- [ ] Distribution lock toggle

**Edge cases (2h)**

- [ ] Parallel auction - two buyers ping, higher bid wins
- [ ] Exclusive campaign - only one buyer per lead
- [ ] Tier with zero deliveries - skipped cleanly
- [ ] Floor price = 0 - edge pricing in financials
- [ ] Re-run same lead through tree after buyer cap hit mid-day - second lead skips capped buyer
- [ ] Large ping tree (10 tiers × multiple nodes) - P95 processing time on Command Center

**Reference:** [`functionalities/04-distribution-ping-tree.md`](./functionalities/04-distribution-ping-tree.md) · `PingTreeRigorTest`

---

### Day 10 - Week 2 regression & lead detail (Fri)

**Core (3h)**

- [ ] Lead show page: tabs (overview, fields, deliveries, logs, financials)
- [ ] Prev/next navigation across lead list
- [ ] Lead redirect `/r/{uuid}` - tracking link works
- [ ] CSV import - map columns, queue processing
- [ ] Live stats `/live-stats` - updates after ingest
- [ ] Re-run: `LeadPipelineFunctionalityTest`, `PingTreeRigorTest`, `LeadIngestApiTest`, `DeliveriesFunctionalityTest`

**Edge cases (2h)**

- [ ] Lead processing timeout - slow buyer → lead not stuck forever in `distributing`
- [ ] Repost lead to different campaign (if supported)
- [ ] Lead with unicode names / emoji in fields - stored and displayed
- [ ] Empty optional fields vs null - API consistency

**Deliverable:** Lead journey diagram validated; list any pipeline gaps.

---

## Week 3 - Network, money & capture surfaces

**Theme:** Buyers, suppliers, billing, portals, forms, postbacks.

### Day 11 - Buyers & eligibility (Mon)

**Core (3h)**

- [ ] Buyer CRUD - reference, status, contact, settings
- [ ] Buyer caps - daily/monthly limits
- [ ] Geo restrictions - state/zip allow/block lists
- [ ] Pricing overrides on buyer
- [ ] Buyer schedules - availability windows
- [ ] Prepay enforcement - require credit before sale
- [ ] Buyer conversion feedback API + portal - `converted`, `called`, `returned`

**Edge cases (2h)**

- [ ] **Zero credit** buyer with prepay required → lead skips buyer
- [ ] Buyer suspended → deliveries skipped
- [ ] Buyer feedback fires postback + buyer webhook URL
- [ ] Two buyers same price in auction - tie-break deterministic
- [ ] Buyer portal cannot see other buyers’ leads

**Reference:** [`functionalities/07-buyers-and-billing.md`](./functionalities/07-buyers-and-billing.md) · `BuyerPrepayEnforcementTest`

---

### Day 12 - Billing & finance (Tue)

**Core (3h)**

- [ ] Billing index - buyer list, balances, pagination
- [ ] Buyer billing detail - transaction history
- [ ] Manual top-up - ledger credit increases
- [ ] Sold lead debits buyer (when prepay enabled)
- [ ] Finance dashboard - revenue, margin summaries
- [ ] Export billing CSV - single buyer + all buyers
- [ ] Stripe integration page - keys, webhook URL (config only; Checkout 🔲)

**Edge cases (2h)**

- [ ] Top-up then sell until balance hits zero - next sale blocked
- [ ] Negative balance prevented (or handled per config)
- [ ] Currency display GBP vs USD tenants
- [ ] Billing export with 0 transactions - valid empty CSV
- [ ] Account billing lock while buyer has credit - ingest still blocked at tenant level

**Reference:** `BillingFunctionalityTest`, `AdvancedBillingTest`, `RevenueCalculatorTest`

---

### Day 13 - Suppliers & portals (Wed)

**Core (3h)**

- [ ] Supplier CRUD - reference, default postback URL
- [ ] Source (SID) management - caps, postback overrides
- [ ] **Buyer portal:** dashboard, my leads, filters, lead detail, CSV download
- [ ] **Supplier portal:** dashboard, submissions, payouts view, embed codes, CSV download
- [ ] Portal branding matches tenant
- [ ] Supplier API ingest with `sid` / `ssid` - attribution in reports

**Edge cases (2h)**

- [ ] Portal cross-access blocked (buyer cannot hit supplier routes)
- [ ] Admin cannot access portal routes without impersonation
- [ ] Supplier portal user cannot access admin `/campaigns`
- [ ] CSV download headers correct; UTF-8 characters
- [ ] Wrong tenant subdomain login message - clear, not generic 500
- [ ] Portal mobile layout - tables, nav drawer

**Reference:** [`functionalities/08-suppliers-and-portals.md`](./functionalities/08-suppliers-and-portals.md) · `EdgeCaseRegressionTest`

---

### Day 14 - Forms, postbacks & webhooks (Thu)

**Core (3h)**

- [ ] Form builder: multi-step form, field types, validation
- [ ] Publish hosted form `/forms/{slug}` - public submit
- [ ] Embed code on external page - lead ingests with supplier attribution
- [ ] Postback manager - create pixel URL, scope to supplier/campaign
- [ ] Fire postbacks on sold / rejected / duplicate events - audit log
- [ ] Outbound webhooks - JSON payload on lead events
- [ ] Auto-responders - trigger on lead sold

**Edge cases (2h)**

- [ ] Form submit without required field - inline validation
- [ ] Double-submit same form session - dedupe behaviour
- [ ] Postback URL down - logged failure, lead still sold
- [ ] Deleted postback - no outbound call
- [ ] Webhook HMAC signing (if configured) - invalid signature rejected on inbound test
- [ ] Hosted form on mobile - steps usable

**Reference:** [`functionalities/06-form-builder.md`](./functionalities/06-form-builder.md) · [`functionalities/10-postbacks-webhooks.md`](./functionalities/10-postbacks-webhooks.md)

---

### Day 15 - Week 3 regression & reports (Fri)

**Core (3h)**

- [ ] Reports dashboard - 28-day charts, campaign breakdown, 10-tier table
- [ ] Filter by date range, campaign, buyer, supplier
- [ ] Export report data if available
- [ ] Operations page - recent leads + latest deliveries equal height
- [ ] Dashboard KPIs align with reports totals (same date range)
- [ ] Re-run: `ReportsFunctionalityTest`, `ReportsRigorTest`, `PostbackManagerTest`, `FormBuilderFunctionalityTest`

**Edge cases (2h)**

- [ ] Reports with no data in range - empty state, not error
- [ ] Midnight boundary - lead at 23:59 vs 00:01 counts on correct day (platform timezone)
- [ ] DST transition date (if applicable to tenant TZ)
- [ ] Large date range performance - page loads under 5s on seeded 30-day data

**Deliverable:** Financial reconciliation spot-check (10 random sold leads: revenue = buyer debit = report row).

---

## Week 4 - Ops, integrations, stress & sign-off

**Theme:** Production readiness, super-admin ops, integrations, full regression.

### Day 16 - Operations & audit logs (Mon)

**Core (3h)**

- [ ] Operations monitor - live queue depth, processing metrics
- [ ] Delivery logs index + detail - ping/post payloads
- [ ] API request logs - filter by status, key
- [ ] Access logs - login, impersonation
- [ ] Security logs - suspicious events
- [ ] Change log - campaign/delivery edits tracked
- [ ] Platform notifications inbox - read/dismiss

**Edge cases (2h)**

- [ ] Search logs by lead UUID - finds all related entries
- [ ] Log pagination at 500+ rows - no timeout
- [ ] PII in logs - confirm masking where expected
- [ ] Export/download logs if available

**Reference:** [`functionalities/12-operations-and-logs.md`](./functionalities/12-operations-and-logs.md)

---

### Day 17 - Command Center & super-admin (Tue)

**Core (3h)** *(central host + super admin only)*

- [ ] Command Center - cross-tenant health, warnings
- [ ] Processing P95 warning threshold (>300ms) - understand false positives on low volume
- [ ] Platform events feed
- [ ] Live feed - real-time lead events
- [ ] Horizon access - queue metrics (super admin only)
- [ ] Partner platform provisioning - new tenant smoke test
- [ ] System status public page `/status`

**Edge cases (2h)**

- [ ] Non-super-admin blocked from `/command-center`
- [ ] Tenant admin blocked from central-only routes
- [ ] Command Center warning clears when condition resolves
- [ ] `platform:sync-alerts` scheduled task - alerts fire to webhook/Slack/email (if configured)

**Reference:** `CommandCenterTest`, `CommandCenterDrillDownTest`

---

### Day 18 - Automation & integrations (Wed)

**Core (3h)**

- [ ] Automation hub - sequences, bulk SMS, event alerts
- [ ] Create alert - webhook + email channel; trigger with test event
- [ ] Bulk SMS campaign - log provider sends (or Twilio test creds)
- [ ] Integrations index - links to validation, lead sources, Stripe
- [ ] Lead source: Facebook webhook verification + sample payload ingest
- [ ] Google / TikTok integration pages - webhook URLs documented
- [ ] Validation integration - IPQS/demo toggle

**Edge cases (2h)**

- [ ] Alert storm - same event 50× in 1 min - rate limit / dedupe
- [ ] Invalid Facebook verify token - 403
- [ ] Lead source payload missing mapped campaign - graceful queue/reject
- [ ] SMS to invalid number - failure logged, no crash

**Reference:** [`functionalities/11-automation.md`](./functionalities/11-automation.md) · `LeadSourceIngestTest`, `AlertsFunctionalityTest`

---

### Day 19 - Marketing, public & cross-browser (Thu)

**Core (3h)**

- [ ] Welcome / home - hero, rotating text, no layout gap
- [ ] Pricing page - plans, features, no stale fraud add-on on Starter list
- [ ] Blog index + article - SEO meta, markdown render
- [ ] Demo request form - submits, notification/email
- [ ] Public help - guest layout mobile
- [ ] API docs in admin - accurate endpoints, decline_url documented
- [ ] Cross-browser: Chrome, Safari, Firefox - login + one lead ingest each

**Edge cases (2h)**

- [ ] Marketing routes on **tenant subdomain** - redirect or 404 per design
- [ ] `/status.json` - valid JSON for external monitors
- [ ] Long blog title - no layout break
- [ ] Pricing anchor links / CTAs go to correct destinations

**Reference:** [`functionalities/13-marketing-site-pricing-blog.md`](./functionalities/13-marketing-site-pricing-blog.md)

---

### Day 20 - Full regression & sign-off (Fri)

**Core (3h)**

- [ ] **Full automated suite:** `php artisan test` - must match or exceed day 1 baseline
- [ ] **Golden path script** (run manually, ~90 min):
  1. Super admin switch tenant
  2. Create campaign → delivery → ping tree
  3. API sync ingest → sold
  4. Buyer portal sees lead
  5. Supplier postback received (use webhook.site)
  6. Buyer feedback converted → supplier postback
  7. Report totals updated
  8. Billing ledger reflects sale
- [ ] Review all P0/P1 defects - fixed or accepted with waiver
- [ ] Update [`IMPLEMENTATION_STATUS.md`](./IMPLEMENTATION_STATUS.md) with test date + known gaps

**Edge cases (2h)**

- [ ] **Chaos hour:** randomly combine 5 edge cases in one session (lock + quarantine + cap + timeout + wrong portal)
- [ ] `migrate:fresh --seed` on clean DB - seeder integrity (`PlatformSeederIntegrityTest` scenarios manually)
- [ ] Document 🔲 items still untested: 2FA login challenge, campaign transfer, 2-step auth delivery, Stripe Checkout

**Deliverable:** Sign-off sheet below.

---

## Sign-off checklist

| Area | Status | Notes |
|------|--------|-------|
| Tenancy & auth | ☐ Pass ☐ Fail | |
| Campaign → ingest → distribute | ☐ Pass ☐ Fail | |
| Ping tree (10 tier) | ☐ Pass ☐ Fail | |
| API sync + async | ☐ Pass ☐ Fail | |
| Quarantine & validation | ☐ Pass ☐ Fail | |
| Buyers & billing | ☐ Pass ☐ Fail | |
| Portals (buyer + supplier) | ☐ Pass ☐ Fail | |
| Forms & postbacks | ☐ Pass ☐ Fail | |
| Reports & operations | ☐ Pass ☐ Fail | |
| Super-admin / Command Center | ☐ Pass ☐ Fail | |
| Marketing & public | ☐ Pass ☐ Fail | |
| Mobile responsive (admin) | ☐ Pass ☐ Fail | |
| Automated test suite | ☐ Pass ☐ Fail | ___ / ___ tests |
| Security (cross-tenant), P0 open | ☐ Pass ☐ Fail | Count: ___ |

**Signed off by:** _______________ **Date:** _______________

---

## Optional stretch (if days run under 6h)

| Topic | Hours | Notes |
|-------|-------|-------|
| Load test - 100 async leads/min | 3h | Watch queue depth, P95, DB locks |
| US tenant full duplicate of UK scenarios | 4h | Currency, date formats |
| Accessibility pass (keyboard, contrast) | 3h | Focus traps in modals/drawers |
| Write missing Feature tests for bugs found | 6h | Prevent regressions |
| Pipeline profile UI (WIP) | 2h | When shipped |

---

## Known gaps - do not fail sign-off for these alone

Document as **accepted limitations** (see [`IMPLEMENTATION_STATUS.md`](./IMPLEMENTATION_STATUS.md)):

- Stripe Checkout in buyer portal (config UI only)
- 2FA TOTP at login (enable/recovery only)
- 2-step auth & campaign transfer delivery methods (enum only)
- Call logic / pay-per-call (not built)
- FTP scheduled exports
- Custom report builder UI
- Full Facebook/Google field mapping automation

---

## Daily command reference

```bash
# Full suite
php artisan test

# Targeted by area
php artisan test --filter=PingTree
php artisan test --filter=Billing
php artisan test --filter=Portal
php artisan test --filter=EdgeCase

# After code fixes
php artisan test --parallel   # if available

# Queue / ops
php artisan queue:work
php artisan horizon:status
php artisan schedule:run      # quarantine expiry, alerts
```

---

*Schedule version 1.0 - June 2026. Adjust dates to your calendar; keep the weekly themes if condensing to 3 weeks by merging days 5+10 and 15+16.*
