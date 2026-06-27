# 02 - Campaigns & Verticals

## Purpose

Campaigns are the core routing containers in PowerByExcellence. Each campaign belongs to a **vertical** (e.g. Auto Insurance, Loans, Solar) which determines default field templates, floor prices, and payout defaults. Campaigns link to deliveries, distribution (ping tree) configs, dedupe rules, and validation settings. This doc covers campaign CRUD, vertical assignment, field templates, and the campaign detail view.

---

## Where to Find It

| Item | Location |
|------|----------|
| Campaign list | `/campaigns` |
| Create campaign | `/campaigns/create` |
| Campaign detail | `/campaigns/{id}` |
| Edit campaign | `/campaigns/{id}/edit` |
| Validation settings | Campaign show page → Validation panel (PATCH `/campaigns/{id}/validation`) |
| Navigation | Sidebar → **Campaigns** → All Campaigns |
| Access | Account Admin |

---

## Seeded UK Campaigns

| Name | Reference | Vertical |
|------|-----------|----------|
| Auto Insurance | `auto-insurance-uk` | Auto Insurance |
| Loans | `loans-uk` | Loans |
| Mortgage | `mortgage-uk` | Mortgage |
| Payday Loans | `payday-loans-uk` | Payday Loans |
| Solar | `solar-uk` | Solar |

US platform has one campaign: `solar-us` (Solar vertical).

---

## How to Test (Step-by-Step)

### 1. List campaigns

1. Log in as `uk@powerbyexcellence.test`
2. Navigate to `/campaigns`

**Expected:** Five UK campaigns listed with lead counts. Pagination works if more than 25 campaigns (not in default seed).

### 2. Open campaign detail - Auto Insurance

1. Click **Auto Insurance** (or navigate to its show page)
2. Review sections: overview, fields, linked deliveries, distribution configs

**Expected:**
- Reference: `auto-insurance-uk`
- Vertical: Auto Insurance
- `use_advanced_distribution`: enabled
- Floor price and payout visible
- Campaign fields include: firstname, lastname, email, phone1, zipcode, vehicle_year, vehicle_make, current_insurer
- Some fields marked as **ping fields** (zipcode, vehicle_year, etc.)
- Multiple deliveries listed (Store Lead, Real-Time Auction, Direct API, Email)
- Distribution config **10-Tier Enterprise Ping Tree** shown as active

### 3. Verify vertical-specific fields per campaign

Repeat show-page review for:

| Campaign | Vertical-specific fields to confirm |
|----------|-------------------------------------|
| Loans | `loan_amount`, `loan_purpose`, `credit_score` |
| Mortgage | `property_value`, `loan_amount`, `employment_status` |
| Payday Loans | `loan_amount`, `employment_status` |
| Solar | `roof_type`, `monthly_bill`, `homeowner` |

**Expected:** Each campaign has fields auto-seeded from the vertical catalog when created.

### 4. Create a new campaign

1. Click **New Campaign** → `/campaigns/create`
2. Fill in:
   - Name: `QA Test Campaign`
   - Reference: `qa-test-uk`
   - Vertical: **Loans**
   - Floor price: `10`
   - Payout: `5`
   - Bidding mode: Real-time auction
   - Enable advanced distribution: checked
3. Submit

**Expected:** Redirect to campaign show page. Success flash message. Loan vertical fields auto-created (loan_amount, etc.). No deliveries yet - empty deliveries section.

### 5. Edit campaign

1. From show page, click **Edit**
2. Change floor price to `12`
3. Save

**Expected:** Show page reflects updated floor price. Existing deliveries retain their config.

### 6. Update validation rules

1. On campaign show page, locate **Validation** section
2. Toggle or adjust validation settings (e.g. required field enforcement)
3. Save validation

**Expected:** Success message. Invalid API leads rejected per updated rules.

### 7. Delete test campaign

1. Delete `QA Test Campaign` from edit page or list actions
2. Confirm deletion

**Expected:** Campaign removed from list. Redirect to `/campaigns` with success flash.

### 8. Multi-tenant isolation (super-admin)

1. Log out
2. Log in as `us@powerbyexcellence.test` (Partner Leads US tenant)
3. Open `/campaigns`

**Expected:** Only US-scoped campaigns for that partner platform (e.g. **Solar** `solar-us`). No campaigns from Excellence Leads UK or other tenants.

**Note:** UK vs US markets on a *single* partner platform are modeled as separate **campaigns** (country, currency, buyers per campaign) - not as separate tenants. Multi-tenancy is for isolating distinct partner businesses under super-admin.

---

## Expected Results (Summary)

- Vertical selection on create auto-populates field templates
- Campaign show page links deliveries and distribution configs
- Auto Insurance campaign has active **10-Tier Enterprise Ping Tree**
- Campaign reference used in API ingest as `campaign_reference`
- Partner platforms (tenants) are fully isolated under super-admin
- UK, US, and other geographies on one platform use separate campaigns

---

## Edge Cases

| Scenario | Expected behaviour |
|----------|-------------------|
| Duplicate campaign reference | Validation error on create |
| Create campaign without vertical | Default general fields (firstname, lastname, email, phone, zipcode) |
| Delete campaign with leads | Cascade or block depending on DB constraints - verify no orphaned leads in UI |
| Super admin on wrong tenant | Switch platform at `/accounts` before creating campaigns |
| Campaign without advanced distribution | Standard priority waterfall routing used instead of ping tree |
| Edit reference on campaign with API traffic | May break existing integrations - reference should be treated as immutable in production |

---

## Related Docs

- [03-deliveries-and-10-tier-ping-tree.md](./03-deliveries-and-10-tier-ping-tree.md) - buyer delivery methods
- [04-distribution-ping-tree.md](./04-distribution-ping-tree.md) - tier configuration
- [09-api-and-sdk.md](./09-api-and-sdk.md) - ingest by `campaign_reference`
