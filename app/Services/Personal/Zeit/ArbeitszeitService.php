<?php

namespace App\Services\Personal\Zeit;

use App\Enums\EmploymentStatus;
use App\Models\personal\Employment;
use App\Models\User;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

/**
 * Arbeitszeitmodell: Soll-Arbeitszeit und Arbeitstage je Mitarbeiter und Tag.
 *
 * Grundlage ist der am jeweiligen Tag gültige Vertrag (Employment):
 *   Wochen-Soll = Stellenanteil × Vollzeit-Stunden (Setting "vollzeit_stunden", Standard 40)
 *   Tages-Soll  = Wochen-Soll ÷ Anzahl Arbeitstage des Vertrags – nur an diesen Arbeitstagen
 * Gesetzliche Feiertage haben kein Soll. Mehrere parallele Verträge werden addiert.
 *
 * Einzige Stelle für diese Berechnung – Arbeitszeitnachweis, Prüfengine,
 * Urlaubstage und Urlaubsanspruch nutzen sie gemeinsam.
 */
class ArbeitszeitService
{
    /** @var array<int, Collection<int, Employment>> */
    private array $vertraege = [];

    public function vollzeitStunden(): float
    {
        $wert = (float) str_replace(',', '.', (string) settings('vollzeit_stunden'));

        return $wert > 0 ? $wert : 40.0;
    }

    /**
     * Alle nicht ruhenden Verträge des Mitarbeiters – pro Request zwischengespeichert.
     * Beendete Verträge bleiben enthalten: Sie begrenzen sich über start/end selbst und
     * werden für zurückliegende Monate weiterhin gebraucht.
     *
     * @return Collection<int, Employment>
     */
    public function vertraege(User $user): Collection
    {
        return $this->vertraege[$user->id] ??= $user->employments()->with('hour_type')->get()
            ->filter(fn (Employment $e) => $e->status !== EmploymentStatus::Ruhend)
            ->values();
    }

    public function vergessen(?User $user = null): void
    {
        if ($user === null) {
            $this->vertraege = [];
            return;
        }
        unset($this->vertraege[$user->id]);
    }

    /**
     * @return Collection<int, Employment>
     */
    public function vertraegeAm(User $user, CarbonInterface $tag): Collection
    {
        $datum = $tag->toDateString();

        return $this->vertraege($user)->filter(function (Employment $e) use ($datum) {
            return $e->start !== null
                && $e->start->toDateString() <= $datum
                && ($e->end === null || $e->end->toDateString() >= $datum);
        })->values();
    }

    public function hatVertraege(User $user): bool
    {
        return $this->vertraege($user)->isNotEmpty();
    }

    public function wochenSollSekunden(Employment $vertrag): float
    {
        return ((float) $vertrag->percent) / 100 * $this->vollzeitStunden() * 3600;
    }

    /**
     * Soll-Arbeitszeit eines Tages in Sekunden.
     */
    public function sollSekunden(User $user, CarbonInterface $tag): float
    {
        if (is_holiday(Carbon::parse($tag))) {
            return 0.0;
        }

        $sekunden = 0.0;
        foreach ($this->vertraegeAm($user, $tag) as $vertrag) {
            $tage = $vertrag->arbeitstage();
            if (in_array($tag->dayOfWeekIso, $tage, true)) {
                $sekunden += $this->wochenSollSekunden($vertrag) / count($tage);
            }
        }

        return $sekunden;
    }

    /**
     * Ist der Tag laut Vertrag ein Arbeitstag (unabhängig von der Stundenzahl)?
     * Ohne hinterlegten Vertrag gilt Montag–Freitag (Altbestand, z. B. Lehrkräfte ohne Vertrag im System).
     */
    public function istArbeitstag(User $user, CarbonInterface $tag): bool
    {
        if (is_holiday(Carbon::parse($tag))) {
            return false;
        }

        if (!$this->hatVertraege($user)) {
            return $tag->isWeekday();
        }

        foreach ($this->vertraegeAm($user, $tag) as $vertrag) {
            if (in_array($tag->dayOfWeekIso, $vertrag->arbeitstage(), true)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Anzahl Arbeitstage im Zeitraum (inklusive) – Grundlage für Urlaubstage.
     */
    public function arbeitstageZwischen(User $user, CarbonInterface $start, CarbonInterface $ende): int
    {
        $anzahl = 0;
        for ($tag = Carbon::parse($start)->startOfDay(); $tag->lte($ende); $tag->addDay()) {
            if ($this->istArbeitstag($user, $tag)) {
                $anzahl++;
            }
        }

        return $anzahl;
    }

    /**
     * Durchschnittliche Arbeitstage pro Woche im Zeitraum (für anteiligen Urlaubsanspruch).
     */
    public function arbeitstageProWoche(User $user, CarbonInterface $stichtag): float
    {
        $vertraege = $this->vertraegeAm($user, $stichtag);
        if ($vertraege->isEmpty()) {
            return 5.0;
        }

        $tage = [];
        foreach ($vertraege as $vertrag) {
            $tage = array_merge($tage, $vertrag->arbeitstage());
        }

        return (float) count(array_unique($tage));
    }
}
