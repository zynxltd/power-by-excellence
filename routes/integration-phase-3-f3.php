<?php

/**
 * Integration Phase 3 — F3 Buyer portal custom branding.
 *
 * No new HTTP routes. Branding is configured on the existing admin buyer form:
 *   GET  buyers/{buyer}/edit   buyers.edit
 *   PUT  buyers/{buyer}        buyers.update
 *
 * Buyer portal pages (existing routes) receive `portalBranding` via BuyerPortalController
 * and shared `buyerPortal.branding` via HandleInertiaRequests → BuyerPortalLocale.
 *
 * Integration Lead: no web.php changes required.
 */
