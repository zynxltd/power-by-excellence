<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\Buyer;
use App\Models\PlatformNotification;
use App\Models\User;
use App\Models\Webhook;
use App\Services\Webhooks\BuyerWebhookService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class BuyerWebhookApprovalTest extends TestCase
{
    use RefreshDatabase;

    protected Account $account;

    protected Buyer $buyer;

    protected User $buyerUser;

    protected User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\PlatformSeeder::class);
        $this->seed(\Database\Seeders\HelpArticleSeeder::class);
        $this->withoutVite();

        $this->account = Account::where('slug', 'excellence-uk')->firstOrFail();
        $this->buyer = Buyer::where('account_id', $this->account->id)->firstOrFail();
        $this->buyerUser = User::where('email', 'buyer-portal@excellence-uk.test')->firstOrFail();
        $this->admin = User::where('email', 'uk@powerbyexcellence.test')->firstOrFail();
    }

    protected function ukHost()
    {
        return $this->withServerVariables(['HTTP_HOST' => 'excellence-uk.powerbyexcellence.test']);
    }

    public function test_buyer_can_create_draft_webhook(): void
    {
        $this->ukHost()
            ->actingAs($this->buyerUser)
            ->post(route('portal.buyer.webhooks.store'), [
                'name' => 'CRM feed',
                'url' => 'https://hooks.example.com/leads',
                'events' => ['lead.sold'],
            ])
            ->assertRedirect();

        $webhook = Webhook::where('name', 'CRM feed')->first();
        $this->assertNotNull($webhook);
        $this->assertSame($this->buyer->id, $webhook->buyer_id);
        $this->assertSame(BuyerWebhookService::STATUS_DRAFT, $webhook->approval_status);
        $this->assertFalse($webhook->is_active);
    }

    public function test_buyer_can_submit_webhook_for_tenant_approval(): void
    {
        $webhook = Webhook::create([
            'account_id' => $this->account->id,
            'buyer_id' => $this->buyer->id,
            'name' => 'Pending hook',
            'url' => 'https://hooks.example.com/leads',
            'events' => ['lead.sold'],
            'is_active' => false,
            'approval_status' => BuyerWebhookService::STATUS_DRAFT,
        ]);

        $this->ukHost()
            ->actingAs($this->buyerUser)
            ->post(route('portal.buyer.webhooks.submit', $webhook), [
                'submission_notes' => 'Please enable for our CRM.',
            ])
            ->assertRedirect();

        $webhook->refresh();
        $this->assertSame(BuyerWebhookService::STATUS_PENDING, $webhook->approval_status);

        $this->assertDatabaseHas('platform_notifications', [
            'account_id' => $this->account->id,
            'title' => 'Webhook approval requested',
        ]);
    }

    public function test_admin_can_approve_buyer_webhook(): void
    {
        $webhook = Webhook::create([
            'account_id' => $this->account->id,
            'buyer_id' => $this->buyer->id,
            'name' => 'Approve me',
            'url' => 'https://hooks.example.com/leads',
            'events' => ['lead.sold'],
            'is_active' => false,
            'approval_status' => BuyerWebhookService::STATUS_PENDING,
            'submitted_at' => now(),
        ]);

        $this->ukHost()
            ->actingAs($this->admin)
            ->post(route('webhooks.approve', $webhook))
            ->assertRedirect();

        $webhook->refresh();
        $this->assertTrue($webhook->isLive());
        $this->assertSame(BuyerWebhookService::STATUS_APPROVED, $webhook->approval_status);
    }

    public function test_buyer_integrations_page_has_webhook_ui(): void
    {
        $this->ukHost()
            ->actingAs($this->buyerUser)
            ->get(route('portal.buyer.integrations'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Portal/Buyer/Integrations')
                ->has('helpUrls')
                ->has('webhookRequests')
                ->has('webhookEventOptions')
                ->missing('endpoints')
            );
    }

    public function test_buyer_can_access_help_on_tenant_host(): void
    {
        $this->ukHost()
            ->actingAs($this->buyerUser)
            ->get(route('help.show', 'buyer-portal-feedback-returns'))
            ->assertOk();
    }
}
