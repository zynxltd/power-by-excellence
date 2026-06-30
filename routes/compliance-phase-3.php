<?php

/**
 * Compliance Phase 3 route manifests.
 *
 * F1 — Data retention: no new HTTP routes (settings.edit / settings.update).
 * Schedule: $schedule->command('data-retention:purge')->dailyAt('02:30')->withoutOverlapping();
 *
 * F3 — Outbound webhook HMAC signing (register inside admin middleware group):
 *   POST webhooks/generate-signing-secret → webhooks.generate-signing-secret
 *
 * F4 — Admin IP allowlist: no new HTTP routes.
 * Settings keys (account.settings.security):
 *   - admin_ip_allowlist_enabled (bool)
 *   - admin_ip_allowlist (array of IPv4 / CIDR strings)
 *   - admin_geo_block_enabled (bool, reserved)
 *   - blocked_country_codes (array, reserved)
 *
 * Integration Lead — register middleware alias in bootstrap/app.php:
 *   'admin.ip-allowlist' => \App\Http\Middleware\EnsureAdminIpAllowlist::class,
 *
 * Add to the admin middleware group in routes/web.php (after SetAccountFromUser):
 *   'admin.ip-allowlist',
 *
 * Local/dev bypass: config platform.security.admin_ip_allowlist_bypass
 * (defaults true when APP_ENV=local; override with ADMIN_IP_ALLOWLIST_BYPASS).
 *
 * F5 — Hosted form GDPR consent: no new HTTP routes (existing forms.show / forms.submit).
 * Settings keys:
 *   Campaign validation_config + hosted form config.consent:
 *     - require_consent (bool)
 *     - consent_text (string)
 *     - lawful_basis (consent | legitimate_interest | contract)
 *     - channel_consent_channels (array: email, sms, phone)
 * Lead metadata.consent artifact stores accepted snapshot, channel_consent, optin_url, ip, user_agent.
 *
 * F6 — Right-to-erasure (register inside admin middleware group):
 *   registerCompliancePhase3LeadErasureRoutes();
 *   POST leads/{lead}/erasure → leads.erasure
 */

use App\Http\Controllers\Admin\LeadAdminController;
use App\Http\Controllers\Admin\WebhookController;
use Illuminate\Support\Facades\Route;

if (! function_exists('registerCompliancePhase3WebhookSigningRoutes')) {
    function registerCompliancePhase3WebhookSigningRoutes(): void
    {
        if (! Route::has('webhooks.generate-signing-secret')) {
            Route::post('webhooks/generate-signing-secret', [WebhookController::class, 'generateSigningSecret'])
                ->name('webhooks.generate-signing-secret');
        }
    }
}

if (! function_exists('registerCompliancePhase3LeadErasureRoutes')) {
    function registerCompliancePhase3LeadErasureRoutes(): void
    {
        if (! Route::has('leads.erasure')) {
            Route::post('leads/{lead}/erasure', [LeadAdminController::class, 'requestErasure'])
                ->name('leads.erasure');
        }
    }
}
