<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\Buyer;
use App\Models\User;
use App\Models\Webhook;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class UserWebhookEditParityTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected Account $account;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\PlatformSeeder::class);
        $this->withoutVite();

        $this->account = Account::where('slug', 'excellence-uk')->firstOrFail();
        $this->admin = User::where('email', 'uk@powerbyexcellence.test')->firstOrFail();
    }

    protected function ukHost()
    {
        return $this->withServerVariables(['HTTP_HOST' => 'excellence-uk.powerbyexcellence.test']);
    }

    public function test_admin_can_open_user_edit_page(): void
    {
        $portalUser = User::where('email', 'buyer-portal@excellence-uk.test')->firstOrFail();

        $this->ukHost()
            ->actingAs($this->admin)
            ->get(route('users.edit', $portalUser))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Admin/Users/Edit')
                ->where('user.id', $portalUser->id)
                ->where('user.email', $portalUser->email)
                ->has('buyers')
                ->has('suppliers')
                ->has('modules'));
    }

    public function test_admin_can_update_user_from_edit_route(): void
    {
        $portalUser = User::where('email', 'buyer-portal@excellence-uk.test')->firstOrFail();
        $buyer = Buyer::where('account_id', $this->account->id)->firstOrFail();

        $this->ukHost()
            ->actingAs($this->admin)
            ->put(route('users.update', $portalUser), [
                'name' => 'Updated Portal User',
                'email' => $portalUser->email,
                'role' => 'buyer_portal',
                'buyer_id' => $buyer->id,
            ])
            ->assertRedirect();

        $portalUser->refresh();
        $this->assertSame('Updated Portal User', $portalUser->name);
        $this->assertSame($buyer->id, $portalUser->buyer_id);
    }

    public function test_admin_can_update_webhook_including_is_active(): void
    {
        $buyer = Buyer::where('account_id', $this->account->id)->firstOrFail();

        $webhook = Webhook::create([
            'account_id' => $this->account->id,
            'name' => 'CRM feed',
            'url' => 'https://hooks.example.com/leads',
            'events' => ['lead.sold'],
            'is_active' => true,
        ]);

        $this->ukHost()
            ->actingAs($this->admin)
            ->put(route('webhooks.update', $webhook), [
                'name' => 'Updated CRM feed',
                'url' => 'https://hooks.example.com/updated',
                'events' => ['lead.sold', 'lead.unsold'],
                'buyer_id' => $buyer->id,
                'is_active' => false,
            ])
            ->assertRedirect()
            ->assertSessionHas('success');

        $webhook->refresh();
        $this->assertSame('Updated CRM feed', $webhook->name);
        $this->assertSame('https://hooks.example.com/updated', $webhook->url);
        $this->assertSame(['lead.sold', 'lead.unsold'], $webhook->events);
        $this->assertSame($buyer->id, $webhook->buyer_id);
        $this->assertFalse($webhook->is_active);
    }

    public function test_admin_cannot_update_buyer_synced_webhook(): void
    {
        $buyer = Buyer::where('account_id', $this->account->id)->firstOrFail();

        $webhook = Webhook::create([
            'account_id' => $this->account->id,
            'buyer_id' => $buyer->id,
            'name' => 'Buyer sold webhook',
            'url' => 'https://hooks.example.com/buyer',
            'events' => ['lead.sold'],
            'is_active' => true,
            'config' => ['synced_from' => 'buyer_sold_webhook'],
        ]);

        $this->ukHost()
            ->actingAs($this->admin)
            ->put(route('webhooks.update', $webhook), [
                'name' => 'Should not change',
                'url' => 'https://hooks.example.com/nope',
                'events' => ['lead.sold'],
                'is_active' => false,
            ])
            ->assertRedirect()
            ->assertSessionHas('error');

        $webhook->refresh();
        $this->assertSame('Buyer sold webhook', $webhook->name);
        $this->assertTrue($webhook->is_active);
    }
}
