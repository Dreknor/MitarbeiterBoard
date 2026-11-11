<?php

namespace App\Services;

use App\Models\Absence;
use App\Models\User;
use App\Notifications\AbwesenheitGemeldet;
use App\Services\Benachrichtigungen\BenachrichtigungsService;
use App\Services\Personal\Zeit\ZeitZugriff;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;

/**
 * Abwesenheiten melden und löschen – gemeinsam für das Web (AbsenceController) und die API.
 *
 * Meldet jemand dieselbe Abwesenheit (gleiche Person, gleicher Grund) direkt anschließend erneut,
 * wird die bestehende verlängert statt eine zweite angelegt.
 *
 * Für andere melden dürfen Personen mit "create absences" (alle) sowie Vorgesetzte und ihre
 * Stellvertretungen (ZeitZugriff) – diese nur für die ihnen unterstellten Personen.
 */
class AbsenceService
{
    public function __construct(
        private readonly BenachrichtigungsService $benachrichtigungen,
        private readonly ZeitZugriff $zugriff,
    ) {
    }

    /**
     * Darf $actor für die Person $employeId eine Abwesenheit melden?
     */
    public function darfMeldenFuer(User $actor, int|string $employeId): bool
    {
        $employeId = (int) $employeId;

        return $employeId === $actor->id
            || $actor->can('create absences')
            || in_array($employeId, $this->zugriff->unterstellteIds($actor), true);
    }

    /**
     * Darf $actor überhaupt für andere melden (Auswahlfeld im Formular / in der App)?
     */
    public function darfFuerAndereMelden(User $actor): bool
    {
        return $actor->can('create absences') || $this->zugriff->unterstellteIds($actor) !== [];
    }

    /**
     * Personen, für die $actor Abwesenheiten melden darf (inkl. sich selbst), nach Name sortiert.
     *
     * @return Collection<int, User>
     */
    public function meldbarePersonen(User $actor): Collection
    {
        $query = User::query()->orderBy('name');

        if (!$actor->can('create absences')) {
            $query->whereIn('id', [$actor->id, ...$this->zugriff->unterstellteIds($actor)]);
        }

        return $query->get();
    }

    /**
     * @param array{users_id: int|string, reason: string, start: string, end: string, before?: int|null, showVertretungsplan?: int|bool|null, sick_note_required?: int|bool|null} $daten
     */
    public function melden(User $actor, array $daten): Absence
    {
        // Nur für sich selbst bzw. Personen, für die man melden darf (sonst für sich selbst)
        if (!$this->darfMeldenFuer($actor, $daten['users_id'])) {
            $daten['users_id'] = $actor->id;
        }
        // Krankenschein-Pflicht festlegen nur mit "create absences"
        if (!$actor->can('create absences')) {
            unset($daten['sick_note_required']);
        }
        // Spalte ist nicht nullbar – ohne Angabe gilt der Standard (bzw. die Krank-Regel im Model)
        if (($daten['sick_note_required'] ?? null) === null) {
            unset($daten['sick_note_required']);
        }

        $start = Carbon::parse($daten['start'])->startOfDay();
        $ende = Carbon::parse($daten['end'])->startOfDay();

        // Nur eine überlappende oder direkt angrenzende Abwesenheit (gleiche Person, gleicher Grund)
        // wird erweitert – nie eine aus einem Urlaub und nie eine, die erst später beginnt.
        $absence = Absence::query()
            ->whereNull('holiday_id')
            ->where('users_id', $daten['users_id'])
            ->where('reason', $daten['reason'])
            ->whereDate('end', '>=', $start->copy()->subDay())
            ->whereDate('start', '<=', $ende->copy()->addDay())
            ->orderBy('start')
            ->first();

        if ($absence === null) {
            $absence = new Absence($daten);
            $absence->save();

            Log::debug('Abwesenheit: Abwesenheit gespeichert', ['user' => $actor->id, 'absence' => $absence->id]);
        } else {
            $absence->update([
                'start' => $absence->start->min($start),
                'end' => $absence->end->max($ende),
                'sick_note_required' => $daten['sick_note_required'] ?? $absence->sick_note_required,
            ]);

            Log::debug('Abwesenheit: Abwesenheit aktualisiert', ['user' => $actor->id, 'absence' => $absence->id]);
        }

        // Sofortmeldung an alle, die die Kategorie „Abwesenheiten“ (Push oder Mail) eingeschaltet haben.
        // Der tägliche Überblick läuft über die Tagesübersicht (Bereich „Abwesenheiten“).
        Notification::send(
            $this->benachrichtigungen->aktiviertFuer($this->benachrichtigungen->nutzerMitPermission('view absences'), 'abwesenheiten'),
            new AbwesenheitGemeldet($absence)
        );

        return $absence;
    }

    /**
     * Löschen dürfen Personen mit "delete absences" und die Person, die die Abwesenheit eingetragen hat.
     */
    public function darfLoeschen(User $actor, Absence $absence): bool
    {
        return $actor->can('delete absences') || $actor->id == $absence->creator_id;
    }

    /**
     * Nur Abwesenheiten, deren Ende nach heute liegt, sind löschbar (Vergangenes bleibt als Nachweis).
     */
    public function loeschbar(Absence $absence): bool
    {
        return $absence->end->greaterThan(Carbon::today()->startOfDay());
    }

    /**
     * Alles zusammen: Recht, Zeitpunkt und keine Urlaubs-Abwesenheit (die läuft über den Urlaub).
     */
    public function loeschbarDurch(User $actor, Absence $absence): bool
    {
        return $absence->holiday_id === null
            && $this->darfLoeschen($actor, $absence)
            && $this->loeschbar($absence);
    }

    public function loeschen(User $actor, Absence $absence): void
    {
        if ($absence->holiday_id !== null) {
            throw ValidationException::withMessages([
                'absence' => 'Diese Abwesenheit stammt aus einem Urlaub – bitte den Urlaub stornieren.',
            ]);
        }

        if (!$this->loeschbar($absence)) {
            throw ValidationException::withMessages([
                'absence' => 'Abwesenheitsmitteilung kann nicht gelöscht werden, da das Ende in der Zukunft liegen muss.',
            ]);
        }

        Log::debug('Abwesenheit: Abwesenheit gelöscht', ['user' => $actor->id, 'absence' => $absence->id]);
        $absence->delete();
    }
}
