# UX & Navigation Audit

> **Last updated:** 25 June 2026  
> **Status:** Recommendations only - not yet implemented unless noted in [`IMPLEMENTATION_STATUS.md`](./IMPLEMENTATION_STATUS.md).

This document captures a product/UX review of admin navigation and user flows. The goal is to make the platform **dead simple** for operators while keeping advanced routing, ping-tree, and delivery configuration available behind progressive disclosure.

---

## Executive summary

The platform is **powerful but operator-shaped**. Technical users who already understand lead distribution can work effectively. New tenant admins and ops staff hit friction from **jargon**, **buried daily tasks**, **stacked navigation layers**, and **no obvious “go live” path**.

**Buyer and supplier portals** are the benchmark: three top-level links, one job each.

**Highest-impact improvements** (no backend required for most):

1. Campaign **go-live checklist** on campaign show
2. Promote **Buyers** and **Billing** out of the **More** menu
3. **Plain-English labels** (Ops → Today, Quarantine → Held leads, etc.)
4. Split **monitoring** vs **routing configuration** in the mental model

---

## Personas & mental models

| Persona | Primary goal | Current entry | Fit |
|---------|--------------|---------------|-----|
| Tenant ops | Are leads flowing? Any fires? | Home → Ops → Live ops / Leads | Moderate |
| Tenant admin | Set up campaign, buyers, billing | Campaigns → scattered tools | Weak |
| Finance | Revenue, credits, margin | More → Finance / Billing / Reports | Weak |
| Super admin | Which tenant? Platform health? | Command Center + tenant switcher | Confusing (3 context UIs) |
| Buyer / supplier | My leads / balance | Portal (3 links) | Strong |

---

## What works well

| Area | Detail |
|------|--------|
| **Top nav** | Home · Campaigns · Ops · Reports · More - compact, scrollable on small screens |
| **Campaign workflow bar** | Overview → Settings → API spec → Leads → Deliveries → Ping tree → Live ops - keeps campaign context |
| **Live stats bar** | Persistent today-metrics with drill-down links |
| **Compact stat strips** | Horizontal KPI rows on dashboard, ops, reports, finance, entity show pages |
| **Tenant hub (More)** | Campaign-scoped shortcuts when inside a campaign context |
| **Portal nav** | Dashboard · Leads · Billing/Payouts |

---

## Friction map

### Critical

#### 1. “More” is a junk drawer (~30 links)

`TenantHub` sections include buyers, suppliers, finance, four log types, integrations, webhooks, postbacks, imports, features, branding, settings, help, support, Horizon, Telescope, etc.

- Daily tasks (**Buyers**, **Billing**) compete with dev tools
- Mobile nav exposes Buyers/Finance; desktop hides them in More
- **Command Center** appears in More when a tenant is selected, but as top-level links only when no `tenantHub` is active

**Recommendation:** Top-level **Partners** (Buyers + Suppliers) and **Money** (Billing + Finance). Reserve **More** for logs, integrations, settings, help, super-admin tools.

#### 2. No “go live” story for campaigns

Typical path: create campaign → API spec → buyer + credit → delivery (8 steps) → optional ping tree → test ingest.

Nothing on campaign overview shows progress (“3 of 6 complete”) or blockers (“no active delivery”).

**Recommendation:** Setup checklist on campaign show: buyer exists, delivery active, API key, first lead today - each linking to the right screen. Optional “Send test lead” CTA.

#### 3. Tenant context explained in three places

Super admins see: header tenant dropdown, `TenantContextBanner`, More → switch tenant.

**Recommendation:** Single canonical context chip in header. Banner only when action required (“Select a platform to manage buyers”). Clear **all-tenants aggregate** vs **single-tenant manage** modes.

#### 4. Jargon in navigation

| Current | Suggested user label | Keep technical term in |
|---------|---------------------|-------------------------|
| Ops | **Today** or **Live** | Subtitle / help |
| Ping tree | **Routing order** | Advanced routing docs |
| Quarantine | **Held leads** | API, logs |
| Deliveries | **Buyer connections** | Delivery method enum |
| Distribution | **Waterfall routing** | Engine code |

---

### High

#### 5. Ops dropdown mixes monitor vs configure

**Monitor:** Live operations, Lead pipeline, Quarantine  
**Configure:** Deliveries, Ping tree, Routing simulator, Automation

**Recommendation:** Split nav or use one **Operations hub** with **Today** and **Routing** tabs.

#### 6. Three finance surfaces

Billing (credits), Finance (margin roll-up), Reports (analytics) overlap.

**Recommendation:** **Money** section with Overview / Credits / Reports (summary vs detail tabs).

#### 7. Stacked nav chrome on deep pages

Global header + live stats + tenant banner + campaign workflow bar + page header = heavy vertical use.

**Recommendation:** Slim campaign bar; hide tenant banner when context shown in header.

#### 8. Delivery setup complexity cliff

8-step delivery form is correct for power users; brutal for first setup.

**Recommendation:** Quick setup (method + URL + buyer + price) with “Advanced” expansion; templates; prominent “Test delivery” after save.

#### 9. Dashboard duplicates More menu

Quick-link panels mirror `TenantHub` / More sections.

**Recommendation:** Role-based Home (ops alerts vs setup tasks vs super-admin health).

---

### Medium

| Issue | Recommendation |
|-------|----------------|
| Five separate log indexes | Unified **Activity & logs** hub with tabs; search by lead UUID |
| Form builder under Campaigns dropdown | **Ingest** block on campaign show: API \| Form \| Import |
| Features vs Automation overlap | Hide Features from main path; surface alerts/responders in context |
| Mobile nav ≠ desktop | Align mobile sheet with desktop IA |
| Help buried in More | Contextual “?” on API spec and delivery forms |

---

## Target information architecture (proposed)

| Priority | Top nav | Contains |
|----------|---------|----------|
| 1 | **Home** | Today’s numbers, alerts, incomplete setup |
| 2 | **Campaigns** | List, new campaign, lead forms |
| 3 | **Today** | Live queue, all leads, held leads |
| 4 | **Partners** | Buyers, suppliers, API keys |
| 5 | **Money** | Credits, finance overview, reports entry |
| 6 | **More** | Logs, integrations, settings, help, dev tools |

Super admin: **Platforms** + **Command Center** always in the same place.

---

## Ideal troubleshooting flow

**Today:** User bounces Home → Ops → More → logs to answer “why isn’t this campaign selling?”

**Target:** Campaign overview → health strip → blocked step (no delivery / post failed / no credit / ingest) → one-click fix.

---

## Prioritized backlog

| Priority | Change | Effort | Impact |
|----------|--------|--------|--------|
| P0 | Campaign go-live checklist | Medium | Very high |
| P0 | Promote Buyers + Billing to top nav | Low | High |
| P0 | Plain-English nav labels | Low | High |
| P1 | Money hub or clearer Finance/Billing/Reports split | Medium | High |
| P1 | Today vs Routing split in Ops | Medium | High |
| P1 | Unified logs entry + lead search | Medium | High |
| P1 | Single tenant context pattern | Low–Med | High |
| P2 | Delivery quick setup + templates | High | High (onboarding) |
| P2 | Reports summary view | Medium | Medium |
| P2 | Role-based Home | Medium | Medium |
| P2 | Contextual help on complex forms | Medium | Medium |
| P3 | Collapse nav chrome on campaign pages | Low | Medium |
| P3 | Mobile/desktop nav parity | Low | Medium |

---

## What should stay complex

Gate behind **Advanced** or **More**:

- Ping-tree tier editor, eligibility rules, routing simulator
- Automation sequences, bulk SMS, alert thresholds
- Full API spec field matrix (link from checklist)
- Horizon / Telescope (super admin only)

---

## Related docs

- [`IMPLEMENTATION_STATUS.md`](./IMPLEMENTATION_STATUS.md) - built features and URL map
- [`functionalities/01-dashboard.md`](./functionalities/01-dashboard.md) - dashboard QA
- [`functionalities/02-campaigns-and-verticals.md`](./functionalities/02-campaigns-and-verticals.md) - campaign setup
- [`admin/command-center.md`](./admin/command-center.md) - super-admin cross-tenant view
