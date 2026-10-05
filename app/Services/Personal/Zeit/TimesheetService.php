<?php

namespace App\Services\Personal\Zeit;

use App\Models\Absence;
use App\Models\personal\Holiday;
use App\Models\personal\RosterEvents;
use App\Models\personal\Timesheet;
use App\Models\personal\TimesheetDays;
use App\Models\personal\WorkingTime;
use App\Models\User;
use App\Notifications\Personal\ZeitwirtschaftNotification;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Zentrale Schreib- und Rechenstelle für Arbeitszeitnachweise.
 *
 * Soll und Ist sind getrennt:
 *   - Soll kommt aus dem Arbeitszeitmodell (Vertrag), der Dienstplan wird nur als "Plan" angezeigt
 *     und auf Wunsch übernommen – nie automatisch als geleistete Zeit eingetragen.
 *   - Ist = Zeitbuchungen (Terminal, manuell, übernommener Plan) + Gutschriften für
 *     genehmigten Urlaub und Abwesenheiten. Gutschriften werden bei jeder Änderung an
 *     Urlaub/Abwesenheit automatisch abgeglichen (nur an Arbeitstagen).
 *   - Saldo = Vormonat + Σ (Ist − Soll) bis heute; Änderungen wirken auf alle Folgemonate.
 */
class TimesheetService
{
    public function __construct(
        private readonly ArbeitszeitService $arbeitszeit,
        private readonly UrlaubskontoService $urlaubskonto,
        private readonly ZeitZugriff $zugriff,
    ) {
    }

    public function forMonth(User $user, CarbonInterface $month): Timesheet
    {
        return Timesheet::firstOrCreate([
            'employe_id' => $user->id,
            'year' => $month->year,
            'month' => $month->month,
        ], [
            'working_time_account' => 0,
        ]);
    }

    public function vorgaenger(Timesheet $timesheet): ?Timesheet
    {
        $vormonat = $timesheet->monthStart()->subMonth();

        return Timesheet::where('employe_id', $timesheet->employe_id)
            ->where('year', $vormonat->year)
            ->where('month', $vormonat->month)
            ->first();
    }

    // =========================================================================
    // Berechnung
    // =========================================================================

    /**
     * Tageszeilen eines Monats für Ansicht und PDF.
     *
     * @return Collection<int, array{date: Carbon, entries: Collection, feiertag: ?string, arbeitstag: bool, soll: float, ist: float, diff: float, plan: ?array, zaehlt: bool}>
     */
    public function monatsZeilen(Timesheet $timesheet, bool $mitPlan = true, ?bool $bisherigeMethode = null): Collection
    {
        $employe = $timesheet->employe;
        $start = $timesheet->monthStart();
        $ende = $timesheet->monthEnd();
        $heute = Carbon::today();

        $tage = $timesheet->timesheet_days()->orderBy('date')->orderBy('start')->get()
            ->groupBy(fn (TimesheetDays $d) => $d->date->toDateString());

        $plaene = $mitPlan ? $this->planFuerZeitraum($employe, $start, $ende) : collect();
        $historisch = $bisherigeMethode ?? $this->istHistorisch($timesheet);

        $zeilen = collect();
        for ($tag = $start->copy(); $tag->lte($ende); $tag->addDay()) {
            $key = $tag->toDateString();
            $eintraege = $tage->get($key, collect());
            $feiertag = is_holiday($tag);

            if ($historisch) {
                // Bestandsschutz: genau die bisherige Rechnung (5-Tage-Woche, Gutschriften auch am Wochenende)
                $tagesSoll = $this->altesTagesSoll($employe, $tag);
                $soll = ($tag->isWeekday() && !$feiertag) ? $tagesSoll : 0.0;
                $ist = $this->altesIst($eintraege, $tagesSoll);
                $arbeitstag = $tag->isWeekday() && !$feiertag;
            } else {
                $soll = $this->arbeitszeit->sollSekunden($employe, $tag);
                $ist = $this->istSekunden($eintraege, $soll);
                $arbeitstag = $this->arbeitszeit->istArbeitstag($employe, $tag);
            }

            $zeilen->push([
                'date' => $tag->copy(),
                'entries' => $eintraege,
                'feiertag' => $feiertag['title'] ?? null,
                'arbeitstag' => $arbeitstag,
                'soll' => $soll,
                'ist' => $ist,
                'diff' => $ist - $soll,
                'zaehlt' => $tag->lte($heute),
                'plan' => $plaene->get($key),
                'offen' => $eintraege->contains(fn (TimesheetDays $d) => $d->start !== null && $d->end === null && !$d->is_credit),
            ]);
        }

        return $zeilen;
    }

    /**
     * Ist-Sekunden eines Tages: Zeitbuchungen abzüglich Pause + Gutschriften (Anteil vom Soll).
     */
    public function istSekunden(Collection $eintraege, float $sollSekunden): float
    {
        $summe = 0.0;
        foreach ($eintraege as $eintrag) {
            if ($eintrag->is_credit) {
                $summe += $sollSekunden / 100 * (float) $eintrag->percent_of_workingtime;
                continue;
            }
            if ($eintrag->start !== null && $eintrag->end !== null) {
                $summe += max(0, $eintrag->start->diffInSeconds($eintrag->end) - ((int) $eintrag->pause * 60));
            }
        }

        return $summe;
    }

    /**
     * Saldo und Urlaubsfelder berechnen; optional alle Folgemonate (bis heute) nachziehen.
     */
    public function recalculate(Timesheet $timesheet, bool $folgemonate = true, bool $explizit = false): void
    {
        if ($timesheet->is_locked || $timesheet->employe === null) {
            return;
        }

        // Bestandsschutz: abgelaufene Altmonate behalten ihre gespeicherten Werte, außer der Monat
        // selbst wird ausdrücklich bearbeitet oder neu berechnet (wie bisher pro Monat).
        if (!$explizit && $this->istEingefroren($timesheet)) {
            return;
        }

        $timesheet->forceFill($this->berechne($timesheet))->save();

        if (!$folgemonate) {
            return;
        }

        $folgende = Timesheet::where('employe_id', $timesheet->employe_id)
            ->whereNull('locked_at')
            ->where(function ($q) use ($timesheet) {
                $q->where('year', '>', $timesheet->year)
                    ->orWhere(fn ($q2) => $q2->where('year', $timesheet->year)->where('month', '>', $timesheet->month));
            })
            ->orderBy('year')->orderBy('month')
            ->get();

        foreach ($folgende as $folge) {
            if ($folge->monthStart()->isAfter(Carbon::today()->endOfMonth())) {
                break;
            }
            // Eingefrorene Altmonate werden nie mitgezogen (die bisherige Methode kannte keine Kaskade)
            $this->recalculate($folge, false);
        }
    }

    /**
     * Werte eines Monats berechnen, ohne zu speichern (auch für die Prüfung vor der Umstellung).
     *
     * @return array{working_time_account: int, holidays_old: float, holidays_new: float, holidays_rest: float}
     */
    public function berechne(Timesheet $timesheet): array
    {
        return $this->istHistorisch($timesheet)
            ? $this->berechneBisher($timesheet)
            : $this->berechneNeu($timesheet);
    }

    /**
     * Beide Methoden nebeneinander (für die Prüfung der Umstellung, speichert nichts).
     *
     * @return array{bisher: array, neu: array}
     */
    public function vergleiche(Timesheet $timesheet): array
    {
        return ['bisher' => $this->berechneBisher($timesheet), 'neu' => $this->berechneNeu($timesheet)];
    }

    // =========================================================================
    // Stichtag / Bestandsschutz
    // =========================================================================

    /**
     * Ab diesem Monat gilt das neue Arbeitszeitmodell (Setting "zeitwirtschaft_stichtag").
     * Frühere Monate werden unverändert nach der bisherigen Methode gerechnet.
     */
    public function stichtag(): Carbon
    {
        return $this->urlaubskonto->stichtag();
    }

    public function istHistorisch(Timesheet $timesheet): bool
    {
        return $timesheet->monthStart()->lt($this->stichtag());
    }

    /**
     * Altmonat, der vollständig vor dem laufenden Monat liegt: wird nicht automatisch neu berechnet.
     */
    public function istEingefroren(Timesheet $timesheet): bool
    {
        return $this->istHistorisch($timesheet) && $timesheet->monthStart()->lt(Carbon::today()->startOfMonth());
    }

    private function berechneNeu(Timesheet $timesheet): array
    {
        $employe = $timesheet->employe;
        $vorher = $this->vorgaenger($timesheet);
        $saldo = (float) ($vorher?->working_time_account ?? 0);

        foreach ($this->monatsZeilen($timesheet, false, false) as $zeile) {
            if ($zeile['zaehlt']) {
                $saldo += $zeile['diff'];
            }
        }

        $monatsende = $timesheet->monthEnd();
        $monatsanfang = $timesheet->monthStart();
        $this->urlaubskonto->vergessen($employe);

        $bisVormonat = $timesheet->month > 1
            ? $this->urlaubskonto->genommen($employe, $timesheet->year, $monatsanfang->copy()->subDay())
            : 0.0;
        $bisMonatsende = $this->urlaubskonto->genommen($employe, $timesheet->year, $monatsende);

        return [
            'working_time_account' => (int) round($saldo),
            'holidays_old' => $bisVormonat,
            'holidays_new' => round($bisMonatsende - $bisVormonat, 1),
            'holidays_rest' => round(
                $this->urlaubskonto->anspruch($employe, $timesheet->year)
                + $this->urlaubskonto->uebertrag($employe, $timesheet->year)
                + $this->urlaubskonto->buchungen($employe, $timesheet->year)
                - $bisMonatsende
                - $this->urlaubskonto->verfallen($employe, $timesheet->year, $monatsende),
                1
            ),
        ];
    }

    /**
     * Unveränderte Übernahme der bisherigen Berechnung (Timesheet::updateTime() bis 09/2026),
     * damit sich Altmonate durch die Umstellung nicht verändern.
     */
    private function berechneBisher(Timesheet $timesheet): array
    {
        $employe = $timesheet->employe;
        $vorher = $this->vorgaenger($timesheet);
        $tage = $timesheet->timesheet_days()->get()->groupBy(fn (TimesheetDays $d) => $d->date->toDateString());
        $saldo = $vorher?->working_time_account;
        $jetzt = Carbon::now();

        for ($tag = $timesheet->monthStart(); $tag->lte($timesheet->monthEnd()); $tag->addDay()) {
            if (!$tag->lessThanOrEqualTo($jetzt)) {
                continue;
            }
            $tagesSoll = $this->altesTagesSoll($employe, $tag);
            $ist = $this->altesIst($tage->get($tag->toDateString(), collect()), $tagesSoll);

            $saldo += ($tag->isWeekday() && !is_holiday($tag)) ? $ist - $tagesSoll : $ist;
        }

        $neu = (float) $timesheet->timesheet_days()->get()->filter(fn ($d) => $d->comment == 'Urlaub')->count();

        if ((int) $timesheet->month === 1) {
            $alt = 0 - (float) ($vorher?->holidays_rest ?? 0);
            $rest = (float) $employe->getHolidayClaim($timesheet->monthStart()) + (float) ($vorher?->holidays_rest ?? 0) - $neu;
        } else {
            $alt = $vorher !== null ? (float) $vorher->holidays_old + (float) $vorher->holidays_new : 0.0;
            $rest = (float) ($vorher?->holidays_rest ?? 0) - $neu;
        }

        return [
            'working_time_account' => (int) round((float) $saldo),
            'holidays_old' => $alt,
            'holidays_new' => $neu,
            'holidays_rest' => $rest,
        ];
    }

    private function altesTagesSoll(User $employe, CarbonInterface $tag): float
    {
        return percent_to_seconds($employe->employments_date(Carbon::parse($tag))->sum('percent')) / 5;
    }

    /**
     * Bisherige Tagesdauer: Gutschriften = Anteil der 5-Tage-Sollzeit (auch am Wochenende), Pause wird abgezogen.
     */
    private function altesIst(Collection $eintraege, float $tagesSoll): float
    {
        $summe = 0.0;
        foreach ($eintraege as $eintrag) {
            $sekunden = 0.0;
            if ($eintrag->start !== null && $eintrag->end !== null) {
                $sekunden = $eintrag->start->diffInSeconds($eintrag->end);
            }
            if ($eintrag->percent_of_workingtime != null) {
                $sekunden = $tagesSoll / 100 * (float) $eintrag->percent_of_workingtime;
            }
            $summe += $sekunden - ((int) $eintrag->pause * 60);
        }

        return $summe;
    }

    // =========================================================================
    // Abgleich Urlaub/Abwesenheiten
    // =========================================================================

    /**
     * Gutschriften für genehmigten Urlaub und Abwesenheiten im Zeitraum abgleichen.
     * Es werden nur bereits vorhandene Nachweise bzw. Monate bis einschließlich heute angefasst.
     */
    public function syncAbwesenheiten(User $user, CarbonInterface $von, CarbonInterface $bis): void
    {
        if (!$user->can('has timesheet')) {
            return;
        }

        $monat = Carbon::parse($von)->startOfMonth();
        $letzter = Carbon::parse($bis)->startOfMonth();
        $stichtag = $this->stichtag();

        while ($monat->lte($letzter)) {
            $timesheet = Timesheet::where('employe_id', $user->id)
                ->where('year', $monat->year)->where('month', $monat->month)->first();

            if ($monat->copy()->startOfMonth()->lt($stichtag)) {
                // Altmonat/Übergangsmonat: Gutschriften wurden wie bisher ohne Verknüpfung übernommen –
                // nur Urlaubszeilen ohne genehmigten Urlaub (storniert, abgelehnt) wieder entfernen.
                if ($timesheet !== null) {
                    $this->verwaisteUrlaubEntfernen($timesheet, $von, $bis);
                }
                $monat->addMonth();
                continue;
            }

            if ($timesheet === null && $monat->lte(Carbon::today())) {
                $timesheet = $this->forMonth($user, $monat);
            }

            if ($timesheet !== null) {
                if ($timesheet->is_locked) {
                    $timesheet->markRequiresReview('Urlaub oder Abwesenheit nach Abschluss geändert ('.now()->format('d.m.Y').')');
                } else {
                    $this->verwaisteUrlaubszeilen($timesheet, $von, $bis)->each->delete();
                    $this->syncMonat($timesheet);
                    $this->recalculate($timesheet);
                }
            }

            $monat->addMonth();
        }
    }

    /**
     * Verwaiste Urlaubsgutschriften entfernen und den Monat neu berechnen (auch Altmonate).
     * Abgeschlossene Nachweise werden nicht verändert, sondern zur Prüfung markiert.
     * Ohne Zeitraum wird der ganze Monat betrachtet. Liefert die Anzahl betroffener Zeilen.
     */
    public function verwaisteUrlaubEntfernen(Timesheet $timesheet, ?CarbonInterface $von = null, ?CarbonInterface $bis = null): int
    {
        $zeilen = $this->verwaisteUrlaubszeilen($timesheet, $von, $bis);
        if ($zeilen->isEmpty()) {
            return 0;
        }

        if ($timesheet->is_locked) {
            $timesheet->markRequiresReview('Urlaub nach Abschluss storniert ('.now()->format('d.m.Y').')');
            return $zeilen->count();
        }

        $zeilen->each->delete();
        $this->recalculate($timesheet, true, true);

        return $zeilen->count();
    }

    /**
     * Urlaubsgutschriften, die zu einem stornierten oder abgelehnten Antrag gehören: An dem Tag liegt
     * ein solcher Antrag, aber weder ein genehmigter Urlaub noch eine Urlaubs-Abwesenheit. Erfasst auch
     * Zeilen ohne Kennzeichnung (bisherige Übernahme aus den Abwesenheiten: Kommentar "Urlaub", keine Quelle).
     * Von Hand eingetragene "Urlaub"-Zeilen ohne Antrag im System bleiben unberührt.
     *
     * @return Collection<int, TimesheetDays>
     */
    public function verwaisteUrlaubszeilen(Timesheet $timesheet, ?CarbonInterface $von = null, ?CarbonInterface $bis = null): Collection
    {
        $employe = $timesheet->employe;
        if ($employe === null) {
            return collect();
        }

        $start = $von !== null ? $timesheet->monthStart()->max(Carbon::parse($von)->startOfDay()) : $timesheet->monthStart();
        $ende = $bis !== null ? $timesheet->monthEnd()->min(Carbon::parse($bis)->startOfDay()) : $timesheet->monthEnd();

        $zeilen = $timesheet->timesheet_days()
            ->whereDate('date', '>=', $start->toDateString())
            ->whereDate('date', '<=', $ende->toDateString())
            ->whereNull('start')
            ->whereNotNull('percent_of_workingtime')
            ->whereIn('comment', ['Urlaub', 'Urlaub (halber Tag)'])
            ->where(fn ($q) => $q->whereNull('source')->orWhere('source', TimesheetDays::SOURCE_URLAUB))
            ->get();

        if ($zeilen->isEmpty()) {
            return $zeilen;
        }

        $urlaube = Holiday::query()->genehmigt()
            ->where('employe_id', $employe->id)
            ->ueberschneidet($start, $ende)
            ->get();
        $abwesenheiten = Absence::query()
            ->where('users_id', $employe->id)
            ->where('reason', 'Urlaub')
            ->whereDate('start', '<=', $ende->toDateString())
            ->whereDate('end', '>=', $start->toDateString())
            ->get();

        $hinfaellig = Holiday::withTrashed()
            ->where('employe_id', $employe->id)
            ->where(fn ($q) => $q->whereNotNull('deleted_at')->orWhere('rejected', true))
            ->ueberschneidet($start, $ende)
            ->get();

        $liegtIn = fn (string $datum) => fn ($z) => $z->start_date->toDateString() <= $datum && $z->end_date->toDateString() >= $datum;

        return $zeilen->filter(function (TimesheetDays $zeile) use ($urlaube, $abwesenheiten, $hinfaellig, $liegtIn) {
            $datum = $zeile->date->toDateString();

            return $hinfaellig->contains($liegtIn($datum))
                && !$urlaube->contains($liegtIn($datum))
                && !$abwesenheiten->contains(fn (Absence $a) => $a->start->toDateString() <= $datum && $a->end->toDateString() >= $datum);
        })->values();
    }

    /**
     * Automatische Gutschriften eines Monats auf den Sollzustand bringen.
     */
    public function syncMonat(Timesheet $timesheet): void
    {
        // Gesperrte Monate und Altmonate (vor dem Stichtag) bleiben unverändert
        if ($timesheet->is_locked || $timesheet->employe === null || $this->istHistorisch($timesheet)) {
            return;
        }

        $employe = $timesheet->employe;
        $start = $timesheet->monthStart();
        $ende = $timesheet->monthEnd();
        $gutschriften = config('config.abwesenheiten_arbeitszeit', []);

        $urlaube = Holiday::query()->genehmigt()
            ->where('employe_id', $employe->id)
            ->ueberschneidet($start, $ende)
            ->get();

        $abwesenheiten = Absence::query()
            ->where('users_id', $employe->id)
            ->whereNull('holiday_id')
            ->whereIn('reason', array_keys($gutschriften))
            ->whereDate('start', '<=', $ende)
            ->whereDate('end', '>=', $start)
            ->get();

        // Sollzustand: [datum|source|ref] => Attribute
        $soll = [];
        for ($tag = $start->copy(); $tag->lte($ende); $tag->addDay()) {
            if (!$this->arbeitszeit->istArbeitstag($employe, $tag)) {
                continue;
            }
            $datum = $tag->toDateString();

            $urlaub = $urlaube->first(fn (Holiday $h) => $h->start_date->toDateString() <= $datum && $h->end_date->toDateString() >= $datum);
            if ($urlaub !== null) {
                $soll[$datum.'|urlaub|'.$urlaub->id] = [
                    'date' => $datum,
                    'source' => TimesheetDays::SOURCE_URLAUB,
                    'holiday_id' => $urlaub->id,
                    'absence_id' => null,
                    'percent_of_workingtime' => $urlaub->half_day ? 50 : 100,
                    'comment' => $urlaub->half_day ? 'Urlaub (halber Tag)' : 'Urlaub',
                ];
                continue;
            }

            $abwesenheit = $abwesenheiten->first(fn (Absence $a) => $a->start->toDateString() <= $datum && $a->end->toDateString() >= $datum);
            if ($abwesenheit !== null) {
                $soll[$datum.'|abwesenheit|'.$abwesenheit->id] = [
                    'date' => $datum,
                    'source' => TimesheetDays::SOURCE_ABWESENHEIT,
                    'holiday_id' => null,
                    'absence_id' => $abwesenheit->id,
                    'percent_of_workingtime' => (int) $gutschriften[$abwesenheit->reason],
                    'comment' => mb_substr($abwesenheit->reason, 0, 60),
                ];
            }
        }

        DB::transaction(function () use ($timesheet, $soll) {
            $vorhanden = $timesheet->timesheet_days()->get();

            foreach ($vorhanden->filter(fn (TimesheetDays $d) => $d->is_automatic) as $zeile) {
                $key = $zeile->date->toDateString().'|'.$zeile->source.'|'.($zeile->holiday_id ?? $zeile->absence_id);
                if (isset($soll[$key])) {
                    unset($soll[$key]);
                } else {
                    $zeile->delete();
                }
            }

            foreach ($soll as $attribute) {
                // Altbestand: gleichlautende manuelle Gutschrift am selben Tag nicht doppeln
                $doppelt = $vorhanden->contains(fn (TimesheetDays $d) => $d->source === null
                    && $d->is_credit
                    && $d->date->toDateString() === $attribute['date']
                    && mb_strtolower((string) $d->comment) === mb_strtolower(explode(' (', $attribute['comment'])[0]));

                if ($doppelt) {
                    continue;
                }

                $zeile = new TimesheetDays($attribute);
                $zeile->timesheet_id = $timesheet->id;
                $zeile->save();
            }
        });
    }

    // =========================================================================
    // Plan (Dienstplan) – nur Vorschlag
    // =========================================================================

    /**
     * Dienstplanzeiten je Tag: [Y-m-d => ['start' => 'H:i', 'end' => 'H:i', 'pause' => Minuten, 'function' => ?]]
     */
    public function planFuerZeitraum(User $user, CarbonInterface $von, CarbonInterface $bis): Collection
    {
        $zeiten = WorkingTime::query()
            ->where('employe_id', $user->id)
            ->whereDate('date', '>=', $von->toDateString())
            ->whereDate('date', '<=', $bis->toDateString())
            ->whereNotNull('start')->whereNotNull('end')
            ->whereHas('roster', fn ($q) => $q->where('type', '!=', 'template'))
            ->get()
            ->groupBy(fn (WorkingTime $w) => $w->date->toDateString());

        $pausen = RosterEvents::query()
            ->where('employe_id', $user->id)
            ->whereDate('date', '>=', $von->toDateString())
            ->whereDate('date', '<=', $bis->toDateString())
            ->where('event', 'LIKE', '%pause%')
            ->get()
            ->groupBy(fn (RosterEvents $e) => $e->date->toDateString());

        return $zeiten->map(function (Collection $tag, string $datum) use ($pausen) {
            return [
                'start' => $tag->min(fn ($w) => $w->start)->format('H:i'),
                'end' => $tag->max(fn ($w) => $w->end)->format('H:i'),
                'pause' => (int) ($pausen->get($datum)?->sum('duration') ?? 0),
                'function' => $tag->pluck('function')->filter()->implode(', ') ?: null,
            ];
        });
    }

    /**
     * Planzeiten eines Tages übernehmen: ergänzt eine offene Buchung oder legt eine neue an.
     */
    public function planUebernehmen(Timesheet $timesheet, CarbonInterface $tag): ?TimesheetDays
    {
        $plan = $this->planFuerZeitraum($timesheet->employe, $tag, $tag)->get($tag->toDateString());
        if ($plan === null) {
            return null;
        }
        $this->pruefeNichtInZukunft($tag, $plan['start'], $plan['end']);

        $offen = $timesheet->timesheet_days()
            ->whereDate('date', $tag->toDateString())
            ->whereNotNull('start')->whereNull('end')
            ->whereNull('percent_of_workingtime')
            ->first();

        if ($offen !== null) {
            $offen->update(['end' => $plan['end']]);
            $zeile = $offen;
        } else {
            $zeile = new TimesheetDays([
                'date' => $tag->toDateString(),
                'start' => $plan['start'],
                'end' => $plan['end'],
                'pause' => $plan['pause'] ?: TimeRecordingService::gesetzlichePause($this->minutenZwischen($plan['start'], $plan['end'])),
                'comment' => 'aus Dienstplan übernommen',
                'source' => TimesheetDays::SOURCE_DIENSTPLAN,
            ]);
            $zeile->timesheet_id = $timesheet->id;
            $zeile->save();
        }

        $this->recalculate($timesheet, true, true);

        return $zeile;
    }

    /**
     * Übergangsmonat (vor dem Stichtag, aber nicht abgelaufen): Ein neuer bzw. leerer Nachweis wird
     * genau wie bisher aus Dienstplan und Abwesenheiten befüllt.
     */
    public function bisherigBefuellen(Timesheet $timesheet): void
    {
        if ($timesheet->is_locked || !$this->istHistorisch($timesheet) || $this->istEingefroren($timesheet)) {
            return;
        }
        if (!$timesheet->wasRecentlyCreated && $timesheet->timesheet_days()->exists()) {
            return;
        }

        $employe = $timesheet->employe;
        $start = $timesheet->monthStart();
        $ende = $timesheet->monthEnd();

        $pausen = RosterEvents::query()
            ->where('employe_id', $employe->id)
            ->where('event', 'LIKE', 'pause')
            ->whereBetween('date', [$start->toDateString(), $ende->toDateString()])
            ->get();

        $zeiten = WorkingTime::query()
            ->where('employe_id', $employe->id)
            ->whereDate('date', '>=', $start->toDateString())
            ->whereDate('date', '<=', $ende->toDateString())
            ->whereHas('roster', fn ($q) => $q->where('type', '!=', 'template'))
            ->get();

        foreach ($zeiten as $zeit) {
            if ($zeit->start === null || $zeit->end === null) {
                continue;
            }
            $pause = $pausen->filter(fn ($e) => $e->date->toDateString() === $zeit->date->toDateString())->sum('duration');
            $zeile = new TimesheetDays([
                'date' => $zeit->date->toDateString(),
                'start' => $zeit->start->format('H:i:s'),
                'end' => $zeit->end->format('H:i:s'),
                'pause' => $pause,
                'comment' => 'aus Dienstplan erstellt',
            ]);
            $zeile->timesheet_id = $timesheet->id;
            $zeile->save();
        }

        $gruende = config('config.abwesenheiten_arbeitszeit', []);
        $abwesenheiten = Absence::query()
            ->whereIn('reason', array_keys($gruende))
            ->where('users_id', $employe->id)
            ->whereDate('start', '>=', $start->toDateString())
            ->whereDate('end', '<=', $ende->toDateString())
            ->get();

        foreach ($abwesenheiten as $abwesenheit) {
            for ($tag = $abwesenheit->start->copy(); $tag->lte($abwesenheit->end); $tag->addDay()) {
                $zeile = new TimesheetDays([
                    'date' => $tag->toDateString(),
                    'percent_of_workingtime' => $gruende[$abwesenheit->reason],
                    'comment' => $abwesenheit->reason,
                ]);
                $zeile->timesheet_id = $timesheet->id;
                $zeile->save();
            }
        }
    }

    /**
     * Bisheriges Verhalten beibehalten (Setting "timesheet_dienstplan_automatisch"): vergangene Tage
     * ohne Buchung werden einmalig mit den Dienstplanzeiten gefüllt. Bereits verarbeitete Tage merkt
     * sich der Nachweis (plan_uebernommen_bis) – gelöschte Einträge kommen daher nicht wieder.
     */
    public function planAutomatisch(Timesheet $timesheet): int
    {
        if ((string) settings('timesheet_dienstplan_automatisch') === '0'
            || $timesheet->is_locked || $this->istHistorisch($timesheet)) {
            return 0;
        }

        $gestern = Carbon::yesterday();
        $bis = $timesheet->monthEnd()->min($gestern)->copy()->startOfDay();
        $von = $timesheet->plan_uebernommen_bis
            ? $timesheet->plan_uebernommen_bis->copy()->addDay()->max($timesheet->monthStart())->copy()
            : $timesheet->monthStart();

        if ($von->gt($bis)) {
            return 0;
        }

        $vorhanden = $timesheet->timesheet_days()->get()->groupBy(fn ($d) => $d->date->toDateString());
        $anzahl = 0;

        foreach ($this->planFuerZeitraum($timesheet->employe, $von, $bis) as $datum => $plan) {
            if ($vorhanden->has($datum)) {
                continue;
            }
            $zeile = new TimesheetDays([
                'date' => $datum,
                'start' => $plan['start'],
                'end' => $plan['end'],
                'pause' => $plan['pause'] ?: TimeRecordingService::gesetzlichePause($this->minutenZwischen($plan['start'], $plan['end'])),
                'comment' => 'aus Dienstplan erstellt',
                'source' => TimesheetDays::SOURCE_DIENSTPLAN,
            ]);
            $zeile->timesheet_id = $timesheet->id;
            $zeile->save();
            $anzahl++;
        }

        $timesheet->forceFill(['plan_uebernommen_bis' => $bis->toDateString()])->save();

        return $anzahl;
    }

    /**
     * Plan für alle vergangenen Tage des Monats ohne jede Buchung übernehmen.
     */
    public function planUebernehmenMonat(Timesheet $timesheet): int
    {
        $heute = Carbon::today();
        $vorhanden = $timesheet->timesheet_days()->get()->groupBy(fn ($d) => $d->date->toDateString());
        $plaene = $this->planFuerZeitraum($timesheet->employe, $timesheet->monthStart(), $timesheet->monthEnd()->min($heute));
        $anzahl = 0;

        foreach ($plaene as $datum => $plan) {
            if ($vorhanden->has($datum) || $this->liegtInZukunft(Carbon::parse($datum), $plan['end'])) {
                continue;
            }
            $zeile = new TimesheetDays([
                'date' => $datum,
                'start' => $plan['start'],
                'end' => $plan['end'],
                'pause' => $plan['pause'] ?: TimeRecordingService::gesetzlichePause($this->minutenZwischen($plan['start'], $plan['end'])),
                'comment' => 'aus Dienstplan übernommen',
                'source' => TimesheetDays::SOURCE_DIENSTPLAN,
            ]);
            $zeile->timesheet_id = $timesheet->id;
            $zeile->save();
            $anzahl++;
        }

        if ($anzahl > 0) {
            $this->recalculate($timesheet, true, true);
        }

        return $anzahl;
    }

    // =========================================================================
    // Buchungen
    // =========================================================================

    public function buchen(Timesheet $timesheet, CarbonInterface $tag, array $daten): TimesheetDays
    {
        $this->pruefeTagImMonat($timesheet, $tag);
        $this->pruefeNichtInZukunft($tag, $daten['start'] ?? null, $daten['end'] ?? null);
        $this->pruefeUeberschneidung($timesheet, $tag, $daten['start'] ?? null, $daten['end'] ?? null);

        $zeile = new TimesheetDays([
            'date' => $tag->toDateString(),
            'start' => $daten['start'],
            'end' => $daten['end'],
            'pause' => $daten['pause'] ?? null,
            'comment' => $daten['comment'] ?? null,
        ]);
        $zeile->timesheet_id = $timesheet->id;
        $zeile->save();

        $this->recalculate($timesheet, true, true);

        return $zeile;
    }

    public function gutschreiben(Timesheet $timesheet, CarbonInterface $tag, string $grund): TimesheetDays
    {
        $gutschriften = config('config.abwesenheiten_arbeitszeit', []);
        if (!array_key_exists($grund, $gutschriften)) {
            throw ValidationException::withMessages(['absence' => 'Unbekannter Abwesenheitsgrund.']);
        }
        $this->pruefeTagImMonat($timesheet, $tag);
        $this->pruefeNichtInZukunft($tag);

        $zeile = new TimesheetDays([
            'date' => $tag->toDateString(),
            'percent_of_workingtime' => $gutschriften[$grund],
            'comment' => $grund,
        ]);
        $zeile->timesheet_id = $timesheet->id;
        $zeile->save();

        $this->recalculate($timesheet, true, true);

        return $zeile;
    }

    public function aendern(TimesheetDays $zeile, array $daten): void
    {
        $this->pruefeNichtInZukunft($zeile->date, $daten['start'] ?? null, $daten['end'] ?? null);
        $this->pruefeUeberschneidung($zeile->timesheet, $zeile->date, $daten['start'] ?? null, $daten['end'] ?? null, $zeile->id);

        $zeile->update([
            'start' => $daten['start'],
            'end' => $daten['end'],
            'pause' => $daten['pause'] ?? null,
            'comment' => $daten['comment'] ?? null,
            // Eine bearbeitete Planübernahme ist eine manuelle Buchung
            'source' => $zeile->source === TimesheetDays::SOURCE_DIENSTPLAN ? null : $zeile->source,
        ]);

        $this->recalculate($zeile->timesheet, true, true);
    }

    public function loeschen(TimesheetDays $zeile): void
    {
        if ($zeile->is_automatic) {
            throw ValidationException::withMessages(['day' => 'Urlaub und Abwesenheiten werden automatisch übernommen – bitte dort ändern.']);
        }

        $timesheet = $zeile->timesheet;
        $zeile->delete();
        $this->recalculate($timesheet, true, true);
    }

    // =========================================================================
    // Workflow Monatsabschluss
    // =========================================================================

    public function einreichen(Timesheet $timesheet, User $actor): void
    {
        $offen = $timesheet->timesheet_days()->whereNotNull('start')->whereNull('end')->whereNull('percent_of_workingtime')->count();
        if ($offen > 0) {
            throw ValidationException::withMessages(['timesheet' => 'Es gibt noch '.$offen.' Buchung(en) ohne Ende. Bitte zuerst ergänzen.']);
        }

        $this->recalculate($timesheet);
        $timesheet->update(['submitted_at' => now(), 'submitted_by' => $actor->id, 'return_reason' => null]);

        $monat = $timesheet->monthStart()->locale('de')->isoFormat('MMMM YYYY');
        $this->benachrichtigen(
            $this->zugriff->nachweisPruefende($timesheet->employe),
            new ZeitwirtschaftNotification(
                'timesheet_submitted',
                'Arbeitszeitnachweis eingereicht: '.$timesheet->employe->name,
                [$timesheet->employe->name.' hat den Arbeitszeitnachweis für '.$monat.' zur Prüfung eingereicht.'],
                route('timesheets.show', [$timesheet->employe_id, $timesheet->monthStart()->format('Y-m')]),
                'Nachweis prüfen'
            )
        );
    }

    public function abschliessen(Timesheet $timesheet, User $actor): void
    {
        $this->recalculate($timesheet);
        $timesheet->update([
            'locked_at' => now(),
            'locked_by' => $actor->id,
            'submitted_at' => $timesheet->submitted_at ?? now(),
            'submitted_by' => $timesheet->submitted_by ?? $actor->id,
        ]);
    }

    public function zurueckgeben(Timesheet $timesheet, User $actor, string $grund): void
    {
        $timesheet->update(['submitted_at' => null, 'submitted_by' => null, 'return_reason' => $grund]);

        $monat = $timesheet->monthStart()->locale('de')->isoFormat('MMMM YYYY');
        $this->benachrichtigen(
            collect([$timesheet->employe]),
            new ZeitwirtschaftNotification(
                'timesheet_returned',
                'Arbeitszeitnachweis '.$monat.' zurückgegeben',
                [$actor->name.' hat deinen Arbeitszeitnachweis zur Korrektur zurückgegeben.', 'Hinweis: '.$grund],
                route('timesheets.show', [$timesheet->employe_id, $timesheet->monthStart()->format('Y-m')]),
                'Nachweis öffnen'
            )
        );
    }

    public function entsperren(Timesheet $timesheet): void
    {
        $timesheet->update(['locked_at' => null, 'locked_by' => null, 'submitted_at' => null, 'submitted_by' => null]);
        $this->syncMonat($timesheet);
        $this->recalculate($timesheet);
    }

    // =========================================================================
    // Hilfen
    // =========================================================================

    /**
     * Arbeitszeit darf nicht im Voraus erfasst werden: keine Tage nach heute, heute nur bis zur aktuellen Uhrzeit.
     * (Automatische Gutschriften aus genehmigtem Urlaub/Abwesenheiten sind davon nicht betroffen.)
     */
    public function liegtInZukunft(CarbonInterface $tag, ?string $start = null, ?string $ende = null): bool
    {
        $tag = Carbon::parse($tag)->startOfDay();
        $heute = Carbon::today();

        if ($tag->gt($heute)) {
            return true;
        }
        if ($tag->lt($heute)) {
            return false;
        }

        $jetzt = Carbon::now()->format('H:i');
        foreach ([$start, $ende] as $zeit) {
            if ($zeit !== null && $zeit !== '' && substr($zeit, 0, 5) > $jetzt) {
                return true;
            }
        }

        return false;
    }

    private function pruefeNichtInZukunft(CarbonInterface $tag, ?string $start = null, ?string $ende = null): void
    {
        if (!$this->liegtInZukunft($tag, $start, $ende)) {
            return;
        }

        throw ValidationException::withMessages([
            Carbon::parse($tag)->isToday() ? 'end' : 'date' => Carbon::parse($tag)->isToday()
                ? 'Arbeitszeiten für heute können nur bis zur aktuellen Uhrzeit ('.Carbon::now()->format('H:i').' Uhr) eingetragen werden.'
                : 'Arbeitszeiten können nicht für zukünftige Tage eingetragen werden.',
        ]);
    }

    private function pruefeTagImMonat(Timesheet $timesheet, CarbonInterface $tag): void
    {
        if ($tag->year !== (int) $timesheet->year || $tag->month !== (int) $timesheet->month) {
            throw ValidationException::withMessages(['date' => 'Das Datum gehört nicht zu diesem Nachweis.']);
        }
    }

    private function pruefeUeberschneidung(Timesheet $timesheet, CarbonInterface $tag, ?string $start, ?string $ende, ?int $ausser = null): void
    {
        if ($start === null || $ende === null) {
            return;
        }

        $start = substr($start, 0, 5);
        $ende = substr($ende, 0, 5);

        $kollision = $timesheet->timesheet_days()
            ->whereDate('date', $tag->toDateString())
            ->whereNotNull('start')->whereNotNull('end')
            ->when($ausser, fn ($q) => $q->where('id', '!=', $ausser))
            ->get()
            ->first(fn (TimesheetDays $d) => $d->start->format('H:i') < $ende && $d->end->format('H:i') > $start);

        if ($kollision !== null) {
            throw ValidationException::withMessages([
                'start' => 'Überschneidet sich mit der Buchung '.$kollision->start->format('H:i').'–'.$kollision->end->format('H:i').' Uhr.',
            ]);
        }
    }

    private function minutenZwischen(string $start, string $ende): int
    {
        return (int) Carbon::createFromFormat('H:i', substr($start, 0, 5))->diffInMinutes(Carbon::createFromFormat('H:i', substr($ende, 0, 5)));
    }

    private function benachrichtigen(Collection $empfaenger, ZeitwirtschaftNotification $notification): void
    {
        foreach ($empfaenger->filter()->unique('id') as $user) {
            try {
                $user->notify($notification);
            } catch (\Throwable $e) {
                report($e);
            }
        }
    }
}
