# Live Feed

**Route:** `/live-feed` (central host, super admin only)

The Live Feed is a **paginated, real-time stream** of lead lifecycle events across all partner platforms.

## Purpose

- Monitor ingest spikes and distribution outcomes platform-wide
- Correlate user-reported issues with specific lead UUIDs
- Complement Command Center aggregates with row-level detail

## Data source

`PlatformLiveFeed` service aggregates recent `LeadEvent` records (and related lead metadata) across tenants without global scope restrictions.

## Usage

1. Open **Live Feed** from super-admin navigation
2. Paginate through events (25 per page)
3. Note `account_id` / tenant name when investigating
4. Cross-reference lead UUID in tenant **Delivery logs** or **Leads** admin

## vs tenant Operations

| Feature | Live Feed | Tenant Live Ops |
|---------|-----------|-----------------|
| Scope | All tenants | Current tenant only |
| Host | Central | Tenant subdomain |
| Audience | Super admin | Tenant staff |

## Tips

- Keep Live Feed open during deploys to catch processing stalls
- Pair with Command Center **pending queue** stat for backlog incidents
