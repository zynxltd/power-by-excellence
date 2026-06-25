<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DemoRequestTest extends TestCase
{
    use RefreshDatabase;

    public function test_demo_request_submission(): void
    {
        $this->withoutVite();

        $response = $this->post(route('demo.request'), [
            'name' => 'Jane Smith',
            'email' => 'jane@company.com',
            'company' => 'Acme Leads',
            'message' => 'Interested in solar vertical',
        ]);

        $response->assertRedirect('/');
        $response->assertSessionHas('demo_success');
    }

    public function test_demo_request_validation(): void
    {
        $this->post(route('demo.request'), [])->assertSessionHasErrors(['name', 'email', 'company']);
    }
}
