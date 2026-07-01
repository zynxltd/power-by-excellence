<?php

/**
 * Compliance Phase 3 route manifests.
 *
 * F1 — Data retention: no new HTTP routes (settings.edit / settings.update).
 * Schedule: $schedule->command('data-retention:purge')->dailyAt('02:30')->withoutOverlapping();
 *
 * F2 — Audit log CSV export:
 *   registerCompliancePhase3LogExportRoutes();
 *
 * F3 — Outbound webhook HMAC signing:
 *   registerCompliancePhase3WebhookSigningRoutes();
 *
 * F4 — Admin IP allowlist: no new HTTP routes (middleware in bootstrap/app.php).
 *
 * F5 — Hosted form GDPR consent: no new HTTP routes (forms.show / forms.submit).
 *
 * F6 — Right-to-erasure:
 *   registerCompliancePhase3LeadErasureRoutes();
 *   POST leads/{lead}/erasure → leads.erasure
 *
 * F7 — Stripe buyer invoice PDF email (register inside portal.buyer middleware group):
 *   registerCompliancePhase3BuyerInvoiceRoutes();
 *   POST invoices/{invoice}/resend → portal.buyer.invoices.resend
 */

use App\Http\Controllers\Admin\AccessLogController;
use App\Http\Controllers\Admin\ChangeLogController;
use App\Http\Controllers\Admin\LeadAdminController;
use App\Http\Controllers\Admin\SecurityLogController;
use App\Http\Controllers\Admin\WebhookController;
use App\Http\Controllers\Portal\BuyerPortalController;
use Illuminate\Support\Facades\Route;

if (! function_exists('registerCompliancePhase3LogExportRoutes')) {
    function registerCompliancePhase3LogExportRoutes(): void
    {
        if (! Route::has('logs.access.export')) {
            Route::get('logs/access/export', [AccessLogController::class, 'export'])->name('logs.access.export');
        }

        if (! Route::has('logs.changes.export')) {
            Route::get('logs/changes/export', [ChangeLogController::class, 'export'])->name('logs.changes.export');
        }

        if (! Route::has('logs.security.export')) {
            Route::get('logs.security/export', [SecurityLogController::class, 'export'])->name('logs.security.export');
        }
    }
}

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

/**
 * F7 — Stripe buyer invoice PDF email (register inside portal.buyer middleware group):
 *   registerCompliancePhase3BuyerInvoiceRoutes();
 *   POST invoices/{invoice}/resend → portal.buyer.invoices.resend
 *
 * Webhook: invoice.finalized → BuyerInvoiceService::syncFromStripeInvoice (queues email).
 * invoice.paid syncs invoice record then applies subscription credit logic.
 */

if (! function_exists('registerCompliancePhase3BuyerInvoiceRoutes')) {
    function registerCompliancePhase3BuyerInvoiceRoutes(): void
    {
        if (Route::has('portal.buyer.invoices.resend')) {
            return;
        }

        Route::post('invoices/{invoice}/resend', [BuyerPortalController::class, 'resendInvoice'])
            ->name('invoices.resend');
    }
}
