<?php

namespace Tests\Unit\Services;

use App\Models\OxCalendar;
use App\Models\OxTermin;
use App\Services\OxCalendarService;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

/**
 * Unit-Tests für den sync-token Ablauf- und Fehlerfall in OxCalendarService.
 *
 * Deckt ab:
 * - 403 + valid-sync-token → Token löschen, Full-Sync (nicht Endlos-Fehler)
 * - 501 → Token löschen, Fallback
 * - ctag-Pseudowert → kein REPORT-Versuch
 * - RequestException vom retry()-Mechanismus wird korrekt behandelt
 */
class OxCalendarServiceSyncTokenTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config([
            'ox-calendar.url'        => 'https://ox.example.com/caldav',
            'ox-calendar.username'   => 'testuser',
            'ox-calendar.password'   => 'testpass',
            'ox-calendar.enabled'    => true,
            'ox-calendar.verify_ssl' => true,
            'ox-calendar.timeout'    => 30,
        ]);
    }

    // ─── Hilfsmethoden ───────────────────────────────────────────────────────

    private function validSyncTokenErrorBody(): string
    {
        return "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\r\n"
            . "<D:error xmlns:D=\"DAV:\">\r\n"
            . "  <valid-sync-token xmlns=\"DAV:\" />\r\n"
            . "</D:error>\r\n";
    }

    private function propfindResponse(array $events = [], ?string $syncToken = 'new-sync-token-456'): string
    {
        $responses = '';
        foreach ($events as $event) {
            $responses .= '<d:response>'
                . '<d:href>' . $event['href'] . '</d:href>'
                . '<d:propstat>'
                . '<d:prop><d:getetag>"etag-1"</d:getetag></d:prop>'
                . '<d:status>HTTP/1.1 200 OK</d:status>'
                . '</d:propstat>'
                . '</d:response>';
        }
        $token = $syncToken ? "<d:sync-token>{$syncToken}</d:sync-token>" : '';
        return '<?xml version="1.0"?><d:multistatus xmlns:d="DAV:">' . $responses . $token . '</d:multistatus>';
    }

    private function ctagResponse(string $ctag = 'ctag-789'): string
    {
        return '<?xml version="1.0"?><d:multistatus xmlns:d="DAV:" xmlns:cs="http://calendarserver.org/ns/">'
            . '<d:response><d:href>/caldav/</d:href>'
            . '<d:propstat><d:prop><cs:getctag>' . $ctag . '</cs:getctag></d:prop>'
            . '<d:status>HTTP/1.1 200 OK</d:status></d:propstat></d:response>'
            . '</d:multistatus>';
    }

    // ─── Tests ───────────────────────────────────────────────────────────────

    public function test_valid_sync_token_403_loescht_token_und_macht_full_sync(): void
    {
        $calendar = OxCalendar::factory()->create([
            'ox_calendar_id' => '/caldav/schulkalender',
            'sync_token'     => 'abgelaufener-token-123',
        ]);

        Http::fake([
            // REPORT → 403 valid-sync-token
            '*' => Http::sequence()
                ->push($this->validSyncTokenErrorBody(), 403)
                // ctag PROPFIND (Depth:0)
                ->push($this->ctagResponse('ctag-fresh'), 207)
                // Depth:1 PROPFIND für Full-Sync
                ->push($this->propfindResponse([]), 207),
        ]);

        $service = new OxCalendarService();
        $result  = $service->syncCalendar($calendar);

        // Kein Fehler – Sync erfolgreich
        $this->assertEquals(0, $result['errors']);

        // Token wurde gelöscht (ctag-Pseudowert gespeichert, kein echtes sync-token)
        $calendar->refresh();
        $this->assertStringStartsWith('ctag:', $calendar->sync_token);
        $this->assertStringNotContainsString('abgelaufener-token-123', $calendar->sync_token);
    }

    public function test_valid_sync_token_403_via_request_exception_wird_korrekt_behandelt(): void
    {
        // Simuliert den Fall, dass retry() mit throw=true eine RequestException wirft
        $calendar = OxCalendar::factory()->create([
            'ox_calendar_id' => '/caldav/schulkalender',
            'sync_token'     => 'alter-token-xyz',
        ]);

        // Http::fake() gibt 403 zurück – das löst die RequestException im retry()-Mechanismus aus
        Http::fake([
            '*' => Http::sequence()
                ->push($this->validSyncTokenErrorBody(), 403)
                ->push($this->ctagResponse(), 207)
                ->push($this->propfindResponse(), 207),
        ]);

        $service = new OxCalendarService();
        $result  = $service->syncCalendar($calendar);

        // Muss als Erfolg (kein error) geloggt werden, nicht als Sync-Fehler
        $this->assertEquals(0, $result['errors']);

        $calendar->refresh();
        $this->assertNotEquals('alter-token-xyz', $calendar->sync_token);
    }

    public function test_501_loescht_sync_token_und_faellt_auf_ctag_zurueck(): void
    {
        $calendar = OxCalendar::factory()->create([
            'ox_calendar_id' => '/caldav/schulkalender',
            'sync_token'     => 'echter-sync-token',
        ]);

        Http::fake([
            '*' => Http::sequence()
                ->push('Not Implemented', 501)
                ->push($this->ctagResponse(), 207)
                ->push($this->propfindResponse(), 207),
        ]);

        $service = new OxCalendarService();
        $result  = $service->syncCalendar($calendar);

        $this->assertEquals(0, $result['errors']);

        $calendar->refresh();
        $this->assertStringStartsWith('ctag:', $calendar->sync_token);
    }

    public function test_ctag_pseudotoken_loest_keinen_report_request_aus(): void
    {
        $calendar = OxCalendar::factory()->create([
            'ox_calendar_id' => '/caldav/schulkalender',
            'sync_token'     => 'ctag:bisheriger-ctag',
        ]);

        $reportCalled = false;

        Http::fake(function ($request) use (&$reportCalled, $calendar) {
            if ($request->method() === 'REPORT') {
                $reportCalled = true;
            }
            // ctag PROPFIND
            if ($request->method() === 'PROPFIND' && str_contains($request->body(), 'getctag')) {
                return Http::response($this->ctagResponse('bisheriger-ctag'), 207);
            }
            return Http::response($this->propfindResponse(), 207);
        });

        $service = new OxCalendarService();
        $service->syncCalendar($calendar);

        $this->assertFalse($reportCalled, 'Bei ctag-Pseudotoken darf kein REPORT-Request gesendet werden');
    }

    public function test_ctag_unveraendert_skip_full_sync(): void
    {
        $calendar = OxCalendar::factory()->create([
            'ox_calendar_id' => '/caldav/schulkalender',
            'sync_token'     => 'ctag:unveraenderter-ctag',
        ]);

        $propfindDepth1Called = false;

        Http::fake(function ($request) use (&$propfindDepth1Called) {
            if ($request->method() === 'PROPFIND' && $request->header('Depth') === '1') {
                $propfindDepth1Called = true;
            }
            // ctag unchanged
            return Http::response($this->ctagResponse('unveraenderter-ctag'), 207);
        });

        $service = new OxCalendarService();
        $result  = $service->syncCalendar($calendar);

        $this->assertFalse($propfindDepth1Called, 'Bei unverändertem ctag darf kein Depth:1-PROPFIND gesendet werden');
        $this->assertEquals(0, $result['errors']);
        $this->assertEquals(0, $result['created']);
    }

    public function test_kein_sync_token_springt_direkt_zu_ctag(): void
    {
        $calendar = OxCalendar::factory()->create([
            'ox_calendar_id' => '/caldav/schulkalender',
            'sync_token'     => null,
        ]);

        $reportCalled = false;

        Http::fake(function ($request) use (&$reportCalled) {
            if ($request->method() === 'REPORT') {
                $reportCalled = true;
            }
            if ($request->method() === 'PROPFIND' && str_contains($request->body(), 'getctag')) {
                return Http::response($this->ctagResponse(), 207);
            }
            return Http::response($this->propfindResponse(), 207);
        });

        $service = new OxCalendarService();
        $service->syncCalendar($calendar);

        $this->assertFalse($reportCalled, 'Ohne sync_token darf kein REPORT-Request gesendet werden');
    }
}

