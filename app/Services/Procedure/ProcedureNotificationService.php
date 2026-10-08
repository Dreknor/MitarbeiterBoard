<?php

namespace App\Services\Procedure;

use App\Notifications\ProzessschrittZugewiesen;
use App\Mail\StepErinnerungMail;
use App\Models\Procedure_Step;
use App\Models\ProcedureStepComment;
use App\Models\User;
use App\Notifications\ProzessKommentar;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * Zentraler Mail-Versand rund um Prozesse (§5.1).
 *
 * Konsolidiert die bisher in `ProcedureController::startNow`,
 * `ProcedureController::done` und `RecurringProcedureController::start`
 * duplizierte Mail-Logik an einer Stelle.
 */
class ProcedureNotificationService
{
    /**
     * Benachrichtigt alle Empfänger eines neuen / fälligen Schrittes
     * (Glocke, Push, Mail nach Einstellung; keine Mail an Abwesende).
     */
    public function notifyStepAssigned(Procedure_Step $step, ?User $exclude = null): int
    {
        $sent = 0;
        $endDate = $step->endDate
            ? Carbon::parse($step->endDate)->format('d.m.Y')
            : Carbon::now()->addDays((int) ($step->durationDays ?? 0))->format('d.m.Y');

        foreach ($step->users as $user) {
            if ($exclude && $user->id === $exclude->id) {
                continue;
            }
            try {
                // Glocke/Push immer nach Einstellung, Mail nicht an Abwesende (siehe Notification)
                $user->notify(new ProzessschrittZugewiesen($step, $endDate));
                $sent++;
            } catch (\Throwable $e) {
                Log::error('Prozesse: Mailversand fehlgeschlagen', [
                    'user'  => $user->id,
                    'step'  => $step->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return $sent;
    }

    /**
     * Sendet eine Erinnerungsmail an einen User mit Liste seiner offenen Schritte.
     */
    public function sendReminder(User $user, array $pendingSteps): void
    {
        if (method_exists($user, 'hasAbsence') && $user->hasAbsence(Carbon::now())) {
            return;
        }
        if (empty($pendingSteps)) {
            return;
        }
        if (!app(\App\Services\Benachrichtigungen\BenachrichtigungsService::class)->mailErlaubt($user, 'prozesse')) {
            return;
        }
        Mail::to($user)->queue(new StepErinnerungMail($user->name, $pendingSteps));
    }

    /**
     * Täglicher Erinnerungslauf: Jede Person mit fälligen/überfälligen offenen Schritten
     * in laufenden Prozessen erhält eine Sammelmail (Abwesende werden übersprungen).
     *
     * @return int Anzahl der erinnerten Personen.
     */
    public function sendDueReminders(): int
    {
        $sent = 0;

        User::whereHas('steps', fn (Builder $q) => $this->dueStepsConstraint($q))
            ->get()
            ->each(function (User $user) use (&$sent) {
                if ($user->hasAbsence(Carbon::now())) {
                    return;
                }
                $pending = $this->pendingStepsFor($user);
                if ($pending !== []) {
                    $this->sendReminder($user, $pending);
                    $sent++;
                }
            });

        return $sent;
    }

    /**
     * Fällige/überfällige offene Schritte einer Person, aufbereitet für die Erinnerungsmail.
     * Schritte aus beendeten, gelöschten oder nicht gestarteten Prozessen werden ignoriert.
     */
    public function pendingStepsFor(User $user): array
    {
        return $user->steps()
            ->with('procedure')
            ->where(fn (Builder $q) => $this->dueStepsConstraint($q))
            ->orderBy('endDate')
            ->get()
            ->map(fn (Procedure_Step $step) => [
                'endDate'       => $step->endDate->format('d.m.Y'),
                'procedureName' => $step->procedure->name,
                'procedureId'   => $step->procedure_id,
                'stepName'      => $step->name,
                'stepId'        => $step->id,
            ])
            ->all();
    }

    private function dueStepsConstraint(Builder $query): Builder
    {
        return $query->where('done', false)
            ->whereNotNull('endDate')
            ->whereDate('endDate', '<=', Carbon::today())
            ->whereHas('procedure', fn (Builder $p) => $p->laufend());
    }

    /**
     * Benachrichtigt Verantwortliche eines Schrittes über einen neuen Kommentar.
     * Author wird ausgenommen, Eltern-/Kindschritte optional (Settings-gesteuert).
     *
     * @return int Anzahl benachrichtigter Personen.
     */
    public function notifyComment(ProcedureStepComment $comment): int
    {
        $step = $comment->step()->with(['users', 'parent_rel.users', 'childs.users', 'procedure'])->first();
        if (!$step) {
            return 0;
        }

        $notifyParents  = function_exists('settings') ? (bool) settings('procedure.comment_notify_parents', true) : true;
        $notifyChildren = function_exists('settings') ? (bool) settings('procedure.comment_notify_children', false) : false;

        /** @var Collection $recipients */
        $recipients = collect($step->users);

        if ($notifyParents && $step->parent_rel) {
            $recipients = $recipients->merge($step->parent_rel->users);
        }
        if ($notifyChildren) {
            foreach ($step->childs as $child) {
                $recipients = $recipients->merge($child->users);
            }
        }

        $recipients = $recipients
            ->unique('id')
            ->filter(fn ($u) => $u && $u->id !== $comment->user_id);

        $sent = 0;
        foreach ($recipients as $user) {
            try {
                $user->notify(new ProzessKommentar($comment, $step));
                $sent++;
            } catch (\Throwable $e) {
                Log::error('Prozesse: Kommentar-Mailversand fehlgeschlagen', [
                    'comment' => $comment->id,
                    'user'    => $user->id,
                    'error'   => $e->getMessage(),
                ]);
            }
        }

        if ($sent > 0) {
            $comment->update(['notified_at' => now()]);
        }

        return $sent;
    }
}

