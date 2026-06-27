<?php

namespace Tests\Unit;

use App\Services\Leads\FieldHashService;
use PHPUnit\Framework\TestCase;

class FieldHashServiceTest extends TestCase
{
    private FieldHashService $hasher;

    protected function setUp(): void
    {
        parent::setUp();
        $this->hasher = new FieldHashService;
    }

    public function test_email_normalisation_is_case_insensitive(): void
    {
        $this->assertSame(
            $this->hasher->hash('email', 'User@Example.COM'),
            $this->hasher->hash('email', 'user@example.com')
        );
    }

    public function test_phone_normalisation_strips_formatting(): void
    {
        $this->assertSame(
            $this->hasher->hash('phone1', '+44 7700 900123'),
            $this->hasher->hash('phone1', '07700900123')
        );
    }

    public function test_pre_hashed_values_are_stored_without_rehashing(): void
    {
        $digest = hash('sha256', 'user@example.com');

        $this->assertSame($digest, $this->hasher->resolveHash('email', $digest));
    }

    public function test_invalid_pre_hash_is_treated_as_raw_value(): void
    {
        $this->assertSame(
            hash('sha256', 'not-a-hash'),
            $this->hasher->resolveHash('email', 'not-a-hash')
        );
    }
}
