<?php

namespace Tests\Unit;

use App\Services\Delivery\TagInterpolator;
use PHPUnit\Framework\TestCase;

class TagInterpolatorTest extends TestCase
{
    public function test_interpolates_square_bracket_tags(): void
    {
        $interpolator = new TagInterpolator;

        $result = $interpolator->interpolate('Hi [firstname] [lastname]', [
            'firstname' => 'Alex',
            'lastname' => 'Morgan',
        ]);

        $this->assertSame('Hi Alex Morgan', $result);
    }

    public function test_interpolates_curly_merge_tags(): void
    {
        $interpolator = new TagInterpolator;

        $result = $interpolator->interpolate('Hi {{firstname}}, email {{email}}', [
            'firstname' => 'Alex',
            'email' => 'alex@example.com',
        ]);

        $this->assertSame('Hi Alex, email alex@example.com', $result);
    }
}
