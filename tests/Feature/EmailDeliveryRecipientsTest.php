<?php

namespace Tests\Feature;

use App\Enums\DeliveryMethod;
use App\Enums\LeadStatus;
use App\Models\Account;
use App\Models\Buyer;
use App\Models\Campaign;
use App\Models\Delivery;
use App\Models\DeliveryLog;
use App\Models\Lead;
use App\Models\User;
use App\Enums\UserRole;
use App\Services\Delivery\DeliveryExecutor;
use App\Support\Delivery\EmailRecipientResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EmailDeliveryRecipientsTest extends TestCase
{
    use RefreshDatabase;

    public function test_resolver_parses_multiple_recipients_and_buyer_email(): void
    {
        $buyer = new Buyer(['email' => 'buyer@example.com']);

        $resolved = app(EmailRecipientResolver::class)->resolve([
            'use_buyer_email' => true,
            'to' => 'ops@example.com, alerts@example.com',
            'cc' => 'cc@example.com',
            'bcc' => 'bcc@example.com',
        ], new Delivery(['buyer_id' => 1])->setRelation('buyer', $buyer));

        $this->assertSame(['buyer@example.com', 'ops@example.com', 'alerts@example.com'], $resolved['to']);
        $this->assertSame(['cc@example.com'], $resolved['cc']);
        $this->assertSame(['bcc@example.com'], $resolved['bcc']);
    }

    public function test_resolver_falls_back_to_portal_user_email(): void
    {
        $account = Account::create([
            'name' => 'Email Test',
            'slug' => 'email-test',
            'default_currency' => 'GBP',
            'default_country' => 'GB',
        ]);

        $buyer = Buyer::create([
            'account_id' => $account->id,
            'reference' => 'email-buyer',
            'name' => 'Email Buyer',
            'status' => 'active',
        ]);

        User::create([
            'account_id' => $account->id,
            'buyer_id' => $buyer->id,
            'name' => 'Portal User',
            'email' => 'portal@buyer.com',
            'password' => bcrypt('password'),
            'role' => UserRole::BuyerPortal,
        ]);

        $resolved = app(EmailRecipientResolver::class)->resolve([
            'use_buyer_email' => true,
        ], new Delivery(['buyer_id' => $buyer->id])->setRelation('buyer', $buyer->fresh()));

        $this->assertSame(['portal@buyer.com'], $resolved['to']);
    }

    public function test_email_delivery_sends_to_multiple_recipients_with_cc_and_bcc(): void
    {
        $account = Account::create([
            'name' => 'Mail Test',
            'slug' => 'mail-test',
            'default_currency' => 'GBP',
            'default_country' => 'GB',
        ]);

        $campaign = Campaign::create([
            'account_id' => $account->id,
            'name' => 'Mail Campaign',
            'reference' => 'mail-campaign',
            'payout_amount' => 5,
        ]);

        $buyer = Buyer::create([
            'account_id' => $account->id,
            'reference' => 'mail-buyer',
            'name' => 'Mail Buyer',
            'email' => 'buyer@mail.test',
            'status' => 'active',
            'credit_balance' => 500,
        ]);

        $delivery = Delivery::create([
            'campaign_id' => $campaign->id,
            'buyer_id' => $buyer->id,
            'name' => 'Email Multi',
            'method' => DeliveryMethod::Email,
            'status' => 'active',
            'priority' => 1,
            'revenue_amount' => 20,
            'config' => [
                'use_buyer_email' => true,
                'to' => 'ops@mail.test',
                'cc' => 'cc@mail.test',
                'bcc' => 'bcc@mail.test',
                'subject' => 'Lead',
                'body' => 'Test',
            ],
        ]);

        $lead = Lead::create([
            'account_id' => $account->id,
            'campaign_id' => $campaign->id,
            'status' => LeadStatus::Accepted,
            'field_data' => ['email' => 'lead@test.com'],
            'received_at' => now(),
        ]);

        $result = app(DeliveryExecutor::class)->execute($lead, $delivery);

        $this->assertTrue($result->success);

        $log = DeliveryLog::query()->where('lead_id', $lead->id)->first();
        $this->assertNotNull($log);
        $this->assertSame(['buyer@mail.test', 'ops@mail.test'], $log->post_request['to']);
        $this->assertSame(['cc@mail.test'], $log->post_request['cc']);
        $this->assertSame(['bcc@mail.test'], $log->post_request['bcc']);
    }
}
