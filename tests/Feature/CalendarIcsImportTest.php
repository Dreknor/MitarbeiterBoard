<?php

namespace Tests\Feature;

use App\Models\OxCalendar;
use App\Models\OxTermin;
use App\Services\Calendar\IcsImportService;
use Illuminate\Http\Client\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class CalendarIcsImportTest extends TestCase
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

    private function ics(): string
    {
        return implode("\r\n", [
            'BEGIN:VCALENDAR',
            'VERSION:2.0',
            'PRODID:-//Test//DE',
            'BEGIN:VTIMEZONE',
            'TZID:Europe/Berlin',
            'BEGIN:STANDARD',
            'DTSTART:19701025T030000',
            'TZOFFSETFROM:+0200',
            'TZOFFSETTO:+0100',
            'RRULE:FREQ=YEARLY;BYMONTH=10;BYDAY=-1SU',
            'END:STANDARD',
            'BEGIN:DAYLIGHT',
            'DTSTART:19700329T020000',
            'TZOFFSETFROM:+0100',
            'TZOFFSETTO:+0200',
            'RRULE:FREQ=YEARLY;BYMONTH=3;BYDAY=-1SU',
            'END:DAYLIGHT',
            'END:VTIMEZONE',
            // 1: Zeittermin in der Zukunft
            'BEGIN:VEVENT',
            'UID:zukunft@test',
            'SUMMARY:Tag der offenen Tür',
            'LOCATION:Aula',
            'DESCRIPTION:Alle Klassen',
            'DTSTART;TZID=Europe/Berlin:20301107T100000',
            'DTEND;TZID=Europe/Berlin:20301107T140000',
            'END:VEVENT',
            // 2: Ganztägig (Herbstferien)
            'BEGIN:VEVENT',
            'UID:ferien@test',
            'SUMMARY:Herbstferien',
            'DTSTART;VALUE=DATE:20301020',
            'DTEND;VALUE=DATE:20301101',
            'END:VEVENT',
            // 3: Vergangen
            'BEGIN:VEVENT',
            'UID:alt@test',
            'SUMMARY:Alter Termin',
            'DTSTART:20200101T090000Z',
            'DTEND:20200101T100000Z',
            'END:VEVENT',
            // 4: Serie mit Ausnahme
            'BEGIN:VEVENT',
            'UID:serie@test',
            'SUMMARY:AG Schach',
            'DTSTART;TZID=Europe/Berlin:20301104T140000',
            'DTEND;TZID=Europe/Berlin:20301104T153000',
            'RRULE:FREQ=WEEKLY;COUNT=5',
            'EXDATE;TZID=Europe/Berlin:20301111T140000',
            'END:VEVENT',
            'BEGIN:VEVENT',
            'UID:serie@test',
            'RECURRENCE-ID;TZID=Europe/Berlin:20301118T140000',
            'SUMMARY:AG Schach (verlegt)',
            'DTSTART;TZID=Europe/Berlin:20301118T150000',
            'DTEND;TZID=Europe/Berlin:20301118T163000',
            'END:VEVENT',
            'END:VCALENDAR',
            '',
        ]);
    }

    // ================================================================
    // Analyse
    // ================================================================

    public function test_Analyse_liefert_Termine_sortiert_mit_Metadaten(): void
    {
        $eintraege = app(IcsImportService::class)->analysieren($this->ics());

        $this->assertCount(4, $eintraege);
        $titel = array_column($eintraege, 'titel');
        $this->assertSame(['Alter Termin', 'Herbstferien', 'AG Schach', 'Tag der offenen Tür'], $titel);

        [$alt, $ferien, $serie, $tdot] = $eintraege;

        $this->assertTrue($alt['vergangen']);
        $this->assertTrue($ferien['ganztaegig']);
        $this->assertSame('2030-10-20 00:00:00', $ferien['beginn']);
        $this->assertSame('2030-11-01 00:00:00', $ferien['ende']);

        $this->assertSame('FREQ=WEEKLY;COUNT=5', $serie['rrule']);
        $this->assertCount(1, $serie['exdates']);
        $this->assertStringContainsString('abweichende Einzeltermin', $serie['warnungen'][0]);

        $this->assertSame('2030-11-07 10:00:00', $tdot['beginn']);
        $this->assertSame('Aula', $tdot['ort']);
        $this->assertSame([0, 1, 2, 3], array_column($eintraege, 'index'));
    }

    public function test_Ungueltige_Datei_wird_abgelehnt(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        app(IcsImportService::class)->analysieren('das ist kein kalender');
    }

    public function test_Hinweise_werden_an_Beschreibung_angehaengt(): void
    {
        $text = app(IcsImportService::class)->mitHinweisen('Original', 'Für alle', 'Nur hier');

        $this->assertSame("Original\n\nHinweis: Für alle\n\nHinweis: Nur hier", $text);
        $this->assertNull(app(IcsImportService::class)->mitHinweisen(null, '  ', ''));
    }

    // ================================================================
    // Upload → Vorschau → Import
    // ================================================================

    public function test_Upload_fuehrt_zur_Vorschau(): void
    {
        $this->actingAsWithPermission('view calendar', 'create calendar events', 'import calendar events');
        OxCalendar::factory()->schreibbar()->create(['name' => 'Schulkalender']);

        $datei = UploadedFile::fake()->createWithContent('jahresplan.ics', $this->ics());

        $response = $this->post(route('calendar.import.preview'), ['datei' => $datei]);
        $response->assertRedirect();

        $this->get($response->headers->get('Location'))
            ->assertOk()
            ->assertSee('jahresplan.ics')
            ->assertSee('Tag der offenen Tür')
            ->assertSee('Schulkalender');
    }

    public function test_Upload_ohne_Import_Berechtigung_ist_verboten(): void
    {
        $this->actingAsWithPermission('view calendar', 'create calendar events');

        $this->post(route('calendar.import.preview'), [
            'datei' => UploadedFile::fake()->createWithContent('x.ics', $this->ics()),
        ])->assertForbidden();
    }

    public function test_Falsche_Dateiendung_wird_abgelehnt(): void
    {
        $this->actingAsWithPermission('view calendar', 'create calendar events', 'import calendar events');

        $this->post(route('calendar.import.preview'), [
            'datei' => UploadedFile::fake()->createWithContent('x.txt', $this->ics()),
        ])->assertSessionHasErrors('datei');
    }

    public function test_Import_uebernimmt_nur_ausgewaehlte_Termine_mit_Hinweisen_in_mehrere_Kalender(): void
    {
        $user = $this->actingAsWithPermission('view calendar', 'create calendar events', 'import calendar events');
        $a = OxCalendar::factory()->schreibbar()->create(['name' => 'Kollegium']);
        $b = OxCalendar::factory()->schreibbar()->create(['name' => 'Eltern']);

        $service   = app(IcsImportService::class);
        $eintraege = $service->analysieren($this->ics());
        $token     = $service->vorschauSpeichern($user, 'plan.ics', $eintraege);

        Http::fake(['*' => Http::response('', 201, ['ETag' => '"e1"'])]);

        // Index 1 = Herbstferien, 3 = Tag der offenen Tür
        $this->post(route('calendar.import.store', $token), [
            'kalender_ids' => [$a->id, $b->id],
            'auswahl'      => [1, 3],
            'hinweis_alle' => 'Aus dem Jahresplan',
            'hinweise'     => [3 => 'Aufsicht einteilen'],
        ])->assertRedirect(route('calendar.index'))
          ->assertSessionHas('type', 'success');

        $this->assertSame(4, OxTermin::count());
        $this->assertFalse(OxTermin::where('titel', 'Alter Termin')->exists());

        $tdot = OxTermin::where('titel', 'Tag der offenen Tür')->where('ox_calendar_id', $a->id)->firstOrFail();
        $this->assertSame("Alle Klassen\n\nHinweis: Aus dem Jahresplan\n\nHinweis: Aufsicht einteilen", $tdot->beschreibung);
        $this->assertSame(2, OxTermin::where('verbund_uid', $tdot->verbund_uid)->count());

        $ferien = OxTermin::where('titel', 'Herbstferien')->firstOrFail();
        $this->assertTrue($ferien->ganztaegig);
        $this->assertSame('2030-11-01', $ferien->ende->format('Y-m-d'));

        Http::assertSentCount(4);
        // iCal-Zeilen sind nach 75 Zeichen gefaltet (RFC 5545) → vor dem Vergleich entfalten
        Http::assertSent(fn (Request $r) => str_contains(str_replace("\r\n ", '', $r->body()), 'Hinweis: Aufsicht einteilen'));

        // Vorschau ist nach erfolgreichem Import verbraucht
        $this->assertNull($service->vorschauLaden($user, $token));
    }

    public function test_Import_einer_Serie_uebernimmt_RRULE_und_EXDATE(): void
    {
        $user = $this->actingAsWithPermission('view calendar', 'create calendar events', 'import calendar events');
        $kal  = OxCalendar::factory()->schreibbar()->create();

        $service = app(IcsImportService::class);
        $token   = $service->vorschauSpeichern($user, 'plan.ics', $service->analysieren($this->ics()));

        Http::fake(['*' => Http::response('', 201, ['ETag' => '"e1"'])]);

        $this->post(route('calendar.import.store', $token), [
            'kalender_ids' => [$kal->id],
            'auswahl'      => [2],
        ])->assertRedirect();

        $serie = OxTermin::where('titel', 'AG Schach')->firstOrFail();
        $this->assertSame('FREQ=WEEKLY;COUNT=5', $serie->rrule);
        $this->assertStringContainsString('EXDATE', $serie->raw_ical);
    }

    public function test_Import_in_fremden_Kalender_ist_verboten(): void
    {
        $user = $this->actingAsWithPermission('view calendar', 'create calendar events', 'import calendar events');
        $kal  = OxCalendar::factory()->create(['schreibbar' => false]);

        $service = app(IcsImportService::class);
        $token   = $service->vorschauSpeichern($user, 'plan.ics', $service->analysieren($this->ics()));

        Http::fake();

        $this->post(route('calendar.import.store', $token), [
            'kalender_ids' => [$kal->id],
            'auswahl'      => [1],
        ])->assertForbidden();

        Http::assertNothingSent();
    }

    public function test_Vorschau_eines_anderen_Users_ist_nicht_abrufbar(): void
    {
        $fremd   = \App\Models\User::factory()->create();
        $service = app(IcsImportService::class);
        $token   = $service->vorschauSpeichern($fremd, 'plan.ics', $service->analysieren($this->ics()));

        $this->actingAsWithPermission('view calendar', 'create calendar events', 'import calendar events');

        $this->get(route('calendar.import.show', $token))
            ->assertRedirect(route('calendar.index'))
            ->assertSessionHas('type', 'warning');
    }

    public function test_Duplikate_in_Zielkalendern_werden_erkannt(): void
    {
        $kal = OxCalendar::factory()->create(['name' => 'Schulkalender']);
        OxTermin::factory()->create([
            'ox_calendar_id' => $kal->id,
            'titel'          => 'Tag der offenen Tür',
            'beginn'         => '2030-11-07 10:00:00',
            'ende'           => '2030-11-07 14:00:00',
        ]);

        $service   = app(IcsImportService::class);
        $eintraege = $service->duplikateMarkieren($service->analysieren($this->ics()), collect([$kal]));

        $tdot = collect($eintraege)->firstWhere('titel', 'Tag der offenen Tür');
        $this->assertSame([['id' => $kal->id, 'name' => 'Schulkalender']], $tdot['duplikate']);
        $this->assertSame([], collect($eintraege)->firstWhere('titel', 'Herbstferien')['duplikate']);
    }
}
