<?php

namespace Tests\Feature;

use App\Models\Klasse;
use App\Models\LessonTime;
use App\Models\Zeitraster;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class ZeitrasterControllerTest extends TestCase
{
    // ─── Hilfsmethode für Klassen-Update-Payload ─────────────────────────────

    /**
     * Erstellt einen vollständigen Update-Payload für KlasseController::update().
     * Alle Pflichtfelder (name, kuerzel) werden mit vorhandenen Werten belegt.
     */
    private function buildKlassePayload(Klasse $klasse, array $overrides = []): array
    {
        return array_merge([
            'name'    => $klasse->name,
            'kuerzel' => $klasse->kuerzel,
            'color'   => $klasse->color,
        ], $overrides);
    }

    // ─── Index ────────────────────────────────────────────────────────────────

    /** @test */
    public function test_index_requires_permission(): void
    {
        $this->actingAsWithPermission('view rooms');
        $this->get(route('zeitraster.index'))->assertStatus(403);
    }

    /** @test */
    public function test_index_shows_zeitraster_list(): void
    {
        Zeitraster::create(['name' => 'Testschule', 'ist_standard' => false]);
        $this->actingAsWithPermission('manage zeitraster');

        $response = $this->get(route('zeitraster.index'));
        $response->assertOk();
        $response->assertSee('Testschule');
    }

    /** @test */
    public function test_index_shows_standard_badge(): void
    {
        $this->actingAsWithPermission('manage zeitraster');

        $response = $this->get(route('zeitraster.index'));
        $response->assertOk();
        // Migration hat ein 'Standard'-Zeitraster angelegt
        $response->assertSee('Standard');
    }

    // ─── Store ────────────────────────────────────────────────────────────────

    /** @test */
    public function test_store_creates_zeitraster(): void
    {
        $this->actingAsWithPermission('manage zeitraster');

        $this->post(route('zeitraster.store'), [
            'name'    => 'Neue Grundschule',
            'stunden' => [['period' => 1, 'start' => '07:30', 'end' => '08:15', 'week' => null]],
        ])->assertRedirect();

        $this->assertDatabaseHas('zeitraster', ['name' => 'Neue Grundschule']);
        $this->assertDatabaseHas('lesson_times', ['period' => 1, 'start' => '07:30']);
        $this->assertEquals('success', session('type'));
    }

    /** @test */
    public function test_store_fails_on_duplicate_name(): void
    {
        Zeitraster::create(['name' => 'Doppelt', 'ist_standard' => false]);
        $this->actingAsWithPermission('manage zeitraster');

        $this->post(route('zeitraster.store'), ['name' => 'Doppelt'])
             ->assertSessionHasErrors('name');
    }

    /** @test */
    public function test_store_fails_on_empty_name(): void
    {
        $this->actingAsWithPermission('manage zeitraster');

        $this->post(route('zeitraster.store'), ['name' => ''])
             ->assertSessionHasErrors('name');
    }

    /** @test */
    public function test_store_fails_on_invalid_stunden_time_format(): void
    {
        $this->actingAsWithPermission('manage zeitraster');

        $this->post(route('zeitraster.store'), [
            'name'    => 'TestZR',
            'stunden' => [['period' => 1, 'start' => '25:00', 'end' => '08:15', 'week' => null]],
        ])->assertSessionHasErrors('stunden.0.start');
    }

    /** @test */
    public function test_store_fails_when_end_before_start(): void
    {
        $this->actingAsWithPermission('manage zeitraster');

        $this->post(route('zeitraster.store'), [
            'name'    => 'TestZR',
            'stunden' => [['period' => 1, 'start' => '09:00', 'end' => '08:00', 'week' => null]],
        ])->assertSessionHasErrors('stunden.0.end');
    }

    /** @test */
    public function test_store_with_ist_standard_sets_as_default(): void
    {
        Zeitraster::query()->update(['ist_standard' => false]);
        Cache::forget('zeitraster_standard');
        $this->actingAsWithPermission('manage zeitraster');

        $this->post(route('zeitraster.store'), [
            'name'         => 'Neuer Standard',
            'ist_standard' => '1',
        ])->assertRedirect();

        $this->assertDatabaseHas('zeitraster', ['name' => 'Neuer Standard', 'ist_standard' => true]);
    }

    // ─── Update ───────────────────────────────────────────────────────────────

    /** @test */
    public function test_update_sets_default_and_resets_others(): void
    {
        $a = Zeitraster::create(['name' => 'A', 'ist_standard' => true]);
        $b = Zeitraster::create(['name' => 'B', 'ist_standard' => false]);
        $this->actingAsWithPermission('manage zeitraster');

        $this->put(route('zeitraster.update', $b), ['name' => 'B', 'ist_standard' => true])
             ->assertRedirect();

        $this->assertFalse($a->fresh()->ist_standard);
        $this->assertTrue($b->fresh()->ist_standard);
    }

    /** @test */
    public function test_update_own_name_does_not_fail_unique(): void
    {
        $zr = Zeitraster::create(['name' => 'GleicherName', 'ist_standard' => false]);
        $this->actingAsWithPermission('manage zeitraster');

        $this->put(route('zeitraster.update', $zr), ['name' => 'GleicherName'])
             ->assertRedirect();
    }

    /** @test */
    public function test_update_replaces_lesson_times(): void
    {
        $zr = Zeitraster::create(['name' => 'OS', 'ist_standard' => false]);
        LessonTime::create(['zeitraster_id' => $zr->id, 'period' => 1, 'start' => '08:00', 'end' => '08:45', 'week' => null]);
        $this->actingAsWithPermission('manage zeitraster');

        $this->put(route('zeitraster.update', $zr), [
            'name'    => 'OS',
            'stunden' => [
                ['period' => 1, 'start' => '07:30', 'end' => '08:15', 'week' => null],
                ['period' => 2, 'start' => '08:25', 'end' => '09:10', 'week' => null],
            ],
        ])->assertRedirect();

        $this->assertDatabaseMissing('lesson_times', ['zeitraster_id' => $zr->id, 'start' => '08:00']);
        $this->assertDatabaseHas('lesson_times', ['zeitraster_id' => $zr->id, 'start' => '07:30']);
        $this->assertEquals(2, LessonTime::where('zeitraster_id', $zr->id)->count());
    }

    // ─── Destroy ──────────────────────────────────────────────────────────────

    /** @test */
    public function test_destroy_blocked_when_klasse_assigned(): void
    {
        $zr = Zeitraster::create(['name' => 'Belegt', 'ist_standard' => false]);
        Klasse::factory()->create(['zeitraster_id' => $zr->id]);
        $this->actingAsWithPermission('manage zeitraster');

        $this->delete(route('zeitraster.destroy', $zr))->assertRedirect();

        $this->assertDatabaseHas('zeitraster', ['id' => $zr->id]);
        $this->assertEquals('warning', session('type'));
    }

    /** @test */
    public function test_destroy_blocked_when_standard(): void
    {
        $zr = Zeitraster::create(['name' => 'EigenesStd', 'ist_standard' => true]);
        $this->actingAsWithPermission('manage zeitraster');

        $this->delete(route('zeitraster.destroy', $zr))->assertRedirect();

        $this->assertDatabaseHas('zeitraster', ['id' => $zr->id]);
        $this->assertEquals('warning', session('type'));
    }

    /** @test */
    public function test_destroy_succeeds_when_free(): void
    {
        $zr = Zeitraster::create(['name' => 'Löschbar', 'ist_standard' => false]);
        $this->actingAsWithPermission('manage zeitraster');

        $this->delete(route('zeitraster.destroy', $zr))->assertRedirect();

        $this->assertDatabaseMissing('zeitraster', ['id' => $zr->id]);
        $this->assertEquals('success', session('type'));
    }

    /** @test */
    public function test_destroy_also_removes_lesson_times(): void
    {
        $zr = Zeitraster::create(['name' => 'MitStunden', 'ist_standard' => false]);
        LessonTime::create(['zeitraster_id' => $zr->id, 'period' => 1, 'start' => '07:30', 'end' => '08:15', 'week' => null]);
        $this->actingAsWithPermission('manage zeitraster');

        $this->delete(route('zeitraster.destroy', $zr))->assertRedirect();

        $this->assertDatabaseMissing('lesson_times', ['zeitraster_id' => $zr->id]);
    }

    // ─── markStandard ─────────────────────────────────────────────────────────

    /** @test */
    public function test_mark_standard_sets_zeitraster_as_default(): void
    {
        $zr = Zeitraster::create(['name' => 'Neuer Standard', 'ist_standard' => false]);
        $this->actingAsWithPermission('manage zeitraster');

        $this->post(route('zeitraster.markStandard', $zr))->assertRedirect();

        $this->assertTrue($zr->fresh()->ist_standard);
        $this->assertEquals('success', session('type'));
    }

    // ─── Routen-Auflösung ────────────────────────────────────────────────────

    /** @test */
    public function test_route_zeitraster_index_resolves(): void
    {
        $this->assertEquals('/zeitraster', route('zeitraster.index', [], false));
    }

    /** @test */
    public function test_route_zeitraster_mark_standard_resolves(): void
    {
        $this->assertEquals('/zeitraster/1/standard', route('zeitraster.markStandard', 1, false));
    }

    // ─── KlasseController: zeitraster_id setzen/löschen ─────────────────────

    /** @test */
    public function test_klasse_update_sets_zeitraster_id(): void
    {
        $zr     = Zeitraster::create(['name' => 'OS', 'ist_standard' => false]);
        $klasse = Klasse::factory()->create(['zeitraster_id' => null]);
        $this->actingAsWithPermission('manage zeitraster', 'edit klassen');

        $this->put(route('klassen.update', $klasse),
                   $this->buildKlassePayload($klasse, ['zeitraster_id' => $zr->id]))
             ->assertRedirect();

        $this->assertEquals($zr->id, $klasse->fresh()->zeitraster_id);
    }

    /** @test */
    public function test_klasse_update_clears_zeitraster_id(): void
    {
        $zr     = Zeitraster::create(['name' => 'OS', 'ist_standard' => false]);
        $klasse = Klasse::factory()->create(['zeitraster_id' => $zr->id]);
        $this->actingAsWithPermission('manage zeitraster', 'edit klassen');

        $this->put(route('klassen.update', $klasse),
                   $this->buildKlassePayload($klasse, ['zeitraster_id' => '']))
             ->assertRedirect();

        $this->assertNull($klasse->fresh()->zeitraster_id);
    }

    /** @test */
    public function test_klasse_update_rejects_invalid_zeitraster_id(): void
    {
        $klasse = Klasse::factory()->create(['zeitraster_id' => null]);
        $this->actingAsWithPermission('manage zeitraster', 'edit klassen');

        $this->put(route('klassen.update', $klasse),
                   $this->buildKlassePayload($klasse, ['zeitraster_id' => 9999]))
             ->assertRedirect()
             ->assertSessionHasErrors('zeitraster_id');
    }

    /** @test */
    public function test_klassen_edit_view_receives_zeitraster_variable(): void
    {
        Zeitraster::create(['name' => 'Hort', 'ist_standard' => false]);
        $klasse = Klasse::factory()->create();
        $this->actingAsWithPermission('edit klassen');

        $response = $this->get(route('klassen.edit', $klasse));
        $response->assertOk();
        $response->assertSee('Hort');
    }
}

