<?php

namespace App\Services\Personal;

use App\Enums\EmploymentStatus;
use App\Models\personal\Employment;
use App\Models\personal\PersonalReminder;
use App\Models\Setting;
use App\Models\User;
use App\Notifications\Personal\PersonalReminderNotification;
use Illuminate\Support\Facades\Notification;

/**
 * Wiedervorlagen der Personalverwaltung: Probezeitende, Ende befristeter Verträge,
 * Aufbewahrungsfristen nach dem Ausscheiden.
 *
 * Die Wiedervorlagen werden bei Vertragsanlage/-änderung (EmploymentObserver, Listener)
 * gepflegt und täglich per Scheduler an alle Personen mit dem Recht "edit contracts" gemeldet.
 */
class PersonalReminderService
{
    /**
     * Legt Wiedervorlagen für Probezeit und Vertragsende an bzw. aktualisiert sie (idempotent).
     */
    public function syncForEmployment(Employment $employment): void
    {
        $this->syncOne(
            $employment,
            PersonalReminder::TYPE_PROBATION,
            $employment->probation_end,
            $this->intSetting('probezeit_erinnerung_tage', 14),
            'Probezeit endet – Übernahme/Beendigung entscheiden.'
        );

        $this->syncOne(
            $employment,
            PersonalReminder::TYPE_CONTRACT_END,
            $employment->contract_type?->isBefristet() ? $employment->end : null,
            $this->intSetting('vertragsende_erinnerung_tage', 60),
            'Befristung läuft aus – Verlängerung oder Beendigung klären.'
        );
    }

    /**
     * Aufbewahrungsfrist: nach dem Ausscheiden (keine weitere offene Anstellung) an die Prüfung/Löschung erinnern.
     */
    public function createRetention(User $employe, Employment $ended): ?PersonalReminder
    {
        $hasOpen = $employe->employments()
            ->where('id', '!=', $ended->id)
            ->where('status', '!=', EmploymentStatus::Beendet->value)
            ->exists();
        if ($hasOpen) {
            return null;
        }

        $years = $this->intSetting('aufbewahrung_jahre', 10);
        $due = ($ended->end ?? now())->copy()->addYears($years);

        // Bei erneuter Beschäftigung/Beendigung zählt immer das späteste Ausscheiden
        PersonalReminder::where('employe_id', $employe->id)
            ->where('type', PersonalReminder::TYPE_RETENTION)->open()->delete();

        return PersonalReminder::create([
            'employe_id'    => $employe->id,
            'employment_id' => $ended->id,
            'type'          => PersonalReminder::TYPE_RETENTION,
            'due_date'      => $due,
            'lead_days'     => 30,
            'note'          => "Aufbewahrungsfrist ({$years} Jahre) nach Ausscheiden abgelaufen – Akte prüfen und ggf. löschen.",
        ]);
    }

    /**
     * Neue Beschäftigung hebt offene Aufbewahrungsfristen auf.
     */
    public function cancelRetention(User $employe): void
    {
        PersonalReminder::where('employe_id', $employe->id)
            ->where('type', PersonalReminder::TYPE_RETENTION)->open()
            ->update(['done_at' => now()]);
    }

    /**
     * Täglicher Lauf: fällige Wiedervorlagen melden, erledigte schließen.
     *
     * @return int Anzahl gemeldeter Wiedervorlagen
     */
    public function notifyDue(): int
    {
        $today = today();
        $sent = 0;

        $recipients = User::permission('edit contracts')->get();

        PersonalReminder::open()->whereNull('notified_at')->with(['employe', 'employment'])->each(
            function (PersonalReminder $reminder) use ($today, $recipients, &$sent) {
                // Bezugsvertrag inzwischen beendet → Erinnerung gegenstandslos (außer Aufbewahrung)
                if ($reminder->type !== PersonalReminder::TYPE_RETENTION
                    && $reminder->employment?->status === EmploymentStatus::Beendet) {
                    $reminder->update(['done_at' => now()]);
                    return;
                }

                if ($reminder->due_date->copy()->subDays($reminder->lead_days)->greaterThan($today)) {
                    return;
                }

                if ($recipients->isNotEmpty() && $reminder->employe) {
                    Notification::send($recipients, new PersonalReminderNotification($reminder));
                }
                $reminder->update(['notified_at' => now()]);
                $sent++;
            }
        );

        return $sent;
    }

    private function syncOne(Employment $employment, string $type, $dueDate, int $leadDays, string $note): void
    {
        $existing = PersonalReminder::where('employment_id', $employment->id)->where('type', $type)->open()->first();

        if ($dueDate === null || $employment->status === EmploymentStatus::Beendet) {
            $existing?->update(['done_at' => now()]);
            return;
        }

        if ($existing === null) {
            PersonalReminder::create([
                'employe_id'    => $employment->employe_id,
                'employment_id' => $employment->id,
                'type'          => $type,
                'due_date'      => $dueDate,
                'lead_days'     => $leadDays,
                'note'          => $note,
            ]);
            return;
        }

        // Datum geändert → erneut erinnern
        if (!$existing->due_date->isSameDay($dueDate)) {
            $existing->update(['due_date' => $dueDate, 'lead_days' => $leadDays, 'notified_at' => null]);
        }
    }

    private function intSetting(string $key, int $default): int
    {
        $value = Setting::where('setting', $key)->value('value');

        return ($value === null || $value === '') ? $default : (int) $value;
    }
}
