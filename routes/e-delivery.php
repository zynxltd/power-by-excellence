<?php

/**
 * E-Delivery route definitions (owned by E-Delivery workstream).
 *
 * Integration Lead: require this file from routes/web.php:
 *
 *   // Public tracking + ESP webhooks (no auth)
 *   require __DIR__.'/e-delivery.php';
 *
 *   // Inside the admin middleware group, call:
 *   registerEDeliveryAdminRoutes();
 *
 * Schedule in bootstrap/app.php:
 *   $schedule->command('bulk:process-scheduled')->everyMinute()->withoutOverlapping();
 *   $schedule->command('automation:process-sequences')->everyMinute()->withoutOverlapping();
 *   $schedule->command('messaging:process-scheduled')->everyMinute()->withoutOverlapping();
 */

use App\Http\Controllers\Admin\AutomationController;
use App\Http\Controllers\Admin\EDeliveryController;
use App\Http\Controllers\Admin\MessagingIntegrationController;
use App\Http\Controllers\EspWebhookController;
use App\Http\Controllers\MessageTrackingController;
use Illuminate\Support\Facades\Route;

Route::get('/messaging/open/{token}', [MessageTrackingController::class, 'open'])->name('messaging.track.open');
Route::get('/messaging/click/{token}', [MessageTrackingController::class, 'click'])->name('messaging.track.click');
Route::get('/messaging/unsubscribe/{token}', [MessageTrackingController::class, 'unsubscribe'])->name('messaging.unsubscribe');
Route::post('/messaging/unsubscribe/{token}', [MessageTrackingController::class, 'confirmUnsubscribe'])->name('messaging.unsubscribe.confirm');

Route::post('/webhooks/esp/sendgrid', [EspWebhookController::class, 'sendgrid'])->name('webhooks.esp.sendgrid');
Route::post('/webhooks/esp/mailgun', [EspWebhookController::class, 'mailgun'])->name('webhooks.esp.mailgun');
Route::post('/webhooks/esp/postmark', [EspWebhookController::class, 'postmark'])->name('webhooks.esp.postmark');

function registerEDeliveryJourneyRoutes(): void
{
    if (! Route::has('e-delivery.journeys.process')) {
        Route::post('e-delivery/journeys/process', [AutomationController::class, 'processJourneys'])
            ->name('e-delivery.journeys.process');
    }

    if (! Route::has('e-delivery.throttle.pause')) {
        Route::post('e-delivery/throttle/pause', [EDeliveryController::class, 'pauseSending'])
            ->name('e-delivery.throttle.pause');
    }

    if (! Route::has('e-delivery.throttle.resume')) {
        Route::post('e-delivery/throttle/resume', [EDeliveryController::class, 'resumeSending'])
            ->name('e-delivery.throttle.resume');
    }

    if (! Route::has('e-delivery.bulk-campaigns.store')) {
        Route::post('e-delivery/bulk-campaigns', [EDeliveryController::class, 'storeBulkCampaign'])
            ->name('e-delivery.bulk-campaigns.store');
    }

    if (! Route::has('e-delivery.bulk-campaigns.send')) {
        Route::post('e-delivery/bulk-campaigns/{bulkSms}/send', [EDeliveryController::class, 'sendBulkCampaign'])
            ->name('e-delivery.bulk-campaigns.send');
    }
}

function registerEDeliveryAdminRoutes(): void
{
    registerEDeliveryJourneyRoutes();

    if (! Route::has('e-delivery.sending-profiles.warmup')) {
        Route::patch('e-delivery/sending-profiles/{profile}/warmup', [EDeliveryController::class, 'updateSendingProfileWarmup'])
            ->name('e-delivery.sending-profiles.warmup');
    }

    if (! Route::has('e-delivery.send-time-settings.update')) {
        Route::patch('e-delivery/send-time-settings', [EDeliveryController::class, 'updateSendTimeSettings'])
            ->name('e-delivery.send-time-settings.update');
    }

    if (Route::has('e-delivery.index')) {
        return;
    }

    Route::get('e-delivery', [EDeliveryController::class, 'index'])->name('e-delivery.index');
    Route::post('e-delivery/segments', [EDeliveryController::class, 'storeSegment'])->name('e-delivery.segments.store');
    Route::delete('e-delivery/segments/{segment}', [EDeliveryController::class, 'destroySegment'])->name('e-delivery.segments.destroy');
    Route::post('e-delivery/templates', [EDeliveryController::class, 'storeTemplate'])->name('e-delivery.templates.store');
    Route::post('e-delivery/templates/preview', [EDeliveryController::class, 'previewTemplate'])->name('e-delivery.templates.preview');
    Route::put('e-delivery/templates/{template}', [EDeliveryController::class, 'updateTemplate'])->name('e-delivery.templates.update');
    Route::delete('e-delivery/templates/{template}', [EDeliveryController::class, 'destroyTemplate'])->name('e-delivery.templates.destroy');
    Route::post('e-delivery/sending-profiles', [EDeliveryController::class, 'storeSendingProfile'])->name('e-delivery.sending-profiles.store');
    Route::delete('e-delivery/sending-profiles/{profile}', [EDeliveryController::class, 'destroySendingProfile'])->name('e-delivery.sending-profiles.destroy');
    Route::post('leads/{lead}/tags', [EDeliveryController::class, 'tagLead'])->name('leads.tags.store');
    Route::delete('leads/{lead}/tags', [EDeliveryController::class, 'untagLead'])->name('leads.tags.destroy');

    Route::get('integrations/messaging', [MessagingIntegrationController::class, 'edit'])->name('integrations.messaging');
    Route::put('integrations/messaging', [MessagingIntegrationController::class, 'update'])->name('integrations.messaging.update');
}
