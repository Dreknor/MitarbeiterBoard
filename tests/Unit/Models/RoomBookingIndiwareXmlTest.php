<?php

namespace Tests\Unit\Models;

use App\Models\Room;
use App\Models\RoomBooking;
use Tests\TestCase;

class RoomBookingIndiwareXmlTest extends TestCase
{
    /** @test */
    public function klassen_und_lehrer_felder_sind_fillable()
    {
        $room = Room::factory()->create();
        $user = $this->actingAsWithPermission('manage rooms');

        $booking = RoomBooking::create([
            'room_id'      => $room->id,
            'users_id'     => $user->id,
            'weekday'      => 1,
            'start'        => '08:00',
            'end'          => '08:45',
            'name'         => 'Mathe 5a',
            'klassen'      => '5a, 5b',
            'lehrer'       => 'Mül',
            'source'       => 'indiware_xml',
            'source_id'    => 'pl_1_1_1_1',
            'is_recurring' => true,
        ]);

        $this->assertDatabaseHas('room_bookings', [
            'id'      => $booking->id,
            'klassen' => '5a, 5b',
            'lehrer'  => 'Mül',
            'source'  => 'indiware_xml',
        ]);
    }

    /** @test */
    public function scope_from_indiware_xml_filtert_korrekt()
    {
        $room = Room::factory()->create();

        RoomBooking::factory()->for($room)->create([
            'source' => 'manual',
            'start'  => '08:00',
            'end'    => '08:45',
        ]);
        RoomBooking::factory()->for($room)->indiwareXml()->create([
            'start' => '09:00',
            'end'   => '09:45',
        ]);
        RoomBooking::factory()->for($room)->create([
            'source' => 'indiware_vp',
            'start'  => '10:00',
            'end'    => '10:45',
        ]);

        $xmlBookings = RoomBooking::fromIndiwareXml()->get();

        $this->assertCount(1, $xmlBookings);
        $this->assertEquals('indiware_xml', $xmlBookings->first()->source);
    }

    /** @test */
    public function update_or_create_aktualisiert_bestehende_indiware_buchung()
    {
        $room = Room::factory()->create();
        $user = $this->actingAsWithPermission('manage rooms');

        // Erste Buchung erstellen
        $booking1 = RoomBooking::updateOrCreate(
            ['source' => 'indiware_xml', 'source_id' => 'pl_100_1_1_1'],
            [
                'room_id'      => $room->id,
                'users_id'     => $user->id,
                'weekday'      => 1,
                'start'        => '08:00',
                'end'          => '08:45',
                'name'         => 'Mathe 5a',
                'klassen'      => '5a',
                'lehrer'       => 'Mül',
                'is_recurring' => true,
            ]
        );

        // Gleiche source_id → Update statt Insert
        $booking2 = RoomBooking::updateOrCreate(
            ['source' => 'indiware_xml', 'source_id' => 'pl_100_1_1_1'],
            [
                'room_id'      => $room->id,
                'users_id'     => $user->id,
                'weekday'      => 1,
                'start'        => '08:00',
                'end'          => '08:45',
                'name'         => 'Deutsch 5a',
                'klassen'      => '5a',
                'lehrer'       => 'Sch',
                'is_recurring' => true,
            ]
        );

        $this->assertEquals($booking1->id, $booking2->id);
        $this->assertCount(1, RoomBooking::fromIndiwareXml()->get());

        $booking2->refresh();
        $this->assertEquals('Deutsch 5a', $booking2->name);
        $this->assertEquals('Sch', $booking2->lehrer);
    }

    /** @test */
    public function manuelle_buchungen_bleiben_bei_indiware_loeschung_erhalten()
    {
        $room = Room::factory()->create();

        // Manuelle Buchung
        $manual = RoomBooking::factory()->for($room)->create([
            'source' => 'manual',
            'name'   => 'AG Theater',
            'start'  => '14:00',
            'end'    => '15:00',
        ]);

        // Indiware-Buchung
        $xml = RoomBooking::factory()->for($room)->indiwareXml()->create([
            'start' => '08:00',
            'end'   => '08:45',
        ]);

        // Nur Indiware-Buchungen löschen
        $room->bookings()->fromIndiwareXml()->forceDelete();

        $this->assertDatabaseHas('room_bookings', ['id' => $manual->id]);
        $this->assertDatabaseMissing('room_bookings', ['id' => $xml->id]);
    }

    /**
     * @test
     * Stellt sicher, dass beim Import die Bereinigung nur Einträge im selben Zeitraster-Slot
     * des aktuellen Indiware-Projekts löscht, nicht Einträge eines anderen Projekts
     * (die in anderen Stunden/Tags liegen) und keine manuellen Buchungen.
     */
    public function veraltete_indiware_eintraege_werden_nur_im_eigenen_zeitraster_geloescht()
    {
        $room = Room::factory()->create();

        // Altes Indiware-XML-Eintrag in Slot Mo/08:00 (dieses Projekts) – soll gelöscht werden
        $alterEintragProjektA = RoomBooking::factory()->for($room)->indiwareXml()->create([
            'weekday'      => 1, // Montag
            'start'        => '08:00',
            'end'          => '08:45',
            'week'         => null,
            'source_id'    => 'pl_OLD_1_1_1',
            'is_recurring' => true,
        ]);

        // Indiware-XML-Eintrag eines anderen Projekts in Slot Mo/10:00 – soll ERHALTEN bleiben
        $eintragProjektB = RoomBooking::factory()->for($room)->indiwareXml()->create([
            'weekday'      => 1, // Montag
            'start'        => '10:00',
            'end'          => '10:45',
            'week'         => null,
            'source_id'    => 'pl_PROJB_1_3_1',
            'is_recurring' => true,
        ]);

        // Manuelle Buchung im selben Slot Mo/08:00 – soll ERHALTEN bleiben
        $manuellerEintrag = RoomBooking::factory()->for($room)->create([
            'source'       => 'manual',
            'weekday'      => 1,
            'start'        => '08:00',
            'end'          => '09:00',
            'week'         => null,
            'is_recurring' => true,
        ]);

        // Neuer Import aus Projekt A aktualisiert Slot Mo/08:00 mit neuer Buchung
        $neuerEintragProjektA = RoomBooking::updateOrCreate(
            ['source' => 'indiware_xml', 'source_id' => 'pl_NEW_1_1_1'],
            [
                'room_id'      => $room->id,
                'users_id'     => $this->actingAsWithPermission('manage rooms')->id,
                'weekday'      => 1,
                'start'        => '08:00',
                'end'          => '08:45',
                'name'         => 'Deutsch 5a',
                'is_recurring' => true,
                'week'         => null,
            ]
        );
        $updatedIds = [$neuerEintragProjektA->id];

        // Selektive Bereinigung: nur Slot Mo/08:00 für diesen Raum
        $importierteSlots = [
            ['room_id' => $room->id, 'weekday' => 1, 'start' => '08:00', 'week' => null],
        ];
        $eindeutigeSlots = collect($importierteSlots)
            ->unique(fn ($s) => $s['room_id'] . '_' . $s['weekday'] . '_' . $s['start'] . '_' . ($s['week'] ?? ''))
            ->values();

        foreach ($eindeutigeSlots as $slot) {
            RoomBooking::fromIndiwareXml()
                ->where('room_id', $slot['room_id'])
                ->where('weekday', $slot['weekday'])
                ->where('start', $slot['start'])
                ->where('week', $slot['week'])
                ->whereNotIn('id', $updatedIds)
                ->forceDelete();
        }

        // Alter Projekt-A-Eintrag (Mo/08:00) muss weg sein
        $this->assertDatabaseMissing('room_bookings', ['id' => $alterEintragProjektA->id]);

        // Projekt-B-Eintrag (Mo/10:00) muss erhalten sein
        $this->assertDatabaseHas('room_bookings', ['id' => $eintragProjektB->id]);

        // Manueller Eintrag muss erhalten sein
        $this->assertDatabaseHas('room_bookings', ['id' => $manuellerEintrag->id]);

        // Neuer Projekt-A-Eintrag muss vorhanden sein
        $this->assertDatabaseHas('room_bookings', ['id' => $neuerEintragProjektA->id]);
    }

    /** @test */
    public function klassen_und_lehrer_koennen_null_sein()
    {
        $room = Room::factory()->create();
        $user = $this->actingAsWithPermission('manage rooms');

        $booking = RoomBooking::create([
            'room_id'      => $room->id,
            'users_id'     => $user->id,
            'weekday'      => 2,
            'start'        => '10:00',
            'end'          => '10:45',
            'name'         => 'Besprechung',
            'klassen'      => null,
            'lehrer'       => null,
            'source'       => 'manual',
            'is_recurring' => true,
        ]);

        $this->assertNull($booking->klassen);
        $this->assertNull($booking->lehrer);
    }
}

