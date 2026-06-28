<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\User;
use App\Services\Messaging\MarketingSuppressionService;
use App\Services\Messaging\MessageSendService;
use App\Support\Tenancy\AccountContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class MarketingOptOutTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected Account $account;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\PlatformSeeder::class);
        $this->admin = User::where('email', 'uk@powerbyexcellence.test')->first();
        $this->account = Account::where('slug', 'excellence-uk')->first();
        AccountContext::set($this->account);
    }

    public function test_csv_import_adds_marketing_suppressions(): void
    {
        $csv = "email,phone\nblocked-import@example.com,+447700900999\n";
        $file = UploadedFile::fake()->createWithContent('opt-outs.csv', $csv);

        $result = app(MarketingSuppressionService::class)->importCsv($this->account->id, $file);

        $this->assertSame(2, $result['imported']);
        $this->assertSame(0, $result['skipped']);

        $service = app(MarketingSuppressionService::class);
        $this->assertTrue($service->isSuppressed($this->account->id, 'email', 'blocked-import@example.com'));
        $this->assertTrue($service->isSuppressed($this->account->id, 'sms', '+447700900999'));

        $this->assertDatabaseHas('marketing_opt_outs', [
            'account_id' => $this->account->id,
            'source' => 'import',
            'field_type' => 'email',
        ]);
    }

    public function test_suppressed_address_is_blocked_on_send(): void
    {
        app(MarketingSuppressionService::class)->optOut(
            $this->account->id,
            'email',
            'blocked-send@example.com',
            'manual',
        );

        $sent = app(MessageSendService::class)->send([
            'account_id' => $this->account->id,
            'channel' => 'email',
            'recipient' => 'blocked-send@example.com',
            'subject' => 'Promo',
            'body' => 'Hello',
            'provider' => 'log',
            'track' => false,
        ]);

        $this->assertFalse($sent);
        $this->assertDatabaseMissing('message_sends', [
            'account_id' => $this->account->id,
            'recipient' => 'blocked-send@example.com',
        ]);
    }

    public function test_opt_out_stores_masked_label_for_admin_list(): void
    {
        app(MarketingSuppressionService::class)->optOut(
            $this->account->id,
            'email',
            'listed@example.com',
            'manual',
        );

        $this->assertDatabaseHas('marketing_opt_outs', [
            'account_id' => $this->account->id,
            'field_type' => 'email',
            'source' => 'manual',
            'label' => 'l***@example.com',
        ]);
    }
}
