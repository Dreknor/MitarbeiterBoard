<?php

namespace Tests\Feature;

use App\Models\OxCalendar;
use App\Models\OxTermin;
use App\Services\Calendar\IcsImportService;
use App\Services\OxCalendarService;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Kalender, in die OX das Dienstkonto nur lesen lässt (HTTP 403 beim Anlegen),
 * werden erkannt, in den Formularen gesperrt angezeigt und erklärt.
 */
class CalendarOxSchreibrechtTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();

        config([
            'ox-calendar.url'      => 'https://ox.example.com/caldav',
            'ox-calendar.username' => 'dienstkonto@example.com',
            'ox-calendar.password' => 'testpass',
            'ox-calendar.enabled'  => true,
        ]);
    }

    private function rechteAntwort(array $privilegien): string
    {
        $p = implode('', array_map(fn ($n) => "<D:privilege><D:$n/></D:privilege>", $privilegien));

        return '<?xml version="1.0" encoding="UTF-8"?>'
            . '<D:multistatus xmlns:D="DAV:"><D:response><D:href>/caldav/x/</D:href><D:propstat>'
            . "<D:prop><D:current-user-privilege-set>$p</D:current-user-privilege-set></D:prop>"
            . '<D:status>HTTP/1.1 200 OK</D:status></D:propstat></D:response></D:multistatus>';
    }

    private function ics(int $anzahl): string
    {
        $zeilen = ['BEGIN:VCALENDAR', 'VERSION:2.0', 'PRODID:-//Test//DE'];
        for ($i = 1; $i <= $anzahl; $i++) {
            array_push($zeilen, 'BEGIN:VEVENT', "UID:t$i@test", "SUMMARY:Termin $i",
                'DTSTART;VALUE=DATE:2030110' . $i, 'DTEND;VALUE=DATE:2030110' . ($i + 1), 'END:VEVENT');
        }
        $zeilen[] = 'END:VCALENDAR';

        return implode("\r\n", $zeilen) . "\r\n";
    }

    public function test_Rechtepruefung_erkennt_nur_lesbaren_Kalender(): void
    {
        $lesen     = OxCalendar::factory()->schreibbar()->create(['ox_calendar_id' => 'nurlesen']);
        $schreiben = OxCalendar::factory()->schreibbar()->create(['ox_calendar_id' => 'schreiben']);

        Http::fake([
            '*nurlesen*'  => Http::response($this->rechteAntwort(['read-acl', 'read', 'write-properties']), 207),
            '*schreiben*' => Http::response($this->rechteAntwort(['read', 'write', 'write-content', 'bind', 'unbind']), 207),
        ]);

        $service = app(OxCalendarService::class);
        $this->assertFalse($service->oxRechtePruefen($lesen));
        $this->assertTrue($service->oxRechtePruefen($schreiben));

        $this->assertFalse($lesen->fresh()->ox_schreibrecht);
        $this->assertNotNull($lesen->fresh()->ox_rechte_geprueft_at);
        $this->assertTrue($schreiben->fresh()->ox_schreibrecht);
    }

    public function test_Unklare_Antwort_sperrt_nichts(): void
    {
        $kal = OxCalendar::factory()->schreibbar()->create(['ox_schreibrecht' => true]);

        Http::fake(['*' => Http::response('Fehler', 500)]);

        $this->assertNull(app(OxCalendarService::class)->oxRechtePruefen($kal));
        $this->assertTrue($kal->fresh()->ox_schreibrecht);
    }

    public function test_Sync_entsperrt_Kalender_wieder(): void
    {
        $kal = OxCalendar::factory()->schreibbar()->create(['ox_schreibrecht' => false]);

        Http::fake(function (Request $r) {
            if ($r->method() === 'PROPFIND' && ($r->header('Depth')[0] ?? '') === '0') {
                return Http::response($this->rechteAntwort(['read', 'bind']), 207);
            }

            return Http::response('<?xml version="1.0"?><d:multistatus xmlns:d="DAV:"/>', 207);
        });

        app(OxCalendarService::class)->syncAll();

        $this->assertTrue($kal->fresh()->ox_schreibrecht);
    }

    public function test_Gesperrter_Kalender_ist_nicht_schreibbar_aber_wird_erklaert(): void
    {
        $user = $this->actingAsWithPermission('view calendar', 'create calendar events');
        $ok   = OxCalendar::factory()->schreibbar()->create(['name' => 'Grundschule']);
        $zu   = OxCalendar::factory()->schreibbar()->create(['name' => 'Oberschule 5/6', 'ox_schreibrecht' => false]);

        $service = app(OxCalendarService::class);
        $this->assertTrue($service->canWriteCalendar($user, $ok));
        $this->assertFalse($service->canWriteCalendar($user, $zu));
        $this->assertTrue($service->gesperrtDurchOx($user, $zu));
        $this->assertFalse($service->gesperrtDurchOx($user, $ok));

        $this->get(route('calendar.index'))
            ->assertOk()
            ->assertSee('Oberschule 5/6')
            ->assertSee('nur lesbar')
            ->assertSee('nur Leserechte', false);
    }

    public function test_Ohne_App_Schreibrecht_wird_keine_OX_Sperre_angezeigt(): void
    {
        $user = $this->actingAsWithPermission('view calendar', 'create calendar events');
        $kal  = OxCalendar::factory()->create(['schreibbar' => false, 'ox_schreibrecht' => false]);

        $this->assertFalse(app(OxCalendarService::class)->gesperrtDurchOx($user, $kal));
    }

    public function test_Import_meldet_OX_Sperre_einmal_statt_pro_Termin(): void
    {
        $user = $this->actingAsWithPermission('view calendar', 'create calendar events', 'import calendar events');
        $kal  = OxCalendar::factory()->schreibbar()->create(['name' => 'Oberschule 5/6']);

        $service   = app(IcsImportService::class);
        $eintraege = $service->analysieren($this->ics(5));
        $token     = $service->vorschauSpeichern($user, 'Jg_5_6.ics', $eintraege);

        Http::fake(['*' => Http::response('', 403)]);

        $this->post(route('calendar.import.store', $token), [
            'kalender_ids' => [$kal->id],
            'auswahl'      => [0, 1, 2, 3, 4],
        ])->assertRedirect()
          ->assertSessionHas('type', 'danger')
          ->assertSessionHas('Meldung', fn ($m) => str_contains($m, '„Oberschule 5/6“')
              && str_contains($m, 'nur Leserechte')
              && !str_contains($m, 'Termin 2'));

        // Nach dem ersten 403 kein weiterer Versuch
        Http::assertSentCount(1);
        $this->assertSame(0, OxTermin::count());
        $this->assertFalse($kal->fresh()->ox_schreibrecht);

        // Vorschau zeigt den Kalender jetzt gesperrt mit Erklärung
        $this->get(route('calendar.import.show', $token))
            ->assertOk()
            ->assertSee('Ein Import ist gerade nicht möglich')
            ->assertSee('Oberschule 5/6');
    }

    public function test_Import_in_gesperrten_Kalender_wird_mit_Erklaerung_abgelehnt(): void
    {
        $user = $this->actingAsWithPermission('view calendar', 'create calendar events', 'import calendar events');
        OxCalendar::factory()->schreibbar()->create(['name' => 'Grundschule']);
        $zu = OxCalendar::factory()->schreibbar()->create(['name' => 'Oberschule 5/6', 'ox_schreibrecht' => false]);

        $service = app(IcsImportService::class);
        $token   = $service->vorschauSpeichern($user, 'x.ics', $service->analysieren($this->ics(1)));

        Http::fake();

        $this->post(route('calendar.import.store', $token), [
            'kalender_ids' => [$zu->id],
            'auswahl'      => [0],
        ])->assertSessionHasErrors(['kalender_ids' => '„Oberschule 5/6“: ' . OxCalendar::OX_SPERRE_HINWEIS]);

        Http::assertNothingSent();

        $this->get(route('calendar.import.show', $token))
            ->assertOk()
            ->assertSee('nur lesbar')
            ->assertSee('Grundschule');
    }

    public function test_Admin_sieht_Sperre_mit_Dienstkonto_und_Abhilfe(): void
    {
        $this->actingAsWithPermission('view calendar', 'manage calendar');
        OxCalendar::factory()->schreibbar()->create(['name' => 'Oberschule 7/8', 'ox_schreibrecht' => false]);

        Http::fake(['*' => Http::response('', 207)]);

        $this->get(route('calendar.admin'))
            ->assertOk()
            ->assertSee('OX: nur lesen')
            ->assertSee('dienstkonto@example.com')
            ->assertSee('Objekte erstellen', false);
    }
}
