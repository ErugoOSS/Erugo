<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    use RefreshDatabase;

    /**
     * A basic test example.
     */
    public function test_the_application_returns_a_successful_response(): void
    {
        $this->seed();

        // The PHP test job doesn't build the frontend, so there is no Vite
        // manifest. Render the page without it.
        $this->withoutVite();

        $response = $this->get('/');

        $response->assertStatus(200);
    }
}
