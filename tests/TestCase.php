<?php

namespace Tests;

use App\Support\Tenancy\AccountContext;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function tearDown(): void
    {
        AccountContext::clear();
        parent::tearDown();
    }
}
