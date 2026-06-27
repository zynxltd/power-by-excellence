<?php

return [
    'category' => 'Buyer Portal',
    'slug' => 'buyer-portal-overview',
    'title' => 'Buyer Portal Overview',
    'summary' => 'What the buyer portal is, who it is for, how leads arrive, and where to find every feature.',
    'audience' => 'buyer',
    'sort_order' => 10,
    'body' => <<<'MD'
## Overview

The **Buyer Portal** is your self-service dashboard for leads purchased through your partner platform. After your account administrator creates your buyer profile and portal login, you sign in on the **tenant subdomain** assigned to your brand - for example `excellence-uk.powerbyexcellence.test` or `insurance-ca.powerbyexcellence.test`. You cannot use the central marketing domain or another partner's subdomain with the same credentials.

The portal is designed for day-to-day lead operations: checking credit, reviewing what was sold to you, exporting data for your CRM, and reporting outcomes back to the platform. You do not configure campaigns, ping trees, or routing rules - those are managed by the platform operator on your behalf.

## Who this is for

Buyer portal users are **lead purchasers** - call centres, lenders, insurers, aggregators, or marketing agencies who receive leads via ping-post or direct post. Typical roles include:

- **Operations managers** - monitor daily volume, credit, and spend trends
- **Dialler / CRM admins** - export CSV batches and reconcile UUIDs with imports
- **Quality teams** - submit conversion feedback and return requests for disputed leads

Each portal user is linked to exactly one **buyer account**. If your organisation runs multiple buyer IDs (e.g. separate brands or geographies), you need a separate login for each unless your administrator consolidates access.

## What you can do in the portal

| Capability | Where to find it |
|------------|------------------|
| Credit balance & 7-day charts | `/portal/buyer` (Dashboard) |
| Search and filter sold leads | `/portal/buyer/leads` |
| CSV export (up to 5,000 rows) | `/portal/buyer/leads/download` |
| Conversion feedback & returns | Forms on `/portal/buyer/leads` |
| Credit ledger & transactions | `/portal/buyer/billing` |

After login, the sidebar shows **Dashboard**, **My Leads**, and **Billing** (exact labels may match your platform branding).

## How you receive leads

Leads arrive in real time when the platform distribution engine selects you as the winning buyer in a ping tree or accepts your filters on a direct post. The sequence is:

1. A consumer submits a form or API payload to a supplier
2. The platform validates, deduplicates, and routes the lead through configured campaigns
3. Your buyer node is pinged (or posted to directly) with price and field preview
4. If you accept and win the auction, the lead is **sold** to your buyer ID
5. The full record appears in **My Leads** with `distributed_at` set to the delivery time
6. If prepay billing is enabled, your **credit balance** is debited by the lead revenue amount

You only see leads where `sold_to_buyer_id` matches your account. Leads you were pinged for but did not win, or that failed validation, do not appear in your inventory.

## Getting started

1. Receive your login URL and credentials from your platform administrator or account manager
2. Open `https://{your-partner-subdomain}/login` in a browser
3. Enter your email and password, then click **Log in**
4. You land on **Buyer Dashboard** at `/portal/buyer`
5. Confirm **Credit Balance** and **Leads Today** look reasonable for your campaigns
6. Open **My Leads** to verify recent deliveries match your dialler or CRM
7. Bookmark the subdomain login URL - it is unique to your platform

## Portal URLs reference

| Page | Path | What you see |
|------|------|--------------|
| Dashboard | `/portal/buyer` | Stat cards, 7-day charts, 10 most recent leads |
| All leads | `/portal/buyer/leads` | Filterable table, feedback/return forms |
| CSV download | `/portal/buyer/leads/download` | Browser download of `leads.csv` |
| Billing & transactions | `/portal/buyer/billing` | Balance, prepay status, transaction ledger |
| Transactions (alias) | `/portal/buyer/transactions` | Same as billing page |

## Example scenarios

### Morning ops check

Sarah manages a UK insurance call centre. Each morning she opens `/portal/buyer`, checks **Leads Today** against her dialler queue, and compares **Spend** on the 7-day chart to her weekly CPL target. If credit is below two days of average spend, she emails her account manager for a top-up before peak hours.

### Reconciling a CRM import

James imported 200 leads overnight but 3 UUIDs failed dedupe in Salesforce. He opens `/portal/buyer/leads`, searches each UUID in the **Search** box, and confirms the leads were sold to his buyer ID on the expected dates. He uses **Export CSV** with the same date range to pull a fresh file for the missing records.

### New integration testing

After onboarding a new campaign, Priya filters **My Leads** by that campaign reference and reviews the first 10 **sold** records. She verifies `firstname`, `email`, `phone1`, and vertical-specific fields (e.g. `loan_amount`) match the campaign schema before pointing live traffic at the buyer node.

## Tips

- Bookmark your tenant login URL - credentials are not valid on other partner subdomains or the central domain
- If prepay is required, monitor **Credit Balance** daily; insufficient credit causes your buyer to be skipped in the ping tree
- Use **Download CSV** for bulk exports; the on-screen table supports search, status, campaign, and date filters
- Copy the full **UUID** from the leads table when submitting feedback or returns - partial fragments in the UI are for display only
- Date filters use the **platform timezone**, not your local timezone or UTC
- Share filtered lead URLs with teammates - filters persist in the query string on `/portal/buyer/leads`

## Troubleshooting

| Symptom | Likely cause | What to do |
|---------|--------------|------------|
| Cannot sign in on central domain | Buyer accounts must use the partner subdomain | Use `https://{your-brand}.powerbyexcellence.test/login` (URL from your agreement) |
| **"This account is not registered on {brand}."** | Wrong subdomain for your user record | Confirm the exact subdomain your administrator assigned |
| **"Partner platforms sign in at …"** | You tried logging in on the central host | Follow the link in the error to your partner `/login` |
| **403 - Buyer account not linked to this user.** | User exists but has no `buyer_id` link | Ask admin to link your portal user to the correct buyer profile |
| **Your account is not registered on this platform domain.** | Session or navigation on wrong tenant host | Sign out, use correct subdomain, sign in again |
| No leads visible | No sales yet, or filters too narrow | Clear filters on **My Leads**; confirm leads were sold to your buyer reference |
| Leads stopped arriving | Low credit (prepay) or buyer suspended | Check `/portal/buyer/billing` and contact your account manager |
MD,
];
