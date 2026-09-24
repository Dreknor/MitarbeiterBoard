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
use Illuminate\Support\Facades\Schema;

/**
 * Gemeinsame Logik für Termine des Pädagogischen Tagebuchs (Web-Frontend und API v1).
 */
class PaedDiaryAppointmentService
{
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
        $appointment->update($this->attributes($data, $pauseEntries));
        $this->syncRelations($appointment, $data, $user);
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

        $appointment->klassen()->detach();
        $appointment->groups()->detach();
        $appointment->schueler()->detach();
        $appointment->exceptions()->delete();
        $appointment->delete();
    }

    public function syncRelations(PaedDiaryAppointment $appointment, array $data, User $user): void
    {
        $allowedClassIds = $user->paed_klassen()->pluck('klassen.id')->toArray();
        $klasseIds       = array_filter($data['klasse_ids'] ?? [], fn ($id) => in_array($id, $allowedClassIds));
        $appointment->klassen()->sync($klasseIds);
        $groupIds = array_filter($data['group_ids'] ?? [], fn ($gid) => PaedDiaryClassGroup::where('id', $gid)->where('user_id', $user->id)->exists());
        $appointment->groups()->sync($groupIds);
        $rawStu = $data['schueler_ids'] ?? [];
        $appointment->schueler()->sync($rawStu ? Schueler::whereIn('id', $rawStu)->whereIn('klasse_id', $allowedClassIds)->pluck('id')->toArray() : []);
    }

    /**
     * Pausiert alle offenen Einträge für die vom Termin betroffenen Schüler
     * an allen Vorkommen des Termins (ab Terminbeginn, max. 90 Tage in die Zukunft).
     */
    public function pauseEntriesForAppointment(PaedDiaryAppointment $appointment): void
    {
        // Spalte existiert noch nicht → Migration ausstehend, überspringen
        if (!Schema::hasColumn('paed_diary_appointments', 'pause_entries') || !$appointment->pause_entries) {
            return;
        }

        $appointment->loadMissing(['klassen', 'schueler', 'exceptions']);

        $schuelerIds = collect();
        foreach ($appointment->klassen as $klasse) {
            $schuelerIds = $schuelerIds->merge(Schueler::where('klasse_id', $klasse->id)->pluck('id'));
        }
        $schuelerIds = $schuelerIds->merge($appointment->schueler->pluck('id'))->unique()->values();
        if ($schuelerIds->isEmpty()) {
            return;
        }

        $rangeStart  = $appointment->start_date->copy()->startOfDay();
        $rangeEnd    = Carbon::today()->addDays(90)->endOfDay();
        $occurrences = $appointment->getOccurrencesInRange($rangeStart->copy(), $rangeEnd->copy());
        if (empty($occurrences)) {
            return;
        }

        $reasonColumnExists = Schema::hasColumn('paed_diary_entry_pauses', 'reason');

        foreach ($occurrences as $occ) {
            $dateStr = $occ['date'];

            $entries = PaedDiaryEntry::whereNull('completed_at')
                ->whereDate('datum', '<=', $dateStr)
                ->whereHas('schueler', fn ($q) => $q->whereIn('schueler.id', $schuelerIds->toArray()))
                ->with('schueler:id')
                ->get();

            foreach ($entries as $entry) {
                foreach ($entry->schueler as $stu) {
                    if (!$schuelerIds->contains($stu->id)) {
                        continue;
                    }
                    PaedDiaryEntryPause::firstOrCreate(
                        ['paed_diary_entry_id' => $entry->id, 'schueler_id' => $stu->id, 'date' => $dateStr],
                        $reasonColumnExists ? ['reason' => 'Termin'] : []
                    );
                }
            }
        }
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
        ];
        if (Schema::hasColumn('paed_diary_appointments', 'pause_entries')) {
            $attributes['pause_entries'] = $pauseEntries;
        }

        return $attributes;
    }
}
