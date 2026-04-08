<?php

namespace Tests\Feature\Personal;

use App\Models\personal\EmployeeQualification;
use App\Models\personal\PersonalDocument;
use App\Models\personal\DocumentType;
use App\Models\personal\QualificationType;
use App\Models\User;
use Tests\TestCase;

class SelfServiceTest extends TestCase
{
    // ── Zugriff & IDOR ──────────────────────────────────────

    /** @test */
    public function unauthenticated_user_is_redirected_to_login(): void
    {
        $this->get(route('self-service.index'))->assertRedirect('/login');
    }

    /** @test */
    public function authenticated_user_can_access_own_profile(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $this->get(route('self-service.index'))->assertStatus(200);
    }

    /** @test */
    public function self_service_has_no_id_parameter_idor_safe(): void
    {
        $user  = User::factory()->create();
        $other = User::factory()->create();
        $this->actingAs($user);

        $response = $this->get(route('self-service.index'));
        $response->assertStatus(200);

        // Die Route nimmt keinen ID-Parameter entgegen – nur eigene Daten
        $response->assertViewHas('rawEmploye', fn($employe) => $employe->id === $user->id);
    }

    // ── Tabs / Unter-Routen ─────────────────────────────────

    /** @test */
    public function vertraege_tab_accessible(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $this->get(route('self-service.vertraege'))->assertStatus(200);
    }

    /** @test */
    public function dokumente_tab_returns_own_documents(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $ownDoc = PersonalDocument::factory()->create(['employe_id' => $user->id]);
        $otherDoc = PersonalDocument::factory()->create(); // anderer Benutzer

        $response = $this->get(route('self-service.dokumente'));
        $response->assertStatus(200);
        $response->assertViewHas('documents', function ($docs) use ($ownDoc, $otherDoc) {
            return $docs->contains('id', $ownDoc->id)
                && ! $docs->contains('id', $otherDoc->id);
        });
    }

    /** @test */
    public function qualifikationen_tab_returns_own_qualifications(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $ownQual = EmployeeQualification::factory()->create(['employe_id' => $user->id]);
        $otherQual = EmployeeQualification::factory()->create(); // anderer Benutzer

        $response = $this->get(route('self-service.qualifikationen'));
        $response->assertStatus(200);
        $response->assertViewHas('qualifikationen', function ($quals) use ($ownQual, $otherQual) {
            return $quals->contains('id', $ownQual->id)
                && ! $quals->contains('id', $otherQual->id);
        });
    }

    /** @test */
    public function einwilligungen_tab_accessible(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $this->get(route('self-service.einwilligungen'))->assertStatus(200);
    }

    /** @test */
    public function gespraeche_tab_accessible(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $this->get(route('self-service.gespraeche'))->assertStatus(200);
    }

    // ── Rate-Limiting ───────────────────────────────────────

    /** @test */
    public function self_service_routes_have_throttle_middleware(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        // Prüfe dass Rate-Limiting aktiv ist (30 pro Minute)
        $route = app('router')->getRoutes()->getByName('self-service.index');
        $this->assertNotNull($route);
        $this->assertTrue(
            collect($route->middleware())->contains(fn($m) => str_contains($m, 'throttle')),
            'Self-Service Routen müssen throttle Middleware haben.'
        );
    }

    // ── Stundenzettel (password.confirm) ────────────────────

    /** @test */
    public function stundenzettel_requires_password_confirmation(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $route = app('router')->getRoutes()->getByName('self-service.stundenzettel');
        $this->assertNotNull($route);
        $this->assertTrue(
            collect($route->middleware())->contains('password.confirm'),
            'Stundenzettel-Route muss password.confirm Middleware haben.'
        );
    }
}

