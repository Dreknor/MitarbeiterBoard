<?php

namespace App\Services\Personal;

use App\Models\personal\EmployeData;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Maatwebsite\Excel\Facades\Excel;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;

/**
 * Geburtstage der Mitarbeitenden aus einer Excel-Liste übernehmen.
 *
 * Ablauf: lesen() → vorschauSpeichern() (Cache je Person + Token) → auswerten()
 * für die Vorschau → importieren(). Zuordnung über users.email (getrimmt, ohne
 * Groß-/Kleinschreibung, nur nicht gelöschte Konten). Bereits gesetzte
 * Geburtstage werden nie überschrieben. Gespeichert wird einzeln über das Model,
 * damit jede Änderung im Änderungsverlauf der Personalakte erscheint.
 */
class GeburtstagImportService
{
    public const CACHE_MINUTEN = 60;
    public const MAX_ZEILEN = 2000;
    public const MIN_ALTER = 14;
    public const MAX_ALTER = 100;

    public const SETZEN = 'setzen';
    public const GLEICH = 'gleich';
    public const ABWEICHEND = 'abweichend';
    public const NICHT_GEFUNDEN = 'nicht_gefunden';
    public const UNGUELTIG = 'ungueltig';
    public const KONFLIKT = 'konflikt';
    public const DOPPELT = 'doppelt';

    /** Reihenfolge und Beschriftung der Gruppen in Vorschau und Bericht. */
    public const STATUS = [
        self::SETZEN         => 'Wird gesetzt',
        self::ABWEICHEND     => 'Bereits gesetzt – anderes Datum',
        self::KONFLIKT       => 'Widersprüchliche Zeilen',
        self::UNGUELTIG      => 'Ungültiges Datum',
        self::NICHT_GEFUNDEN => 'E-Mail nicht gefunden',
        self::GLEICH         => 'Bereits gesetzt – gleiches Datum',
        self::DOPPELT        => 'Doppelte Zeile (ignoriert)',
    ];

    private const SPALTEN_EMAIL = ['email', 'e-mail', 'e_mail', 'mail', 'emailadresse', 'e-mail-adresse', 'e_mail_adresse', 'mailadresse'];
    private const SPALTEN_DATUM = ['geburtstag', 'geburtsdatum', 'birthday', 'birthdate', 'date_of_birth', 'geb', 'geb.', 'geb_datum', 'geb.-datum'];

    // =========================================================================
    // Datei lesen
    // =========================================================================

    /**
     * Liest die erste Tabelle der Datei. Die Kopfzeile darf in einer der ersten
     * zehn Zeilen stehen; weitere Spalten werden ignoriert.
     *
     * @return list<array{zeile: int, email: string, wert: mixed}>
     *
     * Hochgeladene Dateien als UploadedFile übergeben: Der Dateityp wird dann aus
     * dem Originalnamen bestimmt. Temporäre Upload-Pfade haben unter Linux keine
     * Endung, daran scheitert sonst die Typerkennung von Laravel-Excel.
     *
     * @throws \InvalidArgumentException
     */
    public function lesen(string|UploadedFile $datei): array
    {
        try {
            $blaetter = Excel::toArray(new \stdClass(), $datei);
        } catch (\Throwable $e) {
            Log::warning('Geburtstags-Import: Datei nicht lesbar', [
                'datei'  => $datei instanceof UploadedFile ? $datei->getClientOriginalName() : $datei,
                'fehler' => get_class($e) . ': ' . $e->getMessage(),
            ]);

            throw new \InvalidArgumentException('Die Datei konnte nicht gelesen werden. Bitte eine Excel-Datei (.xlsx, .xls, .ods) hochladen.');
        }

        $zeilen = $blaetter[0] ?? [];

        return $this->zeilenAuslesen($zeilen);
    }

    /**
     * @param  array<int, array<int, mixed>>  $zeilen  Rohzeilen der Tabelle (0-basiert)
     * @return list<array{zeile: int, email: string, wert: mixed}>
     *
     * @throws \InvalidArgumentException
     */
    public function zeilenAuslesen(array $zeilen): array
    {
        $kopf = null;
        $spalteEmail = $spalteDatum = null;

        foreach (array_slice($zeilen, 0, 10, true) as $index => $zeile) {
            $spalteEmail = $spalteDatum = null;
            foreach ((array) $zeile as $spalte => $titel) {
                $name = $this->spaltenName($titel);
                if ($spalteEmail === null && in_array($name, self::SPALTEN_EMAIL, true)) {
                    $spalteEmail = $spalte;
                } elseif ($spalteDatum === null && in_array($name, self::SPALTEN_DATUM, true)) {
                    $spalteDatum = $spalte;
                }
            }
            if ($spalteEmail !== null && $spalteDatum !== null) {
                $kopf = $index;
                break;
            }
        }

        if ($kopf === null) {
            throw new \InvalidArgumentException('Keine Kopfzeile mit den Spalten „E-Mail“ und „Geburtstag“ gefunden. Bitte die Beispieldatei als Vorlage nutzen.');
        }

        $ergebnis = [];
        foreach ($zeilen as $index => $zeile) {
            if ($index <= $kopf) {
                continue;
            }
            $email = trim((string) ($zeile[$spalteEmail] ?? ''));
            $wert = $zeile[$spalteDatum] ?? null;
            $wert = is_string($wert) ? trim($wert) : $wert;

            if ($email === '' && ($wert === null || $wert === '')) {
                continue; // leere Zeile
            }

            $ergebnis[] = ['zeile' => $index + 1, 'email' => $email, 'wert' => $wert];
        }

        if ($ergebnis === []) {
            throw new \InvalidArgumentException('Die Datei enthält unter der Kopfzeile keine Einträge.');
        }
        if (count($ergebnis) > self::MAX_ZEILEN) {
            throw new \InvalidArgumentException('Die Datei enthält mehr als ' . self::MAX_ZEILEN . ' Zeilen. Bitte aufteilen.');
        }

        return $ergebnis;
    }

    private function spaltenName(mixed $titel): string
    {
        $name = Str::lower(trim((string) $titel));

        return (string) preg_replace('/\s+/', '', $name);
    }

    // =========================================================================
    // Datum
    // =========================================================================

    /**
     * Wandelt einen Zellwert in ein Datum. Erlaubt: Excel-Datumszelle,
     * TT.MM.JJJJ und JJJJ-MM-TT. Zweistellige Jahre sind nicht eindeutig.
     *
     * @return array{datum: ?Carbon, fehler: ?string}
     */
    public function datumLesen(mixed $wert, ?Carbon $heute = null): array
    {
        $heute = ($heute ?? now())->copy()->startOfDay();

        if ($wert === null || $wert === '') {
            return ['datum' => null, 'fehler' => 'Kein Datum angegeben'];
        }

        $datum = null;

        if ($wert instanceof \DateTimeInterface) {
            $datum = Carbon::instance($wert);
        } elseif (is_int($wert) || is_float($wert) || (is_string($wert) && preg_match('/^\d+(\.0+)?$/', $wert) && (float) $wert > 1000)) {
            $serial = (float) $wert;
            if ($serial < 1 || $serial > 2958465) {
                return ['datum' => null, 'fehler' => 'Unbekanntes Format „' . $wert . '“'];
            }
            $datum = Carbon::instance(ExcelDate::excelToDateTimeObject($serial));
        } elseif (is_string($wert)) {
            if (preg_match('/^(\d{1,2})\.(\d{1,2})\.(\d{4})$/', $wert, $m)) {
                [$tag, $monat, $jahr] = [(int) $m[1], (int) $m[2], (int) $m[3]];
            } elseif (preg_match('/^(\d{4})-(\d{1,2})-(\d{1,2})$/', $wert, $m)) {
                [$jahr, $monat, $tag] = [(int) $m[1], (int) $m[2], (int) $m[3]];
            } elseif (preg_match('/^\d{1,2}\.\d{1,2}\.\d{2}$/', $wert)) {
                return ['datum' => null, 'fehler' => 'Zweistelliges Jahr „' . $wert . '“ ist nicht eindeutig'];
            } else {
                return ['datum' => null, 'fehler' => 'Unbekanntes Format „' . Str::limit($wert, 30) . '“'];
            }

            if (!checkdate($monat, $tag, $jahr)) {
                return ['datum' => null, 'fehler' => 'Kein gültiges Datum „' . $wert . '“'];
            }
            $datum = Carbon::create($jahr, $monat, $tag);
        } else {
            return ['datum' => null, 'fehler' => 'Unbekanntes Format'];
        }

        $datum = $datum->copy()->startOfDay();

        if ($datum->gt($heute)) {
            return ['datum' => null, 'fehler' => 'Datum ' . $datum->format('d.m.Y') . ' liegt in der Zukunft'];
        }
        $alter = $datum->diffInYears($heute);
        if ($alter < self::MIN_ALTER || $alter > self::MAX_ALTER) {
            return ['datum' => null, 'fehler' => 'Unplausibles Alter (' . (int) $alter . ' Jahre) bei ' . $datum->format('d.m.Y')];
        }

        return ['datum' => $datum, 'fehler' => null];
    }

    // =========================================================================
    // Auswertung
    // =========================================================================

    /**
     * Ordnet jede Zeile einem Status zu – gegen den aktuellen Datenbestand.
     *
     * @param  list<array{zeile: int, email: string, wert: mixed}>  $zeilen
     * @return list<array{zeile: int, email: string, wert: string, status: string, datum: ?string, vorhanden: ?string, user_id: ?int, name: ?string, hinweis: ?string}>
     */
    public function auswerten(array $zeilen): array
    {
        $emails = collect($zeilen)->map(fn ($z) => Str::lower(trim($z['email'])))->filter()->unique()->values();

        $nutzer = User::query()
            ->whereIn(DB::raw('LOWER(TRIM(email))'), $emails->all())
            ->with('employe_data')
            ->get()
            ->keyBy(fn (User $u) => Str::lower(trim($u->email)));

        $ergebnis = [];
        foreach ($zeilen as $z) {
            $email = Str::lower(trim($z['email']));
            $gelesen = $email === '' ? ['datum' => null, 'fehler' => null] : $this->datumLesen($z['wert']);
            $user = $nutzer->get($email);

            $eintrag = [
                'zeile'     => $z['zeile'],
                'email'     => $z['email'],
                'wert'      => $this->wertAnzeigen($z['wert']),
                'status'    => null,
                'datum'     => $gelesen['datum']?->toDateString(),
                'vorhanden' => $user?->employe_data?->geburtstag?->toDateString(),
                'user_id'   => $user?->id,
                'name'      => $user?->name,
                'hinweis'   => null,
                'schluessel' => $email,
            ];

            if ($email === '') {
                $eintrag['status'] = self::UNGUELTIG;
                $eintrag['hinweis'] = 'Keine E-Mail-Adresse angegeben';
            } elseif (!$user) {
                $eintrag['status'] = self::NICHT_GEFUNDEN;
            } elseif ($gelesen['fehler']) {
                $eintrag['status'] = self::UNGUELTIG;
                $eintrag['hinweis'] = $gelesen['fehler'];
            }

            $ergebnis[] = $eintrag;
        }

        // Mehrfach genannte Personen: gleiche Daten → einmal werten, sonst Konflikt
        $gruppen = collect($ergebnis)
            ->filter(fn ($e) => $e['status'] === null)
            ->groupBy('schluessel', true);

        foreach ($gruppen as $eintraege) {
            $daten = $eintraege->pluck('datum')->unique();
            $erster = true;
            foreach ($eintraege as $index => $e) {
                if ($daten->count() > 1) {
                    $ergebnis[$index]['status'] = self::KONFLIKT;
                    $ergebnis[$index]['hinweis'] = 'Unterschiedliche Daten in Zeile ' . $eintraege->pluck('zeile')->implode(', ');
                    continue;
                }
                if (!$erster) {
                    $ergebnis[$index]['status'] = self::DOPPELT;
                    $ergebnis[$index]['hinweis'] = 'Gleiche Angabe wie Zeile ' . $eintraege->first()['zeile'];
                    continue;
                }
                $erster = false;

                if ($e['vorhanden'] === null) {
                    $ergebnis[$index]['status'] = self::SETZEN;
                } elseif ($e['vorhanden'] === $e['datum']) {
                    $ergebnis[$index]['status'] = self::GLEICH;
                } else {
                    $ergebnis[$index]['status'] = self::ABWEICHEND;
                }
            }
        }

        return array_map(function ($e) {
            unset($e['schluessel']);
            return $e;
        }, $ergebnis);
    }

    /**
     * @param  list<array{status: string}>  $auswertung
     * @return array<string, list<array>>  Status => Zeilen, in der Reihenfolge von STATUS
     */
    public function gruppieren(array $auswertung): array
    {
        $gruppen = array_fill_keys(array_keys(self::STATUS), []);
        foreach ($auswertung as $e) {
            $gruppen[$e['status']][] = $e;
        }

        return $gruppen;
    }

    private function wertAnzeigen(mixed $wert): string
    {
        if ($wert instanceof \DateTimeInterface) {
            return $wert->format('d.m.Y');
        }
        if (is_float($wert) || is_int($wert)) {
            // Excel-Datumszelle: lesbar machen, Rohwert sonst nichtssagend
            if ($wert >= 1 && $wert <= 2958465) {
                return ExcelDate::excelToDateTimeObject((float) $wert)->format('d.m.Y');
            }
        }

        return Str::limit((string) $wert, 40);
    }

    // =========================================================================
    // Import
    // =========================================================================

    /**
     * Setzt alle Geburtstage mit Status „setzen“. Die Auswertung läuft erneut
     * gegen den aktuellen Bestand, damit zwischenzeitlich gesetzte Daten nicht
     * überschrieben werden.
     *
     * @param  list<array{zeile: int, email: string, wert: mixed}>  $zeilen
     * @return array{gesetzt: list<array>, uebersprungen: int, auswertung: list<array>}
     */
    public function importieren(array $zeilen): array
    {
        $auswertung = $this->auswerten($zeilen);
        $gesetzt = [];

        DB::transaction(function () use (&$auswertung, &$gesetzt) {
            foreach ($auswertung as $i => $e) {
                if ($e['status'] !== self::SETZEN) {
                    continue;
                }

                $user = User::with('employe_data')->find($e['user_id']);
                if (!$user) {
                    continue;
                }

                $daten = $user->employe_data;
                if ($daten && $daten->geburtstag !== null) {
                    // zwischenzeitlich gesetzt – nie überschreiben
                    $auswertung[$i]['vorhanden'] = $daten->geburtstag->toDateString();
                    $auswertung[$i]['status'] = $auswertung[$i]['vorhanden'] === $e['datum'] ? self::GLEICH : self::ABWEICHEND;
                    $auswertung[$i]['hinweis'] = 'Zwischenzeitlich gesetzt';
                    continue;
                }

                if (!$daten) {
                    // Ohne explizites null würde die Datenbank „männlich“ eintragen
                    $daten = new EmployeData(['user_id' => $user->id, 'geschlecht' => null]);
                }

                $daten->geburtstag = $e['datum'];
                $daten->save(); // über das Model → Änderungsverlauf

                $gesetzt[] = $e;
            }
        });

        return [
            'gesetzt'       => $gesetzt,
            'uebersprungen' => count($auswertung) - count($gesetzt),
            'auswertung'    => $auswertung,
        ];
    }

    // =========================================================================
    // Vorschau-Cache
    // =========================================================================

    /**
     * @param  list<array{zeile: int, email: string, wert: mixed}>  $zeilen
     */
    public function vorschauSpeichern(User $user, string $dateiname, array $zeilen): string
    {
        $token = Str::random(32);

        // DateTime-Objekte nicht serialisieren – als Datumstext ablegen
        $zeilen = array_map(function ($z) {
            if ($z['wert'] instanceof \DateTimeInterface) {
                $z['wert'] = $z['wert']->format('Y-m-d');
            }
            return $z;
        }, $zeilen);

        Cache::put($this->cacheKey($user, $token), [
            'dateiname' => $dateiname,
            'zeilen'    => $zeilen,
        ], now()->addMinutes(self::CACHE_MINUTEN));

        return $token;
    }

    /**
     * @return array{dateiname: string, zeilen: list<array>}|null
     */
    public function vorschauLaden(User $user, string $token): ?array
    {
        return Cache::get($this->cacheKey($user, $token));
    }

    public function vorschauVerwerfen(User $user, string $token): void
    {
        Cache::forget($this->cacheKey($user, $token));
    }

    private function cacheKey(User $user, string $token): string
    {
        return 'geburtstag_import_' . $user->id . '_' . $token;
    }
}
