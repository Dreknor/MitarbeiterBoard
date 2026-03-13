<?php

namespace App\Services;

use App\Exceptions\CtagNotSupportedException;
use App\Exceptions\SyncTokenNotSupportedException;
use App\Models\OxCalendar;
use App\Models\OxSyncLog;
use App\Models\OxTermin;
use App\Models\OxTerminTeilnehmer;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Sabre\VObject\Reader;

class OxCalendarService
{
    // ========================================================================
    // Konfiguration & Verbindung
    // ========================================================================

    /**
     * Prüft ob das Kalender-Modul aktiviert ist.
     */
    public function isEnabled(): bool
    {
        return (bool) config('ox-calendar.enabled', false)
            && !empty(config('ox-calendar.url'))
            && !empty(config('ox-calendar.username'));
    }

    /**
     * Testet die CalDAV-Verbindung zum OX-Server.
     * Führt einen PROPFIND auf die Basis-URL aus.
     *
     * @return array{success: bool, message: string, status?: int}
     */
    public function testConnection(): array
    {
        if (!$this->isEnabled()) {
            return ['success' => false, 'message' => 'Kalender-Modul ist nicht aktiviert.'];
        }

        try {
            $response = $this->httpClient()->send('PROPFIND', config('ox-calendar.url'), [
                'headers' => [
                    'Depth'        => '0',
                    'Content-Type' => 'application/xml; charset=utf-8',
                ],
                'body' => '<?xml version="1.0" encoding="utf-8"?>
                    <d:propfind xmlns:d="DAV:">
                        <d:prop>
                            <d:displayname/>
                        </d:prop>
                    </d:propfind>',
            ]);

            if ($response->successful()) {
                return ['success' => true, 'message' => 'Verbindung erfolgreich.', 'status' => $response->status()];
            }

            return [
                'success' => false,
                'message' => 'Server antwortete mit Status ' . $response->status(),
                'status'  => $response->status(),
            ];
        } catch (\Exception $e) {
            return ['success' => false, 'message' => 'Verbindungsfehler: ' . $e->getMessage()];
        }
    }

    // ========================================================================
    // CalDAV HTTP-Kommunikation
    // ========================================================================

    /**
     * Konfigurierter HTTP-Client mit BasicAuth, Retry und SSL.
     */
    protected function httpClient(): PendingRequest
    {
        return Http::withBasicAuth(
                config('ox-calendar.username'),
                config('ox-calendar.password')
            )
            ->timeout(config('ox-calendar.timeout', 30))
            ->retry(3, 200)
            ->withOptions([
                'verify' => config('ox-calendar.verify_ssl', true),
            ]);
    }

    /**
     * PROPFIND-Request auf eine CalDAV-URL.
     *
     * @param string      $url       CalDAV-URL
     * @param string|null $syncToken Optionaler sync-token für Delta-Sync (RFC 6578 REPORT)
     *
     * @return array{responses: array, sync_token: string|null}
     *
     * @throws \App\Exceptions\SyncTokenNotSupportedException wenn Server sync-token nicht unterstützt
     * @throws \RuntimeException bei sonstigen HTTP-Fehlern
     */
    protected function propfind(string $url, ?string $syncToken = null): array
    {
        $isSyncToken = $syncToken !== null;
        $body        = $isSyncToken
            ? $this->buildSyncCollectionBody($syncToken)
            : $this->buildPropfindBody();

        $method  = $isSyncToken ? 'REPORT' : 'PROPFIND';
        $headers = ['Content-Type' => 'application/xml; charset=utf-8'];

        if (!$isSyncToken) {
            $headers['Depth'] = '1';
        }

        $response = $this->httpClient()->send($method, $url, [
            'headers' => $headers,
            'body'    => $body,
        ]);

        if ($isSyncToken && in_array($response->status(), [501, 403], true)) {
            throw new SyncTokenNotSupportedException(
                'Server unterstützt sync-token nicht (Status: ' . $response->status() . ')'
            );
        }

        if (!$response->successful() && $response->status() !== 207) {
            throw new \RuntimeException(
                'CalDAV-Anfrage fehlgeschlagen (Status: ' . $response->status() . ')'
            );
        }

        return $this->parsePropfindResponse($response->body());
    }

    /**
     * Einzelnes Event als iCal-String laden.
     */
    protected function getEvent(string $url): string
    {
        $response = $this->httpClient()->get($url);
        return $response->body();
    }

    // ========================================================================
    // XML-Body-Builder für CalDAV-Requests
    // ========================================================================

    protected function buildPropfindBody(): string
    {
        return '<?xml version="1.0" encoding="utf-8"?>
            <d:propfind xmlns:d="DAV:" xmlns:cs="http://calendarserver.org/ns/" xmlns:c="urn:ietf:params:xml:ns:caldav">
                <d:prop>
                    <d:getetag/>
                    <d:getcontenttype/>
                    <cs:getctag/>
                </d:prop>
            </d:propfind>';
    }

    protected function buildSyncCollectionBody(string $syncToken): string
    {
        return '<?xml version="1.0" encoding="utf-8"?>
            <d:sync-collection xmlns:d="DAV:" xmlns:c="urn:ietf:params:xml:ns:caldav">
                <d:sync-token>' . htmlspecialchars($syncToken) . '</d:sync-token>
                <d:sync-level>1</d:sync-level>
                <d:prop>
                    <d:getetag/>
                </d:prop>
            </d:sync-collection>';
    }

    /**
     * XML-Response eines PROPFIND/REPORT parsen.
     * Extrahiert Event-URLs, ETags und ggf. neuen sync-token.
     *
     * @return array{responses: array<array{href: string, etag: string|null, status: int}>, sync_token: string|null}
     */
    protected function parsePropfindResponse(string $xmlBody): array
    {
        $xml = simplexml_load_string($xmlBody);
        if ($xml === false) {
            throw new \RuntimeException('Ungültige XML-Antwort vom CalDAV-Server');
        }

        $xml->registerXPathNamespace('d', 'DAV:');
        $xml->registerXPathNamespace('cs', 'http://calendarserver.org/ns/');

        $responses = [];
        foreach ($xml->xpath('//d:response') as $response) {
            $hrefNodes = $response->xpath('d:href');
            $href      = !empty($hrefNodes) ? (string) $hrefNodes[0] : '';

            $etagNodes = $response->xpath('d:propstat/d:prop/d:getetag');
            $etag      = !empty($etagNodes) ? (string) $etagNodes[0] : null;

            // Status aus propstat oder direkt unter response (z.B. 404 bei Löschungen)
            $statusCode       = 200;
            $propstatStatus   = $response->xpath('d:propstat/d:status');
            $directStatus     = $response->xpath('d:status');

            if (!empty($propstatStatus)) {
                preg_match('/(\d{3})/', (string) $propstatStatus[0], $matches);
                $statusCode = (int) ($matches[1] ?? 200);
            } elseif (!empty($directStatus)) {
                preg_match('/(\d{3})/', (string) $directStatus[0], $matches);
                $statusCode = (int) ($matches[1] ?? 200);
            }

            // Nur .ics-Dateien berücksichtigen (keine Verzeichnisse)
            if (str_ends_with($href, '.ics')) {
                $responses[] = [
                    'href'   => $href,
                    'etag'   => $etag,
                    'status' => $statusCode,
                ];
            }
        }

        // Sync-Token extrahieren
        $syncTokenNodes = $xml->xpath('//d:sync-token');
        $newSyncToken   = !empty($syncTokenNodes) ? (string) $syncTokenNodes[0] : null;

        return [
            'responses'  => $responses,
            'sync_token' => $newSyncToken,
        ];
    }

    // ========================================================================
    // iCal-Verarbeitung (sabre/vobject)
    // ========================================================================

    /**
     * iCal-String parsen und Termin-Daten extrahieren.
     *
     * Zeitzonen: DTSTART/DTEND werden nach UTC konvertiert.
     * Ganztägige Termine: DATE-Werte → beginn/ende = 00:00:00 UTC.
     *
     * @param string $icalData Roher iCal-String
     *
     * @return array{
     *   titel: string,
     *   beschreibung: ?string,
     *   ort: ?string,
     *   beginn: string,
     *   ende: string,
     *   timezone: ?string,
     *   ganztaegig: bool,
     *   rrule: ?string,
     *   exdates: ?array,
     *   status: ?string,
     *   uid: string,
     * }
     */
    public function parseIcal(string $icalData): array
    {
        $vcalendar = Reader::read($icalData);
        $vevent    = $vcalendar->VEVENT;

        if (!$vevent) {
            throw new \RuntimeException('Kein VEVENT in iCal-Daten gefunden');
        }

        $dtstart    = $vevent->DTSTART;
        $dtend      = $vevent->DTEND;
        $ganztaegig = false;

        // Prüfen ob ganztägig (DATE statt DATE-TIME)
        if ($dtstart && $dtstart->getValueType() === 'DATE') {
            $ganztaegig = true;
        }

        // Original-Timezone extrahieren
        $timezone = null;
        if ($dtstart) {
            $params = $dtstart->parameters();
            if (isset($params['TZID'])) {
                $timezone = (string) $params['TZID'];
            }
        }

        // Beginn/Ende in UTC konvertieren
        $beginn = $dtstart
            ? $dtstart->getDateTime()->setTimezone(new \DateTimeZone('UTC'))
            : null;
        $ende = $dtend
            ? $dtend->getDateTime()->setTimezone(new \DateTimeZone('UTC'))
            : null;

        // RRULE extrahieren
        $rrule = null;
        if (isset($vevent->RRULE)) {
            $rrule = (string) $vevent->RRULE;
        }

        // EXDATE extrahieren
        $exdates = null;
        if (isset($vevent->EXDATE)) {
            $exdates = [];
            foreach ($vevent->EXDATE as $exdate) {
                $exdates[] = $exdate->getDateTime()
                    ->setTimezone(new \DateTimeZone('UTC'))
                    ->format('Y-m-d\TH:i:s\Z');
            }
        }

        // STATUS extrahieren
        $status = isset($vevent->STATUS) ? (string) $vevent->STATUS : null;

        return [
            'titel'        => isset($vevent->SUMMARY) ? (string) $vevent->SUMMARY : 'Ohne Titel',
            'beschreibung' => isset($vevent->DESCRIPTION) ? (string) $vevent->DESCRIPTION : null,
            'ort'          => isset($vevent->LOCATION) ? (string) $vevent->LOCATION : null,
            'beginn'       => $beginn?->format('Y-m-d H:i:s'),
            'ende'         => $ende?->format('Y-m-d H:i:s'),
            'timezone'     => $timezone ?? 'Europe/Berlin',
            'ganztaegig'   => $ganztaegig,
            'rrule'        => $rrule,
            'exdates'      => $exdates,
            'status'       => $status,
            'uid'          => isset($vevent->UID) ? (string) $vevent->UID : '',
        ];
    }

    /**
     * Teilnehmer (ATTENDEE) aus iCal-Daten extrahieren.
     * Wenn $internalDomains leer → alle Teilnehmer zurückgeben.
     *
     * @param string   $icalData        Roher iCal-String
     * @param string[] $internalDomains Erlaubte E-Mail-Domains (leer = alle)
     *
     * @return array<array{email: string, name: ?string, status: string}>
     */
    public function parseTeilnehmer(string $icalData, array $internalDomains = []): array
    {
        $vcalendar = Reader::read($icalData);
        $vevent    = $vcalendar->VEVENT;

        if (!$vevent || !isset($vevent->ATTENDEE)) {
            return [];
        }

        $teilnehmer = [];

        foreach ($vevent->ATTENDEE as $attendee) {
            $email = str_replace('mailto:', '', (string) $attendee->getValue());
            $email = strtolower(trim($email));

            // Nur interne E-Mail-Adressen (wenn Domains konfiguriert)
            if (!empty($internalDomains)) {
                $atPos  = strpos($email, '@');
                $domain = $atPos !== false ? substr($email, $atPos + 1) : '';
                if (!in_array($domain, $internalDomains, true)) {
                    continue;
                }
            }

            // CN (Common Name) extrahieren
            $name   = null;
            $params = $attendee->parameters();
            if (isset($params['CN'])) {
                $name = (string) $params['CN'];
            }

            // PARTSTAT (Teilnahme-Status)
            $partstat = 'NEEDS-ACTION';
            if (isset($params['PARTSTAT'])) {
                $partstat = (string) $params['PARTSTAT'];
            }

            $teilnehmer[] = [
                'email'  => $email,
                'name'   => $name,
                'status' => $partstat,
            ];
        }

        return $teilnehmer;
    }

    // ========================================================================
    // Cache-Invalidierung
    // ========================================================================

    /**
     * Events-Cache invalidieren.
     */
    public function invalidateEventsCache(?int $calendarId = null): void
    {
        Cache::flush();
        Log::debug('Kalender-Cache invalidiert', ['calendar_id' => $calendarId]);
    }

    // ========================================================================
    // Sync-Logik (TODO 05)
    // ========================================================================

    /**
     * Synchronisiert einen einzelnen Kalender von OX in die lokale DB.
     *
     * @return array{created: int, updated: int, deleted: int, errors: int, method: string}
     */
    public function syncCalendar(OxCalendar $calendar): array
    {
        $result = ['created' => 0, 'updated' => 0, 'deleted' => 0, 'errors' => 0, 'method' => ''];

        // Sync-Start loggen
        OxSyncLog::create([
            'ox_calendar_id' => $calendar->id,
            'aktion'         => 'sync_start',
            'details'        => ['calendar_name' => $calendar->name],
        ]);

        try {
            // Fallback-Kette
            $changes          = $this->resolveChanges($calendar);
            $result['method'] = $changes['method'];

            // Geänderte Events verarbeiten
            foreach ($changes['changed'] as $eventInfo) {
                try {
                    $icalData = $this->getEvent($this->buildEventUrl($calendar, $eventInfo['href']));
                    $parsed   = $this->parseIcal($icalData);

                    // Prüfen ob Termin bereits existiert (VOR Upsert)
                    $existed = OxTermin::withTrashed()
                        ->where('ox_calendar_id', $calendar->id)
                        ->where('ox_uid', $parsed['uid'])
                        ->exists();

                    $this->upsertTermin($calendar, $eventInfo, $icalData);

                    $existed ? $result['updated']++ : $result['created']++;
                } catch (\Exception $e) {
                    $result['errors']++;
                    Log::warning('Kalender-Sync: Event-Fehler', [
                        'href'  => $eventInfo['href'],
                        'error' => $e->getMessage(),
                    ]);
                }
            }

            // Gelöschte Events verarbeiten
            foreach ($changes['deleted'] as $href) {
                try {
                    $this->softDeleteTerminByHref($calendar, $href);
                    $result['deleted']++;
                } catch (\Exception $e) {
                    $result['errors']++;
                    Log::warning('Kalender-Sync: Delete-Fehler', ['href' => $href, 'error' => $e->getMessage()]);
                }
            }

            // Sync-Token aktualisieren
            if (!empty($changes['sync_token'])) {
                $calendar->update(['sync_token' => $changes['sync_token']]);
            }

            $calendar->update(['letzte_synchronisation' => now()]);

            // Cache invalidieren
            $this->invalidateEventsCache($calendar->id);

        } catch (\Exception $e) {
            $result['errors']++;
            Log::error('Kalender-Sync fehlgeschlagen', [
                'calendar' => $calendar->name,
                'error'    => $e->getMessage(),
            ]);

            OxSyncLog::create([
                'ox_calendar_id' => $calendar->id,
                'aktion'         => 'error',
                'details'        => ['message' => $e->getMessage()],
            ]);

            return $result;
        }

        // Sync-Complete loggen
        OxSyncLog::create([
            'ox_calendar_id' => $calendar->id,
            'aktion'         => 'sync_complete',
            'details'        => $result,
        ]);

        return $result;
    }

    /**
     * Synchronisiert alle sichtbaren Kalender.
     *
     * @return array<string, array> Ergebnisse pro Kalender-Name
     */
    public function syncAll(): array
    {
        if (!$this->isEnabled()) {
            Log::info('Kalender-Sync übersprungen: Modul deaktiviert');
            return [];
        }

        $calendars = OxCalendar::where('sichtbar', true)->get();
        $results   = [];

        foreach ($calendars as $calendar) {
            $results[$calendar->name] = $this->syncCalendar($calendar);
        }

        return $results;
    }

    /**
     * Ermittelt geänderte und gelöschte Events über die Fallback-Kette.
     *
     * @return array{changed: array, deleted: array, sync_token: ?string, method: string}
     */
    protected function resolveChanges(OxCalendar $calendar): array
    {
        // Strategie 1: sync-token (Delta-Sync, RFC 6578)
        if ($calendar->sync_token) {
            try {
                return $this->syncViaSyncToken($calendar);
            } catch (SyncTokenNotSupportedException $e) {
                Log::info('sync-token nicht unterstützt, Fallback auf ctag', ['calendar' => $calendar->name]);
            }
        }

        // Strategie 2: ctag-Vergleich (Full-Sync)
        try {
            return $this->syncViaCtag($calendar);
        } catch (CtagNotSupportedException $e) {
            Log::info('ctag nicht unterstützt, Fallback auf ETag', ['calendar' => $calendar->name]);
        }

        // Strategie 3: ETag-Vergleich pro Event
        return $this->syncViaEtagComparison($calendar);
    }

    /**
     * Delta-Sync über RFC 6578 sync-token.
     */
    protected function syncViaSyncToken(OxCalendar $calendar): array
    {
        $calendarUrl = $this->buildCalendarUrl($calendar);
        $response    = $this->propfind($calendarUrl, $calendar->sync_token);

        $changed = [];
        $deleted = [];

        foreach ($response['responses'] as $item) {
            if ($item['status'] === 404) {
                // Event wurde in OX gelöscht
                $deleted[] = $item['href'];
            } else {
                $changed[] = $item;
            }
        }

        return [
            'changed'    => $changed,
            'deleted'    => $deleted,
            'sync_token' => $response['sync_token'],
            'method'     => 'sync-token',
        ];
    }

    /**
     * Full-Sync mit ctag-Vergleich.
     * Alle Events aus dem Kalender als "geändert" markieren.
     */
    protected function syncViaCtag(OxCalendar $calendar): array
    {
        $calendarUrl = $this->buildCalendarUrl($calendar);
        $response    = $this->propfind($calendarUrl);

        return [
            'changed'    => $response['responses'],
            'deleted'    => [],
            'sync_token' => $response['sync_token'],
            'method'     => 'ctag',
        ];
    }

    /**
     * ETag-Vergleich pro Event.
     * Alle Event-URLs abrufen, ETags mit lokaler DB vergleichen.
     */
    protected function syncViaEtagComparison(OxCalendar $calendar): array
    {
        $calendarUrl = $this->buildCalendarUrl($calendar);
        $response    = $this->propfind($calendarUrl);

        // Lokale ETags laden
        $localEtags = OxTermin::where('ox_calendar_id', $calendar->id)
            ->whereNotNull('ox_etag')
            ->pluck('ox_etag', 'ox_href')
            ->toArray();

        $changed     = [];
        $remoteHrefs = [];

        foreach ($response['responses'] as $item) {
            $remoteHrefs[] = $item['href'];

            // Nur laden wenn ETag unterschiedlich oder Event unbekannt
            if (!isset($localEtags[$item['href']]) || $localEtags[$item['href']] !== $item['etag']) {
                $changed[] = $item;
            }
        }

        // Events die remote nicht mehr existieren → gelöscht
        $localHrefs = array_keys($localEtags);
        $deleted    = array_values(array_diff($localHrefs, $remoteHrefs));

        return [
            'changed'    => $changed,
            'deleted'    => $deleted,
            'sync_token' => null,
            'method'     => 'etag-comparison',
        ];
    }

    // ========================================================================
    // Hilfsmethoden
    // ========================================================================

    /**
     * Termin in lokaler DB erstellen oder aktualisieren (Upsert).
     */
    protected function upsertTermin(OxCalendar $calendar, array $eventInfo, string $icalData): OxTermin
    {
        $parsed = $this->parseIcal($icalData);

        /** @var OxTermin $termin */
        $termin = OxTermin::withTrashed()->updateOrCreate(
            [
                'ox_calendar_id' => $calendar->id,
                'ox_uid'         => $parsed['uid'],
            ],
            [
                'ox_etag'      => $eventInfo['etag'] ?? null,
                'ox_href'      => $eventInfo['href'],
                'titel'        => $parsed['titel'],
                'beschreibung' => $parsed['beschreibung'],
                'ort'          => $parsed['ort'],
                'beginn'       => $parsed['beginn'],
                'ende'         => $parsed['ende'],
                'timezone'     => $parsed['timezone'],
                'ganztaegig'   => $parsed['ganztaegig'],
                'rrule'        => $parsed['rrule'],
                'exdates'      => $parsed['exdates'],
                'status'       => $parsed['status'],
                'raw_ical'     => $icalData,
                'deleted_at'   => null, // Restore bei Re-Sync
            ]
        );

        // Teilnehmer synchronisieren
        $this->syncTeilnehmer($termin, $icalData);

        return $termin;
    }

    /**
     * Teilnehmer eines Termins synchronisieren.
     */
    protected function syncTeilnehmer(OxTermin $termin, string $icalData): void
    {
        $teilnehmerData = $this->parseTeilnehmer($icalData);

        // Bestehende Teilnehmer löschen und neu anlegen
        $termin->teilnehmer()->delete();

        foreach ($teilnehmerData as $data) {
            $termin->teilnehmer()->create($data);
        }
    }

    /**
     * Termin per CalDAV-Href soft-deleten.
     */
    protected function softDeleteTerminByHref(OxCalendar $calendar, string $href): void
    {
        OxTermin::where('ox_calendar_id', $calendar->id)
            ->where('ox_href', $href)
            ->each(function (OxTermin $termin) {
                $termin->delete(); // SoftDelete
            });
    }

    /**
     * Volle CalDAV-URL eines Kalenders bauen.
     */
    protected function buildCalendarUrl(OxCalendar $calendar): string
    {
        return rtrim(config('ox-calendar.url'), '/') . '/' . ltrim($calendar->ox_calendar_id, '/');
    }

    /**
     * Volle CalDAV-URL eines Events bauen.
     * href kann absolut oder relativ sein.
     */
    protected function buildEventUrl(OxCalendar $calendar, string $href): string
    {
        if (str_starts_with($href, 'http')) {
            return $href;
        }
        return rtrim(config('ox-calendar.url'), '/') . '/' . ltrim($href, '/');
    }
}





