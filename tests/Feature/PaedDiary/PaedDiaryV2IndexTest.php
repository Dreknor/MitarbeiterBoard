<?php

namespace Tests\Feature\PaedDiary;

use App\Models\Klasse;
use App\Models\User;
use Tests\TestCase;

/**
 * Tests für die v2-Route des Pädagogischen Tagebuchs.
 * Prüft Route-Erreichbarkeit, View-Rendering und Autorisierung.
 */
class PaedDiaryV2IndexTest extends TestCase
{
    /** @test */
    public function v2_index_ist_mit_permission_erreichbar(): void
    {
        $user = $this->actingAsWithPermission('view paed diary');
        $klasse = Klasse::factory()->create();
        $user->paed_klassen()->attach($klasse->id);

        $response = $this->get('/paed-diary/v2');

        $response->assertOk();
        $response->assertViewIs('paedDiary.v2.index');
        $response->assertViewHas('klassen');
        $response->assertViewHas('klasse');
        $response->assertViewHas('groups');
    }

    /** @test */
    public function v2_index_leitet_um_ohne_klassen(): void
    {
        $this->actingAsWithPermission('view paed diary');

        $response = $this->get('/paed-diary/v2');

        $response->assertRedirect();
    }

    /** @test */
    public function v2_index_erfordert_permission(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $response = $this->get('/paed-diary/v2');

        $response->assertForbidden();
    }

    /** @test */
    public function v2_index_akzeptiert_klasse_parameter(): void
    {
        $user = $this->actingAsWithPermission('view paed diary');
        $klasse1 = Klasse::factory()->create(['name' => 'Klasse A']);
        $klasse2 = Klasse::factory()->create(['name' => 'Klasse B']);
        $user->paed_klassen()->attach([$klasse1->id, $klasse2->id]);

        $response = $this->get('/paed-diary/v2?klasse=' . $klasse2->id);

        $response->assertOk();
        $response->assertViewHas('klasse', function ($klasse) use ($klasse2) {
            return $klasse->id === $klasse2->id;
        });
    }

    /** @test */
    public function v1_index_funktioniert_weiterhin(): void
    {
        $user = $this->actingAsWithPermission('view paed diary');
        $klasse = Klasse::factory()->create();
        $user->paed_klassen()->attach($klasse->id);

        $response = $this->get('/paed-diary');

        $response->assertOk();
        $response->assertViewIs('paedDiary.index');
    }
}

