<?php

namespace App\Services;

use App\Models\Absence;
use App\Models\User;
use App\Notifications\AbwesenheitGemeldet;
use App\Services\Benachrichtigungen\BenachrichtigungsService;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;

/**
 * Abwesenheiten melden und löschen – gemeinsam für das Web (AbsenceController) und die API.
 *
 * Meldet jemand dieselbe Abwesenheit (gleiche Person, gleicher Grund) direkt anschließend erneut,
 * wird die bestehende verlängert statt eine zweite angelegt.
 */
class AbsenceService
{
    public function __construct(private readonly BenachrichtigungsService $benachrichtigungen)
    {
    }

    /**
     * @param array{users_id: int|string, reason: string, start: string, end: string, before?: int|null, showVertretungsplan?: int|bool|null, sick_note_required?: int|bool|null} $daten
     */
    public function melden(User $actor, array $daten): Absence
    {
        // Ohne "create absences" nur für sich selbst
        if (!$actor->can('create absences')) {
            $daten['users_id'] = $actor->id;
            unset($daten['sick_note_required']);
        }
        // Spalte ist nicht nullbar – ohne Angabe gilt der Standard (bzw. die Krank-Regel im Model)
        if (($daten['sick_note_required'] ?? null) === null) {
            unset($daten['sick_note_required']);
        }

        $absence = Absence::whereDate('end', '>=', Carbon::parse($daten['start'])->subDay())
            ->where('users_id', $daten['users_id'])
            ->where('reason', $daten['reason'])
            ->first();

        if ($absence === null) {
            $absence = new Absence($daten);
            $absence->save();

            Log::debug('Abwesenheit: Abwesenheit gespeichert', ['user' => $actor->id, 'absence' => $absence->id]);
        } else {
            $absence->update([
                'end' => $daten['end'],
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
     * Nur Abwesenheiten, deren Ende nach heute liegt, sind löschbar.
     */
    public function loeschbar(Absence $absence): bool
    {
        return $absence->end->greaterThan(Carbon::today()->startOfDay());
    }

    public function loeschen(User $actor, Absence $absence): void
    {
        if (!$this->loeschbar($absence)) {
            throw ValidationException::withMessages([
                'absence' => 'Abwesenheitsmitteilung kann nicht gelöscht werden, da das Ende in der Zukunft liegen muss.',
            ]);
        }

        Log::debug('Abwesenheit: Abwesenheit gelöscht', ['user' => $actor->id, 'absence' => $absence->id]);
        $absence->delete();
    }
}
