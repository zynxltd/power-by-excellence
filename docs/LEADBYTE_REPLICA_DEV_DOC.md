# LeadByte Replica — Developer Documentation

> **Purpose:** Build a production-grade lead generation and distribution platform that mirrors [LeadByte](https://www.leadbyte.co.uk/) structure, terminology, and capabilities — including ping-tree routing, real-time API distribution, multi-vertical support, buyer/supplier portals, validation, caps, and financial tracking.

---

## Table of Contents

1. [Platform Overview](#1-platform-overview)
2. [LeadByte Domain Model (Mirror Exactly)](#2-leadbyte-domain-model-mirror-exactly)
3. [System Architecture](#3-system-architecture)
4. [Multi-Vertical Support](#4-multi-vertical-support)
5. [Lead Ingestion](#5-lead-ingestion)
6. [Validation & Filtering](#6-validation--filtering)
7. [Deduplication & Suppression](#7-deduplication--suppression)
8. [Distribution Engine (Ping Tree + Routing)](#8-distribution-engine-ping-tree--routing)
9. [Deliveries](#9-deliveries)
10. [Caps, Financials & Billing](#10-caps-financials--billing)
11. [Quarantine & Retry](#11-quarantine--retry)
12. [Portals (Buyer / Supplier)](#12-portals-buyer--supplier)
13. [Reporting, Analytics & Webhooks](#13-reporting-analytics--webhooks)
14. [REST API Specification](#14-rest-api-specification)
15. [Database Schema](#15-database-schema)
16. [Event Flow & State Machine](#16-event-flow--state-machine)
17. [Security & Compliance](#17-security--compliance)
18. [Implementation Phases](#18-implementation-phases)
19. [Tech Stack Recommendations](#19-tech-stack-recommendations)
20. [Appendix: Vertical Field Templates](#20-appendix-vertical-field-templates)

---

## 1. Platform Overview

### What LeadByte Does

LeadByte is a **B2C lead distribution platform** for agencies, brokers, sellers, and buyers. It:

- **Captures** leads in real time (forms, API, Facebook, CSV, webhooks)
- **Validates** and filters junk/duplicate/out-of-criteria leads
- **Routes** leads via waterfall, ping-post, ping-tree, round-robin, weighted %, auction, hybrid groups
- **Delivers** to buyer CRMs via API, email, SMS, FTP/CSV schedules
- **Tracks** revenue, payout, caps, returns, and source attribution (SID/SSID)
- **Provides** buyer and supplier portals with billing (Stripe), feedback, and returns

### Personas (Build For All Three)

| Persona | Role | Core Needs |
|---------|------|------------|
| **Lead Seller** | Generates leads, sells to buyers | Maximize revenue/lead, validation, routing, supplier payouts |
| **Lead Buyer** | Purchases leads | Quality control, caps, DNC, feedback, source visibility |
| **Lead Broker** | Trades between suppliers and buyers | P&L per lead, suppression lists, portals, postbacks |

### Non-Functional Requirements

| Requirement | Target |
|-------------|--------|
| Lead ingest → first ping | < 50ms internal queue |
| Ping tree tier (parallel pings) | < 200ms per tier (configurable timeout) |
| API uptime | 99.9%+ |
| Throughput | 10k+ leads/min per tenant (horizontal scale) |
| Audit | Full delivery log with raw ping/post responses |
| Multi-tenancy | Account-isolated data, custom portal domains |

---

## 2. LeadByte Domain Model (Mirror Exactly)

Use LeadByte terminology in code, DB, and UI so integrations and docs map 1:1.

```
Account (Tenant)
├── Users (Admin, Staff)
├── Buyers
├── Suppliers
│   └── Sources (SID)
│       └── Sub-Suppliers (SSID)
├── Campaigns
│   ├── Fields (standard + custom)
│   ├── Suppliers & Sources (caps, payout overrides)
│   ├── Deduplication rules
│   ├── Validation services
│   ├── Advanced Distribution (routing groups)
│   ├── Deliveries
│   ├── Responders (Email/SMS)
│   ├── Tracking Pixels / Postbacks
│   └── Quarantine rules
├── Suppression Campaigns
├── Hybrid Rule Groups (account-level)
├── REST API Keys (Admin / Supplier scoped)
├── Webhooks
└── Leads
    ├── Lead Events (audit trail)
    ├── Delivery Attempts (ping/post logs)
    └── Financials (revenue, payout, margin)
```

### Core Entities

#### Account
Top-level tenant. Holds default currency, timezone, country, default campaign settings, Stripe config.

#### Campaign
**Central processing hub.** Two types:
- **Standard** — ingest, validate, dedupe, distribute, pay suppliers
- **Suppression** — DNC / do-not-market lists; blocks matching leads elsewhere

Key campaign settings (mirror LeadByte):
- `name`, `reference` (locked after first lead — used in API guides, supplier portal)
- `type`: `standard` | `suppression`
- `country`, `currency` (locked after first lead)
- `payout_supplier_on`: `system_accept` | `buyer_delivery_accept`
- `payout_amount`, `payout_type`
- `status`: `active` | `inactive` | `archived`
- Campaign caps (total, daily, hourly, weekly, monthly, day-specific)
- Field schema (per campaign)
- Grant access (RBAC per campaign)

#### Supplier
Entity that sends leads (affiliate, publisher, call centre, ad network).

#### Source (SID — Supplier ID)
Granular tracking ID per supplier traffic source. Passed on ingest.

#### Sub-Supplier (SSID)
Second-tier attribution (landing page variant, broker sub-source, A/B test).

#### Buyer
Client who purchases leads. Has:
- Portal login (optional custom domain)
- Caps (hourly/daily/weekly/monthly/total, day-specific)
- Credit balance (Stripe top-ups)
- DNC / suppression list uploads
- Insertion orders (contractual agreements)
- Feedback API access
- Schedule (delivery hours, pause)

#### Delivery
**How a lead is sent to a destination.** Linked to one campaign, optionally one buyer.

Trigger types:
- `on_lead_arrival` (automatic)
- `manual_via_api` (Delivery ID triggered via REST)

Methods:
- `direct_post` (HTTP JSON/POST/GET)
- `ping_post`
- `two_step_auth` (token extraction → second call)
- `email`
- `sms`
- `store_lead` (assign buyer, no remote post)
- `campaign_transfer` (route to another campaign)

#### Lead
Single consumer record with campaign field values + system metadata:
- `received_at`, `ip_address`, `user_agent`
- `sid`, `ssid`, `source`, `c1`–`c5` (custom tracking)
- `optin_url`, `consent_text`, `channel_consent`
- `status`: `pending` | `accepted` | `rejected` | `quarantined` | `returned`
- `queue_id` (for async processing / live buyer response)

#### Advanced Distribution
Campaign-level routing orchestrator. Contains **distribution groups** with routing mode:

| Mode | LeadByte Name | Behavior |
|------|---------------|----------|
| Priority / Waterfall | Priority Distribution | Try buyers top→bottom; cascade on reject |
| Weighted % | Weighted Distribution | Allocate by percentage weights |
| Round Robin | Round Robin | Rotate evenly among eligible buyers |
| Ping-Post | PingPost | Partial ping → bid → full post |
| Highest Bidder | Ping Post Exchange / Auction | Parallel pings, winner by price |
| Hybrid | Hybrid Distribution | Multiple groups with different modes + rule gates |

**Advanced Distribution Only** flag on deliveries: delivery only runs inside advanced routing, not standard parallel distribution (prevents accidental multi-sell).

#### Hybrid Rule Groups
Reusable rule templates (e.g. `age >= 21`). Applied to hybrid groups; non-matching leads skip entire group.

---

## 3. System Architecture

```
┌─────────────────────────────────────────────────────────────────────────┐
│                         INGESTION LAYER                                  │
│  REST API │ Web Forms │ Webhooks │ CSV Import │ FB/Google/TikTok Sync   │
└──────────────────────────────────┬──────────────────────────────────────┘
                                   ▼
┌─────────────────────────────────────────────────────────────────────────┐
│                      LEAD PROCESSING PIPELINE                              │
│  1. Parse & normalize fields                                             │
│  2. Campaign accept/reject (caps, inactive)                              │
│  3. Suppression check (cross-campaign)                                   │
│  4. Deduplication (standard + advanced + delivery-level)               │
│  5. Validation services (HLR, email, IP, PAF, custom rules)              │
│  6. Smart rules / filters                                                │
│  7. Quarantine gate (optional hold)                                      │
└──────────────────────────────────┬──────────────────────────────────────┘
                                   ▼
┌─────────────────────────────────────────────────────────────────────────┐
│                   DISTRIBUTION ENGINE (Ping Tree Core)                   │
│  Standard Distribution │ Advanced Distribution │ Hybrid Groups           │
│  ┌─────────┐  ┌──────────────┐  ┌─────────────────────────────────┐   │
│  │ Tier 1  │→ │ Tier 2       │→ │ Tier N → Unsold / Quarantine    │   │
│  │ Ping    │  │ Ping/Auction │  │ Fallback waterfall / retry queue  │   │
│  └─────────┘  └──────────────┘  └─────────────────────────────────┘   │
└──────────────────────────────────┬──────────────────────────────────────┘
                                   ▼
┌─────────────────────────────────────────────────────────────────────────┐
│                      DELIVERY EXECUTION LAYER                            │
│  Direct Post │ PingPost │ 2-Step Auth │ Email │ SMS │ Store │ Transfer  │
└──────────────────────────────────┬──────────────────────────────────────┘
                                   ▼
┌─────────────────────────────────────────────────────────────────────────┐
│  POST-PROCESSING: Revenue/Payout │ Responders │ Postbacks │ Webhooks     │
│  Reporting │ Buyer Feedback │ Returns │ Supplier payout triggers         │
└─────────────────────────────────────────────────────────────────────────┘
```

### Recommended Services

| Service | Responsibility |
|---------|----------------|
| **API Gateway** | Auth (API keys), rate limit, request validation |
| **Ingest Worker** | Queue lead, return `queue_id` / live response |
| **Pipeline Worker** | Validation, dedupe, suppression |
| **Distribution Orchestrator** | Ping tree, waterfall, auction logic |
| **Delivery Worker** | HTTP calls, email/SMS, retry |
| **Scheduler** | Caps reset, quarantine release, CSV exports, bulk SMS |
| **Reporting Service** | Real-time aggregates, BI API |
| **Portal App** | Buyer/supplier UI |

### Message Queue

Use Redis Streams, SQS, or RabbitMQ for:
- Lead processing queue
- Delivery execution (with priority lanes for real-time)
- Dead letter queue for failed deliveries
- Retry queue with exponential backoff

---

## 4. Multi-Vertical Support

LeadByte serves insurance, mortgage, solar, legal, home services, finance, education, etc. Build verticals as **configuration**, not hard-coded branches.

### Vertical Registry

```yaml
verticals:
  insurance_auto:
    display_name: "Auto Insurance"
    countries: [US, UK]
    required_fields: [firstname, lastname, email, phone1, zipcode, state]
    ping_fields: [zipcode, state, age_band, vehicle_year, currently_insured]
    post_fields: [firstname, lastname, email, phone1, address, dob, ...]
    validation_profile: us_auto_insurance
    default_floor_price: 15.00
    compliance: [tcpa_consent, state_insurance_regs]

  mortgage:
    display_name: "Mortgage / Refinance"
    countries: [US]
    required_fields: [firstname, lastname, email, phone1, zipcode, loan_amount]
    ping_fields: [zipcode, state, loan_amount_band, credit_band, property_type]
    ...

  solar:
    ...

  legal_mva:
    ...

  home_services:
    ...
```

### Campaign ↔ Vertical Binding

Each campaign has:
- `vertical_id` (nullable for generic)
- `field_schema` (extends vertical template)
- Vertical-specific **ping field mask** (what goes in ping vs post)
- Vertical-specific **buyer eligibility rules** (geo, license state, product type)

### Category Segmentation (LeadByte Feature)

Allow campaigns to segment by:
- Product sub-type (e.g. life vs auto insurance)
- Intent level (quote vs callback)
- Channel (Facebook, search, call centre)
- Geo (state, county, postcode prefix)

### Multi-Vertical Ping Tree

A **broker account** may run one campaign per vertical OR a **multi-vertical campaign** with routing rules:

```
Lead arrives with vertical=insurance_auto
  → Match buyers in insurance_auto pool only
  → Apply auto-specific floor price
  → Ping with auto-specific partial fields

Lead arrives with vertical=solar
  → Different buyer pool, different auction timeout
```

Implement as: `buyer_contracts.vertical_id` + campaign-level `vertical_routing_rules`.

---

## 5. Lead Ingestion

### 5.1 REST API (Primary — Mirror LeadByte)

Two key types (exact LeadByte model):

| Key Type | Scope | Use Case |
|----------|-------|----------|
| **Administrator** | Account-wide, permission-scoped | Internal, multi-source posting |
| **Supplier** | Locked to one SID | Publisher integration |

**Ingest endpoint:**

```
POST /api/v1/leads
Content-Type: application/json
Authorization: Bearer {api_key}

{
  "campaign_reference": "auto-insurance-us",
  "sid": "google_search_q1",
  "ssid": "landing_page_b",
  "firstname": "Jane",
  "lastname": "Doe",
  "email": "jane@example.com",
  "phone1": "+15551234567",
  "zipcode": "90210",
  "state": "CA",
  "source": "facebook",
  "c1": "everflow_transaction_id",
  "optin_url": "https://example.com/form",
  "ip_address": "203.0.113.1",
  "consent_text": "I agree to be contacted..."
}
```

**Response modes:**

1. **Async (queued)** — default for high volume
```json
{
  "status": "queued",
  "queue_id": "q_8f3a2b1c",
  "lead_id": "ld_9x7y6z"
}
```

2. **Live Buyer Response** (synchronous — LeadByte feature)
```json
{
  "status": "sold",
  "lead_id": "ld_9x7y6z",
  "buyer_reference": "acme_insurance",
  "revenue": 18.50,
  "redirect_url": "https://buyer.com/thank-you?ref=..."
}
```

Poll or webhook for async results.

### 5.2 Web Forms / Form Builder

LeadByte form builder features to replicate:
- Domain-lock security (only accept posts from allowed domains)
- Hidden fields (UTM, SID, SSID)
- Conditional redirects (based on sold/unsold)
- Full CSS styling
- Embed code + hosted URL

### 5.3 CSV Import

Bulk import with:
- Add / update / return modes
- Supplier-scoped import permissions
- Run through same validation + dedupe pipeline
- Async job with progress + error report

### 5.4 Webhooks (Inbound)

Receive leads from:
- Facebook Lead Ads (real-time sync)
- Google Ads lead forms
- TikTok lead gen
- Generic webhook adapter (map fields → campaign fields)

### 5.5 Host & Post

Supplier hosts form; posts to your API on submit (same as REST ingest).

---

## 6. Validation & Filtering

### 6.1 Field-Level Validation

Per campaign + country:
- Postcode/ZIP format
- Phone1–Phone3 format (libphonenumber)
- Email RFC + MX optional
- DOB → age calculation
- Required field enforcement

### 6.2 Validation Services (LeadByte Integrations)

| Service | Purpose | When |
|---------|---------|------|
| **HLR** (mobile) | Number active/reachable | Ingest |
| **Email** | Deliverability / disposable detection | Ingest |
| **IP** | Fraud / geo mismatch | Ingest |
| **PAF** (UK addresses) | Postal address file | Ingest |
| **Custom Validation Service (CVS)** | HTTP callout to 3rd party | Ingest |

Architecture:
```python
class ValidationPipeline:
    def run(lead, campaign) -> ValidationResult:
        for service in campaign.validation_services:
            result = service.validate(lead)
            if result.reject:
                return Reject(reason=result.code, service=service.name)
        return Accept()
```

### 6.3 Smart Rules / Filters

Rule engine (mirror LeadByte rules):
- Field comparisons (`age >= 21`, `state IN [TX, CA]`)
- Regex on any field
- AND/OR groups
- Reusable rule templates
- Apply at: campaign accept, delivery eligibility, hybrid group gate

### 6.4 Fraud Signals

- IP vs ZIP geo distance
- Velocity per IP / phone / email
- Honeypot fields on forms
- Duplicate device fingerprint (optional)

---

## 7. Deduplication & Suppression

### 7.1 Standard Deduplication

- Match on `email` and/or `phone1`
- Reject window: N days (supplier-level override)
- Cross-campaign scope (select campaigns to check against)

### 7.2 Advanced Deduplication

- Multi-field composite key (e.g. email + phone + zip)
- Per-supplier reject days
- Compare against suppression campaigns

### 7.3 Delivery-Level Deduplication

Independent dedupe per delivery (both email AND phone must match to skip).

### 7.4 Suppression Lists

- Buyer uploads hashed (SHA-256) customer data
- Block duplicate leads before distribution
- Prevent re-marketing to opted-out contacts

### 7.5 Suppression Campaigns

Leads ingested into suppression campaigns block future matches in standard campaigns.

---

## 8. Distribution Engine (Ping Tree + Routing)

This is the core differentiator. LeadByte combines **ping-post mechanism** with **ping-tree architecture** and **hybrid groups**.

### 8.1 Concepts

| Term | Definition |
|------|------------|
| **Ping** | HTTP call with partial/non-PII lead data |
| **Post** | HTTP call with full lead data to winning buyer |
| **Ping Tree** | Tiered buyer structure; cascade through tiers on no-sale |
| **Floor Price** | Minimum acceptable bid/revenue |
| **Waterfall** | Sequential priority routing (may use direct post, not auction) |
| **Multi-sell** | Same lead sold to multiple buyers (configurable) |
| **Exclusive** | Lead sold to one buyer only |

### 8.2 Ping Tree Execution Model

```
┌──────────────────────────────────────────────────────────────┐
│                    PING TREE EXECUTOR                         │
├──────────────────────────────────────────────────────────────┤
│  Input: Lead L, Campaign C, Distribution Config D             │
│                                                               │
│  FOR each tier T in D.tiers (ordered):                        │
│    eligible_buyers = filter(T.buyers, L, rules, caps, schedule)│
│                                                               │
│    IF T.mode == PARALLEL_AUCTION:                             │
│      pings = parallel_ping(eligible_buyers, L.ping_fields)    │
│      winner = highest_bid_above_floor(pings, T.floor)         │
│      IF winner: POST(L, winner); RETURN sold                  │
│                                                               │
│    IF T.mode == SEQUENTIAL_PING:                              │
│      FOR buyer in T.priority_order:                           │
│        result = ping_post(buyer, L)                           │
│        IF result.accepted: RETURN sold                        │
│                                                               │
│    IF T.mode == WATERFALL_DIRECT:                             │
│      FOR delivery in T.priority_order:                        │
│        result = direct_post(delivery, L)                        │
│        IF result.success: RETURN sold                         │
│                                                               │
│    IF T.mode == WEIGHTED:                                     │
│      buyer = weighted_select(eligible_buyers)                 │
│      result = deliver(buyer, L)                               │
│      IF result.success: RETURN sold                           │
│                                                               │
│    IF T.mode == ROUND_ROBIN:                                  │
│      buyer = next_round_robin(eligible_buyers)                │
│      ...                                                      │
│                                                               │
│  RETURN unsold → quarantine / fallback / retry                │
└──────────────────────────────────────────────────────────────┘
```

### 8.3 PingPost Protocol (Buyer Integration)

Mirror LeadByte PingPost delivery:

**Step 1 — Ping** (partial data):
```json
POST {buyer_ping_url}
{
  "zipcode": "90210",
  "state": "CA",
  "age_band": "25-34",
  "vertical": "auto_insurance"
}
```

**Step 2 — Evaluate response:**
```json
{
  "Success": true,
  "Cost": 15.00,
  "PingID": "328487327432-1"
}
```
Rules: `Success == true AND Cost >= floor_price` → proceed to post.

**Step 3 — Post** (full data, interpolate ping response):
```json
POST {buyer_post_url}
{
  "fn": "Jane",
  "ln": "Doe",
  "ph": "+15551234567",
  "pingID": "{$ping.PingID}"
}
```

Support `{$ping.FIELD}` and `{$ping.nested.path}` for JSON/XML responses.

### 8.4 Hybrid Distribution (LeadByte Unique Feature)

Example broker setup:

```
Hybrid Group A (Rule: age >= 21 AND state = TX)
  Mode: Priority Waterfall
  Buyers: [Retail_A ($25), Retail_B ($22), Retail_C ($20)]

Hybrid Group B (Rule: default / fallback)
  Mode: Ping Post Auction
  Buyers: [Wholesale_1, Wholesale_2, Wholesale_3]
  Floor: $8
  Timeout: 1500ms

Hybrid Group C (Rule: unsold from A and B)
  Mode: Weighted Round Robin
  Buyers: [Long_tail_1 (40%), Long_tail_2 (60%)]
```

Leads skip groups whose hybrid rules don't match (logged as `skipped_reason`).

### 8.5 Standard vs Advanced Distribution

- **Standard:** All active deliveries with `advanced_distribution_only=false` run per multi-sell rules
- **Advanced:** Orchestrated exclusively through distribution config
- Prevent double-selling: deliveries flagged `advanced_distribution_only` must NOT run in standard when toggled off in advanced UI

### 8.6 Multi-Sell vs Exclusive

| Mode | Behavior |
|------|----------|
| Exclusive | First successful delivery stops pipeline |
| Multi-sell | Continue to additional deliveries (max count configurable) |
| Hybrid | Exclusive within group, multi-sell across groups (configurable) |

### 8.7 Unsold Handling

1. Quarantine for manual review or scheduled release
2. Retry queue (re-run distribution after N minutes)
3. Fallback waterfall (lower-priority fixed-price buyers)
4. Email ping-post (partial data via email — LeadByte feature)
5. Remarketing (SMS/email nurture)

---

## 9. Deliveries

Mirror LeadByte delivery configuration screen field-for-field.

### 9.1 Delivery Configuration Schema

```yaml
delivery:
  name: string
  campaign_id: uuid
  trigger_type: on_lead_arrival | manual_via_api
  method: direct_post | ping_post | two_step_auth | email | sms | store_lead | campaign_transfer
  buyer_id: uuid (optional)
  advanced_distribution_only: boolean

  # Financials
  revenue_type: fixed | dynamic | rule_based
  revenue_amount: decimal
  revenue_rules: [{ conditions, amount }]
  payout_override: decimal (optional)

  # Caps
  cap_type: delivery | buyer
  caps:
    total: int
    daily: int
    hourly: int
    weekly: int
    monthly: int
    day_specific: { mon: int, tue: int, ... }

  # Target
  crm_type: http_json | http_post | http_get | salesforce | custom
  url: string
  headers: [{ key, value }]
  custom_post_data: string  # JSON template with [field] tags
  custom_data_mappings: [{ input_field, output_field, static_value, transforms }]

  # PingPost specific
  ping_url: string
  ping_payload: string
  ping_success_rules: [{ field, operator, value }]
  post_url: string
  post_payload: string
  ping_response_type: json | xml

  # Remote system response matching
  response_rules:
    - match_by: http_status | keyword | json_path
      value: "200" | "Approved" | "$.status==accepted"
      label: success | reject

  # Scheduling & geo
  schedule: { timezone, windows: [{ day, start, end }] }
  location_filter: { states: [], counties: [], zip_prefixes: [] }

  # Rules
  eligibility_rules: RuleSet

  # Chaining
  on_success_trigger_delivery_id: uuid
  on_failure_trigger_delivery_id: uuid

  # Notifications
  trigger_sms_on_success: boolean
  trigger_email_on_success: boolean

  status: saved | inactive | active
```

### 9.2 Delivery Methods Detail

| Method | Implementation Notes |
|--------|---------------------|
| **Direct Post** | HTTP client with mapping engine, tag substitution, SSL cipher support |
| **PingPost** | Two-phase with response capture store for `{$ping.*}` interpolation |
| **2-Step Auth** | First call extracts token → inject into second call header/body |
| **Email** | Template with `[field]` tags, attachment option |
| **SMS** | Twilio/MessageBird; billable per message |
| **Store Lead** | Assign buyer + revenue without HTTP; for manual fulfillment |
| **Campaign Transfer** | Re-ingest lead into target campaign |

### 9.3 Tag / Macro System

Support LeadByte-style tags in payloads, email, SMS, postbacks:

| Tag | Output |
|-----|--------|
| `[firstname]` | Field value |
| `[received]` | Lead received timestamp |
| `[deliveredtime]` | Delivery timestamp |
| `[revenue]` | Revenue amount |
| `[age]` | Computed from DOB |
| `[dobday]`, `[dobmonth]`, `[dobyear]` | DOB parts |
| `{$ping.PingID}` | From ping response |

### 9.4 Delivery Logs

Store per attempt:
- `lead_id`, `delivery_id`, `buyer_id`
- `ping_request`, `ping_response_raw`, `ping_duration_ms`
- `post_request`, `post_response_raw`, `post_duration_ms`
- `http_status`, `matched_response_label`
- `revenue_assigned`, `skipped_reason`
- `status`: success | failed | skipped | timeout

---

## 10. Caps, Financials & Billing

### 10.1 Cap Hierarchy

Caps checked in order (first fail → skip delivery/buyer):

1. Campaign cap
2. Supplier cap (per campaign supplier config)
3. Buyer cap (global buyer settings)
4. Delivery cap

Cap periods: hourly, daily (incl. day-specific), weekly, monthly, total.

Implementation: Redis counters with TTL per period + DB persistence for audit.

### 10.2 Revenue Types

| Type | When Assigned |
|------|---------------|
| **Fixed** | On delivery success |
| **Dynamic** | Parse from buyer API response (e.g. `Cost` field) |
| **Rule-based** | Field conditions (e.g. `source=Facebook → £15, else £10`) |

### 10.3 Payout Types

| `payout_supplier_on` | Behavior |
|---------------------|----------|
| `system_accept` | Pay supplier when lead passes validation into campaign |
| `buyer_delivery_accept` | Pay supplier only when buyer delivery succeeds |

Advanced supplier payout: conditional rules for when NOT to pay.

### 10.4 Buyer Billing (Stripe)

- Buyer portal card on file
- Auto top-up when balance low
- Deduct per sold lead
- Credit via API (`Add Buyer credit via REST API` — LeadByte feature)
- Block delivery when insufficient balance (configurable)

### 10.5 Returns

Buyers submit returns via portal or API:
- Return reason codes
- Auto-adjust revenue / clawback supplier payout
- Return rate reporting per supplier (SSID)

### 10.6 P&L Per Lead

```
margin = revenue - supplier_payout - validation_costs
```

Track at: lead, campaign, supplier, SID, SSID, ad campaign (via c1–c5 / UTM).

---

## 11. Quarantine & Retry

### Lead Quarantine (LeadByte Feature)

Hold leads when:
- Outside buyer delivery schedule
- Awaiting manual review
- Enrichment pending
- Failed distribution (optional)

Release modes:
- Manual approve/reject
- Scheduled auto-release when buyers online
- Reprocess via API (`Update and processing quarantine leads via REST API`)

### Retry Queue

Unsold leads → retry distribution:
- Configurable delay
- Max attempts
- Only retry eligible buyers (caps may have reset)

---

## 12. Portals (Buyer / Supplier)

### 12.1 Buyer Portal

- Custom login URL (white-label domain)
- Multi-language support
- Download leads (CSV)
- Real-time stats (volume, acceptance, returns)
- Submit feedback per lead (conversion status)
- Submit returns
- Upload suppression / DNC lists
- Stripe payment / top-up
- Sign insertion orders
- Cap visibility

### 12.2 Supplier Portal

- Stats by SID / SSID
- Download leads
- CSV import (if API not available)
- Payout reports
- API integration guide (per campaign reference)

### 12.3 Admin Dashboard

- Buyer Schedule (pause contracts, change payout/caps/hours on the fly)
- Change log (all account activity)
- Event alerts (lead flow stopped, cap nearly full, high reject rate)
- Campaign management

---

## 13. Reporting, Analytics & Webhooks

### 13.1 Real-Time Reports

| Report | Dimensions |
|--------|------------|
| Lead volume | Campaign, supplier, SID, SSID, hour |
| Sold / unsold / duplicate / error | Campaign, delivery, buyer |
| Revenue & margin | Campaign, buyer, supplier, source |
| Buyer feedback | Conversion rate by source |
| Cap utilization | Buyer, delivery, campaign |
| Validation failures | Service, reason code |

### 13.2 Custom Reports

User-defined:
- Filters, groupings, metrics
- Scheduled email delivery

### 13.3 Outbound Webhooks

Push events to external BI:
- `lead.accepted`, `lead.rejected`, `lead.sold`, `lead.unsold`
- `delivery.success`, `delivery.failed`
- `buyer.feedback`, `lead.returned`

Payload includes full lead + financial data.

### 13.4 Tracking Pixels / Postbacks

Fire on:
- Lead accepted (system accept)
- Delivery successful
- Lead returned

Per supplier or global. Append all lead fields as query params.

---

## 14. REST API Specification

Mirror [LeadByte REST API section](https://support.leadbyte.co.uk/hc/en-us/sections/360007950571-REST-API).

### 14.1 API Key Management

```
POST   /api/v1/admin/api-keys
GET    /api/v1/admin/api-keys
DELETE /api/v1/admin/api-keys/{id}

# Key types: administrator | supplier
# Permissions: leads.create, leads.search, reports.read, buyers.create, ...
```

### 14.2 Lead Endpoints

```
POST   /api/v1/leads                          # Ingest (JSON)
POST   /api/v1/leads/import                   # CSV upload
GET    /api/v1/leads/{id}                     # Get lead
POST   /api/v1/leads/search                   # Search/filter
PUT    /api/v1/leads/{id}                     # Update lead
POST   /api/v1/leads/{id}/reprocess         # Re-run pipeline
POST   /api/v1/leads/{id}/quarantine        # Quarantine actions
GET    /api/v1/leads/queue/{queue_id}       # Live buyer response poll
```

### 14.3 Delivery Endpoints

```
POST   /api/v1/deliveries                     # Create delivery
PUT    /api/v1/deliveries/{id}                # Update
POST   /api/v1/deliveries/{id}/trigger        # Manual trigger (Delivery ID)
POST   /api/v1/deliveries/{id}/test           # Test delivery
GET    /api/v1/deliveries/{id}/logs           # Delivery logs
```

### 14.4 Buyer Endpoints

```
POST   /api/v1/buyers
PUT    /api/v1/buyers/{id}
POST   /api/v1/buyers/{id}/credit             # Add credit
POST   /api/v1/buyers/{id}/feedback           # Lead feedback
POST   /api/v1/buyers/{id}/returns            # Process return
```

### 14.5 Reports

```
GET    /api/v1/reports/leads
GET    /api/v1/reports/revenue
GET    /api/v1/reports/suppliers
GET    /api/v1/reports/buyers
```

### 14.6 Buyer-Facing Ping/Post API (Your Platform as Buyer)

When **you** act as buyer in someone else's ping tree, expose:

```
POST /api/v1/ping
POST /api/v1/post
```

With standard response contract:
```json
{ "Success": true, "Cost": 15.00, "PingID": "uuid" }
```

### 14.7 Consent API

`Handling 1:1 Consent` — LeadByte feature for TCPA/GDPR:
- Store consent artifact per lead
- Expose consent proof in portal and API

---

## 15. Database Schema

### Core Tables (PostgreSQL recommended)

```sql
-- Tenancy
accounts (id, name, timezone, default_currency, settings_json)
users (id, account_id, email, role, 2fa_enabled)

-- Network
buyers (id, account_id, reference, name, status, stripe_customer_id, credit_balance, caps_json, schedule_json)
suppliers (id, account_id, reference, name, status)
sources (id, supplier_id, sid, name, caps_json, payout_override)
sub_suppliers (id, source_id, ssid, name)

-- Campaigns
campaigns (id, account_id, type, name, reference, country, currency, status, vertical_id, settings_json)
campaign_fields (id, campaign_id, name, type, required, validation_json)
campaign_suppliers (campaign_id, supplier_id, caps_json, payout_json)
distribution_configs (id, campaign_id, name, config_json)  -- tiers, groups, modes

-- Deliveries
deliveries (id, campaign_id, buyer_id, name, method, trigger_type, config_json, status)
delivery_logs (id, lead_id, delivery_id, ping_json, post_json, status, revenue, duration_ms, skipped_reason)

-- Leads
leads (id, account_id, campaign_id, supplier_id, source_id, status, field_data_json, metadata_json, received_at)
lead_events (id, lead_id, event_type, payload_json, created_at)
lead_financials (lead_id, revenue, payout, margin, currency)

-- Dedupe & Suppression
suppression_hashes (account_id, field_type, hash, source)
dedupe_index (account_id, campaign_id, field_key, field_value_hash, lead_id, expires_at)

-- Caps (materialized counters)
cap_counters (entity_type, entity_id, period, count, reset_at)

-- API & Webhooks
api_keys (id, account_id, type, supplier_id, permissions_json, key_hash)
webhooks (id, account_id, url, events_json, secret)

-- Portals
insertion_orders (id, buyer_id, campaign_id, document_url, signed_at)
buyer_feedback (id, lead_id, buyer_id, status, converted, notes)
lead_returns (id, lead_id, buyer_id, reason, status)
```

### Indexes

- `leads(campaign_id, received_at DESC)`
- `leads(account_id, status, received_at)`
- `dedupe_index(account_id, field_key, field_value_hash)` — critical for speed
- `delivery_logs(lead_id)`, `delivery_logs(delivery_id, created_at)`

---

## 16. Event Flow & State Machine

### Lead States

```
[INGESTED] → [VALIDATING] → [ACCEPTED] → [DISTRIBUTING] → [SOLD]
                ↓               ↓              ↓
           [REJECTED]     [QUARANTINED]   [UNSOLD] → [RETRY] → [QUARANTINED]
                                                      ↓
                                                 [REMARKETING]
```

### Sold Lead Side Effects (in order)

1. Assign revenue (fixed/dynamic/rule)
2. Decrement caps
3. Deduct buyer credit (if prepaid)
4. Log delivery success
5. Trigger postback / webhook
6. Trigger on_success chained delivery
7. Trigger responder (SMS/email)
8. Calculate supplier payout (if `buyer_delivery_accept`)
9. Fire tracking pixel

---

## 17. Security & Compliance

| Area | Implementation |
|------|----------------|
| **Auth** | API keys (hashed), JWT for portals, 2FA |
| **IP allowlist** | Per account, buyer, supplier |
| **PII** | Encrypt at rest; minimize in ping payloads |
| **Hashed suppression** | SHA-256; never store buyer raw list in plain text |
| **GDPR** | Consent fields, opt-out, data retention policies, export/delete |
| **TCPA** | 1:1 consent documentation, DNC scrubbing |
| **Audit** | Change log for all config changes |
| **Rate limiting** | Per API key, per IP |

---

## 18. Implementation Phases

### Phase 1 — Foundation (Weeks 1–4)
- [ ] Account, users, RBAC
- [ ] Campaign CRUD + field schema
- [ ] REST API ingest (admin + supplier keys)
- [ ] Basic validation + standard dedupe
- [ ] Direct post delivery (HTTP JSON)
- [ ] Delivery logs
- [ ] Lead search + status

### Phase 2 — Distribution Core (Weeks 5–8)
- [ ] Distribution engine interface
- [ ] Waterfall / priority routing
- [ ] PingPost delivery (ping → evaluate → post)
- [ ] Ping tree tiers with parallel auction
- [ ] Floor price + timeout handling
- [ ] Caps (campaign, buyer, delivery)
- [ ] Live buyer response (sync mode)

### Phase 3 — Advanced Routing (Weeks 9–12)
- [ ] Advanced distribution UI + config
- [ ] Hybrid groups + hybrid rule groups
- [ ] Weighted + round-robin
- [ ] Multi-sell vs exclusive
- [ ] Campaign transfer delivery
- [ ] On success/failure delivery chains
- [ ] Quarantine + reprocess API

### Phase 4 — Network & Money (Weeks 13–16)
- [ ] Buyer + supplier entities, SID/SSID
- [ ] Supplier payout rules
- [ ] Revenue types (fixed, dynamic, rule-based)
- [ ] Buyer portal v1
- [ ] Supplier portal v1
- [ ] Stripe billing + credit
- [ ] Returns + feedback API

### Phase 5 — Scale & Verticals (Weeks 17–20)
- [ ] Vertical registry + field templates
- [ ] Validation service plugins (HLR, email, IP)
- [ ] Suppression campaigns + hashed lists
- [ ] CSV import/export schedules
- [ ] Webhooks + postbacks
- [ ] Reporting API + dashboards
- [ ] Email/SMS responders + bulk SMS

### Phase 6 — Integrations (Weeks 21–24)
- [ ] Facebook/Google/TikTok lead sync
- [ ] Form builder + domain lock
- [ ] Email ping-post
- [ ] 2-step auth deliveries
- [ ] Custom reports + scheduled exports
- [ ] Event alerts
- [ ] Buyer schedule dashboard

---

## 19. Tech Stack Recommendations

| Layer | Suggestion | Rationale |
|-------|------------|-----------|
| API | Laravel 11 / NestJS / Go | High throughput, mature ecosystem |
| Queue | Redis + Horizon / BullMQ | Real-time + retry |
| DB | PostgreSQL | JSON fields, reliability |
| Cache | Redis | Caps, dedupe, rate limits |
| Search | Elasticsearch / Meilisearch | Lead search at scale |
| HTTP delivery | Dedicated worker pool | Isolated timeouts, connection pooling |
| Email/SMS | SendGrid + Twilio | Industry standard |
| Payments | Stripe | Matches LeadByte |
| Frontend | Vue/React + Inertia or separate SPA | Admin + portals |
| Infra | AWS (ECS/EKS) or Laravel Vapor | LeadByte uses Amazon infrastructure |

### Key Libraries

- `libphonenumber` — phone validation
- `guzzle` / `axios` — HTTP delivery
- `jsonpath` — dynamic response parsing
- `bull` / Laravel queues — job processing

---

## 20. Appendix: Vertical Field Templates

### Auto Insurance (US)

| Field | Ping | Post | Required |
|-------|------|------|----------|
| zipcode | ✓ | ✓ | ✓ |
| state | ✓ | ✓ | ✓ |
| currently_insured | ✓ | ✓ | ✓ |
| vehicle_year | ✓ | ✓ | |
| vehicle_make | ✓ | | |
| age_band | ✓ | | |
| firstname | | ✓ | ✓ |
| lastname | | ✓ | ✓ |
| email | | ✓ | ✓ |
| phone1 | | ✓ | ✓ |
| dob | | ✓ | |
| address | | ✓ | |

### Mortgage (US)

| Field | Ping | Post |
|-------|------|------|
| zipcode, state | ✓ | ✓ |
| loan_amount_band | ✓ | |
| loan_amount | | ✓ |
| credit_band | ✓ | |
| property_type | ✓ | ✓ |
| firstname, lastname, email, phone1 | | ✓ |

### Solar (US)

| Field | Ping | Post |
|-------|------|------|
| zipcode, state | ✓ | ✓ |
| electric_bill_band | ✓ | |
| homeowner | ✓ | ✓ |
| roof_shade | ✓ | |
| firstname, lastname, email, phone1, address | | ✓ |

### Legal / MVA

| Field | Ping | Post |
|-------|------|------|
| state, zipcode | ✓ | ✓ |
| incident_type | ✓ | ✓ |
| incident_date_band | ✓ | |
| injury_type | ✓ | |
| PII fields | | ✓ |

### Home Services

| Field | Ping | Post |
|-------|------|------|
| zipcode, service_type | ✓ | ✓ |
| project_timeline | ✓ | |
| homeowner | ✓ | ✓ |
| PII fields | | ✓ |

### Universal System Fields (All Verticals)

```
sid, ssid, source, c1, c2, c3, c4, c5
ip_address, user_agent, optin_url, consent_text, channel_consent
received_at, campaign_reference
utm_source, utm_medium, utm_campaign, utm_content, utm_term
```

---

## Reference Links

- [LeadByte Homepage](https://www.leadbyte.co.uk/)
- [LeadByte Features](https://www.leadbyte.co.uk/features)
- [Campaigns Explained](https://support.leadbyte.co.uk/hc/en-us/articles/212148106-Campaigns-Explained)
- [Deliveries Explained](https://support.leadbyte.co.uk/hc/en-us/articles/213732163-Deliveries-Explained)
- [PingPost Setup](https://support.leadbyte.co.uk/hc/en-us/articles/4408559546769-PingPost)
- [Hybrid Rule Groups](https://support.leadbyte.co.uk/hc/en-us/articles/20380764305937-Hybrid-Rule-Groups)
- [REST API Docs](https://support.leadbyte.co.uk/hc/en-us/sections/360007950571-REST-API)
- [Advanced Distribution Blog](https://www.leadbyte.co.uk/blog/gain-a-competitive-advantage-with-advanced-lead-distribution)

---

## Implementation Status (Laravel + Vue Build)

**Stack:** Laravel 13, Inertia Vue 3, SQLite/MySQL, Queue workers

### Multi-Tenancy (Partner Platforms)

- `accounts` table — each row is an isolated partner platform
- `BelongsToAccount` global scope + `AccountContext` for request/job scoping
- Super admin can switch platforms via `/accounts` UI
- API keys scoped to `account_id`; supplier keys locked to `supplier_id`
- Optional white-label: `accounts.domain` field reserved for portal domains

### Built (Phases 1–4)

| Component | Implementation |
|-----------|----------------|
| Lead ingest API | `POST /api/v1/leads` with sync/async modes |
| API keys | Admin + Supplier types with permissions |
| Validation | Campaign fields, email, phone, custom rules |
| Dedupe | Email/phone hash index, cross-campaign, suppression |
| Distribution | Waterfall, ping-post, parallel auction, weighted, round-robin, hybrid |
| Deliveries | Direct post, ping-post, store lead + delivery logs |
| Caps | Campaign, buyer, delivery counters |
| Financials | Revenue, payout, margin per lead |
| Webhooks | Outbound on sold/unsold |
| Error logging | `PlatformLogger` + `system_error_logs` + `platform` log channel |
| Admin UI | Dashboard, campaigns, deliveries, buyers, suppliers, leads |
| Tests | 34 PHPUnit tests (API, tenancy, rules) |

### Pending (Phases 5–6)

- Buyer/supplier self-service portals with separate auth
- Stripe billing integration
- CSV import/export schedules
- Facebook/Google lead sync
- Form builder with domain lock
- Email/SMS responders

---

*Document version: 1.1 — mirrors LeadByte platform structure as of 2026. Use as the single source of truth for engineering, product, and integration work.*
