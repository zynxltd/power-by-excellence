<?php

namespace Tests\Feature;

use App\Models\SendingProfile;
use App\Models\User;
use App\Services\Messaging\MessageSendService;
use App\Services\Messaging\WarmupGovernor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WarmupGovernorTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\PlatformSeeder::class);
        $this->withoutVite();
        $this->admin = User::where('email', 'uk@powerbyexcellence.test')->first();

        require_once base_path('routes/e-delivery.php');
        registerEDeliveryAdminRoutes();
    }

    protected function warmupProfile(array $overrides = []): SendingProfile
    {
        $account = $this->admin->resolveAccount();

        return SendingProfile::create(array_merge([
            'account_id' => $account->id,
            'name' => 'Warmup domain',
            'provider' => 'log',
            'domain_match' => 'warmup.test',
            'from_email' => 'hello@warmup.test',
            'warmup_enabled' => true,
            'warmup_started_at' => now()->startOfDay(),
            'warmup_day_one_limit' => 2,
            'warmup_target_limit' => 100,
            'warmup_ramp_days' => 14,
        ], $overrides));
    }

    protected function sendEmail(SendingProfile $profile, string $recipient): bool
    {
        return app(MessageSendService::class)->send([
            'account_id' => $profile->account_id,
            'sending_profile_id' => $profile->id,
            'channel' => 'email',
            'recipient' => $recipient,
            'subject' => 'Warmup test',
            'body' => 'Body',
            'provider' => 'log',
            'track' => false,
        ]);
    }

    public function test_warmup_caps_sends_on_day_one(): void
    {
        $profile = $this->warmupProfile();

        $governor = app(WarmupGovernor::class);
        $this->assertSame(2, $governor->dailyLimitForProfile($profile));

        $this->assertTrue($this->sendEmail($profile, 'one@warmup.test'));
        $this->assertTrue($this->sendEmail($profile, 'two@warmup.test'));
        $this->assertFalse($this->sendEmail($profile, 'three@warmup.test'));

        $this->assertDatabaseCount('message_sends', 2);
        $this->assertSame(2, $governor->sendsTodayForProfile($profile));
    }

    public function test_warmup_daily_limit_increases_on_day_two(): void
    {
        $profile = $this->warmupProfile([
            'warmup_started_at' => now()->subDay()->startOfDay(),
        ]);

        $governor = app(WarmupGovernor::class);
        $this->assertSame(2, $governor->currentWarmupDay($profile));
        $this->assertGreaterThan(2, $governor->dailyLimitForProfile($profile));

        $this->assertTrue($this->sendEmail($profile, 'one@warmup.test'));
        $this->assertTrue($this->sendEmail($profile, 'two@warmup.test'));
        $this->assertTrue($this->sendEmail($profile, 'three@warmup.test'));

        $this->assertDatabaseCount('message_sends', 3);
    }

    public function test_warmup_status_includes_progress_fields(): void
    {
        $profile = $this->warmupProfile();
        $status = app(WarmupGovernor::class)->warmupStatusForProfile($profile);

        $this->assertTrue($status['warmup_enabled']);
        $this->assertSame(2, $status['daily_limit']);
        $this->assertArrayHasKey('progress_pct', $status);
        $this->assertArrayHasKey('remaining_today', $status);
    }
}
