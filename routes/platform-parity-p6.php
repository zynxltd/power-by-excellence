<?php

/**
 * P6 — Custom portal domains (settings.custom_portal_domain + accounts.domain).
 *
 * TenantResolver::resolveFromHost and portalUrl now honour settings.custom_portal_domain
 * via App\PlatformFeatureParity\PortalDomain.
 *
 * Settings UI field already exists on Admin/Settings/Edit (AccountSettingsController).
 * Integration Lead: no route changes required.
 */
