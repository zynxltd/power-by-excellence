<?php

use App\Http\Controllers\EspWebhookController;
use App\Http\Controllers\MessageTrackingController;
use App\Http\Controllers\Admin\AccountController;
use App\Http\Controllers\Admin\TenantBillingController;
use App\Http\Controllers\Admin\ApiDocsController;
use App\Http\Controllers\Admin\ApiKeyController;
use App\Http\Controllers\Admin\ApiRequestLogController;
use App\Http\Controllers\Admin\AccessLogController;
use App\Http\Controllers\Admin\AccountSettingsController;
use App\Http\Controllers\Admin\ChangeLogController;
use App\Http\Controllers\Admin\BillingController;
use App\Http\Controllers\Admin\FinanceController;
use App\Http\Controllers\Admin\BrandingController;
use App\Http\Controllers\Admin\BuyerController;
use App\Http\Controllers\Admin\BuyerScheduleController;
use App\Http\Controllers\Admin\CallRecordingController;
use App\Http\Controllers\Admin\CampaignController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\DeliveryController;
use App\Http\Controllers\Admin\DistributionController;
use App\Http\Controllers\Admin\DeliveryLogController;
use App\Http\Controllers\Admin\BillingLockController;
use App\Http\Controllers\Admin\AutoResponderController;
use App\Http\Controllers\Admin\FormBuilderController;
use App\Http\Controllers\Admin\GodModeHandoffController;
use App\Http\Controllers\Admin\FeaturesController;
use App\Http\Controllers\Admin\ReportsController;
use App\Http\Controllers\Admin\RoutingSimulatorController;
use App\Http\Controllers\Admin\ImpersonationController;
use App\Http\Controllers\Admin\ImportController;
use App\Http\Controllers\Admin\IntegrationController;
use App\Http\Controllers\Admin\LeadAdminController;
use App\Http\Controllers\Admin\LiveFeedController;
use App\Http\Controllers\Admin\NotificationInboxController;
use App\Http\Controllers\Admin\OperationsController;
use App\Http\Controllers\Admin\PlatformNotificationAdminController;
use App\Http\Controllers\Admin\AutomationController;
use App\Http\Controllers\Admin\CommandCenterController;
use App\Http\Controllers\Admin\PlatformEventsController;
use App\Http\Controllers\Admin\SecurityLogController;
use App\Http\Controllers\Admin\SupportTicketController;
use App\Http\Controllers\Admin\QuarantineAdminController;
use App\Http\Controllers\Admin\SupplierController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\Admin\LeadSourceIntegrationController;
use App\Http\Controllers\Admin\ValidationIntegrationController;
use App\Http\Controllers\Admin\PostbackController;
use App\Http\Controllers\Admin\WebhookController;
use App\Http\Controllers\Admin\ScheduledExportController;
use App\Http\Controllers\Admin\LogsHubController;
use App\Http\Controllers\Admin\SavedReportController;
use App\Http\Controllers\Admin\VerticalFieldTemplateController;
use App\Http\Controllers\Admin\VerifyBatchController;
use App\Http\Controllers\Admin\MarketingOptOutController;
use App\Http\Controllers\Admin\TenantDataExportController;
use App\Http\Controllers\ClickTrack\SupplierClickPortalController;
use App\Http\Controllers\HelpController;
use App\Http\Controllers\SystemStatusController;
use App\Http\Controllers\UserSupportTicketController;
use App\Http\Controllers\PlatformEntryController;
use App\Http\Controllers\Portal\BuyerCallPortalController;
use App\Http\Controllers\Portal\BuyerPortalController;
use App\Http\Controllers\Portal\BuyerStripeCheckoutController;
use App\Http\Controllers\Portal\PortalBillingLockController;
use App\Http\Controllers\Portal\SupplierPortalController;
use App\Http\Controllers\PublicFormController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\StripeWebhookController;
use App\Http\Controllers\TwoFactorController;
use App\Http\Middleware\EnsurePortalRole;
use App\Http\Middleware\EnsureTenantAccess;
use App\Http\Middleware\RestrictMarketingToCentralHost;
use App\Http\Middleware\SetAccountFromUser;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', function () {
    if (! \App\Support\Tenancy\TenantResolver::isCentralHost()) {
        if (! auth()->check()) {
            return redirect()->route('login');
        }

        $user = auth()->user();

        if ($user->isBuyerPortal()) {
            return redirect()->route('portal.buyer.dashboard');
        }

        if ($user->isSupplierPortal()) {
            return redirect()->route('portal.supplier.dashboard');
        }

        return redirect()->route('dashboard');
    }

    return Inertia::render('Welcome', [
        'canLogin' => Route::has('login'),
        'seo' => [
            'title' => 'PowerByExcellence - Real-Time Lead Distribution Platform',
            'description' => 'Ping-tree routing, real-time buyer auctions, multi-vertical lead capture, and enterprise reporting for agencies and lead sellers.',
        ],
    ]);
})->name('home');

Route::middleware('auth')->get('/platform', PlatformEntryController::class)->name('platform.entry');

Route::middleware('marketing.central')->group(function () {
    Route::get('/pricing', function () {
        return Inertia::render('Marketing/Pricing', [
            'canLogin' => Route::has('login'),
            'seo' => [
                'title' => 'Pricing - PowerByExcellence Lead Distribution',
                'description' => 'Starter, Growth, and Enterprise plans for lead distribution with ping-tree routing, real-time bidding, and fraud protection on Growth.',
            ],
        ]);
    })->name('pricing');

    Route::get('/blog', [\App\Http\Controllers\BlogController::class, 'index'])->name('blog.index');
    Route::get('/blog/{slug}', [\App\Http\Controllers\BlogController::class, 'show'])->name('blog.show');

    Route::get('/help', [HelpController::class, 'index'])->name('help.index');
    Route::get('/help/{slug}', [HelpController::class, 'show'])->name('help.show');

    Route::get('/status', [SystemStatusController::class, 'index'])->name('status.index');
    Route::get('/status.json', [SystemStatusController::class, 'json'])->name('status.json');

    Route::post('/demo-request', [DemoRequestController::class, 'store'])->name('demo.request');
});

Route::get('/sdk/pbe-leads.js', function () {
    return response()->file(base_path('sdk/javascript/pbe-leads.js'), [
        'Content-Type' => 'application/javascript',
        'Cache-Control' => 'public, max-age=3600',
    ]);
})->name('sdk.javascript');

Route::middleware('hosted-form.embed')->group(function () {
    Route::get('/forms/{slug}', [PublicFormController::class, 'show'])->name('forms.show');
    Route::post('/forms/{slug}', [PublicFormController::class, 'submit'])->name('forms.submit');
    Route::get('/forms/{slug}/status/{uuid}', [PublicFormController::class, 'status'])->name('forms.status');
});
Route::get('/r/{lead:uuid}', \App\Http\Controllers\LeadRedirectController::class)->name('lead.redirect');
Route::get('/c/{token}', \App\Http\Controllers\ClickRedirectController::class)->name('click.redirect');
Route::get('/i/{token}', \App\Http\Controllers\ImpressionPixelController::class)->name('click.impression');
Route::get('/god-mode/handoff/{token}', GodModeHandoffController::class)->name('god-mode.handoff');
Route::get('/messaging/open/{token}', [MessageTrackingController::class, 'open'])->name('messaging.track.open');
Route::get('/messaging/click/{token}', [MessageTrackingController::class, 'click'])->name('messaging.track.click');
Route::get('/s/{slug}', [MessageTrackingController::class, 'shortlinkRedirect'])->name('messaging.shortlink.redirect');
Route::get('/messaging/unsubscribe/{token}', [MessageTrackingController::class, 'unsubscribe'])->name('messaging.unsubscribe');
Route::post('/messaging/unsubscribe/{token}', [MessageTrackingController::class, 'confirmUnsubscribe'])->name('messaging.unsubscribe.confirm');
Route::post('/webhooks/esp/sendgrid', [EspWebhookController::class, 'sendgrid'])->name('webhooks.esp.sendgrid');
Route::post('/webhooks/esp/mailgun', [EspWebhookController::class, 'mailgun'])->name('webhooks.esp.mailgun');
Route::post('/webhooks/esp/postmark', [EspWebhookController::class, 'postmark'])->name('webhooks.esp.postmark');
Route::post('/stripe/webhook', StripeWebhookController::class)->name('stripe.webhook');

Route::prefix('webhooks/twilio/voice/{accountSlug}')->group(function () {
    Route::post('/', [\App\Http\Controllers\Webhooks\TwilioVoiceWebhookController::class, 'inbound']);
    Route::post('/gather', [\App\Http\Controllers\Webhooks\TwilioVoiceWebhookController::class, 'gather']);
    Route::post('/status', [\App\Http\Controllers\Webhooks\TwilioVoiceWebhookController::class, 'status']);
    Route::post('/recording', [\App\Http\Controllers\Webhooks\TwilioVoiceWebhookController::class, 'recording']);
});

Route::get('/sdk/pbe-calls.js', function () {
    return response()->file(base_path('sdk/javascript/pbe-calls.js'), [
        'Content-Type' => 'application/javascript',
        'Cache-Control' => 'public, max-age=3600',
    ]);
})->name('sdk.calls');

Route::middleware(['auth', 'verified', 'signup.complete', 'two-factor.verified', SetAccountFromUser::class, 'admin.ip-allowlist', EnsureTenantAccess::class, 'billing.active', EnsurePortalRole::class.':admin', 'module.access'])->group(function () {
    Route::get('/dashboard', DashboardController::class)->name('dashboard');
    Route::get('/live-stats', \App\Http\Controllers\Admin\LiveStatsController::class)->name('live-stats');
    Route::get('/command-center', [CommandCenterController::class, 'index'])->middleware(['superadmin', 'central.host'])->name('command-center.index');
    Route::get('/platform-events', [PlatformEventsController::class, 'index'])->middleware(['superadmin', 'central.host'])->name('platform-events.index');
    Route::get('/live-feed', [LiveFeedController::class, 'index'])->middleware(['superadmin', 'central.host'])->name('live-feed.index');
    Route::get('/operations', [OperationsController::class, 'index'])->name('operations.index');
    Route::get('/logs/hub', [LogsHubController::class, 'index'])->name('logs.hub');
    Route::get('/logs/access', [AccessLogController::class, 'index'])->name('logs.access');
    Route::get('/logs/delivery', [DeliveryLogController::class, 'index'])->name('logs.delivery');
    Route::get('/logs/delivery/{deliveryLog}', [DeliveryLogController::class, 'show'])->name('logs.delivery.show');
    Route::get('/logs/api', [ApiRequestLogController::class, 'index'])->name('logs.api');
    Route::get('/logs/changes', [ChangeLogController::class, 'index'])->name('logs.changes');
    Route::get('/logs/security', [SecurityLogController::class, 'index'])->name('logs.security');
    Route::get('/logs/access/export', [AccessLogController::class, 'export'])->name('logs.access.export');
    Route::get('/logs/changes/export', [ChangeLogController::class, 'export'])->name('logs.changes.export');
    Route::get('/logs/security/export', [SecurityLogController::class, 'export'])->name('logs.security.export');

    Route::get('support/manage', [SupportTicketController::class, 'index'])->middleware(['superadmin', 'central.host'])->name('support.admin.index');
    Route::get('support/manage/{ticket}', [SupportTicketController::class, 'show'])->middleware(['superadmin', 'central.host'])->name('support.admin.show');
    Route::post('support/manage/{ticket}/reply', [SupportTicketController::class, 'reply'])->middleware(['superadmin', 'central.host'])->name('support.admin.reply');
    Route::patch('support/manage/{ticket}/status', [SupportTicketController::class, 'updateStatus'])->middleware(['superadmin', 'central.host'])->name('support.admin.status');

    Route::get('automation', [AutomationController::class, 'index'])->name('automation.index');
    Route::post('automation/sequences', [AutomationController::class, 'storeSequence'])->name('automation.sequences.store');
    Route::patch('automation/sequences/{sequence}', [AutomationController::class, 'updateSequence'])->name('automation.sequences.update');
    Route::post('automation/bulk-sms', [AutomationController::class, 'storeBulkSms'])->name('automation.bulk-sms.store');
    Route::post('automation/bulk-sms/{bulkSms}/send', [AutomationController::class, 'sendBulkSms'])->name('automation.bulk-sms.send');
    Route::post('automation/alerts', [AutomationController::class, 'storeAlert'])->name('automation.alerts.store');
    Route::delete('automation/sequences/{sequence}', [AutomationController::class, 'destroySequence'])->name('automation.sequences.destroy');
    Route::delete('automation/alerts/{alert}', [AutomationController::class, 'destroyAlert'])->name('automation.alerts.destroy');

    Route::get('e-delivery', [\App\Http\Controllers\Admin\EDeliveryController::class, 'index'])->name('e-delivery.index');
    Route::post('e-delivery/segments', [\App\Http\Controllers\Admin\EDeliveryController::class, 'storeSegment'])->name('e-delivery.segments.store');
    Route::delete('e-delivery/segments/{segment}', [\App\Http\Controllers\Admin\EDeliveryController::class, 'destroySegment'])->name('e-delivery.segments.destroy');
    Route::post('e-delivery/templates', [\App\Http\Controllers\Admin\EDeliveryController::class, 'storeTemplate'])->name('e-delivery.templates.store');
    Route::post('e-delivery/templates/preview', [\App\Http\Controllers\Admin\EDeliveryController::class, 'previewTemplate'])->name('e-delivery.templates.preview');
    Route::put('e-delivery/templates/{template}', [\App\Http\Controllers\Admin\EDeliveryController::class, 'updateTemplate'])->name('e-delivery.templates.update');
    Route::delete('e-delivery/templates/{template}', [\App\Http\Controllers\Admin\EDeliveryController::class, 'destroyTemplate'])->name('e-delivery.templates.destroy');
    Route::get('e-delivery/template-library', [\App\Http\Controllers\Admin\EDeliveryController::class, 'templateLibraryIndex'])->name('e-delivery.template-library.index');
    Route::post('e-delivery/templates/from-library', [\App\Http\Controllers\Admin\EDeliveryController::class, 'importTemplateFromLibrary'])->name('e-delivery.templates.from-library');
    Route::post('e-delivery/journeys/process', [AutomationController::class, 'processJourneys'])->name('e-delivery.journeys.process');
    Route::post('e-delivery/throttle/pause', [\App\Http\Controllers\Admin\EDeliveryController::class, 'pauseSending'])->name('e-delivery.throttle.pause');
    Route::post('e-delivery/throttle/resume', [\App\Http\Controllers\Admin\EDeliveryController::class, 'resumeSending'])->name('e-delivery.throttle.resume');
    Route::post('e-delivery/bulk-campaigns', [\App\Http\Controllers\Admin\EDeliveryController::class, 'storeBulkCampaign'])->name('e-delivery.bulk-campaigns.store');
    Route::post('e-delivery/bulk-campaigns/{bulkSms}/send', [\App\Http\Controllers\Admin\EDeliveryController::class, 'sendBulkCampaign'])->name('e-delivery.bulk-campaigns.send');
    Route::post('e-delivery/sending-profiles', [\App\Http\Controllers\Admin\EDeliveryController::class, 'storeSendingProfile'])->name('e-delivery.sending-profiles.store');
    Route::patch('e-delivery/sending-profiles/{profile}', [\App\Http\Controllers\Admin\EDeliveryController::class, 'updateSendingProfile'])->name('e-delivery.sending-profiles.update');
    Route::patch('e-delivery/sending-profiles/{profile}/warmup', [\App\Http\Controllers\Admin\EDeliveryController::class, 'updateSendingProfileWarmup'])->name('e-delivery.sending-profiles.warmup');
    Route::patch('e-delivery/send-time-settings', [\App\Http\Controllers\Admin\EDeliveryController::class, 'updateSendTimeSettings'])->name('e-delivery.send-time-settings.update');
    Route::patch('e-delivery/shortlink-settings', [\App\Http\Controllers\Admin\EDeliveryController::class, 'updateShortlinkSettings'])->name('e-delivery.shortlink-settings.update');
    Route::patch('e-delivery/hygiene-settings', [\App\Http\Controllers\Admin\EDeliveryController::class, 'updateHygieneSettings'])->name('e-delivery.hygiene-settings.update');
    Route::post('e-delivery/hygiene/run', [\App\Http\Controllers\Admin\EDeliveryController::class, 'runHygiene'])->name('e-delivery.hygiene.run');
    Route::delete('e-delivery/sending-profiles/{profile}', [\App\Http\Controllers\Admin\EDeliveryController::class, 'destroySendingProfile'])->name('e-delivery.sending-profiles.destroy');
    Route::post('leads/{lead}/tags', [\App\Http\Controllers\Admin\EDeliveryController::class, 'tagLead'])->name('leads.tags.store');
    Route::delete('leads/{lead}/tags', [\App\Http\Controllers\Admin\EDeliveryController::class, 'untagLead'])->name('leads.tags.destroy');

    Route::get('billing', [BillingController::class, 'index'])->name('billing.index');
    Route::get('finance', [FinanceController::class, 'index'])->name('finance.index');
    Route::get('billing/lock', BillingLockController::class)->name('billing.lock')->withoutMiddleware('billing.active');
    Route::post('billing/unlock', [BillingController::class, 'unlockAccount'])->name('billing.unlock');
    Route::get('billing/{buyer}', [BillingController::class, 'show'])->name('billing.show')->whereNumber('buyer');
    Route::get('billing/{buyer}/export', [BillingController::class, 'export'])->name('billing.export')->whereNumber('buyer');
    Route::get('billing-export', [BillingController::class, 'exportAll'])->name('billing.export-all');
    Route::post('billing/{buyer}/top-up', [BillingController::class, 'topUp'])->name('billing.top-up');

    Route::post('distribution/{distribution}/lock', [DistributionController::class, 'toggleLock'])->name('distribution.lock');
    Route::resource('distribution', DistributionController::class);

    Route::get('routing/simulator', [RoutingSimulatorController::class, 'index'])->name('routing.simulator');
    Route::post('routing/simulator', [RoutingSimulatorController::class, 'run'])->name('routing.simulator.run');

    Route::get('features', [FeaturesController::class, 'index'])->name('features.index');
    Route::get('features/capture', [FeaturesController::class, 'capture'])->name('features.capture');
    Route::get('features/validation', [FeaturesController::class, 'validation'])->name('features.validation');
    Route::get('features/routing', [FeaturesController::class, 'routing'])->name('features.routing');
    Route::get('features/delivery', [FeaturesController::class, 'delivery'])->name('features.delivery');
    Route::get('features/auto-responders', [AutoResponderController::class, 'index'])->name('features.auto-responders');
    Route::post('features/auto-responders/test', [AutoResponderController::class, 'test'])->name('features.auto-responders.test');
    Route::post('features/auto-responders', [AutoResponderController::class, 'store'])->name('features.auto-responders.store');
    Route::patch('features/auto-responders/{autoResponder}', [AutoResponderController::class, 'update'])->name('features.auto-responders.update');
    Route::delete('features/auto-responders/{autoResponder}', [AutoResponderController::class, 'destroy'])->name('features.auto-responders.destroy');

    Route::prefix('click-track')->name('click-track.')->group(function () {
        Route::get('/', \App\Http\Controllers\Admin\ClickTrack\DashboardController::class)->name('dashboard');
        Route::get('links', [\App\Http\Controllers\Admin\ClickTrack\LinkController::class, 'index'])->name('links.index');
        Route::post('links', [\App\Http\Controllers\Admin\ClickTrack\LinkController::class, 'store'])->name('links.store');
        Route::patch('links/{trackingLink}', [\App\Http\Controllers\Admin\ClickTrack\LinkController::class, 'update'])->name('links.update');
        Route::delete('links/{trackingLink}', [\App\Http\Controllers\Admin\ClickTrack\LinkController::class, 'destroy'])->name('links.destroy');
        Route::get('clicks', [\App\Http\Controllers\Admin\ClickTrack\ClickController::class, 'index'])->name('clicks.index');
        Route::get('clicks/export', [\App\Http\Controllers\Admin\ClickTrack\ClickController::class, 'export'])->name('clicks.export');
        Route::get('conversions', [\App\Http\Controllers\Admin\ClickTrack\ConversionController::class, 'index'])->name('conversions.index');
        Route::post('conversions/bulk-approve', [\App\Http\Controllers\Admin\ClickTrack\ConversionController::class, 'bulkApprove'])->name('conversions.bulk-approve');
        Route::post('conversions/{trackingConversion}/approve', [\App\Http\Controllers\Admin\ClickTrack\ConversionController::class, 'approve'])->name('conversions.approve');
        Route::post('conversions/{trackingConversion}/reject', [\App\Http\Controllers\Admin\ClickTrack\ConversionController::class, 'reject'])->name('conversions.reject');
        Route::get('conversions/export', [\App\Http\Controllers\Admin\ClickTrack\ConversionController::class, 'export'])->name('conversions.export');
        Route::get('reports', [\App\Http\Controllers\Admin\ClickTrack\ReportController::class, 'index'])->name('reports.index');
        Route::get('settings', [\App\Http\Controllers\Admin\ClickTrack\SettingsController::class, 'edit'])->name('settings.edit');
        Route::patch('settings', [\App\Http\Controllers\Admin\ClickTrack\SettingsController::class, 'update'])->name('settings.update');
    });

    Route::get('reports', [ReportsController::class, 'index'])->name('reports.index');

    Route::get('call-logic/settings', [\App\Http\Controllers\Admin\CallLogicSettingsController::class, 'edit'])->name('call-logic.settings.edit');
    Route::put('call-logic/settings', [\App\Http\Controllers\Admin\CallLogicSettingsController::class, 'update'])->name('call-logic.settings.update');

    Route::prefix('call-logic')->name('call-logic.')->middleware('product.enabled:call_logic')->group(function () {
        Route::get('calls', [\App\Http\Controllers\Admin\CallSessionController::class, 'index'])->name('calls.index');
        Route::get('calls/{call}', [\App\Http\Controllers\Admin\CallSessionController::class, 'show'])->name('calls.show');
        Route::post('calls/{call}/returns/{return}/approve', [\App\Http\Controllers\Admin\CallSessionController::class, 'approveReturn'])->name('calls.returns.approve');
        Route::post('calls/{call}/returns/{return}/reject', [\App\Http\Controllers\Admin\CallSessionController::class, 'rejectReturn'])->name('calls.returns.reject');
        Route::get('recordings/{recording}/play', [CallRecordingController::class, 'play'])->name('recordings.play');
        Route::get('tracking-numbers', [\App\Http\Controllers\Admin\TrackingNumberController::class, 'index'])->name('tracking-numbers.index');
        Route::post('tracking-numbers/search', [\App\Http\Controllers\Admin\TrackingNumberController::class, 'search'])->name('tracking-numbers.search');
        Route::post('tracking-numbers/purchase', [\App\Http\Controllers\Admin\TrackingNumberController::class, 'purchase'])->name('tracking-numbers.purchase');
        Route::post('tracking-numbers', [\App\Http\Controllers\Admin\TrackingNumberController::class, 'store'])->name('tracking-numbers.store');
        Route::delete('tracking-numbers/{trackingNumber}', [\App\Http\Controllers\Admin\TrackingNumberController::class, 'destroy'])->name('tracking-numbers.destroy');
        Route::get('ivr', [\App\Http\Controllers\Admin\IvrFlowController::class, 'index'])->name('ivr.index');
        Route::get('ivr/create', [\App\Http\Controllers\Admin\IvrFlowController::class, 'create'])->name('ivr.create');
        Route::post('ivr', [\App\Http\Controllers\Admin\IvrFlowController::class, 'store'])->name('ivr.store');
        Route::get('ivr/{ivrFlow}/edit', [\App\Http\Controllers\Admin\IvrFlowController::class, 'edit'])->name('ivr.edit');
        Route::put('ivr/{ivrFlow}', [\App\Http\Controllers\Admin\IvrFlowController::class, 'update'])->name('ivr.update');
        Route::delete('ivr/{ivrFlow}', [\App\Http\Controllers\Admin\IvrFlowController::class, 'destroy'])->name('ivr.destroy');
        Route::get('reports', [\App\Http\Controllers\Admin\CallLogicReportController::class, 'index'])->name('reports.index');
    });

    Route::get('scheduled-exports', [ScheduledExportController::class, 'index'])->name('scheduled-exports.index');
    Route::post('scheduled-exports', [ScheduledExportController::class, 'store'])->name('scheduled-exports.store');
    Route::put('scheduled-exports/{scheduledExport}', [ScheduledExportController::class, 'update'])->name('scheduled-exports.update');
    Route::delete('scheduled-exports/{scheduledExport}', [ScheduledExportController::class, 'destroy'])->name('scheduled-exports.destroy');
    Route::post('scheduled-exports/{scheduledExport}/run', [ScheduledExportController::class, 'runNow'])->name('scheduled-exports.run');

    Route::get('saved-reports', [SavedReportController::class, 'index'])->name('saved-reports.index');
    Route::post('saved-reports', [SavedReportController::class, 'store'])->name('saved-reports.store');
    Route::put('saved-reports/{savedReport}', [SavedReportController::class, 'update'])->name('saved-reports.update');
    Route::delete('saved-reports/{savedReport}', [SavedReportController::class, 'destroy'])->name('saved-reports.destroy');
    Route::post('saved-reports/{savedReport}/run', [SavedReportController::class, 'run'])->name('saved-reports.run');
    Route::get('saved-reports/{savedReport}/export', [SavedReportController::class, 'export'])->name('saved-reports.export');

    Route::get('vertical-field-templates', [VerticalFieldTemplateController::class, 'index'])->name('vertical-field-templates.index');
    Route::post('vertical-field-templates', [VerticalFieldTemplateController::class, 'store'])->name('vertical-field-templates.store');
    Route::post('vertical-field-templates/{verticalFieldTemplate}/apply', [VerticalFieldTemplateController::class, 'apply'])->name('vertical-field-templates.apply');
    require __DIR__.'/leadbyte-phase-3.php';

    Route::get('verify-batches', [VerifyBatchController::class, 'index'])->name('verify-batches.index');
    Route::post('verify-batches', [VerifyBatchController::class, 'store'])->name('verify-batches.store');
    Route::get('verify-batches/{verifyBatch}', [VerifyBatchController::class, 'show'])->name('verify-batches.show');
    Route::post('verify-batches/{verifyBatch}/process', [VerifyBatchController::class, 'process'])->name('verify-batches.process');

    Route::get('buyer-schedule', [BuyerScheduleController::class, 'index'])->name('buyer-schedule.index');
    Route::post('buyers/{buyer}/pause', [BuyerScheduleController::class, 'pause'])->name('buyers.pause');
    Route::post('buyers/{buyer}/resume', [BuyerScheduleController::class, 'resume'])->name('buyers.resume');
    Route::post('buyers/{buyer}/override-caps', [BuyerScheduleController::class, 'overrideCaps'])->name('buyers.override-caps');

    Route::get('integrations/stripe', [StripeIntegrationController::class, 'edit'])->name('integrations.stripe');
    Route::put('integrations/stripe', [StripeIntegrationController::class, 'update'])->name('integrations.stripe.update');

    Route::resource('campaigns', CampaignController::class)->except(['show']);
    Route::get('campaigns/{campaign}', [CampaignController::class, 'show'])->name('campaigns.show');
    Route::get('campaigns/{campaign}/api-spec', [CampaignApiSpecController::class, 'edit'])->name('campaigns.api-spec');
    Route::put('campaigns/{campaign}/api-spec', [CampaignApiSpecController::class, 'update'])->name('campaigns.api-spec.update');
    Route::post('campaigns/{campaign}/api-spec/lock', [CampaignApiSpecController::class, 'toggleLock'])->name('campaigns.api-spec.lock');
    Route::post('campaigns/{campaign}/api-spec/apply-form', [CampaignApiSpecController::class, 'applyToForm'])->name('campaigns.api-spec.apply-form');
    Route::post('campaigns/{campaign}/api-spec/load-template', [CampaignApiSpecController::class, 'loadVerticalTemplate'])->name('campaigns.api-spec.load-template');
    Route::post('campaigns/{campaign}/api-spec/load-premade', [CampaignApiSpecController::class, 'loadPremadeTemplate'])->name('campaigns.api-spec.load-premade');
    Route::patch('campaigns/{campaign}/validation', [CampaignController::class, 'updateValidation'])->name('campaigns.validation');

    Route::resource('deliveries', DeliveryController::class);
    Route::post('deliveries/{delivery}/test', [DeliveryController::class, 'test'])->name('deliveries.test');
    Route::post('deliveries/{delivery}/clone', [DeliveryController::class, 'clone'])->name('deliveries.clone');

    Route::resource('buyers', BuyerController::class);
    Route::resource('suppliers', SupplierController::class);

    Route::get('forms', [FormBuilderController::class, 'index'])->name('forms.index');
    Route::post('forms', [FormBuilderController::class, 'store'])->name('forms.store');
    Route::get('forms/{hostedForm}/edit', [FormBuilderController::class, 'edit'])->name('forms.edit');
    Route::put('forms/{hostedForm}', [FormBuilderController::class, 'update'])->name('forms.update');
    Route::delete('forms/{hostedForm}', [FormBuilderController::class, 'destroy'])->name('forms.destroy');
    Route::post('forms/{hostedForm}/approve', [FormBuilderController::class, 'approve'])->name('forms.approve');
    Route::post('forms/{hostedForm}/reject', [FormBuilderController::class, 'reject'])->name('forms.reject');

    Route::get('integrations', [IntegrationController::class, 'index'])->name('integrations.index');
    Route::get('integrations/validation', [ValidationIntegrationController::class, 'edit'])->name('integrations.validation');
    Route::put('integrations/validation', [ValidationIntegrationController::class, 'update'])->name('integrations.validation.update');
    Route::post('integrations/validation/test', [ValidationIntegrationController::class, 'test'])->name('integrations.validation.test');
    Route::get('integrations/messaging', [\App\Http\Controllers\Admin\MessagingIntegrationController::class, 'edit'])->name('integrations.messaging');
    Route::put('integrations/messaging', [\App\Http\Controllers\Admin\MessagingIntegrationController::class, 'update'])->name('integrations.messaging.update');
    Route::get('integrations/lead-sources/{provider}', [LeadSourceIntegrationController::class, 'edit'])->name('integrations.lead-source');
    Route::put('integrations/lead-sources/{provider}', [LeadSourceIntegrationController::class, 'update'])->name('integrations.lead-source.update');

    Route::get('quarantine', [QuarantineAdminController::class, 'index'])->name('quarantine.index');
    Route::get('buyer-feedback', [\App\Http\Controllers\Admin\BuyerFeedbackController::class, 'index'])->name('buyer-feedback.index');
    Route::post('quarantine/{lead}/release', [QuarantineAdminController::class, 'release'])->name('quarantine.release');
    Route::post('quarantine/{lead}/reject', [QuarantineAdminController::class, 'reject'])->name('quarantine.reject');
    Route::post('quarantine/bulk-release', [QuarantineAdminController::class, 'bulkRelease'])->name('quarantine.bulk-release');
    Route::post('quarantine/bulk-reject', [QuarantineAdminController::class, 'bulkReject'])->name('quarantine.bulk-reject');
    Route::post('quarantine/{lead}/extend', [QuarantineAdminController::class, 'extend'])->name('quarantine.extend');

    Route::get('leads', [LeadAdminController::class, 'index'])->name('leads.index');
    Route::get('leads/export', [LeadAdminController::class, 'export'])->name('leads.export');
    Route::get('leads/{lead}', [LeadAdminController::class, 'show'])->name('leads.show');
    Route::post('leads/{lead}/quarantine/release', [LeadAdminController::class, 'releaseQuarantine'])->name('leads.quarantine.release');
    Route::post('leads/{lead}/quarantine/reject', [LeadAdminController::class, 'rejectQuarantine'])->name('leads.quarantine.reject');
    Route::post('leads/{lead}/repost', [LeadAdminController::class, 'repost'])->name('leads.repost');
    require __DIR__.'/compliance-phase-3.php';
    registerCompliancePhase3LeadErasureRoutes();

    Route::get('imports', [ImportController::class, 'index'])->name('imports.index');
    Route::post('imports', [ImportController::class, 'store'])->name('imports.store');

    Route::get('webhooks', [WebhookController::class, 'index'])->name('webhooks.index');
    Route::post('webhooks', [WebhookController::class, 'store'])->name('webhooks.store');
    Route::put('webhooks/{webhook}', [WebhookController::class, 'update'])->name('webhooks.update');
    Route::post('webhooks/{webhook}/approve', [WebhookController::class, 'approve'])->name('webhooks.approve');
    Route::post('webhooks/{webhook}/reject', [WebhookController::class, 'reject'])->name('webhooks.reject');
    Route::post('webhooks/{webhook}/approve-deletion', [WebhookController::class, 'approveDeletion'])->name('webhooks.approve-deletion');
    Route::post('webhooks/{webhook}/reject-deletion', [WebhookController::class, 'rejectDeletion'])->name('webhooks.reject-deletion');
    Route::delete('webhooks/{webhook}', [WebhookController::class, 'destroy'])->name('webhooks.destroy');
    Route::post('webhooks/generate-signing-secret', [WebhookController::class, 'generateSigningSecret'])->name('webhooks.generate-signing-secret');

    Route::get('postbacks', [PostbackController::class, 'index'])->name('postbacks.index');
    Route::post('postbacks', [PostbackController::class, 'store'])->name('postbacks.store');
    Route::put('postbacks/{postback}', [PostbackController::class, 'update'])->name('postbacks.update');
    Route::post('postbacks/{postback}/approve', [PostbackController::class, 'approve'])->name('postbacks.approve');
    Route::post('postbacks/{postback}/reject', [PostbackController::class, 'reject'])->name('postbacks.reject');
    Route::post('postbacks/{postback}/approve-deletion', [PostbackController::class, 'approveDeletion'])->name('postbacks.approve-deletion');
    Route::post('postbacks/{postback}/reject-deletion', [PostbackController::class, 'rejectDeletion'])->name('postbacks.reject-deletion');
    Route::delete('postbacks/{postback}', [PostbackController::class, 'destroy'])->name('postbacks.destroy');

    Route::get('api-docs', [ApiDocsController::class, 'index'])->name('api-docs.index');
    Route::get('api-keys', [ApiKeyController::class, 'index'])->name('api-keys.index');
    Route::post('api-keys', [ApiKeyController::class, 'store'])->name('api-keys.store');
    Route::delete('api-keys/{apiKey}', [ApiKeyController::class, 'destroy'])->name('api-keys.destroy');

    Route::get('users', [UserController::class, 'index'])->name('users.index');
    Route::get('users/{user}/edit', [UserController::class, 'edit'])->name('users.edit');
    Route::post('users', [UserController::class, 'store'])->name('users.store');
    Route::put('users/{user}', [UserController::class, 'update'])->name('users.update');
    Route::post('users/{user}/suspend', [UserController::class, 'suspend'])->name('users.suspend');
    Route::post('users/{user}/activate', [UserController::class, 'activate'])->name('users.activate');
    Route::post('users/{user}/email-credentials', [UserController::class, 'emailCredentials'])->name('users.email-credentials');
    Route::delete('users/{user}', [UserController::class, 'destroy'])->name('users.destroy');

    Route::get('accounts/billing', [TenantBillingController::class, 'index'])->middleware(['superadmin', 'central.host'])->name('accounts.billing.index');
    Route::get('accounts/{account}/billing', [TenantBillingController::class, 'edit'])->middleware(['superadmin', 'central.host'])->name('accounts.billing.edit');
    Route::put('accounts/{account}/billing', [TenantBillingController::class, 'update'])->middleware(['superadmin', 'central.host'])->name('accounts.billing.update');
    Route::post('accounts/{account}/billing/lock', [TenantBillingController::class, 'lock'])->middleware(['superadmin', 'central.host'])->name('accounts.billing.lock');
    Route::post('accounts/{account}/billing/unlock', [TenantBillingController::class, 'unlock'])->middleware(['superadmin', 'central.host'])->name('accounts.billing.unlock');
    Route::get('accounts', [AccountController::class, 'index'])->middleware(['superadmin', 'central.host'])->name('accounts.index');
    Route::get('accounts/create', [AccountController::class, 'create'])->middleware(['superadmin', 'central.host'])->name('accounts.create');
    Route::post('accounts', [AccountController::class, 'store'])->middleware(['superadmin', 'central.host'])->name('accounts.store');
    Route::post('accounts/switch', [AccountController::class, 'switch'])->middleware(['superadmin', 'central.host'])->name('accounts.switch');
    Route::post('accounts/clear', [AccountController::class, 'clear'])->middleware('superadmin')->name('accounts.clear');
    Route::post('accounts/{accountId}/visit', [AccountController::class, 'visit'])->middleware(['superadmin', 'central.host'])->name('accounts.visit');

    Route::post('impersonate/stop', [ImpersonationController::class, 'stop'])->name('impersonate.stop');
    Route::post('impersonate/{user}', [ImpersonationController::class, 'start'])->name('impersonate.start');

    Route::middleware('superadmin')->prefix('notifications/admin')->name('notifications.admin.')->group(function () {
        Route::get('/', [PlatformNotificationAdminController::class, 'index'])->name('index');
        Route::post('/', [PlatformNotificationAdminController::class, 'store'])->name('store');
        Route::put('/{notification}', [PlatformNotificationAdminController::class, 'update'])->name('update');
        Route::delete('/{notification}', [PlatformNotificationAdminController::class, 'destroy'])->name('destroy');
    });

    Route::get('branding', [BrandingController::class, 'edit'])->name('branding.edit');
    Route::post('branding', [BrandingController::class, 'update'])->name('branding.update');

    Route::get('settings', [AccountSettingsController::class, 'edit'])->name('settings.edit');
    Route::put('settings', [AccountSettingsController::class, 'update'])->name('settings.update');
    Route::post('settings/portal-domain/verify', [AccountSettingsController::class, 'verifyPortalDomain'])->name('settings.portal-domain.verify');

    Route::get('tools/data-export', [TenantDataExportController::class, 'index'])->name('tools.data-export.index');
    Route::post('tools/data-export', [TenantDataExportController::class, 'store'])->name('tools.data-export.store');
    Route::get('tools/data-export/{tenantDataExport}/download', [TenantDataExportController::class, 'download'])->name('tools.data-export.download');

    Route::get('marketing-opt-outs', [MarketingOptOutController::class, 'index'])->name('marketing-opt-outs.index');
    Route::post('marketing-opt-outs/import', [MarketingOptOutController::class, 'import'])->name('marketing-opt-outs.import');
});

Route::middleware(['auth', 'verified', 'signup.complete', 'two-factor.verified', SetAccountFromUser::class, EnsureTenantAccess::class, 'billing.active'])->group(function () {
    Route::get('notifications', [NotificationInboxController::class, 'page'])->name('notifications.index');
    Route::get('notifications/inbox', [NotificationInboxController::class, 'index'])->name('notifications.inbox');
    Route::post('notifications/read-all', [NotificationInboxController::class, 'markAllRead'])->name('notifications.read-all');
    Route::post('notifications/{notification}/read', [NotificationInboxController::class, 'markRead'])->name('notifications.read');

    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::patch('/profile/preferences', [ProfileController::class, 'updatePreferences'])->name('profile.preferences');
    Route::post('/profile/two-factor/enable', [TwoFactorController::class, 'enable'])->name('profile.two-factor.enable');
    Route::post('/profile/two-factor/confirm', [TwoFactorController::class, 'confirm'])->name('profile.two-factor.confirm');
    Route::post('/profile/two-factor/disable', [TwoFactorController::class, 'disable'])->name('profile.two-factor.disable');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    Route::get('support', [UserSupportTicketController::class, 'index'])->name('support.index');
    Route::get('support/create', [UserSupportTicketController::class, 'create'])->name('support.create');
    Route::post('support', [UserSupportTicketController::class, 'store'])->name('support.store');
    Route::get('support/tickets/{ticket}', [UserSupportTicketController::class, 'show'])->name('support.show');
    Route::post('support/tickets/{ticket}/reply', [UserSupportTicketController::class, 'reply'])->name('support.reply');
});

Route::middleware(['auth', 'verified', 'signup.complete', 'two-factor.verified', SetAccountFromUser::class, EnsureTenantAccess::class, 'billing.active'])->group(function () {
    Route::get('portal/billing/lock', PortalBillingLockController::class)
        ->name('portal.billing.lock')
        ->withoutMiddleware('billing.active');
});

Route::middleware(['auth', 'verified', 'signup.complete', 'two-factor.verified', SetAccountFromUser::class, EnsureTenantAccess::class, 'billing.active', EnsurePortalRole::class.':buyer'])
    ->prefix('portal/buyer')
    ->name('portal.buyer.')
    ->group(function () {
        Route::get('/', [BuyerPortalController::class, 'dashboard'])->name('dashboard');
        Route::get('/leads', [BuyerPortalController::class, 'leads'])->name('leads');
        Route::get('/leads/download', [BuyerPortalController::class, 'downloadLeads'])->name('leads.download');
        Route::get('/leads/{uuid}', [BuyerPortalController::class, 'showLead'])->name('leads.show');
        Route::post('/feedback', [BuyerPortalController::class, 'feedback'])->name('feedback');
        Route::post('/feedback/bulk', [BuyerPortalController::class, 'bulkFeedback'])->name('feedback.bulk');
        Route::post('/returns', [BuyerPortalController::class, 'returnLead'])->name('returns');
        Route::post('/returns/bulk', [BuyerPortalController::class, 'bulkReturn'])->name('returns.bulk');
        Route::get('/transactions', [BuyerPortalController::class, 'transactions'])->name('transactions');
        Route::get('/billing', [BuyerPortalController::class, 'billing'])->name('billing');
        registerCompliancePhase3BuyerInvoiceRoutes();
        Route::post('/stripe/checkout', [BuyerStripeCheckoutController::class, 'checkout'])->name('stripe.checkout');
        Route::post('/stripe/subscribe', [BuyerStripeCheckoutController::class, 'subscribe'])->name('stripe.subscribe');
        Route::post('/stripe/subscription/cancel', [BuyerStripeCheckoutController::class, 'cancelSubscription'])->name('stripe.subscription.cancel');
        Route::post('/stripe/subscription/reactivate', [BuyerStripeCheckoutController::class, 'reactivateSubscription'])->name('stripe.subscription.reactivate');
        Route::get('/stripe/success', [BuyerStripeCheckoutController::class, 'success'])->name('stripe.success');
        Route::get('/integrations', [BuyerPortalController::class, 'integrations'])->name('integrations');
        Route::post('/webhooks', [BuyerPortalController::class, 'storeWebhook'])->name('webhooks.store');
        Route::put('/webhooks/{webhook}', [BuyerPortalController::class, 'updateWebhook'])->name('webhooks.update');
        Route::post('/webhooks/{webhook}/submit', [BuyerPortalController::class, 'submitWebhook'])->name('webhooks.submit');
        Route::delete('/webhooks/{webhook}', [BuyerPortalController::class, 'destroyWebhook'])->name('webhooks.destroy');
        Route::post('/webhooks/{webhook}/request-deletion', [BuyerPortalController::class, 'requestWebhookDeletion'])->name('webhooks.request-deletion');
        Route::get('calls', [BuyerCallPortalController::class, 'index'])->name('calls');
        Route::get('/calls/{call:uuid}', [BuyerCallPortalController::class, 'show'])->name('calls.show');
        Route::post('/calls/{call:uuid}/return', [BuyerCallPortalController::class, 'submitReturn'])->name('calls.return');
        Route::get('/calls-export', [BuyerCallPortalController::class, 'export'])->name('calls.export');
    });

Route::middleware(['auth', 'verified', 'signup.complete', 'two-factor.verified', SetAccountFromUser::class, EnsureTenantAccess::class, 'billing.active', EnsurePortalRole::class.':supplier'])
    ->prefix('portal/supplier')
    ->name('portal.supplier.')
    ->group(function () {
        Route::get('/', [SupplierPortalController::class, 'dashboard'])->name('dashboard');
        Route::get('/leads', [SupplierPortalController::class, 'leads'])->name('leads');
        Route::get('/leads/download', [SupplierPortalController::class, 'downloadLeads'])->name('leads.download');
        Route::get('/leads/import', [SupplierPortalController::class, 'importLeads'])->name('leads.import');
        Route::post('/leads/import', [SupplierPortalController::class, 'storeImport'])->name('leads.import.store');
        Route::get('/leads/import/{import}/errors', [SupplierPortalController::class, 'downloadImportErrors'])->name('leads.import.errors');
        Route::get('/payouts/download', [SupplierPortalController::class, 'downloadPayouts'])->name('payouts.download');
        Route::get('/leads/{uuid}', [SupplierPortalController::class, 'showLead'])->name('leads.show');
        Route::get('/embeds', [SupplierPortalController::class, 'embeds'])->name('embeds');
        Route::post('/forms', [SupplierPortalController::class, 'storeForm'])->name('forms.store');
        Route::put('/forms/{hostedForm}', [SupplierPortalController::class, 'updateForm'])->name('forms.update');
        Route::post('/forms/{hostedForm}/submit', [SupplierPortalController::class, 'submitForm'])->name('forms.submit');
        Route::get('/integrations', [SupplierPortalController::class, 'integrations'])->name('integrations');
        Route::post('/postbacks', [SupplierPortalController::class, 'storePostback'])->name('postbacks.store');
        Route::put('/postbacks/{postback}', [SupplierPortalController::class, 'updatePostback'])->name('postbacks.update');
        Route::post('/postbacks/{postback}/submit', [SupplierPortalController::class, 'submitPostback'])->name('postbacks.submit');
        Route::delete('/postbacks/{postback}', [SupplierPortalController::class, 'destroyPostback'])->name('postbacks.destroy');
        Route::post('/postbacks/{postback}/request-deletion', [SupplierPortalController::class, 'requestPostbackDeletion'])->name('postbacks.request-deletion');
        Route::get('/billing', [SupplierPortalController::class, 'billing'])->name('billing');
        Route::get('/clicks', [SupplierClickPortalController::class, '__invoke'])->name('clicks');
        Route::get('/clicks/export', [SupplierClickPortalController::class, 'export'])->name('clicks.export');
    });

require __DIR__.'/auth.php';
