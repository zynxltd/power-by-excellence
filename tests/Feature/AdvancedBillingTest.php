<?php

namespace Tests\Feature;

use App\Models\Buyer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdvancedBillingTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\PlatformSeeder::class);
        $this->admin = User::where('email', 'uk@powerbyexcellence.test')->first();
    }

    public function test_goodwill_credit_with_bypass_flags(): void
    {
        $buyer = Buyer::where('reference', 'buyer-primary')->first();
        $before = (float) $buyer->credit_balance;

        $this->actingAs($this->admin)
            ->post(route('billing.top-up', $buyer), [
                'amount' => 25,
                'type' => 'goodwill',
                'description' => 'Service recovery',
                'bypass_account_lock' => true,
                'suppress_alerts' => true,
            ])
            ->assertRedirect();

        $buyer->refresh();
        $this->assertEquals($before + 25, (float) $buyer->credit_balance);

        $this->assertDatabaseHas('buyer_transactions', [
            'buyer_id' => $buyer->id,
            'type' => 'goodwill',
            'description' => 'Service recovery',
        ]);
    }

    public function test_manual_debit_reduces_balance(): void
    {
        $buyer = Buyer::where('reference', 'buyer-primary')->first();
        $before = (float) $buyer->credit_balance;

        $this->actingAs($this->admin)
            ->post(route('billing.top-up', $buyer), [
                'amount' => 5,
                'type' => 'manual_debit',
                'description' => 'Correction',
            ])
            ->assertRedirect();

        $buyer->refresh();
        $this->assertEquals($before - 5, (float) $buyer->credit_balance);
    }
}
