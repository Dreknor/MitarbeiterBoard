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

/**
 * Service für die CalDAV-Kommunikation mit Open-Xchange.
 *
 * Verantwortlich für:
 * - Synchronisation von OX-Kalendern in die lokale Cache-Schicht (syncAll / syncCalendar)
 * - Bidirektionale Terminerstellung (MB → OX): createTermin, updateTermin, deleteTermin
 * - iCal-Parsing und -Erstellung via sabre/vobject
 * - Fehler-Erkennung und Admin-Benachrichtigung (checkConsecutiveErrors)
 *
 * Konfiguration: config/ox-calendar.php (.env-Variablen: OX_CALDAV_URL, OX_CALDAV_USER, …)
 * Modelle:       OxCalendar, OxTermin, OxTerminTeilnehmer, OxSyncLog
 *
 * @see \App\Console\Commands\OxSyncCalendars
 * @see \App\Http\Controllers\CalendarController
 * @see \App\Http\Controllers\CalendarAdminController
 */
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
            $response = $this->httpClient()
                ->withOptions(['http_errors' => false])
                ->send('PROPFIND', config('ox-calendar.url'), [
                    'headers' => [
                        'Depth'        => '0',
                        'Content-Type' => 'application/xml; charset=utf-8',
                    ],
                    'body' => '<?xml version="1.0" encoding="utf-8"?>
                        <d:propfind xmlns:d="DAV:">
                            <d:prop>
                                <d:displayname/>
                                <d:current-user-principal/>
                            </d:prop>
                        </d:propfind>',
                ]);

            $davHeader = $response->header('DAV');
            $status    = $response->status();

            if ($status === 207 || !empty($davHeader)) {
                return [
                    'success'    => true,
                    'message'    => 'CalDAV-Verbindung erfolgreich.',
                    'status'     => $status,
                    'dav_header' => $davHeader ?: '(nicht gesetzt)',
                ];
            }

            if ($response->successful()) {
                return [
                    'success'    => false,
                    'message'    => 'Server erreichbar (HTTP ' . $status . '), aber kein CalDAV erkannt '
                        . '(kein DAV-Header, kein HTTP-207). Prüfe ob CalDAV konfiguriert ist.',
                    'status'     => $status,
                    'dav_header' => $davHeader ?: '(nicht gesetzt)',
                ];
            }

            return [
                'success' => false,
                'message' => 'Server antwortete mit HTTP ' . $status,
                'status'  => $status,
            ];
        } catch (\Throwable $e) {
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
     * Nutzt DOMDocument/DOMXPath für robuste Namespace-Behandlung
     * (SimpleXML scheitert bei Default-Namespaces wie `xmlns="DAV:"`).
     *
     * @return array{responses: array<array{href: string, etag: string|null, status: int}>, sync_token: string|null}
     */
    protected function parsePropfindResponse(string $xmlBody): array
    {
        $dom = new \DOMDocument();
        $prev = libxml_use_internal_errors(true);
        $loaded = $dom->loadXML($xmlBody);
        libxml_clear_errors();
        libxml_use_internal_errors($prev);

        if (!$loaded) {
            throw new \RuntimeException('Ungültige XML-Antwort vom CalDAV-Server');
        }

        $xpath = new \DOMXPath($dom);
        $xpath->registerNamespace('d',  'DAV:');
        $xpath->registerNamespace('cs', 'http://calendarserver.org/ns/');
        $xpath->registerNamespace('c',  'urn:ietf:params:xml:ns:caldav');

        $responses = [];

        foreach ($xpath->query('//*[local-name()="response"]') as $responseNode) {
            // href
            $hrefNodes = $xpath->query('*[local-name()="href"]', $responseNode);
            $href      = $hrefNodes->length > 0 ? trim($hrefNodes->item(0)->textContent) : '';

            // etag
            $etagNodes = $xpath->query('.//*[local-name()="getetag"]', $responseNode);
            $etag      = $etagNodes->length > 0 ? trim($etagNodes->item(0)->textContent) : null;
            if ($etag !== null) {
                $etag = trim($etag, '"');
            }

            // HTTP-Status
            $statusCode     = 200;
            $statusNodes    = $xpath->query('.//*[local-name()="status"]', $responseNode);
            if ($statusNodes->length > 0) {
                preg_match('/(\d{3})/', $statusNodes->item(0)->textContent, $m);
                $statusCode = (int) ($m[1] ?? 200);
            }

            // Nur .ics-Dateien (keine Verzeichnisse)
            if (str_ends_with($href, '.ics')) {
                $responses[] = [
                    'href'   => $href,
                    'etag'   => $etag,
                    'status' => $statusCode,
                ];
            }
        }

        // sync-token extrahieren
        $syncTokenNodes = $xpath->query('//*[local-name()="sync-token"]');
        $newSyncToken   = $syncTokenNodes->length > 0
            ? trim($syncTokenNodes->item(0)->textContent)
            : null;

        return [
            'responses'  => $responses,
            'sync_token' => $newSyncToken ?: null,
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
    // Öffentliche Test-/Diagnose-Methoden
    // ========================================================================

    /**
     * PROPFIND-Probe auf eine beliebige CalDAV-URL.
     * Liefert Event-Liste und sync-token für Diagnosezwecke zurück,
     * ohne etwas in der DB zu speichern.
     *
     * @param string $url Vollständige CalDAV-URL (z. B. https://…/dav/caldav/…)
     *
     * @return array{
     *   success: bool,
     *   message: string,
     *   events: array,
     *   sync_token: string|null,
     *   status?: int,
     * }
     */
    public function probeCalendarUrl(string $url): array
    {
        $diagnostics = [];

        // ── Stufe 1: OPTIONS ─────────────────────────────────────────────
        try {
            $optResp = $this->httpClient()
                ->withOptions(['http_errors' => false])
                ->send('OPTIONS', $url, []);
            $diagnostics['options_status']  = $optResp->status();
            $diagnostics['allowed_methods'] = $optResp->header('Allow') ?: '(nicht zurückgegeben)';
            $diagnostics['dav_header']      = $optResp->header('DAV')   ?: '(nicht gesetzt)';
        } catch (\Throwable $e) {
            $diagnostics['options_error'] = $e->getMessage();
        }

        // ── Stufe 2: PROPFIND Depth:0 (Ressource existiert?) ─────────────
        try {
            $depth0Resp = $this->httpClient()
                ->withOptions(['http_errors' => false])
                ->send('PROPFIND', $url, [
                    'headers' => [
                        'Depth'        => '0',
                        'Content-Type' => 'application/xml; charset=utf-8',
                    ],
                    'body' => '<?xml version="1.0" encoding="utf-8"?>
                        <d:propfind xmlns:d="DAV:" xmlns:cs="http://calendarserver.org/ns/">
                            <d:prop>
                                <d:displayname/>
                                <d:resourcetype/>
                                <cs:getctag/>
                                <d:sync-token/>
                            </d:prop>
                        </d:propfind>',
                ]);
            $diagnostics['propfind_depth0_status'] = $depth0Resp->status();
            $body = $depth0Resp->body();
            $diagnostics['propfind_depth0_body']   = substr($body, 0, 1000);

            if (preg_match('/<[^:>]*:?displayname[^>]*>([^<]*)</', $body, $m) && trim($m[1]) !== '') {
                $diagnostics['displayname'] = trim($m[1]);
            }
            if (preg_match('/<[^:>]*:?getctag[^>]*>([^<]*)</', $body, $m)) {
                $diagnostics['ctag'] = trim($m[1]);
            }
            if (preg_match('/<[^:>]*:?sync-token[^>]*>([^<]*)</', $body, $m)) {
                $diagnostics['sync_token_raw'] = trim($m[1]);
            }
        } catch (\Throwable $e) {
            $diagnostics['propfind_depth0_error'] = $e->getMessage();
        }

        // ── Stufe 3: PROPFIND Depth:1 (Events listen) ────────────────────
        try {
            $response = $this->propfind($url);

            return [
                'success'     => true,
                'message'     => count($response['responses']) . ' Event(s) gefunden.',
                'events'      => $response['responses'],
                'sync_token'  => $response['sync_token'],
                'diagnostics' => $diagnostics,
            ];
        } catch (\Throwable $e) {
            return [
                'success'     => false,
                'message'     => $e->getMessage(),
                'events'      => [],
                'sync_token'  => null,
                'diagnostics' => $diagnostics,
            ];
        }
    }

    // ========================================================================
    // Cache-Invalidierung
    // ========================================================================

    /**
     * Einzelnes Event von einer CalDAV-URL laden (öffentlicher Wrapper für Diagnose).
     *
     * @return array{success: bool, body: string, message: string}
     */
    public function fetchEventFromUrl(string $url): array
    {
        try {
            $body = $this->getEvent($url);
            return ['success' => true, 'body' => $body, 'message' => 'OK'];
        } catch (\Throwable $e) {
            return ['success' => false, 'body' => '', 'message' => $e->getMessage()];
        }
    }

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

        $syncEnabled = \App\Models\Setting::where('module', 'Kalender')
            ->where('setting', 'calendar_sync_enabled')
            ->value('value') ?? '1';

        if ($syncEnabled !== '1') {
            Log::info('Kalender-Sync übersprungen: Über Settings deaktiviert');
            return [];
        }

        $calendars = OxCalendar::where('sichtbar', true)->get();
        $results   = [];

        foreach ($calendars as $calendar) {
            $results[$calendar->name] = $this->syncCalendar($calendar);
        }

        // Prüfe auf aufeinanderfolgende Fehler und benachrichtige Admins
        $this->checkConsecutiveErrors();

        return $results;
    }

    /**
     * Prüft ob 3+ aufeinanderfolgende Sync-Fehler vorliegen und benachrichtigt Admins.
     * Spam-Schutz: Maximal eine Notification pro Stunde.
     */
    protected function checkConsecutiveErrors(): void
    {
        // Letzte 3 Sync-Abschlüsse prüfen (sync_complete oder error)
        $recentLogs = OxSyncLog::whereIn('aktion', ['sync_complete', 'error'])
            ->orderByDesc('created_at')
            ->limit(3)
            ->pluck('aktion')
            ->toArray();

        // Wenn alle 3 letzten Einträge Fehler sind
        if (count($recentLogs) >= 3 && count(array_unique($recentLogs)) === 1 && $recentLogs[0] === 'error') {
            $letzterFehler = OxSyncLog::where('aktion', 'error')
                ->orderByDesc('created_at')
                ->first();

            $fehlerDetails = $letzterFehler->details['message'] ?? 'Unbekannter Fehler';

            // Spam-Schutz: Nur einmal pro Stunde benachrichtigen
            $letzteNotification = Cache::get('calendar_sync_error_notified');
            if ($letzteNotification) {
                return;
            }

            // Admins mit 'manage calendar'-Berechtigung benachrichtigen
            $admins = \App\Models\User::permission('manage calendar')->get();
            foreach ($admins as $admin) {
                $admin->notify(new \App\Notifications\SyncFailedNotification(
                    count($recentLogs),
                    $fehlerDetails
                ));
            }

            // Cooldown: 1 Stunde
            Cache::put('calendar_sync_error_notified', true, 3600);

            Log::warning('Kalender: 3+ Sync-Fehler hintereinander – Admins benachrichtigt');
        }
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
            ]
        );

        // Soft-deleted Termin bei Re-Sync wiederherstellen
        if ($termin->trashed()) {
            $termin->restore();
        }

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
     * Falls ox_calendar_id bereits eine absolute URL ist, direkt zurückgeben.
     */
    protected function buildCalendarUrl(OxCalendar $calendar): string
    {
        if (str_starts_with($calendar->ox_calendar_id, 'http')) {
            return $calendar->ox_calendar_id;
        }

        return rtrim(config('ox-calendar.url'), '/') . '/' . ltrim($calendar->ox_calendar_id, '/');
    }

    /**
     * Volle CalDAV-URL eines Events bauen.
     * href kann absolut (http/https), absoluter Pfad (/) oder relativ sein.
     */
    protected function buildEventUrl(OxCalendar $calendar, string $href): string
    {
        if (str_starts_with($href, 'http')) {
            return $href;
        }

        $calendarUrl = $this->buildCalendarUrl($calendar);
        $parsed      = parse_url($calendarUrl);
        $origin      = $parsed['scheme'] . '://' . $parsed['host'];
        if (!empty($parsed['port'])) {
            $origin .= ':' . $parsed['port'];
        }

        // Absoluter Pfad (z.B. /dav/caldav/...)
        if (str_starts_with($href, '/')) {
            return $origin . $href;
        }

        // Relativer Pfad → relativ zur Kalender-Collection
        return rtrim($calendarUrl, '/') . '/' . $href;
    }

    // ========================================================================
    // CRUD-Operationen
    // ========================================================================

    /**
     * Neuen Termin erstellen und nach OX schreiben.
     *
     * @param OxCalendar $calendar Zielkalender
     * @param array $data Termin-Daten (titel, beschreibung, ort, beginn, ende, ganztaegig, rrule)
     * @return OxTermin Lokaler Cache-Eintrag
     * @throws \RuntimeException Bei CalDAV-Fehler
     */
    public function createTermin(OxCalendar $calendar, array $data): OxTermin
    {
        // UID generieren
        $uid = \Illuminate\Support\Str::uuid() . '@mitarbeiterboard';

        // iCal bauen
        $icalData = $this->buildIcal(array_merge($data, ['uid' => $uid]));

        // CalDAV-URL für neues Event
        // Nur den Dateinamen als relativen href übergeben – buildEventUrl hängt ihn
        // an die vollständige Kalender-Collection-URL an. Den ox_href als absoluten
        // Pfad speichern, damit er mit PROPFIND-Antworten übereinstimmt.
        $eventFilename = $uid . '.ics';
        $eventUrl      = $this->buildEventUrl($calendar, $eventFilename);
        $eventHref     = parse_url($eventUrl, PHP_URL_PATH); // z.B. /caldav/…/uuid.ics

        // CalDAV PUT
        $response = $this->putEvent($eventUrl, $icalData);

        if (!$response->successful()) {
            throw new \RuntimeException(
                'CalDAV PUT fehlgeschlagen: HTTP ' . $response->status()
            );
        }

        // ETag aus Response extrahieren
        $etag = $response->header('ETag');

        // Lokalen Cache-Eintrag erstellen
        $termin = OxTermin::create([
            'ox_calendar_id' => $calendar->id,
            'ox_uid'         => $uid,
            'ox_etag'        => $etag,
            'ox_href'        => $eventHref,
            'titel'          => $data['titel'],
            'beschreibung'   => $data['beschreibung'] ?? null,
            'ort'            => $data['ort'] ?? null,
            'beginn'         => $data['beginn'],
            'ende'           => $data['ende'],
            'timezone'       => 'Europe/Berlin',
            'ganztaegig'     => $data['ganztaegig'] ?? false,
            'rrule'          => $data['rrule'] ?? null,
            'exdates'        => null,
            'status'         => 'CONFIRMED',
            'erstellt_von'   => auth()->id(),
            'raw_ical'       => $icalData,
        ]);

        // Audit-Log
        OxSyncLog::create([
            'ox_calendar_id' => $calendar->id,
            'aktion'         => 'create',
            'details'        => [
                'titel'  => $termin->titel,
                'beginn' => $termin->beginn->toIso8601String(),
                'ende'   => $termin->ende->toIso8601String(),
                'ox_uid' => $termin->ox_uid,
            ],
            'user_id'    => auth()->id(),
            'ip_adresse' => request()->ip(),
        ]);

        // Cache invalidieren
        $this->invalidateEventsCache($calendar->id);

        return $termin;
    }

    /**
     * Bestehenden Termin aktualisieren und nach OX schreiben.
     * Nutzt raw_ical für verlustfreien Round-Trip.
     *
     * @throws \RuntimeException Bei CalDAV-Fehler oder ETag-Mismatch
     */
    public function updateTermin(OxTermin $termin, array $data): OxTermin
    {
        // iCal bauen (basierend auf raw_ical wenn vorhanden, sonst neu)
        $icalData = $termin->raw_ical
            ? $this->updateExistingIcal($termin->raw_ical, $data)
            : $this->buildIcal(array_merge($data, ['uid' => $termin->ox_uid]));

        $eventUrl = $this->buildEventUrl($termin->kalender, $termin->ox_href);

        // CalDAV PUT mit If-Match (ETag-Prüfung)
        $response = $this->putEvent($eventUrl, $icalData, $termin->ox_etag);

        if ($response->status() === 412) {
            // ETag-Mismatch: Termin wurde in OX geändert
            throw new \RuntimeException(
                'Termin wurde zwischenzeitlich in OX geändert. Bitte neu laden.'
            );
        }

        if (!$response->successful()) {
            throw new \RuntimeException(
                'CalDAV PUT fehlgeschlagen: HTTP ' . $response->status()
            );
        }

        // Lokalen Cache aktualisieren
        $termin->update([
            'ox_etag'      => $response->header('ETag'),
            'titel'        => $data['titel'],
            'beschreibung' => $data['beschreibung'] ?? null,
            'ort'          => $data['ort'] ?? null,
            'beginn'       => $data['beginn'],
            'ende'         => $data['ende'],
            'ganztaegig'   => $data['ganztaegig'] ?? false,
            'rrule'        => $data['rrule'] ?? null,
            'raw_ical'     => $icalData,
        ]);

        // Audit-Log
        OxSyncLog::create([
            'ox_calendar_id' => $termin->ox_calendar_id,
            'aktion'         => 'update',
            'details'        => [
                'titel'      => $termin->titel,
                'ox_uid'     => $termin->ox_uid,
                'aenderungen' => array_keys($data),
            ],
            'user_id'    => auth()->id(),
            'ip_adresse' => request()->ip(),
        ]);

        $this->invalidateEventsCache($termin->ox_calendar_id);

        return $termin;
    }

    /**
     * Termin in OX löschen und lokal soft-deleten.
     */
    public function deleteTermin(OxTermin $termin): bool
    {
        $eventUrl = $this->buildEventUrl($termin->kalender, $termin->ox_href);

        $response = $this->deleteEvent($eventUrl);

        if (!$response->successful() && $response->status() !== 404) {
            throw new \RuntimeException(
                'CalDAV DELETE fehlgeschlagen: HTTP ' . $response->status()
            );
        }

        // Audit-Log
        OxSyncLog::create([
            'ox_calendar_id' => $termin->ox_calendar_id,
            'aktion'         => 'delete',
            'details'        => [
                'titel'  => $termin->titel,
                'ox_uid' => $termin->ox_uid,
            ],
            'user_id'    => auth()->id(),
            'ip_adresse' => request()->ip(),
        ]);

        $termin->delete(); // SoftDelete

        $this->invalidateEventsCache($termin->ox_calendar_id);

        return true;
    }

    /**
     * Termin-Daten in iCal-String konvertieren.
     * Nutzt sabre/vobject Builder (kein manuelles String-Building).
     */
    protected function buildIcal(array $data): string
    {
        $vcalendar           = new \Sabre\VObject\Component\VCalendar();
        $vcalendar->PRODID   = '-//MitarbeiterBoard//Kalender//DE';
        $vcalendar->VERSION  = '2.0';

        $veventData = [
            'UID'     => $data['uid'],
            'SUMMARY' => $data['titel'],
            'DTSTAMP' => new \DateTime('now', new \DateTimeZone('UTC')),
        ];

        // Datum/Zeit
        if (!empty($data['ganztaegig'])) {
            $veventData['DTSTART'] = new \DateTime($data['beginn']);
            $veventData['DTEND']   = new \DateTime($data['ende']);
        } else {
            $veventData['DTSTART'] = new \DateTime($data['beginn'], new \DateTimeZone('Europe/Berlin'));
            $veventData['DTEND']   = new \DateTime($data['ende'], new \DateTimeZone('Europe/Berlin'));
        }

        $vevent = $vcalendar->add('VEVENT', $veventData);

        if (!empty($data['ganztaegig'])) {
            $vevent->DTSTART['VALUE'] = 'DATE';
            $vevent->DTEND['VALUE']   = 'DATE';
        }

        if (!empty($data['beschreibung'])) {
            $vevent->DESCRIPTION = $data['beschreibung'];
        }

        if (!empty($data['ort'])) {
            $vevent->LOCATION = $data['ort'];
        }

        $vevent->STATUS = 'CONFIRMED';

        // RRULE
        if (!empty($data['rrule'])) {
            $vevent->RRULE = $data['rrule'];
        }

        return $vcalendar->serialize();
    }

    /**
     * RRULE-String aus UI-Auswahl generieren (RFC 5545).
     *
     * @param array $recurrence {
     *   frequency: 'DAILY'|'WEEKLY'|'MONTHLY'|'YEARLY',
     *   interval: int (default 1),
     *   byDay: ?array (z.B. ['MO', 'WE', 'FR']),
     *   until: ?string (Datum, Format Y-m-d),
     *   count: ?int,
     * }
     * @return string RRULE-String (z.B. "FREQ=WEEKLY;BYDAY=MO,WE;COUNT=10")
     */
    public function buildRrule(array $recurrence): string
    {
        $parts = [];

        $parts[] = 'FREQ=' . strtoupper($recurrence['frequency']);

        if (isset($recurrence['interval']) && $recurrence['interval'] > 1) {
            $parts[] = 'INTERVAL=' . (int) $recurrence['interval'];
        }

        if (!empty($recurrence['byDay'])) {
            $parts[] = 'BYDAY=' . implode(',', array_map('strtoupper', $recurrence['byDay']));
        }

        if (!empty($recurrence['until'])) {
            $until   = \Carbon\Carbon::parse($recurrence['until'])->format('Ymd\THis\Z');
            $parts[] = 'UNTIL=' . $until;
        } elseif (!empty($recurrence['count'])) {
            $parts[] = 'COUNT=' . (int) $recurrence['count'];
        }

        return implode(';', $parts);
    }

    /**
     * Bestehendes iCal aktualisieren (Round-Trip: X-Properties bleiben erhalten).
     */
    protected function updateExistingIcal(string $rawIcal, array $data): string
    {
        $vcalendar = \Sabre\VObject\Reader::read($rawIcal);
        $vevent    = $vcalendar->VEVENT;

        $vevent->SUMMARY = $data['titel'];

        if (!empty($data['beschreibung'])) {
            $vevent->DESCRIPTION = $data['beschreibung'];
        } else {
            unset($vevent->DESCRIPTION);
        }

        if (!empty($data['ort'])) {
            $vevent->LOCATION = $data['ort'];
        } else {
            unset($vevent->LOCATION);
        }

        // Datum/Zeit aktualisieren
        $vevent->DTSTART = new \DateTime($data['beginn'], new \DateTimeZone('Europe/Berlin'));
        $vevent->DTEND   = new \DateTime($data['ende'], new \DateTimeZone('Europe/Berlin'));

        if (!empty($data['ganztaegig'])) {
            $vevent->DTSTART['VALUE'] = 'DATE';
            $vevent->DTEND['VALUE']   = 'DATE';
        }

        if (!empty($data['rrule'])) {
            $vevent->RRULE = $data['rrule'];
        } else {
            unset($vevent->RRULE);
        }

        $vevent->{'LAST-MODIFIED'} = new \DateTime('now', new \DateTimeZone('UTC'));

        return $vcalendar->serialize();
    }

    /**
     * CalDAV PUT – Event erstellen/aktualisieren.
     */
    protected function putEvent(string $url, string $icalData, ?string $etag = null): \Illuminate\Http\Client\Response
    {
        $headers = [
            'Content-Type' => 'text/calendar; charset=utf-8',
        ];

        if ($etag) {
            $headers['If-Match'] = $etag;
        }

        try {
            return $this->httpClient()
                ->withOptions(['http_errors' => false])
                ->withHeaders($headers)
                ->send('PUT', $url, [
                    'body' => $icalData,
                ]);
        } catch (\Illuminate\Http\Client\RequestException $e) {
            return $e->response;
        }
    }

    /**
     * CalDAV DELETE – Event löschen.
     */
    protected function deleteEvent(string $url): \Illuminate\Http\Client\Response
    {
        try {
            return $this->httpClient()
                ->withOptions(['http_errors' => false])
                ->delete($url);
        } catch (\Illuminate\Http\Client\RequestException $e) {
            return $e->response;
        }
    }
}


