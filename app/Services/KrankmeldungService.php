<?php

namespace App\Services;

use App\Models\Absence;
use Carbon\CarbonPeriod;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * Krankmeldungen auswerten (Seite /sick_notes und Excel-Export) – eine Stelle für alle Regeln.
 *
 * Krankenschein-Regel: ab dem N. Krankheitstag, gezählt in Arbeitstagen (Setting
 * absence_sick_note_days, Standard 3); mit ausdrücklich verlangtem Schein ab dem ersten Tag. Status je Meldung:
 *   liegt_vor     Schein erfasst (sick_note_date)
 *   fehlt         nötig und fällig, aber nicht erfasst
 *   ausstehend    nötig, aber noch nicht fällig (laufende Krankheit)
 *   nicht_noetig  kurze Krankheit ohne Pflicht
 *
 * Arbeitstage zählen ohne Wochenenden und Feiertage; Statistiken zählen nur die Tage im
 * gewählten Zeitraum (eine Krankheit über den Jahreswechsel wird anteilig gezählt).
 */
class KrankmeldungService
{
    public const ZEITRAEUME = [
        '12monate' => 'Letzte 12 Monate',
        'schuljahr' => 'Aktuelles Schuljahr',
        'jahr' => 'Dieses Kalenderjahr',
        'vorjahr' => 'Vorjahr',
        'frei' => 'Eigener Zeitraum',
    ];

    public const STATUS = [
        'fehlt' => 'Schein fehlt',
        'ausstehend' => 'noch nicht fällig',
        'liegt_vor' => 'Schein liegt vor',
        'nicht_noetig' => 'nicht nötig',
    ];

    public const SORTIERUNG = ['start', 'end', 'reason', 'name', 'tage', 'status'];

    public const PERSONEN_SORTIERUNG = ['name', 'faelle', 'tage', 'fehlt', 'kurz', 'laengste'];

    /** Dauer-Gruppen in Arbeitstagen [Bezeichnung => [von, bis]] */
    public const DAUER_GRUPPEN = [
        '1 Tag' => [1, 1],
        '2–3 Tage' => [2, 3],
        '4–5 Tage' => [4, 5],
        '6–10 Tage' => [6, 10],
        '11–30 Tage' => [11, 30],
        'über 30 Tage' => [31, PHP_INT_MAX],
    ];

    /**
     * BEM (§ 167 Abs. 2 SGB IX): innerhalb von 12 Monaten länger als sechs Wochen arbeitsunfähig.
     * Näherung: 30 Arbeitstage eigene Krankheit (ohne "Kind krank") in den letzten 12 Monaten.
     */
    public const BEM_ARBEITSTAGE = 30;

    /** @var array<string, int> */
    private array $arbeitstageCache = [];

    // ---------------------------------------------------------------
    // Regeln
    // ---------------------------------------------------------------

    public function schwelle(): int
    {
        return Absence::krankenscheinTage();
    }

    /** @return array<int, string> */
    public function gruende(): array
    {
        return (array) config('absences.absence_sick_note');
    }

    /** Eigene Krankheit (zählt für Kurzerkrankungen und BEM, "Kind krank" nicht). */
    public function istEigeneKrankheit(Absence $absence): bool
    {
        return in_array(mb_strtolower(trim($absence->reason)), array_map('mb_strtolower', (array) config('absences.absence_own_sick')), true);
    }

    public function kalendertage(Absence $absence): int
    {
        return (int) $absence->start->diffInDays($absence->end) + 1;
    }

    public function scheinNoetig(Absence $absence): bool
    {
        return (bool) $absence->sick_note_required || $this->erreichtSchwelle($absence);
    }

    /** Dauert die Krankheit mindestens N Arbeitstage? */
    public function erreichtSchwelle(Absence $absence): bool
    {
        return $this->arbeitstage($absence) >= $this->schwelle();
    }

    /**
     * Ab diesem Tag muss der Schein vorliegen: bei langer Krankheit am N. Arbeitstag
     * (das Model setzt dann sick_note_required automatisch), bei ausdrücklich verlangtem
     * Schein für eine kurze Krankheit ab dem ersten Tag.
     */
    public function faelligAb(Absence $absence): Carbon
    {
        if (!$this->erreichtSchwelle($absence)) {
            return $absence->start->copy();
        }

        $tag = $absence->start->copy()->startOfDay();
        $gezaehlt = 0;
        while ($tag->lte($absence->end)) {
            if ($tag->isWeekday() && !is_holiday($tag) && ++$gezaehlt >= $this->schwelle()) {
                return $tag;
            }
            $tag->addDay();
        }

        return $absence->end->copy();
    }

    public function status(Absence $absence): string
    {
        if ($absence->sick_note_date !== null) {
            return 'liegt_vor';
        }
        if (!$this->scheinNoetig($absence)) {
            return 'nicht_noetig';
        }

        return $this->faelligAb($absence)->gt(Carbon::today()) ? 'ausstehend' : 'fehlt';
    }

    /** Kurze eigene Krankheit, für die kein Schein nötig war und keiner vorliegt. */
    public function istKurzerkrankung(Absence $absence): bool
    {
        return $this->status($absence) === 'nicht_noetig' && $this->istEigeneKrankheit($absence);
    }

    /**
     * Arbeitstage einer Meldung, optional beschnitten auf [$von, $bis].
     */
    public function arbeitstage(Absence $absence, ?Carbon $von = null, ?Carbon $bis = null): int
    {
        $start = $von ? $absence->start->copy()->max($von) : $absence->start->copy();
        $ende = $bis ? $absence->end->copy()->min($bis) : $absence->end->copy();

        return $this->arbeitstageZwischen($start, $ende);
    }

    private function arbeitstageZwischen(Carbon $start, Carbon $ende): int
    {
        if ($ende->lt($start)) {
            return 0;
        }

        $key = $start->toDateString().'|'.$ende->toDateString();

        return $this->arbeitstageCache[$key] ??= collect(CarbonPeriod::create($start->copy()->startOfDay(), $ende->copy()->startOfDay()))
            ->filter(fn (Carbon $tag) => $tag->isWeekday() && !is_holiday($tag))
            ->count();
    }

    // ---------------------------------------------------------------
    // Filter und Abfrage
    // ---------------------------------------------------------------

    /**
     * @param array<string, mixed> $daten validierte Request-Daten
     * @return array{zeitraum: string, von: Carbon, bis: Carbon, grund: string, person: int|null, status: string, sort: string, richtung: string, psort: string, prichtung: string}
     */
    public function filter(array $daten): array
    {
        $zeitraum = $daten['zeitraum'] ?? '12monate';
        [$von, $bis] = $this->zeitraumGrenzen($zeitraum, $daten['von'] ?? null, $daten['bis'] ?? null);

        return [
            'zeitraum' => $zeitraum,
            'von' => $von,
            'bis' => $bis,
            'grund' => (string) ($daten['grund'] ?? ''),
            'person' => isset($daten['person']) ? (int) $daten['person'] : null,
            'status' => (string) ($daten['status'] ?? ''),
            'sort' => $daten['sort'] ?? 'start',
            'richtung' => $daten['richtung'] ?? 'desc',
            'psort' => $daten['psort'] ?? 'tage',
            'prichtung' => $daten['prichtung'] ?? 'desc',
        ];
    }

    /** @return array{0: Carbon, 1: Carbon} */
    public function zeitraumGrenzen(string $zeitraum, ?string $von = null, ?string $bis = null): array
    {
        $heute = Carbon::today();

        return match ($zeitraum) {
            'schuljahr' => [$this->schuljahresbeginn(), $this->schuljahresbeginn()->addYear()->subDay()],
            'jahr' => [$heute->copy()->startOfYear(), $heute->copy()->endOfYear()->startOfDay()],
            'vorjahr' => [$heute->copy()->subYear()->startOfYear(), $heute->copy()->subYear()->endOfYear()->startOfDay()],
            'frei' => [
                $von ? Carbon::parse($von)->startOfDay() : $heute->copy()->subYear()->addDay(),
                $bis ? Carbon::parse($bis)->startOfDay() : $heute->copy(),
            ],
            default => [$heute->copy()->subYear()->addDay(), $heute->copy()],
        };
    }

    public function schuljahresbeginn(): Carbon
    {
        $heute = Carbon::today();

        return Carbon::create($heute->month >= 8 ? $heute->year : $heute->year - 1, 8, 1)->startOfDay();
    }

    /**
     * Alle Krankmeldungen im Zeitraum (mit Grund-/Personenfilter, ohne Statusfilter) –
     * Grundlage für Liste, Kennzahlen, Diagramme und Personenübersicht.
     *
     * @return Collection<int, Absence>
     */
    public function meldungen(array $filter): Collection
    {
        $query = $this->basis()
            ->whereDate('end', '>=', $filter['von'])
            ->whereDate('start', '<=', $filter['bis']);

        if ($filter['grund'] !== '') {
            $query->where('reason', $filter['grund']);
        }
        if ($filter['person']) {
            $query->where('users_id', $filter['person']);
        }

        return $query->with('user')->orderByDesc('start')->orderByDesc('id')->get();
    }

    /** Krankheitsbedingte Abwesenheiten: Grund aus der Liste oder Schein ausdrücklich verlangt. */
    public function basis(): Builder
    {
        return Absence::query()->where(fn (Builder $q) => $q
            ->whereIn('reason', $this->gruende())
            ->orWhere('sick_note_required', true));
    }

    public function gehoertDazu(Absence $absence): bool
    {
        return $this->basis()->whereKey($absence->id)->exists();
    }

    /**
     * Liste mit Statusfilter und Sortierung.
     *
     * @param Collection<int, Absence> $meldungen
     * @return Collection<int, Absence>
     */
    public function liste(Collection $meldungen, array $filter): Collection
    {
        $liste = $filter['status'] !== ''
            ? $meldungen->filter(fn (Absence $a) => $this->status($a) === $filter['status'])
            : $meldungen;

        $schluessel = match ($filter['sort']) {
            'end' => fn (Absence $a) => $a->end->format('Ymd').sprintf('%010d', $a->id),
            'reason' => fn (Absence $a) => mb_strtolower($a->reason).$a->start->format('Ymd'),
            'name' => fn (Absence $a) => mb_strtolower($a->user->name).$a->start->format('Ymd'),
            'tage' => fn (Absence $a) => sprintf('%05d', $this->arbeitstage($a)).$a->start->format('Ymd'),
            'status' => fn (Absence $a) => array_search($this->status($a), array_keys(self::STATUS), true).$a->start->format('Ymd'),
            default => fn (Absence $a) => $a->start->format('Ymd').sprintf('%010d', $a->id),
        };

        return ($filter['richtung'] === 'asc' ? $liste->sortBy($schluessel) : $liste->sortByDesc($schluessel))->values();
    }

    // ---------------------------------------------------------------
    // Auswertungen
    // ---------------------------------------------------------------

    /**
     * @param Collection<int, Absence> $meldungen
     * @return array{faelle: int, tage: int, personen: int, durchschnitt: float, fehlt: int, ausstehend: int, kurz: int, kurz_anteil: int, tage_je_person: float, langzeit: int}
     */
    public function kennzahlen(Collection $meldungen, array $filter): array
    {
        $tage = $meldungen->sum(fn (Absence $a) => $this->arbeitstage($a, $filter['von'], $filter['bis']));
        $personen = $meldungen->unique('users_id')->count();
        $faelle = $meldungen->count();
        $kurz = $meldungen->filter(fn (Absence $a) => $this->istKurzerkrankung($a))->count();

        return [
            'faelle' => $faelle,
            'tage' => $tage,
            'personen' => $personen,
            'durchschnitt' => $faelle ? round($meldungen->sum(fn (Absence $a) => $this->arbeitstage($a)) / $faelle, 1) : 0.0,
            'fehlt' => $meldungen->filter(fn (Absence $a) => $this->status($a) === 'fehlt')->count(),
            'ausstehend' => $meldungen->filter(fn (Absence $a) => $this->status($a) === 'ausstehend')->count(),
            'kurz' => $kurz,
            'kurz_anteil' => $faelle ? (int) round($kurz / $faelle * 100) : 0,
            'tage_je_person' => $personen ? round($tage / $personen, 1) : 0.0,
            'langzeit' => $meldungen->filter(fn (Absence $a) => $this->arbeitstage($a) > 30)->count(),
        ];
    }

    /**
     * Krankheitstage (Arbeitstage) je Monat und Grund im Zeitraum.
     *
     * @param Collection<int, Absence> $meldungen
     * @return array{monate: array<int, array{label: string, schluessel: string, gesamt: int, faelle: int, gruende: array<string, int>}>, gruende: array<int, string>, max: int}
     */
    public function monatsverlauf(Collection $meldungen, array $filter): array
    {
        $gruende = $meldungen->pluck('reason')->unique()->sort()->values()->all();
        $monate = [];

        $monat = $filter['von']->copy()->startOfMonth();
        while ($monat->lte($filter['bis'])) {
            $von = $monat->copy()->max($filter['von']);
            $bis = $monat->copy()->endOfMonth()->startOfDay()->min($filter['bis']);

            $proGrund = array_fill_keys($gruende, 0);
            $faelle = 0;
            foreach ($meldungen as $absence) {
                if ($absence->start->lte($bis) && $absence->end->gte($von)) {
                    $proGrund[$absence->reason] += $this->arbeitstage($absence, $von, $bis);
                }
                if ($absence->start->between($von, $bis)) {
                    $faelle++;
                }
            }

            $monate[] = [
                'label' => $monat->locale('de')->isoFormat('MMM YY'),
                'schluessel' => $monat->format('Y-m'),
                'gesamt' => array_sum($proGrund),
                'faelle' => $faelle,
                'gruende' => $proGrund,
            ];
            $monat->addMonth();
        }

        return [
            'monate' => $monate,
            'gruende' => $gruende,
            'max' => max(1, ...array_column($monate, 'gesamt') ?: [1]),
        ];
    }

    /**
     * Beginn der Krankmeldungen nach Wochentag (Mo–Fr, Beginn am Wochenende zählt zum Montag).
     *
     * @param Collection<int, Absence> $meldungen
     * @return array<string, int>
     */
    public function wochentage(Collection $meldungen): array
    {
        $tage = ['Mo' => 0, 'Di' => 0, 'Mi' => 0, 'Do' => 0, 'Fr' => 0];
        $namen = array_keys($tage);

        foreach ($meldungen as $absence) {
            $iso = $absence->start->dayOfWeekIso;
            $tage[$namen[$iso >= 6 ? 0 : $iso - 1]]++;
        }

        return $tage;
    }

    /**
     * @param Collection<int, Absence> $meldungen
     * @return array<string, int>
     */
    public function dauerverteilung(Collection $meldungen): array
    {
        $verteilung = array_fill_keys(array_keys(self::DAUER_GRUPPEN), 0);

        foreach ($meldungen as $absence) {
            $tage = max(1, $this->arbeitstage($absence));
            foreach (self::DAUER_GRUPPEN as $label => [$von, $bis]) {
                if ($tage >= $von && $tage <= $bis) {
                    $verteilung[$label]++;
                    break;
                }
            }
        }

        return $verteilung;
    }

    /**
     * Übersicht je Person im Zeitraum (Tage = Arbeitstage im Zeitraum), inkl. BEM-Hinweis.
     *
     * @param Collection<int, Absence> $meldungen
     * @return Collection<int, array{user_id: int, name: string, faelle: int, tage: int, liegt_vor: int, nicht_noetig: int, fehlt: int, ausstehend: int, kurz: int, laengste: int, letzte: Carbon, bem: int|null}>
     */
    public function personen(Collection $meldungen, array $filter): Collection
    {
        $bem = $this->bemTage($meldungen->pluck('users_id')->unique()->all());

        $personen = $meldungen->groupBy('users_id')->map(function (Collection $meldungenPerson, $userId) use ($filter, $bem) {
            $zeile = [
                'user_id' => (int) $userId,
                'name' => $meldungenPerson->first()->user->name,
                'faelle' => $meldungenPerson->count(),
                'tage' => 0,
                'liegt_vor' => 0,
                'nicht_noetig' => 0,
                'fehlt' => 0,
                'ausstehend' => 0,
                'kurz' => 0,
                'laengste' => 0,
                'letzte' => $meldungenPerson->max('start'),
                'bem' => ($bem[(int) $userId] ?? 0) >= self::BEM_ARBEITSTAGE ? $bem[(int) $userId] : null,
            ];

            foreach ($meldungenPerson as $absence) {
                $tage = $this->arbeitstage($absence, $filter['von'], $filter['bis']);
                $zeile['tage'] += $tage;
                $zeile[$this->status($absence)] += $tage;
                $zeile['kurz'] += $this->istKurzerkrankung($absence) ? 1 : 0;
                $zeile['laengste'] = max($zeile['laengste'], $this->arbeitstage($absence));
            }

            return $zeile;
        })->values();

        $schluessel = $filter['psort'] === 'name'
            ? fn (array $p) => mb_strtolower($p['name'])
            : fn (array $p) => sprintf('%06d', $p[$filter['psort']]).mb_strtolower($p['name']);

        return ($filter['prichtung'] === 'asc' ? $personen->sortBy($schluessel) : $personen->sortByDesc($schluessel))->values();
    }

    /**
     * Arbeitstage eigener Krankheit der letzten 12 Monate je Person (unabhängig vom Filter).
     *
     * @param array<int, int> $userIds
     * @return array<int, int>
     */
    public function bemTage(array $userIds): array
    {
        if ($userIds === []) {
            return [];
        }

        $bis = Carbon::today();
        $von = $bis->copy()->subYear()->addDay();

        return Absence::query()
            ->whereIn('users_id', $userIds)
            ->whereDate('end', '>=', $von)
            ->whereDate('start', '<=', $bis)
            ->get()
            ->filter(fn (Absence $a) => $this->istEigeneKrankheit($a))
            ->groupBy('users_id')
            ->map(fn (Collection $c) => $c->sum(fn (Absence $a) => $this->arbeitstage($a, $von, $bis)))
            ->all();
    }

    /** Für Dateinamen und Excel-Blattnamen (max. 31 Zeichen, ohne : \ / ? * [ ]). */
    public static function sichererName(string $name, int $laenge = 31): string
    {
        $name = trim(preg_replace(['/[:\\\\\\/?*\\[\\]]+/u', '/\\s+/u'], ' ', $name));

        return Str::limit($name !== '' ? $name : 'Mitarbeiter', $laenge, '');
    }
}
