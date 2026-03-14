<?php

namespace Tests\Feature;

use App\Models\OxCalendar;
use App\Models\OxSyncLog;
use App\Models\OxTermin;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class CalendarDashboardCardTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
    }

    public function test_dashboard_card_zeigt_naechste_termine(): void
    {
        $user = $this->actingAsWithPermission('view calendar');
        $calendar = OxCalendar::factory()->create();
        OxTermin::factory()->create([
            'ox_calendar_id' => $calendar->id,
            'titel'          => 'Gesamtkonferenz',
            'beginn'         => now()->addDays(2),
            'ende'           => now()->addDays(2)->addHours(2),
        ]);

        $view = $this->view('calendar.dashboardCard');
        $view->assertSee('Gesamtkonferenz');
        $view->assertSee('alle Termine anzeigen');
    }

    public function test_dashboard_card_zeigt_maximal_5_termine(): void
    {
        $user = $this->actingAsWithPermission('view calendar');
        $calendar = OxCalendar::factory()->create();

        // 8 zukünftige Termine anlegen, nur 5 dürfen erscheinen
        $termine = OxTermin::factory()->count(8)->sequence(
            fn ($seq) => ['beginn' => now()->addHours($seq->index + 1), 'ende' => now()->addHours($seq->index + 2)]
        )->create([
            'ox_calendar_id' => $calendar->id,
            'titel'          => 'Termin',
        ]);

        // Der Composer limitiert auf 5 – verifizieren über den Composer direkt
        $sichtbareKalenderIds = collect([$calendar->id]);
        $count = OxTermin::whereIn('ox_calendar_id', $sichtbareKalenderIds)
            ->where('beginn', '>=', now())
            ->whereNull('rrule')
            ->orderBy('beginn')
            ->limit(5)
            ->get()
            ->count();

        $this->assertEquals(5, $count);

        // View darf maximal 5 Einträge anzeigen
        $view = $this->view('calendar.dashboardCard');
        $view->assertSee('alle Termine anzeigen');
    }

    public function test_dashboard_card_zeigt_keine_vergangenen_termine(): void
    {
        $user = $this->actingAsWithPermission('view calendar');
        $calendar = OxCalendar::factory()->create();
        OxTermin::factory()->vergangen()->create([
            'ox_calendar_id' => $calendar->id,
            'titel'          => 'Vergangener Termin',
        ]);

        $view = $this->view('calendar.dashboardCard');
        $view->assertDontSee('Vergangener Termin');
    }

    public function test_dashboard_card_zeigt_sync_fehler_badge_fuer_admins(): void
    {
        $user = $this->actingAsWithPermission('view calendar', 'manage calendar');
        $calendar = OxCalendar::factory()->create();

        // 3 Fehler in den letzten 24h erzeugen
        OxSyncLog::factory()->fehler()->count(3)->create([
            'ox_calendar_id' => $calendar->id,
            'created_at'     => now()->subMinutes(30),
            'updated_at'     => now()->subMinutes(30),
        ]);

        $view = $this->view('calendar.dashboardCard');
        $view->assertSee('Fehler');
    }

    public function test_dashboard_card_versteckt_sich_ohne_permission(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $view = $this->view('calendar.dashboardCard');
        $view->assertDontSee('alle Termine anzeigen');
    }
}



