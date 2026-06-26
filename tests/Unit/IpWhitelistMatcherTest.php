<?php

namespace Tests\Unit;

use App\Services\Validation\IpWhitelistMatcher;
use App\Services\Validation\ValidationContext;
use PHPUnit\Framework\TestCase;

class IpWhitelistMatcherTest extends TestCase
{
    public function test_exact_ip_match(): void
    {
        $this->assertTrue(IpWhitelistMatcher::isWhitelisted('203.0.113.10', '203.0.113.10'));
    }

    public function test_cidr_range_match(): void
    {
        $rules = '198.51.100.0/24';

        $this->assertTrue(IpWhitelistMatcher::isWhitelisted('198.51.100.10', $rules));
        $this->assertFalse(IpWhitelistMatcher::isWhitelisted('198.51.101.10', $rules));
    }

    public function test_whitespace_and_comma_separated_rules(): void
    {
        $rules = "203.0.113.10, 198.51.100.0/24\n10.0.0.5";

        $this->assertTrue(IpWhitelistMatcher::isWhitelisted('10.0.0.5', $rules));
        $this->assertTrue(IpWhitelistMatcher::isWhitelisted('198.51.100.255', $rules));
    }

    public function test_blank_rules_never_match(): void
    {
        $this->assertFalse(IpWhitelistMatcher::isWhitelisted('127.0.0.1', null));
        $this->assertFalse(IpWhitelistMatcher::isWhitelisted('127.0.0.1', ''));
        $this->assertFalse(IpWhitelistMatcher::isWhitelisted('127.0.0.1', "  \n  "));
    }

    public function test_ipv6_and_invalid_cidr_do_not_match(): void
    {
        $this->assertFalse(IpWhitelistMatcher::isWhitelisted('::1', '::1/128'));
        $this->assertFalse(IpWhitelistMatcher::isWhitelisted('8.8.8.8', 'not-a-cidr/24'));
        $this->assertFalse(IpWhitelistMatcher::isWhitelisted('8.8.8.8', '256.0.0.0/24'));
        $this->assertFalse(IpWhitelistMatcher::isWhitelisted('8.8.8.8', '10.0.0.0/99'));
    }

    public function test_whitelist_from_context_prefers_context_over_config(): void
    {
        $context = new ValidationContext(ipWhitelist: '203.0.113.1');

        $this->assertSame('203.0.113.1', IpWhitelistMatcher::whitelistFromContext($context, ['ip_whitelist' => '10.0.0.1']));
        $this->assertSame('10.0.0.1', IpWhitelistMatcher::whitelistFromContext(null, ['ip_whitelist' => '10.0.0.1']));
        $this->assertNull(IpWhitelistMatcher::whitelistFromContext(null, []));
    }
}
