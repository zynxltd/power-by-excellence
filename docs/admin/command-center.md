# Command Center

**Route:** `/command-center` (central host, super admin only)

The Command Center is the operational dashboard for the entire multi-tenant platform. It answers: *Are all partner platforms healthy, and is delivery performing as expected?*

## Access

- User must be a **super admin**
- Must be on the **central marketing/admin host** (middleware `central.host`)
- Tenant-only admins cannot access this page

## Platform stats (header cards)

| Stat | Meaning |
|------|---------|
| **Tenants** | Active partner platform accounts |
| **Users** | All users across tenants |
| **Leads today** | Leads received today (all tenants) |
| **Sold today** | Leads marked `sold` today |
| **Pings / Posts** | Delivery log rows with ping/post payloads today |
| **Internal failed** | Platform-side failures (timeouts, exceptions, misconfiguration) — target **~0%** |
| **Post success %** | Successful posts ÷ posts attempted |
| **Pending queue** | Leads in `pending` or `processing` |
| **Failed jobs** | Horizon/queue failures |
| **Avg / P95 processing ms** | Pipeline latency vs configurable target |

## Tenant table

Each row is one `Account` with:

| Column | Use |
|--------|-----|
| **Health** | `healthy`, `warning`, `critical`, `idle` from `TenantHealth` |
| **Leads / Sold** | Today's ingest and distribution |
| **Pings / Posts** | Buyer integration activity |
| **Errors** | Internal failures today (platform bugs, timeouts) |
| **Buyer fail** | Buyer rejections and post failures (expected in waterfall) |
| **Post %** | Per-tenant post success rate |
| **Skipped** | Normal waterfall skips (filters, caps, outbid) |
| **Pending** | Backlog for that tenant |

### Actions

- **Visit** — Enter [god mode](./god-mode.md) on the tenant subdomain
- **Portal URL** — Open tenant dashboard in a new tab (after visit/handoff)

## Recent events & alerts

- **Lead events** — Last 30 platform-wide lifecycle events
- **Event alert fires** — Threshold alerts that fired recently (from tenant-configured `EventAlert` rules)

## Ops checks

`PlatformOpsCheck` runs infrastructure sanity checks (queue, Redis, Horizon, tenant DNS/Herd links). Review failures before blaming buyer integrations.

## Interpreting failures

Use `DeliveryLogClassifier` semantics:

| Type | Examples | Action |
|------|----------|--------|
| **Internal** | HTTP timeout, uncaught exception, missing URL | Fix platform or delivery config |
| **Buyer** | Post rejected, buyer HTTP 4xx/5xx with business reason | Contact buyer; often &lt;2–5% is normal |
| **Skipped** | Eligibility, cap, schedule, outbid | Expected ping-tree behaviour |

High **skipped** with low **failed** is usually healthy auction traffic.

## Tips

- Compare **internal failed** across tenants to spot one bad deployment or worker
- Use **Live Feed** for drill-down on individual leads
- After demo re-seed, buyer-fail rates should be ~2%, not ~30%
