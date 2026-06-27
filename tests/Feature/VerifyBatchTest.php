<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\VerifyBatch;
use App\Services\Leads\VerifyBatchService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class VerifyBatchTest extends TestCase
{
    use RefreshDatabase;

    protected User $ukAdmin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\PlatformSeeder::class);
        $this->withoutVite();
        $this->ukAdmin = User::where('email', 'uk@powerbyexcellence.test')->first();
    }

    protected function ukHost()
    {
        return $this->withServerVariables(['HTTP_HOST' => 'excellence-uk.powerbyexcellence.test']);
    }

    public function test_verify_batch_service_flags_invalid_email(): void
    {
        $service = app(VerifyBatchService::class);

        $issues = $service->validateRow(['email' => 'not-an-email', 'phone1' => '07700900123']);

        $this->assertNotEmpty($issues);
    }

    public function test_verify_batch_process_marks_valid_rows(): void
    {
        $service = app(VerifyBatchService::class);

        $batch = VerifyBatch::create([
            'account_id' => $this->ukAdmin->account_id,
            'user_id' => $this->ukAdmin->id,
            'filename' => 'test.csv',
            'status' => 'pending',
            'total_rows' => 2,
            'results' => [
                ['email' => 'valid@example.com'],
                ['email' => 'bad-email'],
            ],
        ]);

        $result = $service->process($batch);

        $this->assertSame(1, $result['valid']);
        $this->assertSame(1, $result['invalid']);
    }
}
