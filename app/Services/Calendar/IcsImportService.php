<?php

namespace App\Services\Calendar;

use App\Models\OxTermin;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Sabre\VObject\Component\VEvent;
use Sabre\VObject\Reader;

/**
 * Import von .ics-Dateien in einen oder mehrere OX-Kalender.
 *
 * Ablauf:
 * 1. analysieren(): Datei parsen, Termine als Vorschau-Einträge aufbereiten
 * 2. vorschauSpeichern(): Einträge für den User zwischenspeichern (Token), damit die
 *    Datei nicht erneut hochgeladen werden muss
 * 3. importieren(): ausgewählte Einträge – ergänzt um Hinweise – über den
 *    TerminVerbundService nach OX schreiben
 */
class IcsImportService
{
    public const MAX_TERMINE       = 500;
    public const MAX_AUSWAHL       = 150;
    protected const CACHE_MINUTEN  = 120;

    public function __construct(protected TerminVerbundService $verbund)
    {
    }

    /**
     * ICS-Inhalt parsen.
     *
     * @return array<int, array<string, mixed>>
     *
     * @throws \InvalidArgumentException bei ungültiger Datei
     */
    public function analysieren(string $inhalt): array
    {
        try {
            $vcalendar = Reader::read($inhalt, Reader::OPTION_FORGIVING);
        } catch (\Throwable $e) {
            throw new \InvalidArgumentException('Die Datei ist keine gültige iCalendar-Datei (.ics).');
        }

        $vevents = $vcalendar->select('VEVENT');
        if (empty($vevents)) {
            throw new \InvalidArgumentException('Die Datei enthält keine Termine.');
        }

        // Abweichende Einzeltermine einer Serie (RECURRENCE-ID) zählen – sie werden
        // nicht separat übernommen, sondern als Hinweis beim Serientermin angezeigt.
        $ausnahmen = [];
        $serienUids = [];
        foreach ($vevents as $vevent) {
            $uid = (string) ($vevent->UID ?? '');
            if (isset($vevent->{'RECURRENCE-ID'})) {
                $ausnahmen[$uid] = ($ausnahmen[$uid] ?? 0) + 1;
            } elseif (isset($vevent->RRULE)) {
                $serienUids[$uid] = true;
            }
        }

        $eintraege = [];
        foreach ($vevents as $vevent) {
            $uid = (string) ($vevent->UID ?? '');

            // Ausnahme zu einer vorhandenen Serie → übersprungen (siehe oben)
            if (isset($vevent->{'RECURRENCE-ID'}) && isset($serienUids[$uid])) {
                continue;
            }

            if (count($eintraege) >= self::MAX_TERMINE) {
                break;
            }

            $eintrag = $this->eintragAus($vevent, count($eintraege));
            if ($eintrag === null) {
                continue;
            }

            if (!empty($ausnahmen[$uid]) && $eintrag['rrule']) {
                $eintrag['warnungen'][] = $ausnahmen[$uid] . ' abweichende Einzeltermin(e) der Serie werden nicht übernommen.';
            }

            $eintraege[] = $eintrag;
        }

        if (empty($eintraege)) {
            throw new \InvalidArgumentException('Die Datei enthält keine lesbaren Termine.');
        }

        usort($eintraege, fn ($a, $b) => strcmp($a['beginn'], $b['beginn']));

        // Index nach Sortierung neu vergeben – er dient als stabile Auswahl-ID im Formular
        foreach ($eintraege as $i => &$e) {
            $e['index'] = $i;
        }

        return $eintraege;
    }

    /**
     * Ein VEVENT in einen Vorschau-Eintrag umwandeln (null = nicht verwertbar).
     */
    protected function eintragAus(VEvent $vevent, int $index): ?array
    {
        if (!isset($vevent->DTSTART)) {
            return null;
        }

        $appTz      = new \DateTimeZone(config('app.timezone', 'Europe/Berlin'));
        $ganztaegig = !$vevent->DTSTART->hasTime();
        $beginn     = Carbon::instance($vevent->DTSTART->getDateTime($appTz))->setTimezone($appTz);

        if (isset($vevent->DTEND)) {
            $ende = Carbon::instance($vevent->DTEND->getDateTime($appTz))->setTimezone($appTz);
        } elseif (isset($vevent->DURATION)) {
            $ende = $beginn->copy()->add($vevent->DURATION->getDateInterval());
        } else {
            $ende = $ganztaegig ? $beginn->copy()->addDay() : $beginn->copy();
        }

        if ($ende->lt($beginn)) {
            $ende = $beginn->copy();
        }

        $warnungen = [];

        $exdates = [];
        foreach ($vevent->select('EXDATE') as $exdate) {
            foreach ($exdate->getDateTimes() as $dt) {
                $exdates[] = Carbon::instance($dt)->utc()->format('Y-m-d\TH:i:s\Z');
            }
        }

        $status   = isset($vevent->STATUS) ? strtoupper((string) $vevent->STATUS) : null;
        $abgesagt = $status === 'CANCELLED';
        if ($abgesagt) {
            $warnungen[] = 'Termin ist in der Quelle als abgesagt markiert.';
        }

        $titel = trim((string) ($vevent->SUMMARY ?? '')) ?: 'Ohne Titel';
        $beschreibung = isset($vevent->DESCRIPTION) ? trim((string) $vevent->DESCRIPTION) : null;

        $rrule = isset($vevent->RRULE) ? (string) $vevent->RRULE : null;
        $letztesEnde = $rrule ? null : $ende;

        return [
            'index'        => $index,
            'uid'          => (string) ($vevent->UID ?? ''),
            'titel'        => Str::limit($titel, 250, ''),
            'beschreibung' => $beschreibung ? Str::limit($beschreibung, 4500) : null,
            'ort'          => isset($vevent->LOCATION) ? Str::limit(trim((string) $vevent->LOCATION), 250, '') : null,
            'beginn'       => $beginn->format('Y-m-d H:i:s'),
            'ende'         => $ende->format('Y-m-d H:i:s'),
            'ganztaegig'   => $ganztaegig,
            'rrule'        => $rrule,
            'exdates'      => $exdates ?: null,
            'abgesagt'     => $abgesagt,
            'vergangen'    => $letztesEnde !== null && $letztesEnde->isPast(),
            'warnungen'    => $warnungen,
            'duplikate'    => [],
        ];
    }

    /**
     * Markiert Einträge, die in den übergebenen Kalendern bereits existieren
     * (gleicher Titel und gleicher Beginn). duplikate = [['id' => Kalender-ID, 'name' => …], …]
     *
     * @param  Collection<int, \App\Models\OxCalendar>  $kalender
     */
    public function duplikateMarkieren(array $eintraege, Collection $kalender): array
    {
        if ($kalender->isEmpty() || empty($eintraege)) {
            return $eintraege;
        }

        $vorhanden = OxTermin::query()
            ->whereIn('ox_calendar_id', $kalender->pluck('id'))
            ->whereIn('beginn', collect($eintraege)->pluck('beginn')->unique()->values())
            ->get(['ox_calendar_id', 'titel', 'beginn'])
            ->groupBy(fn (OxTermin $t) => mb_strtolower($t->titel) . '|' . $t->beginn->format('Y-m-d H:i:s'));

        $namen = $kalender->pluck('name', 'id');

        foreach ($eintraege as &$e) {
            $key = mb_strtolower($e['titel']) . '|' . $e['beginn'];
            $e['duplikate'] = $vorhanden->get($key, collect())
                ->unique('ox_calendar_id')
                ->map(fn (OxTermin $t) => ['id' => (int) $t->ox_calendar_id, 'name' => $namen[$t->ox_calendar_id] ?? '?'])
                ->values()->all();
        }

        return $eintraege;
    }

    // =========================================================================
    // Zwischenspeicher für die Vorschau
    // =========================================================================

    public function vorschauSpeichern(User $user, string $dateiname, array $eintraege): string
    {
        $token = Str::random(32);

        Cache::put($this->cacheKey($user, $token), [
            'dateiname' => $dateiname,
            'eintraege' => $eintraege,
        ], now()->addMinutes(self::CACHE_MINUTEN));

        return $token;
    }

    /**
     * @return array{dateiname: string, eintraege: array}|null
     */
    public function vorschauLaden(User $user, string $token): ?array
    {
        return Cache::get($this->cacheKey($user, $token));
    }

    public function vorschauVerwerfen(User $user, string $token): void
    {
        Cache::forget($this->cacheKey($user, $token));
    }

    protected function cacheKey(User $user, string $token): string
    {
        return 'kalender_ics_import_' . $user->id . '_' . $token;
    }

    // =========================================================================
    // Import
    // =========================================================================

    /**
     * Ausgewählte Einträge importieren.
     *
     * @param  int[]  $auswahl  Indizes der zu importierenden Einträge
     * @param  array<int, string|null>  $hinweise  Hinweis je Eintrag (Index → Text)
     * @param  Collection<int, \App\Models\OxCalendar>  $kalender
     * @return array{importiert: int, fehlgeschlagen: array<string, string>}
     */
    public function importieren(
        User $user,
        array $eintraege,
        array $auswahl,
        array $hinweise,
        ?string $hinweisAlle,
        Collection $kalender
    ): array {
        $auswahl  = array_flip(array_map('intval', $auswahl));
        $ergebnis = ['importiert' => 0, 'fehlgeschlagen' => []];

        foreach ($eintraege as $e) {
            if (!isset($auswahl[$e['index']])) {
                continue;
            }

            $daten = [
                'titel'        => $e['titel'],
                'beschreibung' => $this->mitHinweisen($e['beschreibung'], $hinweisAlle, $hinweise[$e['index']] ?? null),
                'ort'          => $e['ort'],
                'beginn'       => $e['beginn'],
                'ende'         => $e['ende'],
                'ganztaegig'   => $e['ganztaegig'],
                'rrule'        => $e['rrule'],
                'exdates'      => $e['exdates'],
            ];

            $res = $this->verbund->anlegen($user, $kalender, $daten);

            if ($res['termine']->isNotEmpty()) {
                $ergebnis['importiert']++;
            }

            foreach ($res['fehler'] as $kal => $msg) {
                $ergebnis['fehlgeschlagen'][$e['titel'] . ' (' . $kal . ')'] = $msg;
            }
        }

        return $ergebnis;
    }

    /**
     * Beschreibung um allgemeinen und terminbezogenen Hinweis ergänzen.
     */
    public function mitHinweisen(?string $beschreibung, ?string $hinweisAlle, ?string $hinweis): ?string
    {
        $teile = array_filter([
            trim((string) $beschreibung),
            ($h = trim((string) $hinweisAlle)) !== '' ? 'Hinweis: ' . $h : '',
            ($h = trim((string) $hinweis)) !== '' ? 'Hinweis: ' . $h : '',
        ], fn ($t) => $t !== '');

        return $teile ? Str::limit(implode("\n\n", $teile), 5000, '') : null;
    }
}
