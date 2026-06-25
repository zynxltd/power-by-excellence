<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\Buyer;
use App\Models\Lead;
use App\Models\PlatformNotification;
use App\Models\User;
use App\Services\Buyers\BuyerNotificationService;
use App\Services\Platform\PlatformNotificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class NotificationsFunctionalityTest extends TestCase
{
    use RefreshDatabase;

    protected User $superAdmin;

    protected User $ukAdmin;

    protected User $usAdmin;

    protected Account $ukAccount;

    protected Account $usAccount;

    protected PlatformNotificationService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\PlatformSeeder::class);
        $this->withoutVite();
        $this->superAdmin = User::where('email', 'admin@powerbyexcellence.test')->first();
        $this->ukAdmin = User::where('email', 'uk@powerbyexcellence.test')->first();
        $this->usAdmin = User::where('email', 'us@powerbyexcellence.test')->first();
        $this->ukAccount = Account::where('slug', 'excellence-uk')->first();
        $this->usAccount = Account::where('slug', 'partner-solar-us')->first();
        $this->service = app(PlatformNotificationService::class);
    }

    protected function ukHost()
    {
        return $this->withServerVariables(['HTTP_HOST' => 'excellence-uk.powerbyexcellence.test']);
    }

    public function test_tenant_admin_inbox_excludes_super_admin_activity(): void
    {
        $this->service->logTenantActivity(
            $this->ukAccount,
            $this->ukAdmin,
            'campaign.created',
            'Campaign created',
            'A campaign was created on UK.',
        );

        $this->assertSame(0, $this->service->unreadCount($this->ukAdmin));

        $this->ukHost()
            ->actingAs($this->ukAdmin)
            ->get(route('notifications.inbox'))
            ->assertOk()
            ->assertJsonPath('unread_count', 0)
            ->assertJsonCount(0, 'notifications');
    }

    public function test_super_admin_inbox_excludes_tenant_broadcasts(): void
    {
        $this->service->broadcast(
            $this->superAdmin,
            'UK maintenance window',
            'Tonight only for UK.',
            $this->ukAccount->id,
            'warning',
        );

        $this->assertSame(0, $this->service->unreadCount($this->superAdmin));

        $this->actingAs($this->superAdmin)
            ->get(route('notifications.inbox'))
            ->assertOk()
            ->assertJsonPath('unread_count', 0);
    }

    public function test_tenant_broadcast_is_scoped_to_target_platform(): void
    {
        $this->service->broadcast(
            $this->superAdmin,
            'UK-only notice',
            'Applies to Excellence UK only.',
            $this->ukAccount->id,
        );

        $this->assertSame(1, $this->service->unreadCount($this->ukAdmin));
        $this->assertSame(0, $this->service->unreadCount($this->usAdmin));
    }

    public function test_global_broadcast_reaches_all_tenant_admins(): void
    {
        $this->service->broadcast(
            $this->superAdmin,
            'Platform-wide update',
            'All tenants should see this.',
            null,
            'info',
        );

        $this->assertSame(1, $this->service->unreadCount($this->ukAdmin));
        $this->assertSame(1, $this->service->unreadCount($this->usAdmin));
        $this->assertSame(0, $this->service->unreadCount($this->superAdmin));
    }

    public function test_expired_notifications_are_hidden_from_inbox(): void
    {
        PlatformNotification::create([
            'account_id' => $this->ukAccount->id,
            'created_by_user_id' => $this->superAdmin->id,
            'audience' => 'tenant',
            'type' => 'broadcast',
            'severity' => 'info',
            'title' => 'Expired notice',
            'body' => 'No longer relevant.',
            'expires_at' => now()->subMinute(),
        ]);

        $this->assertSame(0, $this->service->unreadCount($this->ukAdmin));

        $this->ukHost()
            ->actingAs($this->ukAdmin)
            ->get(route('notifications.inbox'))
            ->assertOk()
            ->assertJsonCount(0, 'notifications');
    }

    public function test_tenant_admin_cannot_mark_super_admin_activity_read(): void
    {
        $activity = $this->service->logTenantActivity(
            $this->ukAccount,
            $this->ukAdmin,
            'api_key.created',
            'API key created',
        );

        $this->ukHost()
            ->actingAs($this->ukAdmin)
            ->post(route('notifications.read', $activity))
            ->assertForbidden();
    }

    public function test_tenant_admin_cannot_access_notification_admin_pages(): void
    {
        $this->ukHost()
            ->actingAs($this->ukAdmin)
            ->get(route('notifications.admin.index'))
            ->assertForbidden();

        $this->ukHost()
            ->actingAs($this->ukAdmin)
            ->post(route('notifications.admin.store'), [
                'title' => 'Sneaky broadcast',
                'severity' => 'critical',
            ])
            ->assertForbidden();
    }

    public function test_mark_single_notification_read_reduces_unread_count(): void
    {
        $notice = $this->service->broadcast(
            $this->superAdmin,
            'Read me once',
            'Single read test.',
            $this->ukAccount->id,
        );

        $this->assertSame(1, $this->service->unreadCount($this->ukAdmin));

        $this->ukHost()
            ->actingAs($this->ukAdmin)
            ->post(route('notifications.read', $notice))
            ->assertRedirect();

        $this->assertSame(0, $this->service->unreadCount($this->ukAdmin));
    }

    public function test_inbox_payload_has_coherent_fields_for_tenant_broadcast(): void
    {
        $this->service->broadcast(
            $this->superAdmin,
            'Scheduled maintenance',
            'Brief outage at midnight UTC.',
            $this->ukAccount->id,
            'warning',
        );

        $this->ukHost()
            ->actingAs($this->ukAdmin)
            ->get(route('notifications.inbox'))
            ->assertOk()
            ->assertJson(fn ($json) => $json
                ->where('unread_count', 1)
                ->has('notifications.0', fn ($n) => $n
                    ->where('title', 'Scheduled maintenance')
                    ->where('body', 'Brief outage at midnight UTC.')
                    ->where('severity', 'warning')
                    ->where('type', 'broadcast')
                    ->where('is_read', false)
                    ->has('created_at')
                    ->etc()
                )
            );
    }

    public function test_super_admin_activity_inbox_includes_tenant_context(): void
    {
        $this->service->logTenantActivity(
            $this->ukAccount,
            $this->ukAdmin,
            'form.created',
            'Hosted form created',
            'New form published.',
        );

        $this->actingAs($this->superAdmin)
            ->get(route('notifications.inbox'))
            ->assertOk()
            ->assertJson(fn ($json) => $json
                ->where('unread_count', 1)
                ->has('notifications.0', fn ($n) => $n
                    ->where('title', 'Hosted form created')
                    ->where('type', 'activity')
                    ->where('account.name', $this->ukAccount->name)
                    ->etc()
                )
            );
    }

    public function test_super_admin_can_update_and_delete_broadcast(): void
    {
        $notice = $this->service->broadcast(
            $this->superAdmin,
            'Original title',
            'Original body',
            null,
            'info',
        );

        $this->actingAs($this->superAdmin)
            ->put(route('notifications.admin.update', $notice), [
                'title' => 'Updated title',
                'body' => 'Updated body',
                'severity' => 'critical',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('platform_notifications', [
            'id' => $notice->id,
            'title' => 'Updated title',
            'severity' => 'critical',
        ]);

        $this->actingAs($this->superAdmin)
            ->delete(route('notifications.admin.destroy', $notice))
            ->assertRedirect();

        $this->assertDatabaseMissing('platform_notifications', ['id' => $notice->id]);
    }

    public function test_super_admin_cannot_update_activity_notification(): void
    {
        $activity = $this->service->logTenantActivity(
            $this->ukAccount,
            $this->ukAdmin,
            'campaign.created',
            'Campaign created',
        );

        $this->actingAs($this->superAdmin)
            ->put(route('notifications.admin.update', $activity), [
                'title' => 'Tampered',
                'severity' => 'info',
            ])
            ->assertForbidden();
    }

    public function test_buyer_sale_email_only_when_notify_on_sale_enabled(): void
    {
        Mail::fake();

        $buyer = Buyer::where('account_id', $this->ukAccount->id)->first();
        $campaign = $this->ukAccount->campaigns()->first();
        $lead = Lead::create([
            'account_id' => $this->ukAccount->id,
            'campaign_id' => $campaign->id,
            'status' => 'sold',
            'field_data' => ['email' => 'buyer-notify@test.test'],
            'received_at' => now(),
            'distributed_at' => now(),
        ]);

        app(BuyerNotificationService::class)->notifyLeadPurchase($buyer, $lead, 25.0);
        Mail::assertNothingSent();

        $buyer->update(['settings' => array_merge($buyer->settings ?? [], ['notify_on_sale' => true])]);

        app(BuyerNotificationService::class)->notifyLeadPurchase($buyer, $lead->fresh(), 25.0);
        Mail::assertSent(\App\Mail\BuyerLeadPurchaseMail::class);
    }

    public function test_admin_notifications_page_lists_broadcasts_and_activity(): void
    {
        $this->service->broadcast($this->superAdmin, 'Broadcast row', null, null);
        $this->service->logTenantActivity($this->ukAccount, $this->ukAdmin, 'test', 'Activity row');

        $this->actingAs($this->superAdmin)
            ->get(route('notifications.admin.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Admin/Notifications/Index')
                ->has('notifications.data', 2)
                ->has('tenants', fn (Assert $tenants) => $tenants->each(fn (Assert $t) => $t
                    ->has('id')
                    ->has('name')
                    ->has('slug')
                    ->etc()
                ))
                ->has('severities', 3)
            );
    }
}
