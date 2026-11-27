<?php

namespace Tests\Feature\Rooms;

use App\Models\LessonTime;
use App\Models\Room;
use App\Models\RoomBooking;
use App\Models\Zeitraster;
use App\Services\Rooms\RaumSucheService;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class FreieRaeumeSucheTest extends TestCase
{
    /** Montag einer B-Woche */
    private Carbon $montag;

    protected function setUp(): void
    {
        parent::setUp();
        $this->montag = Carbon::parse('2026-11-30');
        DB::table('vertretungsplan_weeks')->insert([
            'week' => $this->montag->toDateString(), 'type' => 'B',
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    private function suche(string $von, string $bis, ?Carbon $datum = null): array
    {
        return app(RaumSucheService::class)->suche($datum ?? $this->montag, $von, $bis);
    }

    private function freieIds(array $ergebnis): array
    {
        return $ergebnis['frei']->map(fn ($e) => $e['room']->id)->all();
    }

    /** @test */
    public function einzeltermin_und_wiederkehrende_buchung_belegen_den_raum(): void
    {
        $frei      = Room::factory()->create();
        $einzeln   = Room::factory()->create();
        $woechentl = Room::factory()->create();

        RoomBooking::factory()->create([
            'room_id' => $einzeln->id, 'is_recurring' => false,
            'booking_date' => $this->montag, 'weekday' => 1, 'start' => '09:30', 'end' => '10:30',
        ]);
        RoomBooking::factory()->create([
            'room_id' => $woechentl->id, 'weekday' => 1, 'start' => '08:00', 'end' => '09:15',
        ]);

        $ergebnis = $this->suche('09:00', '10:00');

        $this->assertSame([$frei->id], $this->freieIds($ergebnis));
        $this->assertEqualsCanonicalizing([$einzeln->id, $woechentl->id], $ergebnis['belegt']->map(fn ($e) => $e['room']->id)->all());
        $this->assertSame('B', $ergebnis['wochen_typ']);
    }

    /** @test */
    public function angrenzende_buchungen_belegen_nicht_und_liefern_frei_ab_bis(): void
    {
        $raum = Room::factory()->create();
        RoomBooking::factory()->create(['room_id' => $raum->id, 'weekday' => 1, 'start' => '08:00', 'end' => '09:00']);
        RoomBooking::factory()->create(['room_id' => $raum->id, 'weekday' => 1, 'start' => '10:00', 'end' => '11:00']);
        RoomBooking::factory()->create(['room_id' => $raum->id, 'weekday' => 1, 'start' => '12:00', 'end' => '13:00']);

        $eintrag = $this->suche('09:00', '10:00')['frei']->first();

        $this->assertSame($raum->id, $eintrag['room']->id);
        $this->assertSame('09:00', $eintrag['frei_ab']);
        $this->assertSame('10:00', $eintrag['frei_bis']);
    }

    /** @test */
    public function andere_ab_woche_anderer_tag_und_stornierte_buchungen_zaehlen_nicht(): void
    {
        $raum = Room::factory()->create();
        RoomBooking::factory()->create(['room_id' => $raum->id, 'weekday' => 1, 'week' => 'A', 'start' => '09:00', 'end' => '10:00']);
        RoomBooking::factory()->create(['room_id' => $raum->id, 'weekday' => 2, 'start' => '09:00', 'end' => '10:00']);
        RoomBooking::factory()->cancelled()->create(['room_id' => $raum->id, 'weekday' => 1, 'start' => '09:00', 'end' => '10:00']);
        RoomBooking::factory()->create([
            'room_id' => $raum->id, 'is_recurring' => false, 'weekday' => 1,
            'booking_date' => $this->montag->copy()->addWeek(), 'start' => '09:00', 'end' => '10:00',
        ]);

        $this->assertSame([$raum->id], $this->freieIds($this->suche('09:00', '10:00')));
    }

    /** @test */
    public function vp_stornierung_gibt_wiederkehrende_stunde_frei(): void
    {
        $raum = Room::factory()->create();
        RoomBooking::factory()->indiwareXml()->create(['room_id' => $raum->id, 'weekday' => 1, 'start' => '09:00', 'end' => '09:45']);
        RoomBooking::factory()->create([
            'room_id' => $raum->id, 'is_recurring' => false, 'weekday' => 1, 'booking_date' => $this->montag,
            'start' => '09:00', 'end' => '09:45', 'source' => 'indiware_vp', 'cancelled' => true,
        ]);

        $this->assertSame([$raum->id], $this->freieIds($this->suche('09:00', '09:45')));
        // Eine Woche später gilt die Stornierung nicht
        $this->assertSame([], $this->freieIds($this->suche('09:00', '09:45', $this->montag->copy()->addWeeks(2))));
    }

    /** @test */
    public function unbekannte_ab_woche_wertet_beide_wochen_als_belegt(): void
    {
        $raum = Room::factory()->create();
        RoomBooking::factory()->create(['room_id' => $raum->id, 'weekday' => 1, 'week' => 'A', 'start' => '09:00', 'end' => '10:00']);

        $ergebnis = $this->suche('09:00', '10:00', $this->montag->copy()->addWeek());

        $this->assertNull($ergebnis['wochen_typ']);
        $this->assertSame([], $this->freieIds($ergebnis));
    }

    /** @test */
    public function nicht_buchbare_raeume_werden_nicht_angezeigt(): void
    {
        Room::factory()->notBookable()->create();

        $ergebnis = $this->suche('09:00', '10:00');

        $this->assertCount(0, $ergebnis['frei']);
        $this->assertCount(0, $ergebnis['belegt']);
    }

    /** @test */
    public function raumuebersicht_zeigt_suchergebnis(): void
    {
        $this->actingAsWithPermission('view roomBooking');
        $frei   = Room::factory()->create(['name' => 'Musikraum']);
        $belegt = Room::factory()->create(['name' => 'Werkraum']);
        RoomBooking::factory()->create(['room_id' => $belegt->id, 'weekday' => 1, 'start' => '09:00', 'end' => '10:00', 'name' => 'Werken 5a']);

        $this->get('rooms/rooms?datum=2026-11-30&von=09:15&bis=09:45')
            ->assertOk()
            ->assertSee('Freien Raum finden')
            ->assertSee('data-testid="raumsuche-ergebnis"', false)
            ->assertSee('rooms/rooms/' . $frei->id . '/B/2026-11-30?von=09%3A15&amp;bis=09%3A45', false)
            ->assertSee('Werken 5a')
            ->assertDontSee('rooms/rooms/' . $belegt->id . '/B/2026-11-30?von=', false);
    }

    /** @test */
    public function raumuebersicht_ohne_suche_zeigt_nur_formular(): void
    {
        $this->actingAsWithPermission('view roomBooking');
        Room::factory()->create();

        $this->get('rooms/rooms')
            ->assertOk()
            ->assertSee('Freien Raum finden')
            ->assertDontSee('data-testid="raumsuche-ergebnis"', false);
    }

    /** @test */
    public function ungueltiger_zeitraum_wird_abgelehnt(): void
    {
        $this->actingAsWithPermission('view roomBooking');

        $this->get('rooms/rooms?datum=2026-11-30&von=10:00&bis=09:00')
            ->assertSessionHasErrors('bis');
    }

    /** @test */
    public function schnellauswahl_nutzt_standard_zeitraster_passend_zur_woche(): void
    {
        $zr = Zeitraster::create(['name' => 'Test-Raster', 'ist_standard' => false]);
        $zr->markAlsStandard();
        LessonTime::create(['zeitraster_id' => $zr->id, 'period' => 1, 'start' => '07:45', 'end' => '08:30', 'week' => null]);
        LessonTime::create(['zeitraster_id' => $zr->id, 'period' => 2, 'start' => '08:35', 'end' => '09:20', 'week' => 'A']);
        LessonTime::create(['zeitraster_id' => $zr->id, 'period' => 2, 'start' => '08:40', 'end' => '09:25', 'week' => 'B']);

        $stunden = app(RaumSucheService::class)->stunden('B');

        $this->assertSame([
            ['stunde' => 1, 'von' => '07:45', 'bis' => '08:30'],
            ['stunde' => 2, 'von' => '08:40', 'bis' => '09:25'],
        ], $stunden->all());
    }

    /** @test */
    public function buchungsseite_uebernimmt_zeitraum_aus_der_suche(): void
    {
        $this->actingAsWithPermission('view roomBooking');
        $raum = Room::factory()->create();

        $this->get('rooms/rooms/' . $raum->id . '/B/2026-11-30?von=09:15&bis=09:45')
            ->assertOk()
            ->assertSee('value="09:15"', false)
            ->assertSee('value="09:45"', false)
            ->assertSee("switchTab('single');", false);
    }
}
