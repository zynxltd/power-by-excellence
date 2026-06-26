<?php

namespace Tests\Feature;

use App\Models\SupportTicket;
use App\Models\SupportTicketMessage;
use App\Models\User;
use App\Services\Platform\PlatformNotificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SupportTicketTest extends TestCase
{
    use RefreshDatabase;

    protected User $tenantAdmin;

    protected User $superAdmin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\PlatformSeeder::class);
        $this->withoutVite();
        $this->tenantAdmin = User::where('email', 'uk@powerbyexcellence.test')->first();
        $this->superAdmin = User::where('email', 'admin@powerbyexcellence.test')->first();
    }

    public function test_tenant_admin_can_create_ticket_visible_to_super_admin(): void
    {
        $this->actingAs($this->tenantAdmin)
            ->post(route('support.store'), [
                'subject' => 'Billing question',
                'body' => 'Need help with buyer credits.',
                'priority' => 'high',
            ])
            ->assertRedirect();

        $ticket = SupportTicket::where('subject', 'Billing question')->first();
        $this->assertNotNull($ticket);
        $this->assertSame($this->tenantAdmin->id, $ticket->user_id);
        $this->assertSame($this->tenantAdmin->account_id, $ticket->account_id);

        $this->actingAs($this->superAdmin)
            ->get(route('support.admin.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Admin/Support/Index')
                ->has('tickets.data', 1)
                ->where('tickets.data.0.subject', 'Billing question')
            );
    }

    public function test_super_admin_support_index_redirects_to_queue(): void
    {
        $this->actingAs($this->superAdmin)
            ->get(route('support.index'))
            ->assertRedirect(route('support.admin.index'));
    }

    public function test_super_admin_cannot_create_support_ticket(): void
    {
        $this->actingAs($this->superAdmin)
            ->get(route('support.create'))
            ->assertForbidden();

        $this->actingAs($this->superAdmin)
            ->post(route('support.store'), [
                'subject' => 'Should not exist',
                'body' => 'Super admin ticket',
            ])
            ->assertForbidden();
    }

    public function test_super_admin_can_reply_to_tenant_ticket(): void
    {
        $ticket = SupportTicket::create([
            'user_id' => $this->tenantAdmin->id,
            'account_id' => $this->tenantAdmin->account_id,
            'portal_role' => 'admin',
            'subject' => 'Routing help',
            'priority' => 'normal',
            'status' => 'open',
        ]);

        SupportTicketMessage::create([
            'support_ticket_id' => $ticket->id,
            'user_id' => $this->tenantAdmin->id,
            'body' => 'How do I set up ping-post?',
            'is_staff' => false,
        ]);

        $this->actingAs($this->superAdmin)
            ->post(route('support.admin.reply', $ticket), ['body' => 'Configure your delivery method first.'])
            ->assertRedirect();

        $this->assertDatabaseHas('support_ticket_messages', [
            'support_ticket_id' => $ticket->id,
            'user_id' => $this->superAdmin->id,
            'body' => 'Configure your delivery method first.',
            'is_staff' => true,
        ]);

        $this->assertSame('pending', $ticket->fresh()->status);
    }

    public function test_super_admin_reply_notifies_tenant_platform(): void
    {
        $ticket = SupportTicket::create([
            'user_id' => $this->tenantAdmin->id,
            'account_id' => $this->tenantAdmin->account_id,
            'portal_role' => 'admin',
            'subject' => 'Routing help',
            'priority' => 'normal',
            'status' => 'open',
        ]);

        $this->actingAs($this->superAdmin)
            ->post(route('support.admin.reply', $ticket), ['body' => 'Configure your delivery method first.'])
            ->assertRedirect();

        $this->assertSame(1, app(PlatformNotificationService::class)->unreadCount($this->tenantAdmin));

        $this->actingAs($this->tenantAdmin)
            ->getJson(route('notifications.inbox'))
            ->assertOk()
            ->assertJsonPath('unread_count', 1)
            ->assertJsonPath('notifications.0.title', 'Support replied: Routing help')
            ->assertJsonPath('notifications.0.type', 'support');
    }

    public function test_tenant_ticket_creation_notifies_super_admin(): void
    {
        $this->actingAs($this->tenantAdmin)
            ->post(route('support.store'), [
                'subject' => 'API limits',
                'body' => 'What are the rate limits?',
                'priority' => 'normal',
            ])
            ->assertRedirect();

        $this->assertGreaterThanOrEqual(1, app(PlatformNotificationService::class)->unreadCount($this->superAdmin));

        $this->actingAs($this->superAdmin)
            ->getJson(route('notifications.inbox'))
            ->assertOk()
            ->assertJsonFragment(['title' => 'New support ticket: API limits']);
    }
}
