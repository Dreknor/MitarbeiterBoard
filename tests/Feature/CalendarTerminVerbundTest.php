<?php

namespace Tests\Feature;

use App\Models\OxCalendar;
use App\Models\OxTermin;
use App\Models\Room;
use App\Models\RoomBooking;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Termine in mehreren Kalendern (Terminverbund) und Raumbuchung aus dem Kalender.
 */
class CalendarTerminVerbundTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();

        config([
            'ox-calendar.url'      => 'https://ox.example.com/caldav',
            'ox-calendar.username' => 'testuser',
            'ox-calendar.password' => 'testpass',
            'ox-calendar.enabled'  => true,
        ]);
    }

    private function schreiber(string ...$weitere)
    {
        return $this->actingAsWithPermission('view calendar', 'create calendar events', 'edit calendar events', ...$weitere);
    }

    // ================================================================
    // Anlegen in mehreren Kalendern
    // ================================================================

    public function test_Termin_wird_in_allen_gewaehlten_Kalendern_in_OX_angelegt(): void
    {
        $this->schreiber();
        $a = OxCalendar::factory()->schreibbar()->create(['ox_calendar_id' => 'kal-a/']);
        $b = OxCalendar::factory()->schreibbar()->create(['ox_calendar_id' => 'kal-b/']);

        Http::fake(['*' => Http::response('', 201, ['ETag' => '"e1"'])]);

        $this->post(route('calendar.store'), [
            'kalender_ids' => [$a->id, $b->id],
            'titel'        => 'Gesamtkonferenz',
            'beginn'       => '2026-10-05 14:00:00',
            'ende'         => '2026-10-05 16:00:00',
        ])->assertRedirect()->assertSessionHas('type', 'success');

        $termine = OxTermin::where('titel', 'Gesamtkonferenz')->get();
        $this->assertCount(2, $termine);
        $this->assertEqualsCanonicalizing([$a->id, $b->id], $termine->pluck('ox_calendar_id')->all());

        // Gemeinsamer Verbund, aber unterschiedliche iCal-UIDs
        $this->assertNotNull($termine[0]->verbund_uid);
        $this->assertSame($termine[0]->verbund_uid, $termine[1]->verbund_uid);
        $this->assertNotSame($termine[0]->ox_uid, $termine[1]->ox_uid);

        // Zwei PUTs an OX – je Kalender-Collection eines – mit Verbund-Kennung im iCal
        Http::assertSentCount(2);
        Http::assertSent(fn (Request $r) => $r->method() === 'PUT'
            && str_contains($r->url(), 'kal-a/')
            && str_contains($r->body(), 'X-MB-VERBUND:' . $termine[0]->verbund_uid));
        Http::assertSent(fn (Request $r) => $r->method() === 'PUT' && str_contains($r->url(), 'kal-b/'));
    }

    public function test_Anlegen_wird_abgewiesen_wenn_ein_Kalender_nicht_schreibbar_ist(): void
    {
        $this->schreiber();
        $a = OxCalendar::factory()->schreibbar()->create();
        $b = OxCalendar::factory()->create(['schreibbar' => false]);

        Http::fake();

        $this->post(route('calendar.store'), [
            'kalender_ids' => [$a->id, $b->id],
            'titel'        => 'Test',
            'beginn'       => '2026-10-05 14:00:00',
            'ende'         => '2026-10-05 16:00:00',
        ])->assertForbidden();

        Http::assertNothingSent();
        $this->assertSame(0, OxTermin::count());
    }

    public function test_Teilfehler_in_einem_Kalender_wird_als_Warnung_gemeldet(): void
    {
        $this->schreiber();
        $a = OxCalendar::factory()->schreibbar()->create(['name' => 'Kollegium', 'ox_calendar_id' => 'ok/']);
        $b = OxCalendar::factory()->schreibbar()->create(['name' => 'Hort', 'ox_calendar_id' => 'kaputt/']);

        Http::fake([
            '*/kaputt/*' => Http::response('', 500),
            '*'          => Http::response('', 201, ['ETag' => '"e1"']),
        ]);

        $this->post(route('calendar.store'), [
            'kalender_ids' => [$a->id, $b->id],
            'titel'        => 'Teilweise',
            'beginn'       => '2026-10-05 14:00:00',
            'ende'         => '2026-10-05 16:00:00',
        ])->assertRedirect()->assertSessionHas('type', 'warning');

        $this->assertSame([$a->id], OxTermin::where('titel', 'Teilweise')->pluck('ox_calendar_id')->all());
        $this->assertStringContainsString('Hort', session('Meldung'));
    }

    public function test_Ganztaegiger_Termin_hat_exklusives_Ende(): void
    {
        $this->schreiber();
        $kal = OxCalendar::factory()->schreibbar()->create();

        Http::fake(['*' => Http::response('', 201, ['ETag' => '"e1"'])]);

        $this->post(route('calendar.store'), [
            'kalender_ids' => [$kal->id],
            'titel'        => 'Projekttage',
            'beginn'       => '2026-10-05',
            'ende'         => '2026-10-07',
            'ganztaegig'   => '1',
        ])->assertRedirect();

        $termin = OxTermin::where('titel', 'Projekttage')->firstOrFail();
        $this->assertSame('2026-10-08', $termin->ende->format('Y-m-d'));
        $this->assertStringContainsString('DTSTART;VALUE=DATE:20261005', $termin->raw_ical);
        $this->assertStringContainsString('DTEND;VALUE=DATE:20261008', $termin->raw_ical);
    }

    // ================================================================
    // Bearbeiten / Löschen im Verbund
    // ================================================================

    private function verbund(array $kalender, array $attribute = []): array
    {
        $uid = 'verbund-' . uniqid();

        return array_map(fn (OxCalendar $k) => OxTermin::factory()->create(array_merge([
            'ox_calendar_id' => $k->id,
            'verbund_uid'    => $uid,
            'titel'          => 'Elternabend',
            'beginn'         => '2026-10-05 18:00:00',
            'ende'           => '2026-10-05 19:30:00',
        ], $attribute)), $kalender);
    }

    public function test_Bearbeiten_aktualisiert_alle_Kopien_und_ergaenzt_neue_Kalender(): void
    {
        $this->schreiber();
        $a = OxCalendar::factory()->schreibbar()->create();
        $b = OxCalendar::factory()->schreibbar()->create();
        $c = OxCalendar::factory()->schreibbar()->create();
        [$ta, $tb] = $this->verbund([$a, $b]);

        Http::fake(['*' => Http::response('', 201, ['ETag' => '"neu"'])]);

        $this->put(route('calendar.update', $ta), [
            'kalender_ids'        => [$a->id, $b->id, $c->id],
            'titel'               => 'Elternabend Klasse 5',
            'beginn'              => '2026-10-05 18:00',
            'ende'                => '2026-10-05 20:00',
            'expected_updated_at' => $ta->updated_at->toIso8601String(),
        ])->assertRedirect()->assertSessionHas('type', 'success');

        $this->assertSame('Elternabend Klasse 5', $tb->fresh()->titel);
        $this->assertSame(3, OxTermin::where('verbund_uid', $ta->verbund_uid)->count());
        $this->assertTrue(OxTermin::where('ox_calendar_id', $c->id)->where('titel', 'Elternabend Klasse 5')->exists());
    }

    public function test_Abgewaehlter_Kalender_wird_aus_OX_entfernt(): void
    {
        $this->schreiber();
        $a = OxCalendar::factory()->schreibbar()->create();
        $b = OxCalendar::factory()->schreibbar()->create();
        [$ta, $tb] = $this->verbund([$a, $b]);

        Http::fake(['*' => Http::response('', 204, ['ETag' => '"neu"'])]);

        $this->put(route('calendar.update', $ta), [
            'kalender_ids' => [$a->id],
            'titel'        => 'Elternabend',
            'beginn'       => '2026-10-05 18:00',
            'ende'         => '2026-10-05 19:30',
        ])->assertRedirect();

        $this->assertSoftDeleted($tb);
        $this->assertNotSoftDeleted($ta);
        Http::assertSent(fn (Request $r) => $r->method() === 'DELETE' && str_contains($r->url(), basename($tb->ox_href)));
    }

    public function test_Loeschen_entfernt_standardmaessig_alle_Kopien(): void
    {
        $this->schreiber();
        $a = OxCalendar::factory()->schreibbar()->create();
        $b = OxCalendar::factory()->schreibbar()->create();
        [$ta, $tb] = $this->verbund([$a, $b]);

        Http::fake(['*' => Http::response('', 204)]);

        $this->delete(route('calendar.destroy', $ta))->assertSessionHas('type', 'success');

        $this->assertSoftDeleted($ta);
        $this->assertSoftDeleted($tb);
    }

    public function test_Loeschen_nur_dieser_Kopie_laesst_andere_bestehen(): void
    {
        $this->schreiber();
        $a = OxCalendar::factory()->schreibbar()->create();
        $b = OxCalendar::factory()->schreibbar()->create();
        [$ta, $tb] = $this->verbund([$a, $b]);

        Http::fake(['*' => Http::response('', 204)]);

        $this->delete(route('calendar.destroy', $ta), ['nur_dieser' => 1])->assertSessionHas('type', 'success');

        $this->assertSoftDeleted($ta);
        $this->assertNotSoftDeleted($tb);
    }

    public function test_Events_Endpoint_zeigt_Verbund_nur_einmal_mit_allen_Kalendern(): void
    {
        $this->actingAsWithPermission('view calendar');
        $a = OxCalendar::factory()->create();
        $b = OxCalendar::factory()->create();
        $this->verbund([$a, $b]);

        $events = $this->getJson('/calendar/events?start=2026-10-01&end=2026-10-10')
            ->assertOk()
            ->json();

        $this->assertCount(1, $events);
        $this->assertCount(2, $events[0]['extendedProps']['kalender']);
    }

    public function test_Events_Endpoint_liefert_nichts_wenn_alle_Kalender_ausgeblendet(): void
    {
        $this->actingAsWithPermission('view calendar');
        $a = OxCalendar::factory()->create();
        $this->verbund([$a]);

        $this->getJson('/calendar/events?start=2026-10-01&end=2026-10-10&calendars=none')
            ->assertOk()
            ->assertExactJson([]);
    }

    public function test_Mehrtaegiger_Termin_der_vor_dem_Zeitraum_beginnt_wird_angezeigt(): void
    {
        $this->actingAsWithPermission('view calendar');
        $a = OxCalendar::factory()->create();
        OxTermin::factory()->create([
            'ox_calendar_id' => $a->id,
            'titel'          => 'Klassenfahrt',
            'beginn'         => '2026-09-28 08:00:00',
            'ende'           => '2026-10-07 16:00:00',
        ]);

        $this->getJson('/calendar/events?start=2026-10-05&end=2026-10-12')
            ->assertOk()
            ->assertJsonFragment(['title' => 'Klassenfahrt']);
    }

    // ================================================================
    // Sync: Verbund-Kennung übersteht den Roundtrip über OX
    // ================================================================

    public function test_Sync_uebernimmt_Verbund_Kennung_aus_OX(): void
    {
        $service = app(\App\Services\OxCalendarService::class);
        $ical = "BEGIN:VCALENDAR\r\nVERSION:2.0\r\nBEGIN:VEVENT\r\nUID:abc@ox\r\nSUMMARY:Test\r\n"
            . "DTSTART:20261005T100000Z\r\nDTEND:20261005T110000Z\r\nX-MB-VERBUND:verbund-123\r\nEND:VEVENT\r\nEND:VCALENDAR\r\n";

        $this->assertSame('verbund-123', $service->parseIcal($ical)['verbund_uid']);
    }

    // ================================================================
    // Raumbuchung
    // ================================================================

    public function test_Termin_mit_Raumbuchung_bucht_Raum_einmal_fuer_alle_Kalender(): void
    {
        $this->schreiber('view roomBooking', 'create roomBooking');
        $a = OxCalendar::factory()->schreibbar()->create();
        $b = OxCalendar::factory()->schreibbar()->create();
        $raum = Room::factory()->create(['name' => 'Aula', 'bookable' => true]);

        Http::fake(['*' => Http::response('', 201, ['ETag' => '"e1"'])]);

        $this->post(route('calendar.store'), [
            'kalender_ids' => [$a->id, $b->id],
            'titel'        => 'Schulversammlung',
            'beginn'       => '2026-10-06 10:00',
            'ende'         => '2026-10-06 11:30',
            'room_id'      => $raum->id,
        ])->assertRedirect()->assertSessionHas('type', 'success');

        $termin = OxTermin::where('titel', 'Schulversammlung')->firstOrFail();
        $this->assertSame('Aula', $termin->ort);

        $this->assertSame(1, RoomBooking::where('ox_verbund_uid', $termin->verbund_uid)->count());
        $this->assertDatabaseHas('room_bookings', [
            'room_id'        => $raum->id,
            'ox_verbund_uid' => $termin->verbund_uid,
            'is_recurring'   => false,
            'start'          => '10:00',
            'end'            => '11:30',
        ]);
    }

    public function test_Belegter_Raum_verhindert_Anlegen_in_OX(): void
    {
        $this->schreiber('view roomBooking', 'create roomBooking');
        $kal  = OxCalendar::factory()->schreibbar()->create();
        $raum = Room::factory()->create(['bookable' => true]);
        RoomBooking::factory()->create([
            'room_id'      => $raum->id,
            'is_recurring' => false,
            'booking_date' => '2026-10-06',
            'weekday'      => 2,
            'start'        => '09:00',
            'end'          => '12:00',
            'name'         => 'Mathe',
        ]);

        Http::fake();

        $this->post(route('calendar.store'), [
            'kalender_ids' => [$kal->id],
            'titel'        => 'Kollision',
            'beginn'       => '2026-10-06 10:00',
            'ende'         => '2026-10-06 11:00',
            'room_id'      => $raum->id,
        ])->assertSessionHasErrors('room_id');

        Http::assertNothingSent();
        $this->assertSame(0, OxTermin::count());
    }

    public function test_Raumbuchung_ohne_Berechtigung_wird_abgelehnt(): void
    {
        $this->schreiber();
        $kal  = OxCalendar::factory()->schreibbar()->create();
        $raum = Room::factory()->create(['bookable' => true]);

        Http::fake();

        $this->post(route('calendar.store'), [
            'kalender_ids' => [$kal->id],
            'titel'        => 'Ohne Recht',
            'beginn'       => '2026-10-06 10:00',
            'ende'         => '2026-10-06 11:00',
            'room_id'      => $raum->id,
        ])->assertSessionHasErrors('room_id');

        Http::assertNothingSent();
    }

    public function test_Verschieben_zieht_Raumbuchung_mit(): void
    {
        $this->schreiber('view roomBooking', 'create roomBooking');
        $kal  = OxCalendar::factory()->schreibbar()->create();
        $raum = Room::factory()->create(['bookable' => true]);
        [$termin] = $this->verbund([$kal], [
            'beginn' => '2026-10-06 10:00:00',
            'ende'   => '2026-10-06 11:00:00',
        ]);
        RoomBooking::factory()->create([
            'room_id'        => $raum->id,
            'ox_verbund_uid' => $termin->verbund_uid,
            'is_recurring'   => false,
            'booking_date'   => '2026-10-06',
            'weekday'        => 2,
            'start'          => '10:00',
            'end'            => '11:00',
        ]);

        Http::fake(['*' => Http::response('', 204, ['ETag' => '"neu"'])]);

        $this->put(route('calendar.update', $termin), [
            'kalender_ids' => [$kal->id],
            'titel'        => 'Elternabend',
            'beginn'       => '2026-10-07 13:00',
            'ende'         => '2026-10-07 14:00',
        ])->assertRedirect()->assertSessionHas('type', 'success');

        $buchung = RoomBooking::where('ox_verbund_uid', $termin->verbund_uid)->firstOrFail();
        $this->assertSame('2026-10-07', $buchung->booking_date->format('Y-m-d'));
        $this->assertSame('13:00', \Carbon\Carbon::parse($buchung->start)->format('H:i'));
        $this->assertSame(3, (int) $buchung->weekday);
    }

    public function test_Raum_abwaehlen_gibt_Buchung_frei(): void
    {
        $this->schreiber('view roomBooking', 'create roomBooking');
        $kal  = OxCalendar::factory()->schreibbar()->create();
        $raum = Room::factory()->create(['bookable' => true]);
        [$termin] = $this->verbund([$kal]);
        $buchung = RoomBooking::factory()->create([
            'room_id'        => $raum->id,
            'ox_verbund_uid' => $termin->verbund_uid,
            'is_recurring'   => false,
            'booking_date'   => '2026-10-05',
            'start'          => '18:00',
            'end'            => '19:30',
        ]);

        Http::fake(['*' => Http::response('', 204, ['ETag' => '"neu"'])]);

        $this->put(route('calendar.update', $termin), [
            'kalender_ids' => [$kal->id],
            'titel'        => 'Elternabend',
            'beginn'       => '2026-10-05 18:00',
            'ende'         => '2026-10-05 19:30',
            'raum_aendern' => '1',
        ])->assertRedirect();

        $this->assertSoftDeleted($buchung);
    }

    public function test_Loeschen_des_Termins_gibt_Raum_frei(): void
    {
        $this->schreiber();
        $kal  = OxCalendar::factory()->schreibbar()->create();
        [$termin] = $this->verbund([$kal]);
        $buchung = RoomBooking::factory()->create([
            'ox_verbund_uid' => $termin->verbund_uid,
            'is_recurring'   => false,
            'booking_date'   => '2026-10-05',
        ]);

        Http::fake(['*' => Http::response('', 204)]);

        $this->delete(route('calendar.destroy', $termin));

        $this->assertSoftDeleted($buchung);
    }

    public function test_Raumbelegung_wird_als_Events_geliefert(): void
    {
        $this->actingAsWithPermission('view calendar', 'view roomBooking');
        $raum = Room::factory()->create(['name' => 'Raum 101']);

        // Montags wiederkehrend (A/B-unabhängig) + eine Einzelbuchung am Mittwoch
        RoomBooking::factory()->create([
            'room_id' => $raum->id, 'is_recurring' => true, 'weekday' => 1,
            'start' => '08:00', 'end' => '08:45', 'name' => 'Deutsch', 'klassen' => '5a',
        ]);
        RoomBooking::factory()->create([
            'room_id' => $raum->id, 'is_recurring' => false, 'weekday' => 3,
            'booking_date' => '2026-10-07', 'start' => '14:00', 'end' => '15:00', 'name' => 'AG',
        ]);

        $events = $this->getJson('/calendar/events?start=2026-10-05&end=2026-10-12&calendars=none&rooms=' . $raum->id)
            ->assertOk()
            ->json();

        $this->assertCount(2, $events);
        $this->assertSame('Raum 101: Deutsch (5a)', $events[0]['title']);
        $this->assertStringStartsWith('2026-10-05T08:00', $events[0]['start']);
        $this->assertTrue($events[0]['extendedProps']['isRoomBooking']);
        $this->assertStringStartsWith('2026-10-07T14:00', $events[1]['start']);
    }

    public function test_Raumbelegung_nur_mit_Berechtigung(): void
    {
        $this->actingAsWithPermission('view calendar');
        $raum = Room::factory()->create();
        RoomBooking::factory()->create(['room_id' => $raum->id, 'weekday' => 1]);

        $this->getJson('/calendar/events?start=2026-10-05&end=2026-10-12&calendars=none&rooms=' . $raum->id)
            ->assertOk()
            ->assertExactJson([]);
    }

    public function test_Raumverfuegbarkeit_markiert_belegte_Raeume(): void
    {
        $this->actingAsWithPermission('view calendar', 'create calendar events', 'view roomBooking');
        $frei    = Room::factory()->create(['name' => 'Frei']);
        $belegt  = Room::factory()->create(['name' => 'Belegt']);
        RoomBooking::factory()->create([
            'room_id' => $belegt->id, 'is_recurring' => false, 'weekday' => 2,
            'booking_date' => '2026-10-06', 'start' => '09:00', 'end' => '12:00', 'name' => 'Sport',
        ]);

        $antwort = collect($this->getJson('/calendar/raum-verfuegbarkeit?beginn=2026-10-06T10:00&ende=2026-10-06T11:00')
            ->assertOk()
            ->json())->keyBy('name');

        $this->assertTrue($antwort['Frei']['frei']);
        $this->assertFalse($antwort['Belegt']['frei']);
        $this->assertStringContainsString('Sport', $antwort['Belegt']['belegt_durch']);
    }
}
