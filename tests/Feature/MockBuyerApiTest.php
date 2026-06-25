<?php

namespace Tests\Feature;

use App\Models\Delivery;
use App\Models\User;
use App\Support\Tenancy\AccountContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MockBuyerApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_all_ten_mock_buyer_tiers_respond(): void
    {
        for ($tier = 1; $tier <= 10; $tier++) {
            $response = $this->postJson("/api/v1/mock/buyers/{$tier}/ping", [
                'floor' => 10,
                'phone1' => '07700900123',
                'zipcode' => 'SW1A 1AA',
            ]);

            $response->assertOk();
        }
    }

    public function test_tier_3_rejects_ping(): void
    {
        $this->postJson('/api/v1/mock/buyers/3/ping', ['floor' => 10])
            ->assertOk()
            ->assertJsonPath('Success', false);
    }

    public function test_tier_6_ping_accept_post_reject(): void
    {
        $ping = $this->postJson('/api/v1/mock/buyers/6/ping', ['floor' => 10])
            ->assertOk()
            ->assertJsonPath('Success', true);

        $pingId = $ping->json('PingID');

        $this->postJson('/api/v1/mock/buyers/6/post', ['PingID' => $pingId])
            ->assertOk()
            ->assertJsonPath('Success', false);
    }

    public function test_tier_1_post_accepts(): void
    {
        $this->postJson('/api/v1/mock/buyers/1/post', ['PingID' => 'test'])
            ->assertOk()
            ->assertJsonPath('Approved', true);
    }

    public function test_mock_buyer_docs_endpoint(): void
    {
        $this->getJson('/api/v1/mock/buyers')
            ->assertOk()
            ->assertJsonStructure(['tiers']);
    }
}
