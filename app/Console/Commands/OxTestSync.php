<?php

namespace App\Console\Commands;

use App\Models\OxCalendar;
use App\Services\OxCalendarService;
use Illuminate\Console\Command;

class OxTestSync extends Command
{
    protected $signature = 'ox:test-sync
                            {url? : CalDAV-URL des zu testenden Kalenders}
                            {--persist : Kalender in der DB anlegen und echten Sync durchfuehren}
                            {--limit=3 : Maximale Anzahl Events die geparst und angezeigt werden}
                            {--name=Test-Kalender : Name des Kalenders (nur bei --persist)}';

    protected $description = 'Testet CalDAV-Verbindung, Event-Listing und iCal-Parsing fuer einen bestimmten Kalender-URL';

    public function handle(OxCalendarService $service): int
    {
        $url = $this->argument('url')
            ?? 'https://webmail.dllp.schule/dav/caldav/Y2FsOi8vMC8yNjcz';

        $this->info('OX-Kalender Sync-Test');
        $this->info('   URL: ' . $url);
        $this->newLine();

        // ----------------------------------------------------------------
        // Schritt 1: Verbindungstest (Basis-URL)
        // ----------------------------------------------------------------
        $this->line('-- Schritt 1: Verbindungstest (PROPFIND Depth:0 auf Basis-URL)');

        $connResult = $service->testConnection();

        if ($connResult['success']) {
            $this->info('   OK: ' . $connResult['message'] . ' (HTTP ' . ($connResult['status'] ?? '?') . ')');
            $this->line('   DAV-Header: ' . ($connResult['dav_header'] ?? '-'));
        } else {
            $this->warn('   WARNUNG: ' . $connResult['message']);
            if (!empty($connResult['status'])) {
                $this->line('   HTTP-Status: ' . $connResult['status']);
            }
        }

        $this->newLine();

        // ----------------------------------------------------------------
        // Schritt 2: Kalender-URL diagnostizieren
        // ----------------------------------------------------------------
        $this->line('-- Schritt 2: Kalender-URL diagnostizieren');

        $probe  = $service->probeCalendarUrl($url);
        $diag   = $probe['diagnostics'] ?? [];
        $failed = false;

        // OPTIONS
        if (isset($diag['options_error'])) {
            $this->warn('   OPTIONS:      Fehler - ' . $diag['options_error']);
        } else {
            $this->line('   OPTIONS:      HTTP ' . ($diag['options_status'] ?? '?'));
            $this->line('   Allow-Header: ' . ($diag['allowed_methods'] ?? '-'));
            $this->line('   DAV-Header:   ' . ($diag['dav_header'] ?? '-'));
        }

        // PROPFIND Depth:0
        if (isset($diag['propfind_depth0_error'])) {
            $this->warn('   PROPFIND/D0:  Fehler - ' . $diag['propfind_depth0_error']);
        } else {
            $this->line('   PROPFIND/D0:  HTTP ' . ($diag['propfind_depth0_status'] ?? '?'));
            if (!empty($diag['displayname'])) {
                $this->line('   Displayname:  ' . $diag['displayname']);
            }
            if (!empty($diag['ctag'])) {
                $this->line('   ctag:         ' . $diag['ctag']);
            }
            if (!empty($diag['sync_token_raw'])) {
                $this->line('   sync-token:   ' . $diag['sync_token_raw']);
            }
        }

        $this->newLine();
        $this->line('   PROPFIND/D1 (Event-Listing):');

        if (!$probe['success']) {
            $this->error('   FEHLER: ' . $probe['message']);
            if (!empty($diag['propfind_depth0_body'])) {
                $this->newLine();
                $this->line('Rohe Antwort (Depth:0):');
                $this->line($diag['propfind_depth0_body']);
            }
            $failed = true;
        }

        $events = $probe['events'];

        if (!$failed) {
            $this->info('   OK: ' . $probe['message']);

            if (!empty($probe['sync_token'])) {
                $this->line('   sync-token: ' . $probe['sync_token']);
            } else {
                $this->warn('   Kein sync-token - Server unterstuetzt RFC 6578 nicht. Fallback auf ctag/etag.');
            }

            $this->newLine();
        }

        // ----------------------------------------------------------------
        // Schritt 3: Event-Liste + iCal-Parsing
        // ----------------------------------------------------------------
        if (!$failed && !empty($events)) {
            $limit      = (int) $this->option('limit');
            $tableRows  = [];
            $sampleUrls = [];

            foreach (array_slice($events, 0, max($limit, 10)) as $ev) {
                $tableRows[] = [
                    $ev['href'],
                    $ev['etag'] ?? '-',
                    $ev['status'],
                ];
                if (count($sampleUrls) < $limit) {
                    $sampleUrls[] = $ev['href'];
                }
            }

            if (count($events) > 10) {
                $tableRows[] = ['... (' . (count($events) - 10) . ' weitere)', '', ''];
            }

            $this->table(['href', 'etag', 'HTTP-Status'], $tableRows);
            $this->newLine();

            $this->line('-- Schritt 3: iCal-Parsing (' . count($sampleUrls) . ' Beispiel-Events)');

            $parseErrors = 0;
            foreach ($sampleUrls as $i => $href) {
                // Absoluter Pfad (beginnt mit '/') → schema+host aus der Kalender-URL + href
                if (str_starts_with($href, 'http')) {
                    $eventUrl = $href;
                } elseif (str_starts_with($href, '/')) {
                    $parsed   = parse_url($url);
                    $eventUrl = $parsed['scheme'] . '://' . $parsed['host']
                        . (isset($parsed['port']) ? ':' . $parsed['port'] : '')
                        . $href;
                } else {
                    $eventUrl = rtrim($url, '/') . '/' . $href;
                }
                $fetchResult = $service->fetchEventFromUrl($eventUrl);

                $this->line('   Event ' . ($i + 1) . ': ' . $href);

                if (!$fetchResult['success']) {
                    $this->error('      FEHLER Fetch: ' . $fetchResult['message']);
                    $parseErrors++;
                    continue;
                }

                try {
                    $parsed = $service->parseIcal($fetchResult['body']);

                    $this->line('      Titel:     ' . $parsed['titel']);
                    $this->line('      Beginn:    ' . ($parsed['beginn'] ?? '-'));
                    $this->line('      Ende:      ' . ($parsed['ende'] ?? '-'));
                    $this->line('      Ganztaeig: ' . ($parsed['ganztaegig'] ? 'Ja' : 'Nein'));
                    $this->line('      Ort:       ' . ($parsed['ort'] ?? '-'));
                    $this->line('      Status:    ' . ($parsed['status'] ?? '-'));
                    $this->line('      UID:       ' . ($parsed['uid'] ?? '-'));

                    if (!empty($parsed['rrule'])) {
                        $this->line('      RRULE:     ' . $parsed['rrule']);
                    }

                    $teilnehmer = $service->parseTeilnehmer($fetchResult['body']);
                    if (!empty($teilnehmer)) {
                        $this->line('      Teilnehmer: ' . implode(', ', array_column($teilnehmer, 'email')));
                    }
                } catch (\Exception $e) {
                    $this->error('      FEHLER Parse: ' . $e->getMessage());
                    $parseErrors++;
                }

                $this->newLine();
            }

            if ($parseErrors === 0) {
                $this->info('   OK: Alle Sample-Events erfolgreich geparst.');
            } else {
                $this->warn('   ' . $parseErrors . ' Events konnten nicht geparst werden.');
            }

            $this->newLine();
        }

        // ----------------------------------------------------------------
        // Schritt 4: Echter Sync (optional mit --persist)
        // ----------------------------------------------------------------
        if (!$failed && $this->option('persist')) {
            $this->line('-- Schritt 4: Echter Sync (--persist aktiviert)');

            $calendar = OxCalendar::firstOrCreate(
                ['ox_calendar_id' => $url],
                [
                    'name'       => $this->option('name'),
                    'sichtbar'   => true,
                    'schreibbar' => false,
                    'farbe'      => '#3B82F6',
                ]
            );

            $this->line('   Kalender: ' . ($calendar->wasRecentlyCreated ? 'Neu erstellt' : 'Bereits vorhanden') . ' (ID ' . $calendar->id . ')');
            $this->line('   Starte Sync...');

            $result = $service->syncCalendar($calendar);

            $this->newLine();
            $this->table(
                ['Neu', 'Aktualisiert', 'Geloescht', 'Fehler', 'Methode'],
                [[$result['created'], $result['updated'], $result['deleted'], $result['errors'], $result['method']]]
            );

            if ($result['errors'] === 0) {
                $this->info('   OK: Sync erfolgreich abgeschlossen.');
            } else {
                $this->warn('   Sync mit ' . $result['errors'] . ' Fehler(n) abgeschlossen.');
            }
        } elseif (!$failed) {
            $this->line('-- Schritt 4: Echter Sync uebersprungen (kein --persist)');
            $this->line('   Ausfuehren mit: php artisan ox:test-sync "' . $url . '" --persist');
        }

        $this->newLine();
        $this->info('Test abgeschlossen.');

        // ----------------------------------------------------------------
        // Diagnose-Zusammenfassung bei Fehler
        // ----------------------------------------------------------------
        if ($failed) {
            $this->newLine();
            $this->line('============================================================');
            $this->line('  DIAGNOSE-ZUSAMMENFASSUNG');
            $this->line('============================================================');
            $this->newLine();

            $allowHeader = $diag['allowed_methods'] ?? '';
            $noPropfind  = !str_contains(strtoupper($allowHeader), 'PROPFIND');
            $noDav       = empty($diag['dav_header']) || $diag['dav_header'] === '(nicht gesetzt)';

            if (isset($diag['options_status']) && $noPropfind && $noDav) {
                $this->error('  Problem: CalDAV/WebDAV ist auf dem Server NICHT konfiguriert.');
                $this->line('  Allow-Header: ' . ($allowHeader ?: 'GET,POST,OPTIONS,HEAD'));
                $this->line('  Kein DAV-Header, kein PROPFIND erlaubt.');
                $this->newLine();
                $this->line('  Moegliche Ursachen:');
                $this->line('  1. Apache mod_dav / mod_dav_caldav ist nicht geladen');
                $this->line('  2. Reverse-Proxy-Mapping zu OX CalDAV fehlt');
                $this->line('  3. OX CalDAV-Dienst auf dem Backend nicht gestartet');
                $this->newLine();
                $this->line('  Pruefbefehle (auf dem Server als root):');
                $this->line('  apache2ctl -M | grep -i dav');
                $this->line('  curl -v -X PROPFIND http://localhost:8009/servlet/dav/ -u "user:pass"');
            }

            $this->newLine();
            $this->line('  Kalender-URL:  ' . $url);
            $this->line('  Basis-URL:     ' . config('ox-calendar.url'));
            $this->line('  Benutzer:      ' . config('ox-calendar.username'));
            $this->line('============================================================');

            return Command::FAILURE;
        }

        return Command::SUCCESS;
    }
}


