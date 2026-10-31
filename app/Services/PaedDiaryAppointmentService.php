<?php

namespace App\Services;

use App\Models\PaedDiaryAppointment;
use App\Models\PaedDiaryAppointmentException;
use App\Models\PaedDiaryClassGroup;
use App\Models\PaedDiaryEntry;
use App\Models\PaedDiaryEntryPause;
use App\Models\Schueler;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Collection;

/**
 * Gemeinsame Logik für Termine des Pädagogischen Tagebuchs (Web-Frontend und API v1).
 */
class PaedDiaryAppointmentService
{
    /** Grund für Pausen, die ein Termin mit „Einträge pausieren“ erzeugt. */
    public const GRUND_TERMIN = 'Termin';

    /**
     * Termin anlegen. `$data` ist bereits validiert (Felder wie im Web-Formular).
     */
    public function create(array $data, bool $pauseEntries, User $user): PaedDiaryAppointment
    {
        $attributes = $this->attributes($data, $pauseEntries);
        $attributes['user_id'] = $user->id;
        $attributes['is_paused'] = false;

        $appointment = PaedDiaryAppointment::create($attributes);
        $this->syncRelations($appointment, $data, $user);
        $this->pauseEntriesForAppointment($appointment);

        return $appointment;
    }

    /**
     * Termin ändern (alle Felder, wie im Web-Formular).
     */
    public function update(PaedDiaryAppointment $appointment, array $data, bool $pauseEntries, User $user): PaedDiaryAppointment
    {
        if (!($data['is_recurring'] ?? false)) {
            $appointment->is_paused = false;
        }
        $vorher = $appointment->pause_entries ? $this->betroffen($appointment) : [collect(), []];
        $appointment->update($this->attributes($data, $pauseEntries));
        $this->syncRelations($appointment, $data, $user);
        $this->removeStaleAppointmentPauses($vorher, $appointment->fresh());
        // Immer pausieren wenn Option aktiv – ensure ist idempotent
        $this->pauseEntriesForAppointment($appointment);

        return $appointment;
    }

    /**
     * Zugriff: Ersteller oder Nutzer mit Zugang zu mind. einer zugeordneten Klasse.
     */
    public function canAccess(PaedDiaryAppointment $appointment, User $user): bool
    {
        $userClassIds = $user->paed_klassen()->pluck('klassen.id')->toArray();

        return $appointment->user_id === $user->id
            || $appointment->klassen()->whereIn('klassen.id', $userClassIds)->exists()
            || $appointment->schueler()->whereIn('schueler.klasse_id', $userClassIds)->exists()
            || $appointment->groups()->whereHas('klassen', fn ($q) => $q->whereIn('klassen.id', $userClassIds))->exists();
    }

    /**
     * Darf der Benutzer den Termin als Ganzes ändern/löschen? Ersteller oder Zugriff auf alle
     * zugeordneten Klassen, Kopplungen und Schüler. Sonst betrifft „Löschen“ nur die eigenen Zuordnungen.
     */
    public function canManageAll(PaedDiaryAppointment $appointment, User $user): bool
    {
        if ((int) $appointment->user_id === (int) $user->id) {
            return true;
        }
        $allowed = $user->paed_klassen()->pluck('klassen.id')->all();

        return !$appointment->klassen()->whereNotIn('klassen.id', $allowed)->exists()
            && !$appointment->groups()->where('paed_diary_class_groups.user_id', '!=', $user->id)->exists()
            && !$appointment->schueler()->where(fn ($q) => $q->whereNotIn('schueler.klasse_id', $allowed)->orWhereNull('schueler.klasse_id'))->exists();
    }

    /**
     * Ist mindestens eine Klasse, Kopplung oder ein Schüler angegeben? Termine ohne Zuordnung
     * wären für niemanden sichtbar.
     */
    public function hasAssignments(array $data): bool
    {
        return !empty($data['klasse_ids']) || !empty($data['group_ids']) || !empty($data['schueler_ids']);
    }

    /**
     * Löscht den Termin für einen Benutzer: Wer nicht alle Zuordnungen verwalten darf, entfernt
     * bei `all` nur die eigenen (Klassen, Kopplungen, Schüler); Serien kürzen darf nur, wer alles
     * verwalten darf. Mit `$schuelerIds` werden nur diese Schüler aus der Zuordnung entfernt.
     *
     * @return bool false, wenn der Benutzer die gewünschte Änderung nicht vornehmen darf
     */
    public function deleteFor(PaedDiaryAppointment $appointment, User $user, string $mode = 'all', ?string $occurrenceDate = null, array $schuelerIds = []): bool
    {
        $vorher = $appointment->pause_entries ? $this->betroffen($appointment) : [collect(), []];

        if (!empty($schuelerIds) || !$this->canManageAll($appointment, $user)) {
            if ($mode !== 'all') {
                return false;
            }
            $allowed = $user->paed_klassen()->pluck('klassen.id')->all();
            if (!empty($schuelerIds)) {
                $appointment->schueler()->detach(Schueler::whereIn('id', $schuelerIds)->whereIn('klasse_id', $allowed)->pluck('id')->all());
            } else {
                $appointment->klassen()->detach($allowed);
                $appointment->groups()->detach($appointment->groups()->where('paed_diary_class_groups.user_id', $user->id)->pluck('paed_diary_class_groups.id')->all());
                $appointment->schueler()->detach(Schueler::whereIn('klasse_id', $allowed)->pluck('id')->all());
            }

            if ($appointment->klassen()->exists() || $appointment->groups()->exists() || $appointment->schueler()->exists()) {
                $this->removeStaleAppointmentPauses($vorher, $appointment->fresh());

                return true;
            }
        }

        $this->delete($appointment, $mode, $occurrenceDate);

        return true;
    }

    /**
     * Termin löschen: ganz (`all`), nur ein Vorkommen (`only_this`) oder ab einem Vorkommen
     * (`this_and_future`) – wie im Web.
     */
    public function delete(PaedDiaryAppointment $appointment, string $mode = 'all', ?string $occurrenceDate = null): void
    {
        if ($appointment->is_recurring && $occurrenceDate) {
            $carbon = Carbon::parse($occurrenceDate);

            if ($mode === 'only_this') {
                PaedDiaryAppointmentException::firstOrCreate([
                    'appointment_id' => $appointment->id,
                    'exception_date' => $carbon->toDateString(),
                ]);

                return;
            }

            if ($mode === 'this_and_future' && $carbon->toDateString() > $appointment->start_date->toDateString()) {
                // Serie bis zum Vortag kürzen; zukünftige Ausnahmen entfernen
                $appointment->update(['recurring_end_date' => $carbon->copy()->subDay()->toDateString()]);
                $appointment->exceptions()->where('exception_date', '>=', $carbon->toDateString())->delete();

                return;
            }
        }

        if ($appointment->pause_entries) {
            $this->removeStaleAppointmentPauses($this->betroffen($appointment), null);
        }
        $appointment->klassen()->detach();
        $appointment->groups()->detach();
        $appointment->schueler()->detach();
        $appointment->exceptions()->delete();
        $appointment->delete();
    }

    /**
     * Setzt Klassen, Kopplungen und Schüler des Termins. Der Benutzer kann nur Zuordnungen ändern,
     * auf die er Zugriff hat – bestehende Zuordnungen anderer Klassen/Kopplungen bleiben erhalten,
     * sonst würde z. B. eine Lehrkraft der 3a beim Bearbeiten die 3b lautlos aus dem Termin entfernen.
     */
    public function syncRelations(PaedDiaryAppointment $appointment, array $data, User $user): void
    {
        $allowedClassIds = $user->paed_klassen()->pluck('klassen.id')->map(fn ($id) => (int) $id)->all();

        $klasseIds = array_filter(array_map('intval', $data['klasse_ids'] ?? []), fn ($id) => in_array($id, $allowedClassIds, true));
        $fremdeKlassen = $appointment->klassen()->pluck('klassen.id')->map(fn ($id) => (int) $id)
            ->reject(fn ($id) => in_array($id, $allowedClassIds, true))->all();
        $appointment->klassen()->sync(array_values(array_unique(array_merge($klasseIds, $fremdeKlassen))));

        $groupIds = array_filter(array_map('intval', $data['group_ids'] ?? []), fn ($gid) => PaedDiaryClassGroup::where('id', $gid)->where('user_id', $user->id)->exists());
        $fremdeGruppen = $appointment->groups()->where('paed_diary_class_groups.user_id', '!=', $user->id)
            ->pluck('paed_diary_class_groups.id')->map(fn ($id) => (int) $id)->all();
        $appointment->groups()->sync(array_values(array_unique(array_merge($groupIds, $fremdeGruppen))));

        $rawStu = $data['schueler_ids'] ?? [];
        $schuelerIds = $rawStu ? Schueler::whereIn('id', $rawStu)->whereIn('klasse_id', $allowedClassIds)->pluck('id')->map(fn ($id) => (int) $id)->all() : [];
        $fremdeSchueler = $appointment->schueler()->where(fn ($q) => $q->whereNotIn('schueler.klasse_id', $allowedClassIds)->orWhereNull('schueler.klasse_id'))
            ->pluck('schueler.id')->map(fn ($id) => (int) $id)->all();
        $appointment->schueler()->sync(array_values(array_unique(array_merge($schuelerIds, $fremdeSchueler))));
    }

    /**
     * Pausiert alle offenen Einträge für die vom Termin betroffenen Schüler (Klassen, Kopplungen,
     * einzelne Schüler) an allen Vorkommen des Termins (ab Terminbeginn, max. 90 Tage in die Zukunft).
     */
    public function pauseEntriesForAppointment(PaedDiaryAppointment $appointment): void
    {
        if (!$appointment->pause_entries) {
            return;
        }

        [$schuelerIds, $dates] = $this->betroffen($appointment);
        if ($schuelerIds->isEmpty() || empty($dates)) {
            return;
        }

        // Offene Einträge einmal laden statt pro Vorkommen
        $entries = PaedDiaryEntry::whereNull('completed_at')
            ->whereDate('datum', '<=', max($dates))
            ->whereHas('schueler', fn ($q) => $q->whereIn('schueler.id', $schuelerIds->all()))
            ->with('schueler:id')
            ->get();

        $pausen = [];
        foreach ($dates as $dateStr) {
            foreach ($entries as $entry) {
                if ($entry->datum->toDateString() > $dateStr) {
                    continue;
                }
                foreach ($entry->schueler as $stu) {
                    if ($schuelerIds->contains($stu->id)) {
                        $pausen[] = [$entry->id, $stu->id, $dateStr, self::GRUND_TERMIN];
                    }
                }
            }
        }

        app(PaedDiaryCalendarService::class)->ensureEntryPauses($pausen);
    }

    /**
     * Entfernt die Termin-Pausen eines früheren Stands des Termins (ab heute), die nach der Änderung
     * nicht mehr gelten: weggefallene Tage oder Schüler bzw. abgeschaltetes „Einträge pausieren“.
     *
     * @param array{0: Collection, 1: string[]} $vorher Ergebnis von betroffen() vor der Änderung
     */
    public function removeStaleAppointmentPauses(array $vorher, ?PaedDiaryAppointment $nachher): void
    {
        [$alteSchueler, $alteTage] = $vorher;
        $heute = Carbon::today()->toDateString();
        $alteTage = array_values(array_filter($alteTage, fn ($d) => $d >= $heute));
        if ($alteSchueler->isEmpty() || empty($alteTage)) {
            return;
        }

        [$neueSchueler, $neueTage] = $nachher && $nachher->pause_entries ? $this->betroffen($nachher) : [collect(), []];

        foreach ($alteTage as $tag) {
            $weg = in_array($tag, $neueTage, true) ? $alteSchueler->diff($neueSchueler) : $alteSchueler;
            if ($weg->isEmpty()) {
                continue;
            }
            PaedDiaryEntryPause::whereIn('schueler_id', $weg->all())
                ->whereDate('date', $tag)
                ->where('reason', self::GRUND_TERMIN)
                ->delete();
        }
    }

    /**
     * Betroffene Schüler-IDs und Vorkommen (Y-m-d, ab Terminbeginn bis heute + 90 Tage) eines Termins.
     *
     * @return array{0: Collection, 1: string[]}
     */
    public function betroffen(PaedDiaryAppointment $appointment): array
    {
        $appointment->load(['klassen:id', 'schueler:id', 'exceptions', 'groups.klassen:id']);

        $klassenIds = $appointment->klassen->pluck('id')
            ->merge($appointment->groups->flatMap(fn ($g) => $g->klassen->pluck('id')))
            ->unique();
        $schuelerIds = Schueler::whereIn('klasse_id', $klassenIds->all())->pluck('id')
            ->merge($appointment->schueler->pluck('id'))
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values();

        $occurrences = $appointment->getOccurrencesInRange(
            $appointment->start_date->copy()->startOfDay(),
            Carbon::today()->addDays(90)->endOfDay()
        );

        return [$schuelerIds, array_column($occurrences, 'date')];
    }

    private function attributes(array $data, bool $pauseEntries): array
    {
        $isRecurring = (bool) ($data['is_recurring'] ?? false);
        $attributes = [
            'title'              => trim($data['title']),
            'description'        => $data['description'] ?? null,
            'start_date'         => Carbon::parse($data['start_date'])->toDateString(),
            'start_time'         => !empty($data['start_time']) ? Carbon::parse($data['start_date'] . ' ' . $data['start_time']) : null,
            'end_time'           => !empty($data['end_time']) ? Carbon::parse($data['start_date'] . ' ' . $data['end_time']) : null,
            'is_recurring'       => $isRecurring,
            'recurring_type'     => $isRecurring ? ($data['recurring_type'] ?? null) : null,
            'recurring_interval' => $isRecurring ? ($data['recurring_interval'] ?? 1) : 1,
            'recurring_end_date' => $isRecurring && !empty($data['recurring_end_date']) ? Carbon::parse($data['recurring_end_date'])->toDateString() : null,
            'pause_entries'      => $pauseEntries,
        ];

        return $attributes;
    }
}
