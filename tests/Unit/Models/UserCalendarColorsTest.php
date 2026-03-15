<?php

namespace Tests\Unit\Models;

use App\Models\OxCalendar;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Unit-Tests für die User-Kalenderfarben-Relation (TODO 29).
 */
class UserCalendarColorsTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_hat_calendarColors_relation(): void
    {
        $user     = User::factory()->create();
        $calendar = OxCalendar::factory()->create();

        $user->calendarColors()->attach($calendar->id, ['farbe' => '#ff5733']);

        $this->assertCount(1, $user->calendarColors);
        $this->assertEquals('#ff5733', $user->calendarColors->first()->pivot->farbe);
    }

    public function test_calendar_hat_userColors_relation(): void
    {
        $user     = User::factory()->create();
        $calendar = OxCalendar::factory()->create();

        $calendar->userColors()->attach($user->id, ['farbe' => '#33ff57']);

        $this->assertCount(1, $calendar->userColors);
        $this->assertEquals('#33ff57', $calendar->userColors->first()->pivot->farbe);
    }

    public function test_sync_ueberschreibt_bestehende_eintraege(): void
    {
        $user     = User::factory()->create();
        $calendar = OxCalendar::factory()->create();

        $user->calendarColors()->attach($calendar->id, ['farbe' => '#ff0000']);

        $user->calendarColors()->sync([$calendar->id => ['farbe' => '#00ff00']]);

        $this->assertCount(1, $user->calendarColors()->get());
        $this->assertEquals('#00ff00', $user->calendarColors()->first()->pivot->farbe);
    }

    public function test_cascade_delete_bei_user_loeschung(): void
    {
        $user     = User::factory()->create();
        $calendar = OxCalendar::factory()->create();

        $user->calendarColors()->attach($calendar->id, ['farbe' => '#ff0000']);
        $user->forceDelete();

        $this->assertDatabaseMissing('user_calendar_colors', [
            'ox_calendar_id' => $calendar->id,
        ]);
    }

    public function test_cascade_delete_bei_kalender_force_delete(): void
    {
        $user     = User::factory()->create();
        $calendar = OxCalendar::factory()->create();

        $user->calendarColors()->attach($calendar->id, ['farbe' => '#ff0000']);

        // SoftDelete löst keinen Cascade aus
        $calendar->delete();
        $this->assertDatabaseHas('user_calendar_colors', [
            'user_id'        => $user->id,
            'ox_calendar_id' => $calendar->id,
        ]);

        // ForceDelete löst Cascade aus
        $calendar->forceDelete();
        $this->assertDatabaseMissing('user_calendar_colors', [
            'user_id' => $user->id,
        ]);
    }

    public function test_mehrere_user_koennen_verschiedene_farben_fuer_gleichen_kalender_haben(): void
    {
        $user1    = User::factory()->create();
        $user2    = User::factory()->create();
        $calendar = OxCalendar::factory()->create();

        $user1->calendarColors()->attach($calendar->id, ['farbe' => '#ff0000']);
        $user2->calendarColors()->attach($calendar->id, ['farbe' => '#0000ff']);

        $this->assertEquals('#ff0000', $user1->calendarColors()->first()->pivot->farbe);
        $this->assertEquals('#0000ff', $user2->calendarColors()->first()->pivot->farbe);
    }
}

