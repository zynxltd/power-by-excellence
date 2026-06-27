<?php

return [
    'category' => 'Supplier Portal',
    'slug' => 'supplier-portal-overview',
    'title' => 'Supplier Portal Overview',
    'summary' => 'Affiliate and supplier self-service for lead submissions, performance, and payouts.',
    'audience' => 'supplier',
    'sort_order' => 10,
    'body' => <<<'MD'
## Overview

The **Supplier Portal** is your self-service dashboard for leads you send into a partner platform. If you are an **affiliate, media buyer, or lead source**, this portal lets you monitor submissions, conversion outcomes, and payout earnings - without needing admin access to the platform operator's console.

You sign in on your **tenant subdomain** (for example `partner-uk.powerbyexcellence.test`), not the central marketing site. After login you land on `/portal/supplier`.

## Who this is for

Supplier portal users are linked to a **Supplier** record on the platform. Your account manager or platform administrator creates the supplier profile, assigns **Sources (SIDs)**, and provisions portal login credentials. You see only leads attributed to your supplier account - never another affiliate's traffic.

Typical users include:

- PPC and paid-search affiliates sending leads via API
- Email and display partners with sub-affiliate networks
- Call-centre or form publishers reconciling daily submissions

## What you can do

### Monitor performance

1. Open `/portal/supplier` to view today's stats: leads submitted, sold count, and payout total
2. Review **7-day charts** for submission volume, sold rate, and daily payout trends
3. Check the **Your Sources (SID)** panel to confirm which source identifiers are active under your account

### Browse and filter leads

1. Go to `/portal/supplier/leads`
2. Filter by **status**, **campaign**, **date range**, or search by **UUID / email**
3. Paginate through results (25 per page) and inspect payout per row

### Export for reconciliation

1. From the Leads page, set your **From** and **To** date filters
2. Click **Export CSV** to download up to 5,000 leads as `supplier-leads.csv`
3. Match UUIDs against your internal tracking or postback logs

### Review payouts

1. Open `/portal/supplier/billing`
2. View **Total Payouts**, **This Month**, and **Sold Leads** summary cards
3. Scroll the **Recent Payouts** table for the latest 25 sold leads with amounts and sold timestamps

## What you cannot do

The portal is **read-only reporting**. You cannot:

- Create or edit campaigns, buyers, or ping trees (tenant admin only)
- Change payout rates or rev-share percentages
- View other suppliers' leads, sources, or earnings
- See buyer identities or buyer-side pricing beyond your own payout on sold leads
- Submit leads through the portal UI (use the REST API for ingest)

## Portal URLs

| Page | Path | Purpose |
|------|------|---------|
| Dashboard | `/portal/supplier` | Today's stats, 7-day charts, SID list |
| Leads | `/portal/supplier/leads` | Searchable lead table with filters |
| CSV export | `/portal/supplier/leads/download` | Download filtered lead data |
| Payouts | `/portal/supplier/billing` | Lifetime and monthly earnings summary |

## API vs portal

Most affiliates use **both** channels:

| Method | Purpose |
|--------|---------|
| **REST API** (`POST /api/v1/leads`) | Real-time lead submission with your API key |
| **Supplier portal** | Performance reporting, CSV exports, payout visibility |

Your **API key** authenticates ingest requests. Your **portal email and password** authenticate the dashboard. These are separate credentials - losing one does not affect the other.

## Typical daily workflow

1. **Morning** - Check `/portal/supplier` for yesterday's sold rate and payout
2. **Midday** - Filter `/portal/supplier/leads` by `quarantined` or `rejected` to spot data-quality issues
3. **End of day** - Export CSV for the current date range and reconcile against ad spend or sub-affiliate reports
4. **Weekly** - Compare `/portal/supplier/billing` monthly total with your account manager's statement

## Tips

- Bookmark your tenant login URL (`{subdomain}/login`) - credentials are not valid on other partner subdomains
- Your API key and SID values are issued by the platform administrator - portal login is separate from API authentication
- Sub-affiliates are tracked via **SSID**; see the dashboard Sources panel and the **SID, SSID & Postback Tracking** article
- Sold rate = sold ÷ received - benchmark by campaign vertical, not across unrelated offers
- Payout shown in the portal is **your rev-share**, not the buyer's purchase price

## Troubleshooting

- **Cannot sign in on the main website** - Use your partner subdomain `/login` only
- **Redirected to wrong portal** - Buyer and supplier portals are separate; confirm your account has the `supplier_portal` role
- **Dashboard shows zero leads** - Verify you are posting with the correct `supplier_id` mapping via API and that SIDs match configured Sources
- **403 / Supplier account not linked** - Your portal user is not linked to a Supplier record; contact your account manager
- **Sources panel is empty** - No Sources configured yet; ask the platform admin to add SIDs under your supplier profile
MD,
];
