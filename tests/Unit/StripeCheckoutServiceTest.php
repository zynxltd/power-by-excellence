<?php

namespace Tests\Unit;

use App\Models\Account;
use App\Services\Billing\StripeCheckoutService;
use Tests\TestCase;

class StripeCheckoutServiceTest extends TestCase
{
    public function test_minimum_top_up_defaults_to_one(): void
    {
        $service = app(StripeCheckoutService::class);
        $account = new Account(['settings' => []]);

        $this->assertSame(1.0, $service->minimumTopUp($account));
    }

    public function test_validate_top_up_rejects_below_minimum(): void
    {
        $service = app(StripeCheckoutService::class);
        $account = new Account(['settings' => ['stripe' => ['min_topup' => 25]]]);

        $this->assertSame('Minimum top-up is 25.', $service->validateTopUpAmount($account, 10));
        $this->assertNull($service->validateTopUpAmount($account, 50));
    }

    public function test_buyer_self_serve_requires_enabled_stripe(): void
    {
        $service = app(StripeCheckoutService::class);
        $account = new Account(['settings' => ['stripe' => ['enabled' => false, 'allow_buyer_self_serve' => true]]]);

        $this->assertFalse($service->buyerSelfServeEnabled($account));
    }
}
