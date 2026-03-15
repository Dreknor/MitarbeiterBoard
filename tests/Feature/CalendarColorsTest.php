<?php

namespace Tests\Feature;

use App\Models\OxCalendar;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

/**
 * Feature-Tests für TODO 29: Kalenderfarben-Persistenz (Hybrid DB/localStorage).
 */
class CalendarColorsTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
    }

    // ── GET /calendar/farben ──────────────────────────────────────────────

    public function test_get_colors_liefert_user_farben(): void
    {
        $user = $this->createUserWithPermission('view calendar');
        $cal1 = OxCalendar::factory()->create();
        $cal2 = OxCalendar::factory()->create();

        $user->calendarColors()->attach($cal1->id, ['farbe' => '#ff0000']);
        $user->calendarColors()->attach($cal2->id, ['farbe' => '#00ff00']);

        $response = $this->actingAs($user)
            ->getJson(route('calendar.colors.index'))
            ->assertOk();

        $data = $response->json();
        $this->assertEquals('#ff0000', $data[$cal1->id]);
        $this->assertEquals('#00ff00', $data[$cal2->id]);
    }

    public function test_get_colors_liefert_nur_eigene_farben(): void
    {
        $user  = $this->createUserWithPermission('view calendar');
        $other = $this->createUserWithPermission('view calendar');
        $cal   = OxCalendar::factory()->create();

        $other->calendarColors()->attach($cal->id, ['farbe' => '#ff0000']);

        $this->actingAs($user)
            ->getJson(route('calendar.colors.index'))
            ->assertOk()
            ->assertExactJson([]);
    }

    public function test_get_colors_erfordert_view_calendar_permission(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->getJson(route('calendar.colors.index'))
            ->assertForbidden();
    }

    // ── PUT /calendar/farben ──────────────────────────────────────────────

    public function test_save_colors_speichert_batch(): void
    {
        $user = $this->createUserWithPermission('view calendar');
        $cal1 = OxCalendar::factory()->create();
        $cal2 = OxCalendar::factory()->create();

        $this->actingAs($user)
            ->putJson(route('calendar.colors.save'), [
                'farben' => [
                    $cal1->id => '#ff0000',
                    $cal2->id => '#00ff00',
                ],
            ])
            ->assertOk()
            ->assertJson(['success' => true, 'count' => 2]);

        $this->assertDatabaseHas('user_calendar_colors', [
            'user_id'        => $user->id,
            'ox_calendar_id' => $cal1->id,
            'farbe'          => '#ff0000',
        ]);
        $this->assertDatabaseHas('user_calendar_colors', [
            'user_id'        => $user->id,
            'ox_calendar_id' => $cal2->id,
            'farbe'          => '#00ff00',
        ]);
    }

    public function test_save_colors_ueberschreibt_bestehende(): void
    {
        $user = $this->createUserWithPermission('view calendar');
        $cal  = OxCalendar::factory()->create();

        $user->calendarColors()->attach($cal->id, ['farbe' => '#ff0000']);

        $this->actingAs($user)
            ->putJson(route('calendar.colors.save'), [
                'farben' => [$cal->id => '#0000ff'],
            ])
            ->assertOk();

        $this->assertEquals('#0000ff', $user->calendarColors()->first()->pivot->farbe);
    }

    public function test_save_colors_entfernt_nicht_mehr_gesendete_farben(): void
    {
        $user = $this->createUserWithPermission('view calendar');
        $cal1 = OxCalendar::factory()->create();
        $cal2 = OxCalendar::factory()->create();

        $user->calendarColors()->attach($cal1->id, ['farbe' => '#ff0000']);
        $user->calendarColors()->attach($cal2->id, ['farbe' => '#00ff00']);

        // Nur cal1 senden → cal2 wird durch sync() entfernt
        $this->actingAs($user)
            ->putJson(route('calendar.colors.save'), [
                'farben' => [$cal1->id => '#ff0000'],
            ])
            ->assertOk()
            ->assertJson(['count' => 1]);

        $this->assertDatabaseHas('user_calendar_colors', [
            'user_id' => $user->id, 'ox_calendar_id' => $cal1->id,
        ]);
        $this->assertDatabaseMissing('user_calendar_colors', [
            'user_id' => $user->id, 'ox_calendar_id' => $cal2->id,
        ]);
    }

    public function test_save_colors_ignoriert_ungueltige_kalender_ids(): void
    {
        $user = $this->createUserWithPermission('view calendar');

        $this->actingAs($user)
            ->putJson(route('calendar.colors.save'), [
                'farben' => [99999 => '#ff0000'],
            ])
            ->assertOk()
            ->assertJson(['count' => 0]);
    }

    public function test_save_colors_validiert_hex_format(): void
    {
        $user = $this->createUserWithPermission('view calendar');
        $cal  = OxCalendar::factory()->create();

        $this->actingAs($user)
            ->putJson(route('calendar.colors.save'), [
                'farben' => [$cal->id => 'nicht-hex'],
            ])
            ->assertUnprocessable();
    }

    public function test_save_colors_erfordert_permission(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->putJson(route('calendar.colors.save'), ['farben' => []])
            ->assertForbidden();
    }

    // ── DELETE /calendar/farben/{id} ──────────────────────────────────────

    public function test_reset_color_entfernt_einzelne_farbe(): void
    {
        $user = $this->createUserWithPermission('view calendar');
        $cal1 = OxCalendar::factory()->create();
        $cal2 = OxCalendar::factory()->create();

        $user->calendarColors()->attach($cal1->id, ['farbe' => '#ff0000']);
        $user->calendarColors()->attach($cal2->id, ['farbe' => '#00ff00']);

        $this->actingAs($user)
            ->deleteJson(route('calendar.colors.reset', $cal1))
            ->assertOk()
            ->assertJson(['success' => true]);

        $this->assertDatabaseMissing('user_calendar_colors', [
            'user_id'        => $user->id,
            'ox_calendar_id' => $cal1->id,
        ]);
        $this->assertDatabaseHas('user_calendar_colors', [
            'user_id'        => $user->id,
            'ox_calendar_id' => $cal2->id,
        ]);
    }

    public function test_reset_color_erfordert_permission(): void
    {
        $user = User::factory()->create();
        $cal  = OxCalendar::factory()->create();

        $this->actingAs($user)
            ->deleteJson(route('calendar.colors.reset', $cal))
            ->assertForbidden();
    }

    // ── index-View ────────────────────────────────────────────────────────

    public function test_index_liefert_user_colors_als_data_attribut(): void
    {
        $user = $this->actingAsWithPermission('view calendar');
        $cal  = OxCalendar::factory()->create(['sichtbar' => true]);

        $user->calendarColors()->attach($cal->id, ['farbe' => '#abc123']);

        $this->get(route('calendar.index'))
            ->assertOk()
            ->assertSee('#abc123');
    }
}

