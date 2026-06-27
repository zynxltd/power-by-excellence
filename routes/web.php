<?php

use App\Http\Controllers\Admin\AccessLogController;
use App\Http\Controllers\Admin\AccountController;
use App\Http\Controllers\Admin\TenantBillingController;
use App\Http\Controllers\Admin\ApiDocsController;
use App\Http\Controllers\Admin\ApiKeyController;
use App\Http\Controllers\Admin\ApiRequestLogController;
use App\Http\Controllers\Admin\AccountSettingsController;
use App\Http\Controllers\Admin\ChangeLogController;
use App\Http\Controllers\Admin\BillingController;
use App\Http\Controllers\Admin\FinanceController;
use App\Http\Controllers\Admin\BrandingController;
use App\Http\Controllers\Admin\BuyerController;
use App\Http\Controllers\Admin\CampaignApiSpecController;
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
use App\Http\Controllers\DemoRequestController;
use App\Http\Controllers\HelpController;
use App\Http\Controllers\SystemStatusController;
use App\Http\Controllers\UserSupportTicketController;
use App\Http\Controllers\PlatformEntryController;
use App\Http\Controllers\Portal\BuyerPortalController;
use App\Http\Controllers\Portal\SupplierPortalController;
use App\Http\Controllers\PublicFormController;
use App\Http\Controllers\ProfileController;
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
Route::get('/god-mode/handoff/{token}', GodModeHandoffController::class)->name('god-mode.handoff');

Route::middleware(['auth', 'verified', 'signup.complete', SetAccountFromUser::class, EnsureTenantAccess::class, 'billing.active', EnsurePortalRole::class.':admin', 'module.access'])->group(function () {
    Route::get('/dashboard', DashboardController::class)->name('dashboard');
    Route::get('/live-stats', \App\Http\Controllers\Admin\LiveStatsController::class)->name('live-stats');
    Route::get('/command-center', [CommandCenterController::class, 'index'])->middleware(['superadmin', 'central.host'])->name('command-center.index');
    Route::get('/platform-events', [PlatformEventsController::class, 'index'])->middleware(['superadmin', 'central.host'])->name('platform-events.index');
    Route::get('/live-feed', [LiveFeedController::class, 'index'])->middleware(['superadmin', 'central.host'])->name('live-feed.index');
    Route::get('/operations', [OperationsController::class, 'index'])->name('operations.index');
    Route::get('/logs/access', [AccessLogController::class, 'index'])->name('logs.access');
    Route::get('/logs/delivery', [DeliveryLogController::class, 'index'])->name('logs.delivery');
    Route::get('/logs/delivery/{deliveryLog}', [DeliveryLogController::class, 'show'])->name('logs.delivery.show');
    Route::get('/logs/api', [ApiRequestLogController::class, 'index'])->name('logs.api');
    Route::get('/logs/changes', [ChangeLogController::class, 'index'])->name('logs.changes');
    Route::get('/logs/security', [SecurityLogController::class, 'index'])->name('logs.security');

    Route::get('support/manage', [SupportTicketController::class, 'index'])->middleware(['superadmin', 'central.host'])->name('support.admin.index');
    Route::get('support/manage/{ticket}', [SupportTicketController::class, 'show'])->middleware(['superadmin', 'central.host'])->name('support.admin.show');
    Route::post('support/manage/{ticket}/reply', [SupportTicketController::class, 'reply'])->middleware(['superadmin', 'central.host'])->name('support.admin.reply');
    Route::patch('support/manage/{ticket}/status', [SupportTicketController::class, 'updateStatus'])->middleware(['superadmin', 'central.host'])->name('support.admin.status');

    Route::get('automation', [AutomationController::class, 'index'])->name('automation.index');
    Route::post('automation/sequences', [AutomationController::class, 'storeSequence'])->name('automation.sequences.store');
    Route::post('automation/bulk-sms', [AutomationController::class, 'storeBulkSms'])->name('automation.bulk-sms.store');
    Route::post('automation/bulk-sms/{bulkSms}/send', [AutomationController::class, 'sendBulkSms'])->name('automation.bulk-sms.send');
    Route::post('automation/alerts', [AutomationController::class, 'storeAlert'])->name('automation.alerts.store');
    Route::delete('automation/sequences/{sequence}', [AutomationController::class, 'destroySequence'])->name('automation.sequences.destroy');
    Route::delete('automation/alerts/{alert}', [AutomationController::class, 'destroyAlert'])->name('automation.alerts.destroy');

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
    Route::post('features/auto-responders', [AutoResponderController::class, 'store'])->name('features.auto-responders.store');
    Route::delete('features/auto-responders/{autoResponder}', [AutoResponderController::class, 'destroy'])->name('features.auto-responders.destroy');

    Route::get('reports', [ReportsController::class, 'index'])->name('reports.index');

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

    Route::get('imports', [ImportController::class, 'index'])->name('imports.index');
    Route::post('imports', [ImportController::class, 'store'])->name('imports.store');

    Route::get('webhooks', [WebhookController::class, 'index'])->name('webhooks.index');
    Route::post('webhooks', [WebhookController::class, 'store'])->name('webhooks.store');
    Route::post('webhooks/{webhook}/approve', [WebhookController::class, 'approve'])->name('webhooks.approve');
    Route::post('webhooks/{webhook}/reject', [WebhookController::class, 'reject'])->name('webhooks.reject');
    Route::post('webhooks/{webhook}/approve-deletion', [WebhookController::class, 'approveDeletion'])->name('webhooks.approve-deletion');
    Route::post('webhooks/{webhook}/reject-deletion', [WebhookController::class, 'rejectDeletion'])->name('webhooks.reject-deletion');
    Route::delete('webhooks/{webhook}', [WebhookController::class, 'destroy'])->name('webhooks.destroy');

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
});

Route::middleware(['auth', 'verified', 'signup.complete', SetAccountFromUser::class, EnsureTenantAccess::class, 'billing.active'])->group(function () {
    Route::get('notifications', [NotificationInboxController::class, 'page'])->name('notifications.index');
    Route::get('notifications/inbox', [NotificationInboxController::class, 'index'])->name('notifications.inbox');
    Route::post('notifications/read-all', [NotificationInboxController::class, 'markAllRead'])->name('notifications.read-all');
    Route::post('notifications/{notification}/read', [NotificationInboxController::class, 'markRead'])->name('notifications.read');

    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::patch('/profile/preferences', [ProfileController::class, 'updatePreferences'])->name('profile.preferences');
    Route::post('/profile/two-factor/enable', [TwoFactorController::class, 'enable'])->name('profile.two-factor.enable');
    Route::post('/profile/two-factor/disable', [TwoFactorController::class, 'disable'])->name('profile.two-factor.disable');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    Route::get('support', [UserSupportTicketController::class, 'index'])->name('support.index');
    Route::get('support/create', [UserSupportTicketController::class, 'create'])->name('support.create');
    Route::post('support', [UserSupportTicketController::class, 'store'])->name('support.store');
    Route::get('support/tickets/{ticket}', [UserSupportTicketController::class, 'show'])->name('support.show');
    Route::post('support/tickets/{ticket}/reply', [UserSupportTicketController::class, 'reply'])->name('support.reply');
});

Route::middleware(['auth', 'verified', 'signup.complete', SetAccountFromUser::class, EnsureTenantAccess::class, 'billing.active', EnsurePortalRole::class.':buyer'])
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
        Route::get('/integrations', [BuyerPortalController::class, 'integrations'])->name('integrations');
        Route::post('/webhooks', [BuyerPortalController::class, 'storeWebhook'])->name('webhooks.store');
        Route::put('/webhooks/{webhook}', [BuyerPortalController::class, 'updateWebhook'])->name('webhooks.update');
        Route::post('/webhooks/{webhook}/submit', [BuyerPortalController::class, 'submitWebhook'])->name('webhooks.submit');
        Route::delete('/webhooks/{webhook}', [BuyerPortalController::class, 'destroyWebhook'])->name('webhooks.destroy');
        Route::post('/webhooks/{webhook}/request-deletion', [BuyerPortalController::class, 'requestWebhookDeletion'])->name('webhooks.request-deletion');
    });

Route::middleware(['auth', 'verified', 'signup.complete', SetAccountFromUser::class, EnsureTenantAccess::class, 'billing.active', EnsurePortalRole::class.':supplier'])
    ->prefix('portal/supplier')
    ->name('portal.supplier.')
    ->group(function () {
        Route::get('/', [SupplierPortalController::class, 'dashboard'])->name('dashboard');
        Route::get('/leads', [SupplierPortalController::class, 'leads'])->name('leads');
        Route::get('/leads/download', [SupplierPortalController::class, 'downloadLeads'])->name('leads.download');
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
    });

require __DIR__.'/auth.php';
