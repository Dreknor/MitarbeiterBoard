<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class ExampleTest extends TestCase
{
    /**
     * A basic test example.
     *
     * @return void
     */
    public function testBasicTest(): void
    {
        $response = $this->get('/');

        // Die Startseite leitet nicht authentifizierte Nutzer zum Login weiter
        $response->assertStatus(302);
        $response->assertRedirect('/login');
    }
}
