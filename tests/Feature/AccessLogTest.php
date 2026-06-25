<?php

namespace Tests\Feature;

use App\Models\AccessLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AccessLogTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_creates_access_log(): void
    {
        $this->seed(\Database\Seeders\PlatformSeeder::class);

        $admin = User::where('email', 'uk@powerbyexcellence.test')->first();

        $this->withServerVariables(['HTTP_HOST' => 'excellence-uk.powerbyexcellence.test'])
            ->post('http://excellence-uk.powerbyexcellence.test/login', [
                'email' => $admin->email,
                'password' => 'password',
            ])->assertRedirect('/dashboard');

        $this->assertDatabaseHas('access_logs', [
            'user_id' => $admin->id,
            'action' => 'login',
        ]);
    }

    public function test_access_logs_page_loads(): void
    {
        $this->seed(\Database\Seeders\PlatformSeeder::class);
        $this->withoutVite();

        $admin = User::where('email', 'uk@powerbyexcellence.test')->first();

        AccessLog::create([
            'account_id' => $admin->account_id,
            'user_id' => $admin->id,
            'action' => 'login',
            'ip_address' => '127.0.0.1',
            'path' => 'login',
        ]);

        $this->withServerVariables(['HTTP_HOST' => 'excellence-uk.powerbyexcellence.test'])
            ->actingAs($admin)->get('/logs/access')->assertOk();
        $this->withServerVariables(['HTTP_HOST' => 'excellence-uk.powerbyexcellence.test'])
            ->actingAs($admin)->get('/logs/changes')->assertOk();
    }

    public function test_delivery_show_page_loads(): void
    {
        $this->seed(\Database\Seeders\PlatformSeeder::class);
        $this->withoutVite();

        $admin = User::where('email', 'uk@powerbyexcellence.test')->first();
        $delivery = \App\Models\Delivery::first();

        $this->withServerVariables(['HTTP_HOST' => 'excellence-uk.powerbyexcellence.test'])
            ->actingAs($admin)->get("/deliveries/{$delivery->id}")->assertOk();
    }
}
