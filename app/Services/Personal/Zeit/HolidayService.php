<?php

namespace App\Services\Personal\Zeit;

use App\Models\Absence;
use App\Models\personal\Holiday;
use App\Models\User;
use App\Notifications\Personal\ZeitwirtschaftNotification;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Einzige Schreibstelle für Urlaubsanträge.
 *
 * Nach jedem Statuswechsel gleicht der HolidayObserver Abwesenheit (Vertretungsplan),
 * Arbeitszeitnachweis und Dienstplan über abgleichen() ab; der Service benachrichtigt
 * die Beteiligten.
 */
class HolidayService
{
    public function __construct(
        private readonly UrlaubskontoService $urlaubskonto,
        private readonly TimesheetService $timesheets,
        private readonly RosterService $rosters,
        private readonly ZeitZugriff $zugriff,
    ) {
    }

    /**
     * Vorschau für das Antragsformular: Tage, Rest danach, Überschneidungen im Team.
     *
     * @return array{tage: float, rest_vorher: float, rest_nachher: float, jahr: int, ueberschneidung_eigen: bool, team: array<int, array{name: string, von: string, bis: string, status: string}>}
     */
    public function vorschau(User $employe, CarbonInterface $start, CarbonInterface $ende, bool $halberTag = false): array
    {
        $tage = $this->tageFuer($employe, $start, $ende, $halberTag);
        $jahr = $start->year;
        $rest = $this->urlaubskonto->rest($employe, $jahr) - $this->urlaubskonto->beantragt($employe, $jahr);

        return [
            'tage' => $tage,
            'jahr' => $jahr,
            'rest_vorher' => round($rest, 1),
            'rest_nachher' => round($rest - $tage, 1),
            'ueberschneidung_eigen' => $this->hatUeberschneidung($employe, $start, $ende),
            'team' => $this->teamAbwesenheiten($employe, $start, $ende)->all(),
        ];
    }

    public function tageFuer(User $employe, CarbonInterface $start, CarbonInterface $ende, bool $halberTag = false): float
    {
        return $this->urlaubskonto->tageFuerZeitraum($employe, $start, $ende, $halberTag);
    }

    /**
     * Antrag stellen. Darf der Antragstellende selbst genehmigen (nie für sich selbst),
     * wird der Urlaub direkt genehmigt. Jahresübergreifende Anträge werden geteilt.
     *
     * @return Collection<int, Holiday>
     */
    public function beantragen(User $actor, User $employe, CarbonInterface $start, CarbonInterface $ende, bool $halberTag = false, ?string $kommentar = null): Collection
    {
        $start = Carbon::parse($start)->startOfDay();
        $ende = Carbon::parse($ende)->startOfDay();

        if ($ende->lt($start)) {
            throw ValidationException::withMessages(['end_date' => 'Das Enddatum darf nicht vor dem Startdatum liegen.']);
        }
        if ($halberTag && !$start->isSameDay($ende)) {
            throw ValidationException::withMessages(['half_day' => 'Ein halber Urlaubstag ist nur für einen einzelnen Tag möglich.']);
        }
        if ($this->hatUeberschneidung($employe, $start, $ende)) {
            throw ValidationException::withMessages(['start_date' => 'Für diesen Zeitraum gibt es bereits einen Urlaubsantrag.']);
        }
        if ($this->tageFuer($employe, $start, $ende, $halberTag) <= 0) {
            throw ValidationException::withMessages(['start_date' => 'Im gewählten Zeitraum liegen keine Arbeitstage.']);
        }

        $genehmigt = $this->zugriff->darfUrlaubGenehmigen($actor, $employe);

        $antraege = DB::transaction(function () use ($employe, $start, $ende, $halberTag, $kommentar, $genehmigt, $actor) {
            $antraege = collect();
            foreach ($this->nachJahrenTeilen($start, $ende) as [$von, $bis]) {
                $tage = $this->tageFuer($employe, $von, $bis, $halberTag);
                if ($tage <= 0) {
                    continue;
                }
                $antraege->push($employe->holidays()->create([
                    'start_date' => $von->toDateString(),
                    'end_date' => $bis->toDateString(),
                    'half_day' => $halberTag,
                    'comment' => $kommentar,
                    'days' => $tage,
                    'approved' => $genehmigt,
                    'approved_by' => $genehmigt ? $actor->id : null,
                    'approved_at' => $genehmigt ? now() : null,
                ]));
            }

            return $antraege;
        });

        $this->urlaubskonto->vergessen($employe);

        // Abgleich (Abwesenheit, Nachweis, Dienstplan) übernimmt der HolidayObserver
        if ($genehmigt) {
            if ($actor->id !== $employe->id) {
                $antraege->each(fn (Holiday $h) => $this->benachrichtigeEntscheidung($h, $actor));
            }
        } else {
            $antraege->each(fn (Holiday $h) => $this->benachrichtigeAntrag($h));
        }

        return $antraege;
    }

    /**
     * Urlaub für mehrere Mitarbeitende (z. B. Betriebsferien) – direkt genehmigt,
     * bestehende Überschneidungen werden übersprungen.
     *
     * @param iterable<User> $mitarbeitende
     */
    public function fuerMehrereEintragen(User $actor, iterable $mitarbeitende, CarbonInterface $start, CarbonInterface $ende, ?string $kommentar = null): int
    {
        $anzahl = 0;
        foreach ($mitarbeitende as $employe) {
            if ($employe->id === $actor->id || $this->hatUeberschneidung($employe, $start, $ende)) {
                continue;
            }
            if ($this->tageFuer($employe, $start, $ende) <= 0) {
                continue;
            }
            $anzahl += $this->beantragen($actor, $employe, $start, $ende, false, $kommentar)->count();
        }

        return $anzahl;
    }

    public function genehmigen(Holiday $holiday, User $actor): void
    {
        $holiday->update([
            'approved' => true,
            'rejected' => false,
            'rejection_reason' => null,
            'approved_by' => $actor->id,
            'approved_at' => now(),
        ]);

        $this->urlaubskonto->vergessen($holiday->employe);
        $this->benachrichtigeEntscheidung($holiday, $actor);
    }

    public function ablehnen(Holiday $holiday, User $actor, ?string $grund = null): void
    {
        $holiday->update([
            'approved' => false,
            'rejected' => true,
            'rejection_reason' => $grund,
            'cancellation_requested_at' => null,
            'approved_by' => $actor->id,
            'approved_at' => now(),
        ]);

        $this->urlaubskonto->vergessen($holiday->employe);
        $this->benachrichtigeEntscheidung($holiday, $actor);
    }

    public function stornoBeantragen(Holiday $holiday, User $actor, ?string $grund = null): void
    {
        $holiday->update([
            'cancellation_requested_at' => now(),
            'cancellation_reason' => $grund,
        ]);

        $this->benachrichtigen(
            $this->zugriff->urlaubsGenehmigende($holiday->employe),
            new ZeitwirtschaftNotification(
                'holiday_cancellation_requested',
                'Stornierung beantragt: '.$holiday->employe->name,
                [
                    $holiday->employe->name.' möchte den genehmigten Urlaub vom '.$this->zeitraum($holiday).' stornieren.',
                    $grund ? 'Begründung: '.$grund : 'Keine Begründung angegeben.',
                ],
                route('holidays.index'),
                'Zur Urlaubsverwaltung'
            )
        );
    }

    public function stornoEntscheiden(Holiday $holiday, User $actor, bool $zustimmen): void
    {
        if ($zustimmen) {
            $this->stornieren($holiday, $actor);
            return;
        }

        $holiday->update(['cancellation_requested_at' => null, 'cancellation_reason' => null]);
        $this->benachrichtigen(collect([$holiday->employe]), new ZeitwirtschaftNotification(
            'holiday_cancellation_denied',
            'Stornierung abgelehnt',
            ['Die Stornierung deines Urlaubs vom '.$this->zeitraum($holiday).' wurde von '.$actor->name.' abgelehnt. Der Urlaub bleibt bestehen.'],
            route('holidays.index'),
            'Zur Urlaubsverwaltung'
        ));
    }

    /**
     * Genehmigten Urlaub stornieren bzw. Antrag löschen (Soft-Delete, bleibt nachvollziehbar).
     */
    public function stornieren(Holiday $holiday, User $actor): void
    {
        $warGenehmigt = $holiday->approved;
        $employe = $holiday->employe;

        $holiday->update(['cancelled_by' => $actor->id]);
        $holiday->delete();

        $this->urlaubskonto->vergessen($employe);

        if ($warGenehmigt && $actor->id !== $holiday->employe_id) {
            $this->benachrichtigen(collect([$employe]), new ZeitwirtschaftNotification(
                'holiday_cancelled',
                'Urlaub storniert',
                ['Dein Urlaub vom '.$this->zeitraum($holiday).' wurde von '.$actor->name.' storniert.'],
                route('holidays.index'),
                'Zur Urlaubsverwaltung'
            ));
        }
    }

    /**
     * Abwesenheit (Vertretungsplan), Arbeitszeitnachweis und Dienstplan an den Antrag angleichen.
     */
    public function abgleichen(Holiday $holiday): void
    {
        $employe = $holiday->employe;
        if ($employe === null) {
            return;
        }

        $aktiv = !$holiday->trashed() && $holiday->approved && !$holiday->rejected;

        if ($aktiv && (string) settings('absence_auto_create', 'holidays') === '1') {
            $absence = Absence::where('holiday_id', $holiday->id)->first()
                // Altbestand: vom früheren Observer angelegte, noch unverknüpfte Absence übernehmen
                ?? Absence::whereNull('holiday_id')
                    ->where('users_id', $employe->id)
                    ->where('reason', 'Urlaub')
                    ->whereDate('start', $holiday->start_date->toDateString())
                    ->whereDate('end', $holiday->end_date->toDateString())
                    ->first();
            if ($absence === null) {
                $absence = new Absence(['holiday_id' => $holiday->id, 'users_id' => $employe->id, 'reason' => 'Urlaub']);
                $absence->creator_id = $holiday->approved_by ?? $employe->id;
            }
            $absence->holiday_id = $holiday->id;
            $absence->start = $holiday->start_date;
            $absence->end = $holiday->end_date;
            $absence->save();
        } elseif (!$aktiv) {
            Absence::where('holiday_id', $holiday->id)->get()->each->delete();

            // Altbestand: vom früheren Observer angelegte, nie verknüpfte Absence desselben Urlaubs
            if ($holiday->approved || $holiday->wasChanged('approved')) {
                Absence::whereNull('holiday_id')
                    ->where('users_id', $employe->id)
                    ->where('reason', 'Urlaub')
                    ->whereDate('start', $holiday->start_date->toDateString())
                    ->whereDate('end', $holiday->end_date->toDateString())
                    ->get()->each->delete();
            }
        }

        $this->timesheets->syncAbwesenheiten($employe, $holiday->start_date, $holiday->end_date);
        $this->rosters->abwesenheitenAbgleichen($employe, $holiday->start_date, $holiday->end_date);
    }

    public function hatUeberschneidung(User $employe, CarbonInterface $start, CarbonInterface $ende, ?int $ausser = null): bool
    {
        return Holiday::query()
            ->nichtAbgelehnt()
            ->where('employe_id', $employe->id)
            ->ueberschneidet($start->toDateString(), $ende->toDateString())
            ->when($ausser, fn ($q) => $q->where('id', '!=', $ausser))
            ->exists();
    }

    /**
     * Wer aus denselben Gruppen/Abteilungen ist im Zeitraum ebenfalls weg?
     */
    public function teamAbwesenheiten(User $employe, CarbonInterface $start, CarbonInterface $ende): Collection
    {
        $gruppen = $employe->groups_rel()->pluck('groups.id');
        if ($gruppen->isEmpty()) {
            return collect();
        }

        return Holiday::query()
            ->nichtAbgelehnt()
            ->ueberschneidet($start->toDateString(), $ende->toDateString())
            ->where('employe_id', '!=', $employe->id)
            ->whereHas('employe.groups_rel', fn ($q) => $q->whereIn('groups.id', $gruppen))
            ->with('employe')
            ->orderBy('start_date')
            ->get()
            ->map(fn (Holiday $h) => [
                'name' => $h->employe?->name ?? 'Unbekannt',
                'von' => $h->start_date->format('d.m.'),
                'bis' => $h->end_date->format('d.m.'),
                'status' => $h->status_label,
            ]);
    }

    /**
     * @return array<int, array{0: Carbon, 1: Carbon}>
     */
    private function nachJahrenTeilen(Carbon $start, Carbon $ende): array
    {
        $teile = [];
        $von = $start->copy();
        while ($von->lte($ende)) {
            $bis = $von->copy()->endOfYear()->startOfDay()->min($ende);
            $teile[] = [$von->copy(), $bis->copy()];
            $von = $bis->copy()->addDay();
        }

        return $teile;
    }

    private function benachrichtigeAntrag(Holiday $holiday): void
    {
        $this->benachrichtigen(
            $this->zugriff->urlaubsGenehmigende($holiday->employe),
            new ZeitwirtschaftNotification(
                'holiday_requested',
                'Neuer Urlaubsantrag: '.$holiday->employe->name,
                array_values(array_filter([
                    $holiday->employe->name.' beantragt Urlaub vom '.$this->zeitraum($holiday).' ('.$holiday->days_label.').',
                    $holiday->comment ? 'Bemerkung: '.$holiday->comment : null,
                ])),
                route('holidays.index'),
                'Antrag prüfen'
            )
        );
    }

    private function benachrichtigeEntscheidung(Holiday $holiday, User $actor): void
    {
        if ($holiday->employe === null || $holiday->employe_id === $actor->id) {
            return;
        }

        $genehmigt = $holiday->approved;
        $this->benachrichtigen(collect([$holiday->employe]), new ZeitwirtschaftNotification(
            $genehmigt ? 'holiday_approved' : 'holiday_rejected',
            $genehmigt ? 'Urlaub genehmigt' : 'Urlaub abgelehnt',
            array_values(array_filter([
                'Dein Urlaub vom '.$this->zeitraum($holiday).' wurde von '.$actor->name.($genehmigt ? ' genehmigt.' : ' abgelehnt.'),
                !$genehmigt && $holiday->rejection_reason ? 'Begründung: '.$holiday->rejection_reason : null,
            ])),
            route('holidays.index'),
            'Zur Urlaubsverwaltung'
        ));
    }

    private function zeitraum(Holiday $holiday): string
    {
        return $holiday->start_date->isSameDay($holiday->end_date)
            ? $holiday->start_date->format('d.m.Y').($holiday->half_day ? ' (halber Tag)' : '')
            : $holiday->start_date->format('d.m.Y').' bis '.$holiday->end_date->format('d.m.Y');
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
