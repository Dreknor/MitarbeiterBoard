<?php

namespace Tests\Unit\Models;

use App\Models\Room;
use App\Models\RoomBooking;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class RoomHasBookingTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
    }

    // ─── Test 1: Stornierte Buchung zählt nicht als belegt ────────────────────

    public function test_cancelled_booking_not_counted(): void
    {
        $room = Room::factory()->create();
        $date = Carbon::parse('2026-03-16');

        RoomBooking::factory()->create([
            'room_id'      => $room->id,
            'weekday'      => 1, // Montag
            'start'        => '07:30',
            'end'          => '08:15',
            'is_recurring' => true,
            'cancelled'    => true,
            'source'       => 'manual',
        ]);

        $result = $room->hasBooking(1, '07:30', null, $date);
        $this->assertNull($result, 'Stornierte Buchung muss null zurückgeben');
    }

    // ─── Test 2: VP-Stornierung hebt wiederkehrende Buchung auf ──────────────

    public function test_vp_cancel_overrides_recurring(): void
    {
        $room = Room::factory()->create();
        $date = Carbon::parse('2026-03-16'); // Montag (dayOfWeek=1)

        // Wiederkehrende Buchung Mo 07:30-08:15
        RoomBooking::factory()->create([
            'room_id'      => $room->id,
            'weekday'      => $date->dayOfWeek,
            'start'        => '07:30',
            'end'          => '08:15',
            'is_recurring' => true,
            'cancelled'    => false,
            'source'       => 'manual',
            'booking_date' => null,
        ]);

        // VP-Stornierung für 16.03. 07:30-08:15 (exakt gleiche Zeiten)
        RoomBooking::factory()->create([
            'room_id'      => $room->id,
            'weekday'      => $date->dayOfWeek,
            'start'        => '07:30',
            'end'          => '08:15',
            'is_recurring' => false,
            'cancelled'    => true,
            'source'       => 'indiware_vp',
            'booking_date' => $date,
        ]);

        // Cache leeren (Bookings sind jetzt in DB)
        Cache::flush();

        $result = $room->hasBooking($date->dayOfWeek, '07:30', null, $date);
        $this->assertNull($result, 'VP-Stornierung muss wiederkehrende Buchung aufheben');
    }

    // ─── Test 3: VP-Stornierung mit falscher Zeit → Buchung bleibt ────────────

    public function test_vp_cancel_wrong_time_no_effect(): void
    {
        $room = Room::factory()->create();
        $date = Carbon::parse('2026-03-16');

        // Wiederkehrende Buchung 07:30-08:15
        RoomBooking::factory()->create([
            'room_id'      => $room->id,
            'weekday'      => $date->dayOfWeek,
            'start'        => '07:30',
            'end'          => '08:15',
            'is_recurring' => true,
            'cancelled'    => false,
            'source'       => 'manual',
            'booking_date' => null,
        ]);

        // VP-Stornierung mit ANDEREM Zeitfenster (09:30-10:15)
        RoomBooking::factory()->create([
            'room_id'      => $room->id,
            'weekday'      => $date->dayOfWeek,
            'start'        => '09:30',
            'end'          => '10:15',
            'is_recurring' => false,
            'cancelled'    => true,
            'source'       => 'indiware_vp',
            'booking_date' => $date,
        ]);

        Cache::flush();

        // Die 07:30-Buchung muss trotzdem als belegt gelten
        $result = $room->hasBooking($date->dayOfWeek, '07:30', null, $date);
        $this->assertNotNull($result, 'Buchung 07:30 darf durch Stornierung 09:30 nicht aufgehoben werden');
    }

    // ─── Test 4: VP-Stornierung für anderen Tag → kein Effekt ─────────────────

    public function test_vp_cancel_different_date_no_effect(): void
    {
        $room     = Room::factory()->create();
        $dateMonday = Carbon::parse('2026-03-16'); // Montag
        $dateTuesday = Carbon::parse('2026-03-17'); // Dienstag

        // Wiederkehrende Buchung Mo 07:30-08:15
        RoomBooking::factory()->create([
            'room_id'      => $room->id,
            'weekday'      => $dateMonday->dayOfWeek,
            'start'        => '07:30',
            'end'          => '08:15',
            'is_recurring' => true,
            'cancelled'    => false,
            'source'       => 'manual',
            'booking_date' => null,
        ]);

        // VP-Stornierung für DIENSTAG (17.03.) – gleiche Uhrzeit
        RoomBooking::factory()->create([
            'room_id'      => $room->id,
            'weekday'      => $dateTuesday->dayOfWeek,
            'start'        => '07:30',
            'end'          => '08:15',
            'is_recurring' => false,
            'cancelled'    => true,
            'source'       => 'indiware_vp',
            'booking_date' => $dateTuesday,
        ]);

        Cache::flush();

        // Montag-Buchung bleibt belegt
        $result = $room->hasBooking($dateMonday->dayOfWeek, '07:30', null, $dateMonday);
        $this->assertNotNull($result, 'Stornierung für Dienstag darf Montag nicht beeinflussen');
    }

    // ─── Test: TODO-2 – OR→AND in Stornierungsprüfung ────────────────────────

    public function test_cancellation_requires_matching_start_AND_end(): void
    {
        $room = Room::factory()->create();
        $date = Carbon::parse('2026-03-16');

        // Wiederkehrende Buchung 07:30-08:15
        RoomBooking::factory()->create([
            'room_id'      => $room->id,
            'weekday'      => $date->dayOfWeek,
            'start'        => '07:30',
            'end'          => '08:15',
            'is_recurring' => true,
            'cancelled'    => false,
            'source'       => 'manual',
            'booking_date' => null,
        ]);

        // VP-Stornierung mit komplett anderem Zeitfenster
        RoomBooking::factory()->create([
            'room_id'      => $room->id,
            'weekday'      => $date->dayOfWeek,
            'start'        => '09:30',
            'end'          => '10:15',
            'is_recurring' => false,
            'cancelled'    => true,
            'source'       => 'indiware_vp',
            'booking_date' => $date,
        ]);

        Cache::flush();

        // Buchung 07:30-08:15 muss trotzdem belegt sein (kein Match für Start AND Ende)
        $result = $room->hasBooking($date->dayOfWeek, '07:30', null, $date);
        $this->assertNotNull($result, 'Raum muss belegt sein – Stornierung trifft andere Zeiten');
    }

    public function test_cancellation_with_exact_match_frees_room(): void
    {
        $room = Room::factory()->create();
        $date = Carbon::parse('2026-03-16');

        // Wiederkehrende Buchung 07:30-08:15
        RoomBooking::factory()->create([
            'room_id'      => $room->id,
            'weekday'      => $date->dayOfWeek,
            'start'        => '07:30',
            'end'          => '08:15',
            'is_recurring' => true,
            'cancelled'    => false,
            'source'       => 'manual',
            'booking_date' => null,
        ]);

        // VP-Stornierung mit exakt gleichen Zeiten
        RoomBooking::factory()->create([
            'room_id'      => $room->id,
            'weekday'      => $date->dayOfWeek,
            'start'        => '07:30',
            'end'          => '08:15',
            'is_recurring' => false,
            'cancelled'    => true,
            'source'       => 'indiware_vp',
            'booking_date' => $date,
        ]);

        Cache::flush();

        // Raum muss frei sein (exakter Match)
        $result = $room->hasBooking($date->dayOfWeek, '07:30', null, $date);
        $this->assertNull($result, 'Exakter Match: Raum muss durch VP-Stornierung frei sein');
    }

    public function test_cancellation_only_affects_same_date(): void
    {
        $room       = Room::factory()->create();
        $dateMonday = Carbon::parse('2026-03-16'); // Montag
        $dateNextMon = Carbon::parse('2026-03-23'); // nächste Woche Montag

        // Wiederkehrende Buchung Mo 07:30-08:15
        RoomBooking::factory()->create([
            'room_id'      => $room->id,
            'weekday'      => $dateMonday->dayOfWeek,
            'start'        => '07:30',
            'end'          => '08:15',
            'is_recurring' => true,
            'cancelled'    => false,
            'source'       => 'manual',
            'booking_date' => null,
        ]);

        // VP-Stornierung nur für Montag 16.03.
        RoomBooking::factory()->create([
            'room_id'      => $room->id,
            'weekday'      => $dateMonday->dayOfWeek,
            'start'        => '07:30',
            'end'          => '08:15',
            'is_recurring' => false,
            'cancelled'    => true,
            'source'       => 'indiware_vp',
            'booking_date' => $dateMonday,
        ]);

        Cache::flush();

        // Nächste Woche Montag darf NICHT frei sein
        $result = $room->hasBooking($dateNextMon->dayOfWeek, '07:30', null, $dateNextMon);
        $this->assertNotNull($result, 'Stornierung für 16.03. darf 23.03. nicht beeinflussen');
    }
}

