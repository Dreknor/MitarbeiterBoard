<?php

namespace App\Services\Procedure;

use App\Models\Procedure;
use App\Models\Procedure_Step;
use App\Models\ProcedureTemplate;
use App\Models\ProcedureTemplateStep;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Domain-Service rund um Prozesse und ihre Erzeugung aus Vorlagen (§5.1).
 *
 * Phase 1: Schreibt sowohl in das neue (`procedure_templates` /
 * `procedure_template_steps`) als auch in das alte Modell (`procedures.started_at IS NULL`,
 * `procedure_steps`), damit das bestehende UI bis Phase 2 unverändert weiterläuft.
 */
class ProcedureService
{
    public function __construct(private readonly ProcedureNotificationService $notifications) {}

    /**
     * Erstellt eine Vorlage. Schreibt in `procedure_templates` und – aus
     * Übergangsgründen – auch in `procedures` (started_at = null).
     */
    public function createTemplate(array $data, ?int $authorId): array
    {
        return DB::transaction(function () use ($data, $authorId) {
            /** @var Procedure $legacy */
            $legacy = Procedure::create([
                'name'        => $data['name'],
                'description' => $data['description'] ?? null,
                'category_id' => $data['category_id'],
                'author_id'   => $authorId,
                'started_at'  => null,
                'ended_at'    => null,
            ]);

            $template = ProcedureTemplate::create([
                'name'                => $legacy->name,
                'description'         => $legacy->description,
                'category_id'         => $legacy->category_id,
                'author_id'           => $authorId,
                'color'               => $data['color'] ?? null,
                'legacy_procedure_id' => $legacy->id,
            ]);

            $legacy->update(['template_id' => $template->id]);

            return ['template' => $template, 'legacy' => $legacy];
        });
    }

    /**
     * Startet einen Prozess auf Basis einer Vorlage (legacy oder neu).
     * Repliziert alle Schritte rekursiv und versendet Benachrichtigungen.
     *
     * @param Procedure $template  Legacy-Vorlage (`procedures.started_at IS NULL`)
     */
    public function startFromTemplate(Procedure $template, array $params, ?int $authorId = null, ?int $excludeUserId = null): Procedure
    {
        return DB::transaction(function () use ($template, $params, $authorId, $excludeUserId) {
            /** @var Procedure $started */
            $started = $template->replicate(['template_id']);
            $started->name        = $params['name'] ?? $template->name;
            $started->started_at  = $params['started_at'] ?? now();
            $started->ended_at    = null;
            $started->author_id   = $authorId ?? $template->author_id;
            $started->template_id = $template->template_id ?? optional($template->template)->id;
            $started->save();

            $exclude = $excludeUserId ? \App\Models\User::find($excludeUserId) : null;

            foreach ($template->steps()->whereNull('parent')->get() as $rootStep) {
                $this->replicateStepTree($rootStep, $started, null, $exclude);
            }

            return $started->fresh();
        });
    }

    /**
     * Repliziert einen Schritt (mit Kindern) in einen gestarteten Prozess.
     * Setzt `endDate` korrekt für die erste Ebene, weist Position-Mitglieder zu
     * und versendet die Mails.
     */
    private function replicateStepTree(Procedure_Step $source, Procedure $target, ?int $parentId, ?\App\Models\User $exclude): Procedure_Step
    {
        $new = $source->replicate(['done', 'completed_at', 'completed_by', 'endDate']);
        $new->procedure_id     = $target->id;
        $new->parent           = $parentId;
        $new->template_step_id = $source->id;
        $new->done             = false;
        $new->completed_at     = null;
        $new->completed_by     = null;

        if (!$parentId) {
            $start = $target->started_at ? Carbon::parse($target->started_at) : now();
            $new->endDate = $start->copy()->addDays((int) ($source->durationDays ?? 0));
        } else {
            $new->endDate = null;
        }
        $new->save();

        if ($source->position) {
            $users = $source->position->users;
            if ($exclude && $users->contains('id', $exclude->id)) {
                $new->users()->attach($exclude->id);
            } else {
                $new->users()->attach($users->pluck('id')->all());
                if (!$parentId) {
                    // Mails nur für Wurzelschritte – Kinder erhalten Mail erst beim Erledigen des Parents.
                    $new->load('users', 'procedure');
                    $this->notifications->notifyStepAssigned($new, $exclude);
                }
            }
        }

        foreach ($source->childs as $child) {
            $this->replicateStepTree($child, $target, $new->id, $exclude);
        }

        return $new;
    }

    /**
     * Beendet einen laufenden Prozess vorzeitig.
     */
    public function endProcedure(Procedure $procedure, ?string $reason = null): void
    {
        DB::transaction(function () use ($procedure, $reason) {
            $procedure->steps()->where('done', false)->update([
                'done'         => true,
                'completed_at' => now(),
            ]);
            $procedure->update([
                'ended_at'     => now(),
                'ended_reason' => $reason,
            ]);
        });
    }

    /**
     * Dupliziert eine Vorlage (§B-05): Kopiert Vorlagen-Datensatz + alle Schritte rekursiv.
     * Schreibt sowohl in `procedure_templates` als auch in `procedures` (Legacy-Bridge).
     *
     * @return array{template: ProcedureTemplate, legacy: Procedure}
     */
    public function cloneTemplate(Procedure $legacy, ?int $authorId = null): array
    {
        return DB::transaction(function () use ($legacy, $authorId) {
            // Legacy-Eintrag kopieren
            $legacyClone = $legacy->replicate(['template_id', 'started_at', 'ended_at', 'ended_reason']);
            $legacyClone->name      = $legacy->name . ' (Kopie)';
            $legacyClone->author_id = $authorId ?? $legacy->author_id;
            $legacyClone->started_at  = null;
            $legacyClone->ended_at    = null;
            $legacyClone->ended_reason = null;
            $legacyClone->save();

            // Neuen Vorlagen-Datensatz (procedure_templates) anlegen
            $originalTemplate = $legacy->template_id
                ? ProcedureTemplate::find($legacy->template_id)
                : null;

            $templateClone = ProcedureTemplate::create([
                'name'                => $legacyClone->name,
                'description'         => $legacyClone->description,
                'category_id'         => $legacyClone->category_id,
                'author_id'           => $legacyClone->author_id,
                'color'               => $originalTemplate?->color,
                'legacy_procedure_id' => $legacyClone->id,
            ]);

            $legacyClone->update(['template_id' => $templateClone->id]);

            // Schritte rekursiv kopieren (Legacy-Schritte)
            foreach ($legacy->steps()->whereNull('parent')->orderBy('sort_order')->orderBy('id')->get() as $rootStep) {
                $this->cloneStepTree($rootStep, $legacyClone->id, null);
            }

            return ['template' => $templateClone, 'legacy' => $legacyClone];
        });
    }

    /**
     * Kopiert einen Step-Baum in einen anderen Prozess (für cloneTemplate).
     */
    private function cloneStepTree(Procedure_Step $source, int $targetProcedureId, ?int $newParentId): Procedure_Step
    {
        $copy = $source->replicate(['done', 'completed_at', 'completed_by', 'endDate', 'template_step_id', 'procedure_id', 'parent']);
        $copy->procedure_id    = $targetProcedureId;
        $copy->parent          = $newParentId;
        $copy->template_step_id = null;
        $copy->done             = false;
        $copy->completed_at     = null;
        $copy->completed_by     = null;
        $copy->endDate          = null;
        $copy->save();

        foreach ($source->childs()->orderBy('sort_order')->orderBy('id')->get() as $child) {
            $this->cloneStepTree($child, $targetProcedureId, $copy->id);
        }

        return $copy;
    }

    /**
     * Löscht eine Vorlage (Legacy + neue Tabelle) per Soft-Delete.
     */
    public function deleteTemplate(Procedure $template): void
    {
        DB::transaction(function () use ($template) {
            if ($template->template_id) {
                ProcedureTemplate::where('id', $template->template_id)->delete();
            }
            $template->delete();
        });
    }
}

