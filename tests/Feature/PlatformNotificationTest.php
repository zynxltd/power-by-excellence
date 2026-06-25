<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\Campaign;
use App\Models\PlatformNotification;
use App\Models\User;
use App\Services\Platform\PlatformNotificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class PlatformNotificationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\PlatformSeeder::class);
    }

    public function test_tenant_activity_logged_for_super_admin(): void
    {
        $account = Account::where('slug', 'excellence-uk')->first();
        $admin = User::where('email', 'uk@powerbyexcellence.test')->first();
        $super = User::where('email', 'admin@powerbyexcellence.test')->first();

        app(PlatformNotificationService::class)->logTenantActivity(
            $account,
            $admin,
            'campaign.created',
            'Campaign created',
            'Test campaign was created.',
        );

        $this->actingAs($super);

        $this->assertSame(1, app(PlatformNotificationService::class)->unreadCount($super));

        $this->get(route('notifications.inbox'))
            ->assertOk()
            ->assertJsonPath('unread_count', 1)
            ->assertJsonPath('notifications.0.title', 'Campaign created');
    }

    public function test_super_admin_can_broadcast_to_tenant(): void
    {
        $this->withoutVite();

        $super = User::where('email', 'admin@powerbyexcellence.test')->first();
        $account = Account::where('slug', 'excellence-uk')->first();
        $tenantAdmin = User::where('email', 'uk@powerbyexcellence.test')->first();

        $this->actingAs($super)
            ->post(route('notifications.admin.store'), [
                'title' => 'Scheduled maintenance',
                'body' => 'Platform will be updated tonight.',
                'severity' => 'warning',
                'account_id' => $account->id,
            ])
            ->assertRedirect();

        $this->actingAs($tenantAdmin)
            ->withServerVariables(['HTTP_HOST' => 'excellence-uk.powerbyexcellence.test']);

        $this->assertSame(1, app(PlatformNotificationService::class)->unreadCount($tenantAdmin));
    }

    public function test_super_admin_can_manage_notifications_page(): void
    {
        $this->withoutVite();

        $super = User::where('email', 'admin@powerbyexcellence.test')->first();

        $this->actingAs($super)
            ->get(route('notifications.admin.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('Admin/Notifications/Index'));
    }

    public function test_creating_campaign_logs_activity(): void
    {
        $admin = User::where('email', 'uk@powerbyexcellence.test')->first();

        $this->withServerVariables(['HTTP_HOST' => 'excellence-uk.powerbyexcellence.test'])
            ->actingAs($admin)
            ->post(route('campaigns.store'), [
                'name' => 'Notify Test Campaign',
                'reference' => 'notify-test-campaign',
                'vertical_id' => 'loans',
                'country' => 'GB',
                'currency' => 'GBP',
                'status' => 'active',
                'payout_amount' => 10,
                'floor_price' => 5,
            ]);

        $this->assertDatabaseHas('platform_notifications', [
            'type' => 'activity',
            'audience' => 'super_admin',
            'title' => 'Campaign created',
        ]);
    }

    public function test_mark_all_notifications_read(): void
    {
        $super = User::where('email', 'admin@powerbyexcellence.test')->first();
        $account = Account::first();

        PlatformNotification::create([
            'account_id' => $account->id,
            'audience' => 'super_admin',
            'type' => 'activity',
            'severity' => 'info',
            'title' => 'Test activity',
        ]);

        $this->actingAs($super)
            ->post(route('notifications.read-all'))
            ->assertRedirect();

        $this->assertSame(0, app(PlatformNotificationService::class)->unreadCount($super));
    }
}
