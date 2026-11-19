<?php

namespace App\Services;

use App\Models\Absence;
use App\Models\AbsenceSerie;
use App\Models\User;
use App\Notifications\AbwesenheitsserieGemeldet;
use App\Services\Benachrichtigungen\BenachrichtigungsService;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Wiederkehrende Abwesenheiten (Web: AbsenceController, Formulare /absences und Dashboard-Karten).
 *
 * Die Termine werden beim Anlegen einmalig als einzelne eintägige Abwesenheiten erzeugt und über
 * absences.serie_id verknüpft – Vertretungsplan, Arbeitszeitnachweis und Export arbeiten damit
 * unverändert. Regeln:
 *  - Beginn frühestens heute, Ende höchstens ein Jahr nach Beginn, keine Krank-Gründe.
 *  - Wochenenden und Feiertage werden übersprungen, ebenso Tage, an denen die Person schon
 *    abwesend ist (egal aus welchem Grund, auch Urlaub). Serientermine werden nie zusammengeführt.
 *  - Eine Benachrichtigung pro Serie statt pro Termin.
 *  - Löschen: einzelne Termine wie bisher oder alle zukünftigen Termine der Serie; die Regel bleibt.
 */
class AbsenceSerieService
{
    public function __construct(
        private readonly AbsenceService $absences,
        private readonly BenachrichtigungsService $benachrichtigungen,
    ) {
    }

    /**
     * @param array{users_id: int|string, reason: string, start: string, end: string, showVertretungsplan?: int|bool|null,
     *     rhythmus: string, wochentage?: array<int, int|string>|null, intervall?: int|string|null,
     *     monat_position?: int|string|null, monat_wochentag?: int|string|null} $daten
     * @return array{serie: AbsenceSerie, angelegt: int, uebersprungen: int}
     */
    public function anlegen(User $actor, array $daten): array
    {
        if (!$this->absences->darfMeldenFuer($actor, $daten['users_id'])) {
            $daten['users_id'] = $actor->id;
        }

        $serie = new AbsenceSerie([
            'users_id' => (int) $daten['users_id'],
            'creator_id' => $actor->id,
            'reason' => $daten['reason'],
            'rhythmus' => $daten['rhythmus'],
            'wochentage' => $daten['rhythmus'] === 'woechentlich'
                ? collect($daten['wochentage'] ?? [])->map(fn ($t) => (int) $t)->unique()->sort()->values()->all()
                : null,
            'intervall' => $daten['rhythmus'] === 'woechentlich' ? max(1, (int) ($daten['intervall'] ?? 1)) : 1,
            'monat_position' => $daten['rhythmus'] === 'monatlich' ? (int) $daten['monat_position'] : null,
            'monat_wochentag' => $daten['rhythmus'] === 'monatlich' ? (int) $daten['monat_wochentag'] : null,
            'start' => Carbon::parse($daten['start'])->startOfDay(),
            'end' => Carbon::parse($daten['end'])->startOfDay(),
            'showVertretungsplan' => (bool) ($daten['showVertretungsplan'] ?? true),
        ]);

        $this->pruefen($serie);

        $termine = $this->termine($serie);
        $belegt = $this->belegteTage($serie->users_id, $serie->start, $serie->end);
        $frei = $termine->reject(fn (Carbon $tag) => isset($belegt[$tag->toDateString()]))->values();

        if ($frei->isEmpty()) {
            throw ValidationException::withMessages([
                'rhythmus' => $termine->isEmpty()
                    ? 'Im gewählten Zeitraum gibt es keinen passenden Termin (Wochenenden und Feiertage werden übersprungen).'
                    : 'An allen Terminen der Serie ist die Person bereits abwesend.',
            ]);
        }

        DB::transaction(function () use ($serie, $frei) {
            $serie->save();

            foreach ($frei as $tag) {
                $absence = new Absence([
                    'users_id' => $serie->users_id,
                    'serie_id' => $serie->id,
                    'creator_id' => $serie->creator_id,
                    'reason' => $serie->reason,
                    'start' => $tag,
                    'end' => $tag,
                    'showVertretungsplan' => $serie->showVertretungsplan,
                ]);
                $absence->save();
            }
        });

        Log::debug('Abwesenheit: Serie gespeichert', ['user' => $actor->id, 'serie' => $serie->id, 'termine' => $frei->count()]);

        Notification::send(
            $this->benachrichtigungen->aktiviertFuer($this->benachrichtigungen->nutzerMitPermission('view absences'), 'abwesenheiten'),
            new AbwesenheitsserieGemeldet($serie, $frei->count())
        );

        return [
            'serie' => $serie,
            'angelegt' => $frei->count(),
            'uebersprungen' => $termine->count() - $frei->count(),
        ];
    }

    /**
     * Alle Tage der Regel im Zeitraum – ohne Wochenenden und Feiertage.
     *
     * @return Collection<int, Carbon>
     */
    public function termine(AbsenceSerie $serie): Collection
    {
        $start = $serie->start->copy()->startOfDay();
        $ende = $serie->end->copy()->startOfDay();
        $tage = collect();

        if ($serie->rhythmus === 'monatlich') {
            $monat = $start->copy()->startOfMonth();
            while ($monat->lte($ende)) {
                $tag = $serie->monat_position === -1
                    ? $monat->copy()->lastOfMonth($serie->monat_wochentag % 7)
                    : $monat->copy()->nthOfMonth($serie->monat_position, $serie->monat_wochentag % 7);

                if ($tag instanceof Carbon && $tag->betweenIncluded($start, $ende)) {
                    $tage->push($tag->startOfDay());
                }
                $monat->addMonthNoOverflow()->startOfMonth();
            }
        } else {
            $wochentage = array_map('intval', $serie->wochentage ?? []);
            $ersteWoche = $start->copy()->startOfWeek(Carbon::MONDAY);

            foreach (CarbonPeriod::create($start, $ende) as $tag) {
                $woche = (int) floor($ersteWoche->diffInDays($tag->copy()->startOfWeek(Carbon::MONDAY)) / 7);
                if (in_array($tag->dayOfWeekIso, $wochentage, true) && $woche % $serie->intervall === 0) {
                    $tage->push($tag->copy()->startOfDay());
                }
            }
        }

        return $tage->filter(fn (Carbon $tag) => $tag->isWeekday() && !is_holiday($tag))->values();
    }

    /**
     * Löscht alle Termine der Serie, die nach heute liegen (Vergangenes bleibt als Nachweis).
     *
     * @return int Anzahl gelöschter Termine
     */
    public function zukuenftigeLoeschen(User $actor, AbsenceSerie $serie): int
    {
        if (!$this->darfLoeschen($actor, $serie)) {
            throw ValidationException::withMessages(['serie' => 'Berechtigung fehlt.']);
        }

        $termine = $serie->absences()
            ->whereNull('holiday_id')
            ->whereDate('end', '>', Carbon::today())
            ->get();

        DB::transaction(fn () => $termine->each(fn (Absence $absence) => $this->absences->loeschen($actor, $absence)));

        Log::debug('Abwesenheit: Serientermine gelöscht', ['user' => $actor->id, 'serie' => $serie->id, 'termine' => $termine->count()]);

        return $termine->count();
    }

    public function darfLoeschen(User $actor, AbsenceSerie $serie): bool
    {
        return $actor->can('delete absences') || $actor->id == $serie->creator_id;
    }

    /**
     * Gibt es noch zukünftige Termine, die $actor löschen darf? (Schaltfläche in der Übersicht)
     */
    public function hatLoeschbareTermine(User $actor, AbsenceSerie $serie): bool
    {
        return $this->darfLoeschen($actor, $serie)
            && $serie->absences()->whereNull('holiday_id')->whereDate('end', '>', Carbon::today())->exists();
    }

    /**
     * Krank-Gründe sind ausgeschlossen (Krankheit ist nicht planbar, die Krankenschein-Regeln würden verfälscht).
     */
    public static function grundErlaubt(string $grund): bool
    {
        return !Str::contains($grund, (array) config('absences.absence_sick_note'));
    }

    private function pruefen(AbsenceSerie $serie): void
    {
        $fehler = [];

        if (!self::grundErlaubt($serie->reason)) {
            $fehler['reason'] = 'Krankheit kann nicht als wiederkehrende Abwesenheit eingetragen werden.';
        }
        if ($serie->start->lt(Carbon::today())) {
            $fehler['start'] = 'Eine wiederkehrende Abwesenheit kann frühestens heute beginnen.';
        }
        if ($serie->end->lt($serie->start)) {
            $fehler['end'] = 'Das Ende muss nach dem Beginn liegen.';
        } elseif ($serie->end->gt($serie->start->copy()->addYear())) {
            $fehler['end'] = 'Eine wiederkehrende Abwesenheit darf höchstens ein Jahr dauern.';
        }
        if ($serie->rhythmus === 'woechentlich' && empty($serie->wochentage)) {
            $fehler['wochentage'] = 'Bitte mindestens einen Wochentag wählen.';
        }

        if ($fehler !== []) {
            throw ValidationException::withMessages($fehler);
        }
    }

    /**
     * Tage, an denen die Person im Zeitraum schon abwesend ist.
     *
     * @return array<string, true>
     */
    private function belegteTage(int $userId, Carbon $start, Carbon $ende): array
    {
        $belegt = [];

        Absence::query()
            ->where('users_id', $userId)
            ->whereDate('end', '>=', $start)
            ->whereDate('start', '<=', $ende)
            ->get(['start', 'end'])
            ->each(function (Absence $absence) use (&$belegt, $start, $ende) {
                $von = $absence->start->max($start);
                $bis = $absence->end->min($ende);
                foreach (CarbonPeriod::create($von, $bis) as $tag) {
                    $belegt[$tag->toDateString()] = true;
                }
            });

        return $belegt;
    }
}
