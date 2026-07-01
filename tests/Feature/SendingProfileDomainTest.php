<?php

namespace Tests\Feature;

use App\Models\SendingProfile;
use App\Models\User;
use App\Services\Messaging\MessageSendService;
use App\Services\Messaging\MessagingCredentialsResolver;
use App\Services\Messaging\MessagingGateway;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Mockery;
use Tests\TestCase;

class SendingProfileDomainTest extends TestCase
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

        Route::middleware('web')->patch('e-delivery/sending-profiles/{profile}', [\App\Http\Controllers\Admin\EDeliveryController::class, 'updateSendingProfile'])
            ->name('e-delivery.sending-profiles.update');
    }

    public function test_send_uses_profile_sending_domain_for_from_and_reply_to(): void
    {
        $account = $this->admin->resolveAccount();

        $profile = SendingProfile::create([
            'account_id' => $account->id,
            'name' => 'Client mail',
            'provider' => 'log',
            'sending_domain' => 'mail.client.com',
            'from_name' => 'Client Co',
            'from_email' => 'news@legacy.test',
        ]);

        $messaging = Mockery::mock(MessagingGateway::class);
        $messaging->shouldReceive('sendEmail')
            ->once()
            ->withArgs(function (string $to, string $subject, string $body, array $options) {
                return $to === 'reader@example.com'
                    && $options['from'] === 'Client Co <news@mail.client.com>'
                    && $options['reply_to'] === 'news@mail.client.com';
            })
            ->andReturn(true);
        $this->app->instance(MessagingGateway::class, $messaging);

        $sent = app(MessageSendService::class)->send([
            'account_id' => $account->id,
            'sending_profile_id' => $profile->id,
            'channel' => 'email',
            'recipient' => 'reader@example.com',
            'subject' => 'Hello',
            'body' => 'Body',
            'provider' => 'log',
            'track' => false,
        ]);

        $this->assertTrue($sent);
    }

    public function test_send_falls_back_to_profile_from_email_when_sending_domain_null(): void
    {
        $account = $this->admin->resolveAccount();

        $profile = SendingProfile::create([
            'account_id' => $account->id,
            'name' => 'Legacy',
            'provider' => 'log',
            'from_email' => 'news@tenant.test',
        ]);

        $messaging = Mockery::mock(MessagingGateway::class);
        $messaging->shouldReceive('sendEmail')
            ->once()
            ->withArgs(function (string $to, string $subject, string $body, array $options) {
                return $options['from'] === 'news@tenant.test'
                    && $options['reply_to'] === null;
            })
            ->andReturn(true);
        $this->app->instance(MessagingGateway::class, $messaging);

        $sent = app(MessageSendService::class)->send([
            'account_id' => $account->id,
            'sending_profile_id' => $profile->id,
            'channel' => 'email',
            'recipient' => 'reader@example.com',
            'subject' => 'Hello',
            'body' => 'Body',
            'provider' => 'log',
            'track' => false,
        ]);

        $this->assertTrue($sent);
    }

    public function test_patch_persists_sending_domain(): void
    {
        $account = $this->admin->resolveAccount();

        $profile = SendingProfile::create([
            'account_id' => $account->id,
            'name' => 'Patch me',
            'provider' => 'log',
        ]);

        $response = $this->actingAs($this->admin)
            ->patch("/e-delivery/sending-profiles/{$profile->id}", [
                'sending_domain' => 'mail.client.com',
            ]);

        $response->assertRedirect()
            ->assertSessionHasNoErrors()
            ->assertSessionHas('success', 'Sending profile updated.');

        $this->assertDatabaseHas('sending_profiles', [
            'id' => $profile->id,
            'sending_domain' => 'mail.client.com',
        ]);
    }

    public function test_sending_domain_must_be_unique_per_account(): void
    {
        $account = $this->admin->resolveAccount();

        SendingProfile::create([
            'account_id' => $account->id,
            'name' => 'First',
            'provider' => 'log',
            'sending_domain' => 'mail.client.com',
        ]);

        $this->actingAs($this->admin)
            ->post(route('e-delivery.sending-profiles.store'), [
                'name' => 'Second',
                'provider' => 'log',
                'sending_domain' => 'mail.client.com',
            ])
            ->assertSessionHasErrors('sending_domain');
    }

    public function test_dns_hints_helper_returns_spf_and_dkim_strings(): void
    {
        $hints = app(MessagingCredentialsResolver::class)->dnsHintsForSendingDomain('mail.client.com');

        $this->assertArrayHasKey('spf', $hints);
        $this->assertArrayHasKey('dkim', $hints);
        $this->assertStringContainsString('mail.client.com', $hints['spf']);
        $this->assertStringContainsString('mail.client.com', $hints['dkim']);
    }
}
