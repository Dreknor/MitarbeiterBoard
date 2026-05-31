<?php

namespace App\Services\Procedure;

use App\Mail\newStepMail;
use App\Mail\StepErinnerungMail;
use App\Models\Procedure_Step;
use App\Models\ProcedureStepComment;
use App\Models\User;
use Carbon\Carbon;
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
     * Benachrichtigt alle Empfänger eines neuen / fälligen Schrittes per Mail.
     * Abwesende Empfänger (`hasAbsence(now())`) werden übersprungen.
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
            if (method_exists($user, 'hasAbsence') && $user->hasAbsence(Carbon::now())) {
                continue;
            }

            try {
                Mail::to($user)->queue(new newStepMail(
                    $user->name,
                    $endDate,
                    $step->name,
                    optional($step->procedure)->name ?? '',
                    optional($step->procedure)->id ?? 0
                ));
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
        Mail::to($user)->queue(new StepErinnerungMail($user->name, $pendingSteps));
    }

    /**
     * Benachrichtigt Verantwortliche eines Schrittes über einen neuen Kommentar.
     * Author wird ausgenommen, Eltern-/Kindschritte optional (Settings-gesteuert).
     *
     * @return int Anzahl tatsächlich versendeter Mails.
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
            ->filter(fn ($u) => $u && $u->id !== $comment->user_id)
            ->filter(fn ($u) => !(method_exists($u, 'hasAbsence') && $u->hasAbsence(Carbon::now())));

        $sent = 0;
        foreach ($recipients as $user) {
            try {
                Mail::to($user)->queue(new \App\Mail\ProcedureStepCommentMail(
                    recipientName: $user->name,
                    authorName:    optional($comment->user)->name ?? 'System',
                    stepName:      $step->name,
                    procedureName: optional($step->procedure)->name ?? '',
                    procedureId:   optional($step->procedure)->id ?? 0,
                    body:          $comment->body
                ));
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

