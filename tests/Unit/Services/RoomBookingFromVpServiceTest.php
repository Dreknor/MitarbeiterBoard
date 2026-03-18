<?php

namespace Tests\Unit\Services;

use App\Models\Klasse;
use App\Models\LessonTime;
use App\Models\Room;
use App\Models\RoomBooking;
use App\Models\Setting;
use App\Models\Zeitraster;
use App\Services\RoomBookingFromVpService;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

class RoomBookingFromVpServiceTest extends TestCase
{
    private RoomBookingFromVpService $service;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
        $this->service = new RoomBookingFromVpService();
        $this->seedLessonTimes();
    }

    private function seedLessonTimes(): void
    {
        $times = [
            ['period' => 1, 'start' => '07:30', 'end' => '08:15', 'week' => null],
            ['period' => 2, 'start' => '08:25', 'end' => '09:10', 'week' => null],
            ['period' => 3, 'start' => '09:30', 'end' => '10:15', 'week' => null],
            ['period' => 4, 'start' => '10:25', 'end' => '11:10', 'week' => null],
            ['period' => 5, 'start' => '11:30', 'end' => '12:15', 'week' => null],
            ['period' => 6, 'start' => '12:20', 'end' => '13:05', 'week' => null],
            ['period' => 7, 'start' => '13:35', 'end' => '14:20', 'week' => null],
            ['period' => 8, 'start' => '14:25', 'end' => '15:10', 'week' => null],
        ];
        foreach ($times as $t) {
            LessonTime::create($t);
        }
    }

    private function createRoom(string $name, string $shortname): Room
    {
        return Room::factory()->create([
            'name'               => $name,
            'room_number'        => $name,
            'indiware_shortname' => $shortname,
        ]);
    }

    private function makeAktion(array $override = []): object
    {
        return (object) array_merge([
            'Ak_Id'       => 1000,
            'Ak_Art'      => 'Änd.',
            'Ak_StundeVon' => 1,
            'Ak_StundenAnz' => 1,
            'Ak_Fach'     => 'MA',
            'Raeume'      => ['R101'],
            'VRaeume'     => ['R101'],
            'Klassen'     => ['5a'],
        ], $override);
    }

    private function enableIntegration(): void
    {
        // Eintrag mit machine-key 'vp_room_integration_enabled' als 'setting'-Spalte
        \Illuminate\Support\Facades\DB::table('settings')->updateOrInsert(
            ['setting' => 'vp_room_integration_enabled'],
            ['module' => 'rooms', 'setting_name' => 'VP Raumintegration', 'type' => 'boolean',
             'value' => '1', 'created_at' => now(), 'updated_at' => now()]
        );
    }

    // ─── Test 1: Ausfall erstellt Stornierungseintrag ─────────────────────────

    public function test_ausfall_creates_cancellation(): void
    {
        $this->enableIntegration();
        $this->createRoom('R101', 'R101');
        $date = Carbon::parse('2026-03-16');

        $aktion = $this->makeAktion(['Ak_Art' => 'Ausf.', 'Raeume' => ['R101'], 'VRaeume' => []]);
        $this->service->processAktion($aktion, $date);

        $this->assertDatabaseHas('room_bookings', [
            'cancelled' => 1,
            'source'    => 'indiware_vp',
        ]);
        $this->assertEquals(1, $this->service->getSummary()['room_bookings_cancelled']);
    }

    // ─── Test 2: Ausfall ohne Raum → Skip ─────────────────────────────────────

    public function test_ausfall_without_room_skips(): void
    {
        $this->enableIntegration();
        $date = Carbon::parse('2026-03-16');

        $aktion = $this->makeAktion(['Ak_Art' => 'Ausf.', 'Raeume' => []]);
        $this->service->processAktion($aktion, $date);

        $this->assertEquals(0, RoomBooking::count());
        $this->assertEquals(1, $this->service->getSummary()['room_bookings_skipped']);
    }

    // ─── Test 3: Änderung gleicher Raum → eine aktive Buchung ─────────────────

    public function test_aenderung_same_room(): void
    {
        $this->enableIntegration();
        $this->createRoom('R101', 'R101');
        $date = Carbon::parse('2026-03-16');

        $aktion = $this->makeAktion(['Raeume' => ['R101'], 'VRaeume' => ['R101']]);
        $this->service->processAktion($aktion, $date);

        // Nur eine aktive Buchung, keine Stornierung
        $this->assertEquals(1, RoomBooking::where('cancelled', false)->count());
        $this->assertEquals(0, RoomBooking::where('cancelled', true)->count());
        $this->assertEquals(1, $this->service->getSummary()['room_bookings_created']);
        $this->assertEquals(0, $this->service->getSummary()['room_bookings_cancelled']);
    }

    // ─── Test 4: Änderung Raumwechsel → 1 Stornierung + 1 Buchung ────────────

    public function test_aenderung_room_change(): void
    {
        $this->enableIntegration();
        $this->createRoom('R101', 'R101');
        $this->createRoom('R202', 'R202');
        $date = Carbon::parse('2026-03-16');

        $aktion = $this->makeAktion(['Raeume' => ['R101'], 'VRaeume' => ['R202']]);
        $this->service->processAktion($aktion, $date);

        $this->assertEquals(1, RoomBooking::where('cancelled', true)->count());   // R101 storniert
        $this->assertEquals(1, RoomBooking::where('cancelled', false)->count());  // R202 belegt
        $this->assertEquals(1, $this->service->getSummary()['room_bookings_created']);
        $this->assertEquals(1, $this->service->getSummary()['room_bookings_cancelled']);
    }

    // ─── Test 5: Verlegung → Stornierung + neue Buchung ──────────────────────

    public function test_verlegung_cancel_and_create(): void
    {
        $this->enableIntegration();
        $this->createRoom('R101', 'R101');
        $date = Carbon::parse('2026-03-16');

        $aktion = $this->makeAktion([
            'Ak_Art'       => 'Verl.',
            'Raeume'       => ['R101'],
            'VRaeume'      => ['R101'],
            'Ak_DatumVon'  => '16.03.2026',
            'Ak_DatumNach' => '17.03.2026',
            'Ak_StundeNach' => 1,
        ]);
        $this->service->processAktion($aktion, $date);

        $this->assertEquals(1, RoomBooking::where('cancelled', true)->count());
        $this->assertEquals(1, RoomBooking::where('cancelled', false)->count());
        $this->assertEquals(1, $this->service->getSummary()['room_bookings_cancelled']);
        $this->assertEquals(1, $this->service->getSummary()['room_bookings_created']);
    }

    // ─── Test 6: Neu erstellt Buchung ─────────────────────────────────────────

    public function test_neu_creates_booking(): void
    {
        $this->enableIntegration();
        $this->createRoom('R101', 'R101');
        $date = Carbon::parse('2026-03-16');

        $aktion = $this->makeAktion(['Ak_Art' => 'Neu', 'VRaeume' => ['R101']]);
        $this->service->processAktion($aktion, $date);

        $this->assertEquals(1, RoomBooking::where('cancelled', false)->count());
        $this->assertEquals(1, $this->service->getSummary()['room_bookings_created']);
    }

    // ─── Test 7: Unbekannte Art → Skip ────────────────────────────────────────

    public function test_unknown_art_skips(): void
    {
        $this->enableIntegration();
        $date = Carbon::parse('2026-03-16');

        $aktion = $this->makeAktion(['Ak_Art' => 'XYZ']);
        $this->service->processAktion($aktion, $date);

        $this->assertEquals(0, RoomBooking::count());
        $this->assertEquals(1, $this->service->getSummary()['room_bookings_skipped']);
    }

    // ─── Test 8: Fehlender Raum → missing_rooms + skipped ────────────────────

    public function test_missing_room_in_summary(): void
    {
        $this->enableIntegration();
        $date = Carbon::parse('2026-03-16');

        // Ausfall mit unbekanntem Raum (kein Raum in DB)
        $aktion = $this->makeAktion(['Ak_Art' => 'Ausf.', 'Raeume' => ['UNBEKANNT']]);
        $this->service->processAktion($aktion, $date);

        $this->assertEquals(0, RoomBooking::count());
        $summary = $this->service->getSummary();
        $this->assertContains('UNBEKANNT', $summary['missing_rooms']);
        $this->assertGreaterThanOrEqual(1, $summary['room_bookings_skipped']);
    }

    // ─── Test 9: Doppelstunde → korrekte Zeitspanne ──────────────────────────

    public function test_doppelstunde_time_range(): void
    {
        $this->enableIntegration();
        $this->createRoom('R101', 'R101');
        $date = Carbon::parse('2026-03-16');

        // Stunde 3 + 4 → 09:30 – 11:10
        $aktion = $this->makeAktion([
            'Ak_StundeVon'  => 3,
            'Ak_StundenAnz' => 2,
            'Raeume'        => ['R101'],
            'VRaeume'       => ['R101'],
        ]);
        $this->service->processAktion($aktion, $date);

        $booking = RoomBooking::first();
        $this->assertNotNull($booking);
        $this->assertEquals('09:30', substr($booking->start, 0, 5));
        $this->assertEquals('11:10', substr($booking->end, 0, 5));
    }

    // ─── Test 10: Dreifachstunde → korrekte Zeitspanne ───────────────────────

    public function test_dreifachstunde_time_range(): void
    {
        $this->enableIntegration();
        $this->createRoom('R101', 'R101');
        $date = Carbon::parse('2026-03-16');

        // Stunde 1+2+3 → 07:30 – 10:15
        $aktion = $this->makeAktion([
            'Ak_StundeVon'  => 1,
            'Ak_StundenAnz' => 3,
            'Raeume'        => ['R101'],
            'VRaeume'       => ['R101'],
        ]);
        $this->service->processAktion($aktion, $date);

        $booking = RoomBooking::first();
        $this->assertNotNull($booking);
        $this->assertEquals('07:30', substr($booking->start, 0, 5));
        $this->assertEquals('10:15', substr($booking->end, 0, 5));
    }

    // ─── Test 11: clearVpBookingsForDate löscht hart (forceDelete) ────────────

    public function test_clearVpBookingsForDate_deletes_hard(): void
    {
        $room = $this->createRoom('R101', 'R101');
        $date = Carbon::parse('2026-03-16');

        // 2 VP-Buchungen + 1 manuelle Buchung für dasselbe Datum
        RoomBooking::factory()->create([
            'room_id'      => $room->id,
            'source'       => 'indiware_vp',
            'booking_date' => $date,
            'is_recurring' => false,
        ]);
        RoomBooking::factory()->create([
            'room_id'      => $room->id,
            'source'       => 'indiware_vp',
            'booking_date' => $date,
            'is_recurring' => false,
        ]);
        RoomBooking::factory()->create([
            'room_id'      => $room->id,
            'source'       => 'manual',
            'booking_date' => $date,
            'is_recurring' => false,
        ]);

        $this->assertEquals(3, RoomBooking::count());

        $this->service->clearVpBookingsForDate($date);

        // VP-Buchungen wurden hart gelöscht – also auch withTrashed() nur 1 (manual)
        $this->assertEquals(1, RoomBooking::withTrashed()->count());
        $this->assertEquals(1, RoomBooking::count());
    }

    // ─── Test 12: Konflikt mit manueller Buchung wird erkannt ─────────────────

    public function test_conflict_detected_with_manual_booking(): void
    {
        $this->enableIntegration();
        $room = $this->createRoom('R101', 'R101');
        $date = Carbon::parse('2026-03-16'); // Montag

        // Manuelle Buchung 07:30-08:15 am 16.03.
        RoomBooking::factory()->create([
            'room_id'      => $room->id,
            'source'       => 'manual',
            'start'        => '07:30',
            'end'          => '08:15',
            'booking_date' => $date,
            'is_recurring' => false,
            'cancelled'    => false,
        ]);

        // VP-Änderung für den gleichen Raum/Zeit
        $aktion = $this->makeAktion([
            'Ak_StundeVon' => 1,  // 07:30-08:15
            'Raeume'       => ['R101'],
            'VRaeume'      => ['R101'],
        ]);
        $this->service->processAktion($aktion, $date);

        // VP-Buchung trotzdem erstellt
        $this->assertEquals(1, RoomBooking::where('source', 'indiware_vp')->count());
        // Konflikt erkannt
        $this->assertCount(1, $this->service->getSummary()['room_conflicts']);
    }

    // ─── Test 13: Integration deaktiviert → keine Buchungen ──────────────────

    public function test_integration_disabled_skips_all(): void
    {
        \Illuminate\Support\Facades\DB::table('settings')->updateOrInsert(
            ['setting' => 'vp_room_integration_enabled'],
            ['module' => 'rooms', 'setting_name' => 'VP Raumintegration', 'type' => 'boolean',
             'value' => '0', 'created_at' => now(), 'updated_at' => now()]
        );

        $this->createRoom('R101', 'R101');
        $date = Carbon::parse('2026-03-16');

        $aktion = $this->makeAktion(['Raeume' => ['R101'], 'VRaeume' => ['R101']]);
        $this->service->processAktion($aktion, $date);

        $this->assertEquals(0, RoomBooking::count());
        $summary = $this->service->getSummary();
        $this->assertEquals(0, $summary['room_bookings_created']);
        $this->assertEquals(0, $summary['room_bookings_cancelled']);
    }

    // ─── Test: Idempotenz (kein Zombie) ──────────────────────────────────────

    public function test_idempotent_import_no_zombies(): void
    {
        $this->enableIntegration();
        $this->createRoom('R101', 'R101');
        $date = Carbon::parse('2026-03-16');

        $aktion = $this->makeAktion(['Raeume' => ['R101'], 'VRaeume' => ['R101']]);

        // Erster Import
        $this->service->processAktion($aktion, $date);
        $this->assertEquals(1, RoomBooking::count());

        // clearVpBookings simulieren + zweiter Import
        $service2 = new RoomBookingFromVpService();
        $service2->clearVpBookingsForDate($date);
        $service2->processAktion($aktion, $date);

        // Genau 1 Buchung, kein Zombie
        $this->assertEquals(1, RoomBooking::count());
        $this->assertEquals(1, RoomBooking::withTrashed()->count());
    }

    // ─── Test: findRoom Single Query ─────────────────────────────────────────

    public function test_findRoom_single_query(): void
    {
        $this->enableIntegration();
        $this->createRoom('R101', 'R101');
        $this->createRoom('R202', 'R202');
        $this->createRoom('R303', 'R303');
        $date = Carbon::parse('2026-03-16');

        DB::enableQueryLog();

        // 5 Aufrufe von processAktion
        for ($i = 0; $i < 5; $i++) {
            $aktion = $this->makeAktion(['Raeume' => ['R101'], 'VRaeume' => ['R101']]);
            $this->service->processAktion($aktion, $date);
        }

        $queries   = DB::getQueryLog();
        DB::disableQueryLog();

        // Zähle Room-Selects
        $roomQueries = array_filter($queries, fn($q) => str_contains($q['query'], 'from "rooms"') || str_contains($q['query'], 'from `rooms`'));
        $this->assertCount(1, array_values($roomQueries), 'Es darf nur 1 DB-Query für Räume geben');
    }

    // ─── Test: LessonTime Single Query ───────────────────────────────────────

    public function test_lessonTime_single_query(): void
    {
        $this->enableIntegration();
        $this->createRoom('R101', 'R101');
        $date = Carbon::parse('2026-03-16');

        DB::enableQueryLog();

        for ($i = 0; $i < 10; $i++) {
            $aktion = $this->makeAktion(['Raeume' => ['R101'], 'VRaeume' => ['R101']]);
            $this->service->processAktion($aktion, $date);
        }

        $queries = DB::getQueryLog();
        DB::disableQueryLog();

        // Zähle LessonTime-Selects
        $ltQueries = array_filter($queries, fn($q) => str_contains($q['query'], 'from "lesson_times"') || str_contains($q['query'], 'from `lesson_times`'));
        $this->assertCount(1, array_values($ltQueries), 'Es darf nur 1 DB-Query für LessonTimes geben');
    }

    // ─── Test: TODO-5 missing_room_counted_in_skipped ────────────────────────

    public function test_missing_room_counted_in_skipped(): void
    {
        $this->enableIntegration();
        $date = Carbon::parse('2026-03-16');

        // Ausfall mit unbekanntem Raum, kein Raum in DB
        $aktion = $this->makeAktion(['Ak_Art' => 'Ausf.', 'Raeume' => ['UNBEKANNT'], 'VRaeume' => []]);
        $this->service->processAktion($aktion, $date);

        $summary = $this->service->getSummary();
        $this->assertEquals(1, $summary['room_bookings_skipped']);
        $this->assertContains('UNBEKANNT', $summary['missing_rooms']);
    }

    // ─── Test: TODO-11 leere Räume in handleAenderung ────────────────────────

    public function test_aenderung_empty_raeume_and_vraeume_skips(): void
    {
        $this->enableIntegration();
        $date = Carbon::parse('2026-03-16');

        $aktion = $this->makeAktion(['Ak_Art' => 'Änd.', 'Raeume' => [], 'VRaeume' => []]);
        // Überschreibe array → null
        $aktion->Raeume  = [];
        $aktion->VRaeume = [];

        $this->service->processAktion($aktion, $date);

        $this->assertEquals(0, RoomBooking::count());
        $this->assertEquals(1, $this->service->getSummary()['room_bookings_skipped']);
    }

    public function test_aenderung_empty_raeume_but_vraeume_creates_booking(): void
    {
        $this->enableIntegration();
        $this->createRoom('R101', 'R101');
        $date = Carbon::parse('2026-03-16');

        // Raeume leer, aber VRaeume gesetzt
        $aktion = $this->makeAktion(['Ak_Art' => 'Änd.', 'Raeume' => [], 'VRaeume' => ['R101']]);

        $this->service->processAktion($aktion, $date);

        // Nur neuer Raum belegt, keine Stornierung
        $this->assertEquals(1, RoomBooking::where('cancelled', false)->count());
        $this->assertEquals(0, RoomBooking::where('cancelled', true)->count());
        $this->assertEquals(1, $this->service->getSummary()['room_bookings_created']);
    }

    // ─── TODO-14: Zeitraster-spezifische Tests ───────────────────────────────

    /**
     * @test
     * Klasse mit eigenem Zeitraster → zeitraster-spezifische LessonTime wird verwendet.
     */
    public function test_zeitraster_specific_times_used_for_booking(): void
    {
        $this->enableIntegration();

        // Globale LessonTime (Fallback)
        LessonTime::create(['period' => 1, 'start' => '07:30', 'end' => '08:15', 'week' => null, 'zeitraster_id' => null]);

        // GS-Zeitraster mit eigener LessonTime
        $gs = Zeitraster::create(['name' => 'GS', 'ist_standard' => false]);
        LessonTime::create(['period' => 1, 'start' => '07:00', 'end' => '07:45', 'week' => null, 'zeitraster_id' => $gs->id]);

        Klasse::factory()->create(['name' => '1a', 'kuerzel' => '1a', 'zeitraster_id' => $gs->id]);
        $this->createRoom('R101', 'R101');

        $aktion = $this->makeAktion([
            'Ak_Art'       => 'Änd.',
            'Ak_StundeVon' => 1,
            'Klassen'      => ['1a'],
            'Raeume'       => ['R101'],
            'VRaeume'      => ['R101'],
        ]);
        $this->service->processAktion($aktion, Carbon::parse('2026-03-30'));

        $booking = RoomBooking::where('cancelled', false)->first();
        $this->assertNotNull($booking);
        $this->assertEquals('07:00', substr($booking->start, 0, 5));
        $this->assertEquals('07:45', substr($booking->end, 0, 5));
    }

    /**
     * @test
     * Klasse ohne Zeitraster-Zuordnung → globale LessonTimes werden genutzt.
     */
    public function test_global_times_used_when_klasse_has_no_zeitraster(): void
    {
        $this->enableIntegration();

        LessonTime::create(['period' => 1, 'start' => '07:30', 'end' => '08:15', 'week' => null, 'zeitraster_id' => null]);
        Klasse::factory()->create(['name' => '5b', 'kuerzel' => '5b', 'zeitraster_id' => null]);
        $this->createRoom('R202', 'R202');

        $aktion = $this->makeAktion([
            'Ak_Art'       => 'Änd.',
            'Ak_StundeVon' => 1,
            'Klassen'      => ['5b'],
            'Raeume'       => ['R202'],
            'VRaeume'      => ['R202'],
        ]);
        $this->service->processAktion($aktion, Carbon::parse('2026-03-30'));

        $booking = RoomBooking::where('cancelled', false)->first();
        $this->assertNotNull($booking);
        $this->assertEquals('07:30', substr($booking->start, 0, 5));
        $this->assertEquals('08:15', substr($booking->end, 0, 5));
    }

    /**
     * @test
     * Bei 5 Aktionen mit gleicher Klasse: DB-Query für Klassen genau einmal (Cache-Test).
     */
    public function test_klassen_cache_single_db_query(): void
    {
        $this->enableIntegration();
        LessonTime::create(['period' => 1, 'start' => '07:30', 'end' => '08:15', 'week' => null, 'zeitraster_id' => null]);
        Klasse::factory()->create(['name' => '3a', 'kuerzel' => '3a', 'zeitraster_id' => null]);
        $this->createRoom('R303', 'R303');

        $queries = [];
        DB::listen(function ($q) use (&$queries) {
            $queries[] = $q->sql;
        });

        for ($i = 0; $i < 5; $i++) {
            // Neuer Service-Instanz pro Aufruf würde Cache invalidieren –
            // alle Aufrufe auf dieselbe Instanz testen den Cache
            $aktion = $this->makeAktion([
                'Ak_Art'       => 'Änd.',
                'Ak_StundeVon' => 1,
                'Klassen'      => ['3a'],
                'Raeume'       => ['R303'],
                'VRaeume'      => ['R303'],
            ]);
            $this->service->processAktion($aktion, Carbon::parse('2026-03-30')->addDays($i));
        }

        $klassenQueries = array_values(array_filter(
            $queries,
            fn ($sql) => str_contains(strtolower($sql), 'klassen')
        ));

        $this->assertCount(1, $klassenQueries, 'Klassen dürfen nur einmal aus der DB geladen werden');
    }

    /**
     * @test
     * Zwei Klassen mit verschiedenen Zeitrastern → Log::warning ausgelöst.
     */
    public function test_mixed_zeitraster_logs_warning(): void
    {
        $this->enableIntegration();
        $gs = Zeitraster::create(['name' => 'GS', 'ist_standard' => false]);
        $os = Zeitraster::create(['name' => 'OS', 'ist_standard' => false]);
        LessonTime::create(['period' => 1, 'start' => '07:30', 'end' => '08:15', 'week' => null, 'zeitraster_id' => null]);
        Klasse::factory()->create(['name' => '1a', 'kuerzel' => '1a', 'zeitraster_id' => $gs->id]);
        Klasse::factory()->create(['name' => '5a', 'kuerzel' => '5a', 'zeitraster_id' => $os->id]);
        $this->createRoom('R101', 'R101');

        Log::shouldReceive('warning')
            ->once()
            ->withArgs(fn ($msg) => str_contains($msg, 'unterschiedlichen Zeitrastern'))
            ->andReturn(null);

        $aktion = $this->makeAktion([
            'Ak_Art'       => 'Änd.',
            'Ak_StundeVon' => 1,
            'Klassen'      => ['1a', '5a'],
            'Raeume'       => ['R101'],
            'VRaeume'      => ['R101'],
        ]);
        $this->service->processAktion($aktion, Carbon::parse('2026-03-30'));
    }
}











