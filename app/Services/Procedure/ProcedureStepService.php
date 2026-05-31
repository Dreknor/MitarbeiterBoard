<?php

namespace App\Services\Procedure;

use App\Models\Procedure_Step;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Service für Operationen an einzelnen Schritten (§5.1).
 */
class ProcedureStepService
{
    public function __construct(private readonly ProcedureNotificationService $notifications) {}

    /**
     * Markiert einen Schritt als erledigt, setzt `completed_at`/`completed_by`,
     * triggert das Event `ProcedureStepCompleted` und benachrichtigt Folgeschritte.
     *
     * @return bool true wenn der zugehörige Prozess komplett abgeschlossen wurde.
     */
    public function complete(Procedure_Step $step, ?User $completedBy): bool
    {
        return DB::transaction(function () use ($step, $completedBy) {
            $step->update([
                'done'         => true,
                'completed_at' => now(),
                'completed_by' => optional($completedBy)->id,
            ]);

            // Event feuern (für Listener wie UpdateQualificationFromStep, StartOnboardingProcess …)
            if (class_exists(\App\Events\Personal\ProcedureStepCompleted::class)) {
                event(new \App\Events\Personal\ProcedureStepCompleted(
                    $step->procedure_id,
                    $step->id,
                    optional($completedBy)->id ?? 0
                ));
            }

            // Folgeschritte: endDate setzen + Mails
            foreach ($step->childs as $child) {
                $child->update([
                    'endDate' => Carbon::now()->addDays((int) ($child->durationDays ?? 0)),
                ]);
                $child->load('users', 'procedure');
                $this->notifications->notifyStepAssigned($child, $completedBy);
            }

            // Komplett abgeschlossen?
            $open = Procedure_Step::where('procedure_id', $step->procedure_id)
                ->where('done', false)
                ->count();

            if ($open === 0) {
                $step->procedure()->update(['ended_at' => now()]);
                return true;
            }

            return false;
        });
    }

    /**
     * Schritt wieder öffnen (§B-16). Setzt done = false, löscht completed_*.
     */
    public function reopen(Procedure_Step $step): void
    {
        $step->update([
            'done'         => false,
            'completed_at' => null,
            'completed_by' => null,
        ]);

        // Wenn der Prozess vorher abgeschlossen war, ist er es jetzt nicht mehr.
        if ($step->procedure && $step->procedure->ended_at) {
            $step->procedure()->update(['ended_at' => null, 'ended_reason' => null]);
        }
    }

    /**
     * Mehrere User einem Schritt zuweisen.
     *
     * @param int[] $userIds
     */
    public function assignUsers(Procedure_Step $step, array $userIds): int
    {
        $existing = $step->users()->pluck('users.id')->all();
        $toAttach = array_values(array_diff($userIds, $existing));
        if ($toAttach) {
            $step->users()->attach($toAttach);
        }
        return count($toAttach);
    }

    /** Einen User von einem Schritt entfernen. */
    public function removeUser(Procedure_Step $step, User $user): void
    {
        $step->users()->detach($user->id);
    }

    /**
     * Schritt verschieben: Eltern-Schritt und/oder Reihenfolge ändern (§B-09).
     *
     * @param int|null $newParentId  null = Root-Schritt im selben Prozess
     * @param int      $sortOrder    Neue Reihenfolge unter dem neuen Eltern-Schritt
     */
    public function moveStep(Procedure_Step $step, ?int $newParentId, int $sortOrder): void
    {
        // Zirkuläre Elternschaft verhindern
        if ($newParentId !== null && $this->isDescendant($step, $newParentId)) {
            throw new \InvalidArgumentException('Ein Schritt kann nicht unter seinen eigenen Nachfahren verschoben werden.');
        }

        DB::transaction(function () use ($step, $newParentId, $sortOrder) {
            // sort_order der Geschwister nach oben schieben, um Platz zu machen
            Procedure_Step::where('procedure_id', $step->procedure_id)
                ->where('parent', $newParentId)
                ->where('id', '!=', $step->id)
                ->where('sort_order', '>=', $sortOrder)
                ->increment('sort_order');

            $step->update([
                'parent'     => $newParentId,
                'sort_order' => $sortOrder,
            ]);
        });
    }

    /**
     * Bulk-Neusortierung aller Kinder eines Elternknotens.
     * Erwartet geordnetes Array von Step-IDs.
     *
     * @param int[] $orderedIds Step-IDs in der gewünschten Reihenfolge
     */
    public function reorderSiblings(int $procedureId, ?int $parentId, array $orderedIds): void
    {
        DB::transaction(function () use ($procedureId, $parentId, $orderedIds) {
            foreach ($orderedIds as $idx => $stepId) {
                Procedure_Step::where('id', $stepId)
                    ->where('procedure_id', $procedureId)
                    ->where('parent', $parentId)
                    ->update(['sort_order' => $idx]);
            }
        });
    }

    /** Prüft ob $ancestor ein Vorfahre von $step ist (zirkuläre Verschiebung verhindern). */
    private function isDescendant(Procedure_Step $step, int $candidateParentId): bool
    {
        if ($step->id === $candidateParentId) {
            return true;
        }
        foreach ($step->childs as $child) {
            if ($this->isDescendant($child, $candidateParentId)) {
                return true;
            }
        }
        return false;
    }
}


