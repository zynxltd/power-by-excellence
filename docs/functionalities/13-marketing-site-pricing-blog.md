# 13 — Marketing Site, Pricing & Blog

## Purpose

The public marketing site introduces PowerByExcellence to prospects. It includes the homepage, pricing page, blog, demo request form, help centre, and SDK asset hosting. These pages require no authentication and demonstrate the product positioning: real-time lead distribution, ping-tree routing, multi-vertical capture, and enterprise reporting.

---

## Where to Find It

| Item | Location |
|------|----------|
| Homepage | `/` |
| Pricing | `/pricing` |
| Blog index | `/blog` |
| Blog article | `/blog/{slug}` |
| Demo request | POST `/demo-request` (form on homepage) |
| Help centre | `/help`, `/help/{slug}` |
| JavaScript SDK (public) | `/sdk/pbe-leads.js` |
| Login | `/login` |
| Access | Public (no auth required) |

---

## Seeded Blog Articles

| Slug | Topic |
|------|-------|
| `ping-tree-routing-explained` | Ping tree architecture |
| `real-time-bidding-for-lead-buyers` | Ping-post bidding |
| `multi-vertical-lead-capture` | Vertical campaigns |

(Exact slugs from `config/blog.php` — verify on `/blog` index.)

---

## How to Test (Step-by-Step)

### 1. Homepage

1. Open incognito window
2. Navigate to `https://powerbyexcellence.test/`

**Expected:**
- Page title: "PowerByExcellence — Real-Time Lead Distribution Platform"
- Hero section with product value proposition
- Feature sections: ping-tree, billing, multi-vertical, reporting
- "Book a demo" or CTA form visible
- Login link in navigation
- No JavaScript console errors
- Responsive layout on mobile width (resize browser)

### 2. SEO meta tags

1. View page source on homepage
2. Check `<title>` and meta description

**Expected:** SEO props rendered via `SeoHead` component. Description mentions ping-tree routing and lead distribution.

### 3. Demo request form

1. On homepage, find demo request form
2. Fill in: name, email, company, message
3. Submit

**Expected:** Success flash or confirmation message. Entry logged (verify `DemoRequestTest` behaviour). No login required.

### 4. Pricing page

1. Navigate to `/pricing`
2. Review plan tiers: Starter, Growth, Enterprise (or as designed)
3. Check CTA buttons link to login or demo

**Expected:**
- Page title: "Pricing — PowerByExcellence Lead Distribution"
- Three plan cards with features listed
- Consistent branding with homepage
- Login link available

### 5. Blog index

1. Navigate to `/blog`
2. Review article cards

**Expected:** At least 3 articles from config. Each shows title, excerpt, date. Links to individual posts.

### 6. Blog article

1. Click first article (e.g. ping-tree routing)
2. Read full content

**Expected:** Markdown body rendered. Article title in page head. Back link to blog index. No admin sidebar. Content uses PowerByExcellence terminology.

### 7. Invalid blog slug

1. Navigate to `/blog/nonexistent-article`

**Expected:** 404 page. Graceful error, not stack trace.

### 8–12. Help, SDK, login, and public form

Browse `/help` articles. Confirm `/sdk/pbe-leads.js` serves JavaScript. Test login from marketing nav. Verify `/dashboard` redirects guests to `/login`. Cross-check `/forms/auto-insurance-quote-uk` (see [06-form-builder.md](./06-form-builder.md)).

---

## Expected Results (Summary)

- All marketing pages load without authentication
- Branding consistent across homepage, pricing, blog
- Demo request form accepts submissions
- Blog articles render from config (no CMS database required)
- Help centre provides in-product documentation
- SDK publicly accessible for embed integrations
- SEO metadata present on key pages

---

## Edge Cases

| Scenario | Expected behaviour |
|----------|-------------------|
| Dark mode on marketing pages | Marketing pages use system/default theme (may not follow user preference) |
| Demo request spam | Throttle or validation on email format |
| Very long blog slug | 404 |
| HTTP vs HTTPS on Herd | Both work; API calls from SDK should match protocol |
| Registration URL | Registration disabled — no `/register` route |
| Pricing page direct access while logged in | Page still accessible; admin nav may overlay |

---

## Related Docs

- [06-form-builder.md](./06-form-builder.md) — public lead capture forms
- [09-api-and-sdk.md](./09-api-and-sdk.md) — SDK usage
- [README.md](./README.md) — demo setup and credentials
