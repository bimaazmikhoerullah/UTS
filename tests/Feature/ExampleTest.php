<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    use RefreshDatabase;

    public function test_returns_a_successful_response()
    {
        // Fokus proyek ini adalah REST API + outbox, bukan UI Inertia (Vite).
        // Test jalur web Inertia dihindari karena butuh build asset yang
        // tidak relevan dengan scope checkpoint.
        $response = $this->get('/api/v1');

        $response->assertOk();
    }
}
