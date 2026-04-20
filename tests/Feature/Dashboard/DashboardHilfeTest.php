<?php

namespace Tests\Feature\Dashboard;

use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class DashboardHilfeTest extends TestCase
{
    /**
     * Hilfe-Seite rendert erfolgreich (200).
     */
    public function test_hilfe_page_renders_successfully(): void
    {
        $this->actingAsWithPermission();

        $response = $this->get('/dashboard/hilfe');
        $response->assertStatus(200);
        $response->assertSee('Hilfe');
        $response->assertSee('Dashboard');
    }

    /**
     * Nicht eingeloggte User werden zur Login-Seite weitergeleitet (302).
     */
    public function test_hilfe_page_requires_authentication(): void
    {
        $response = $this->get('/dashboard/hilfe');
        $response->assertStatus(302);
        $response->assertRedirect('/login');
    }

    /**
     * Hilfe-HTML wird gecacht.
     */
    public function test_hilfe_caches_rendered_html(): void
    {
        $this->actingAsWithPermission();

        // Cache leeren
        Cache::forget('dashboard.hilfe.html');

        // Ersten Aufruf machen (füllt Cache)
        $this->get('/dashboard/hilfe')->assertOk();

        // Cache sollte jetzt gesetzt sein
        $this->assertTrue(Cache::has('dashboard.hilfe.html'));
    }
}

