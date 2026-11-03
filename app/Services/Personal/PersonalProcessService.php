<?php

namespace App\Services\Personal;

use App\Enums\EmploymentStatus;
use App\Enums\ProcedureLinkStatus;
use App\Enums\ProcedureLinkType;
use App\Enums\QualificationStatus;
use App\Models\personal\Employment;
use App\Models\personal\EmployeeQualification;
use App\Models\personal\ProcedureLink;
use App\Models\personal\QualificationType;
use App\Models\Procedure;
use App\Models\Setting;
use App\Services\Procedure\ProcedureService;
use Illuminate\Support\Facades\Log;

/**
 * Verbindet die Personalverwaltung mit dem Prozess-Modul:
 * Onboarding bei Ersteinstellung, Offboarding beim Ausscheiden, Rückmeldung erledigter Schritte.
 *
 * Welche Vorlage verwendet wird, steht in den Einstellungen (Modul "Personal").
 */
class PersonalProcessService
{
    public function __construct(private readonly ProcedureService $procedures) {}

    /**
     * Startet das Onboarding – nur bei einer echten Ersteinstellung
     * (keine weitere Anstellung, kein Nachfolge-/Wechselvertrag, noch kein Onboarding).
     */
    public function startOnboarding(Employment $employment): ?ProcedureLink
    {
        if ($employment->replaced_employment_id || $employment->is_internal_transfer || $employment->is_amendment) {
            return null;
        }

        $hasOthers = Employment::withTrashed()
            ->where('employe_id', $employment->employe_id)
            ->where('id', '!=', $employment->id)
            ->exists();
        $hadOnboarding = ProcedureLink::where('employe_id', $employment->employe_id)
            ->where('type', ProcedureLinkType::Onboarding->value)->exists();

        if ($hasOthers || $hadOnboarding) {
            return null;
        }

        return $this->start($employment, ProcedureLinkType::Onboarding, 'onboarding_template_id', 'Onboarding');
    }

    /**
     * Startet das Offboarding, wenn die Person keine weitere laufende oder künftige Anstellung hat.
     */
    public function startOffboarding(Employment $employment): ?ProcedureLink
    {
        if ($this->hasOtherOpenEmployment($employment)) {
            return null;
        }

        $running = ProcedureLink::where('employe_id', $employment->employe_id)
            ->where('type', ProcedureLinkType::Offboarding->value)
            ->where('status', ProcedureLinkStatus::Aktiv->value)->exists();
        if ($running) {
            return null;
        }

        return $this->start($employment, ProcedureLinkType::Offboarding, 'offboarding_template_id', 'Offboarding');
    }

    public function hasOtherOpenEmployment(Employment $employment): bool
    {
        return Employment::where('employe_id', $employment->employe_id)
            ->where('id', '!=', $employment->id)
            ->where('status', '!=', EmploymentStatus::Beendet->value)
            ->exists();
    }

    /**
     * Rückmeldung eines erledigten Prozessschritts:
     *  - schließt die Verknüpfung, wenn alle Schritte erledigt sind
     *  - Schrittname = Name einer Qualifikationsart → Qualifikation gilt als erworben
     */
    public function handleStepCompleted(int $procedureId, int $stepId, int $userId): void
    {
        $link = ProcedureLink::where('procedure_id', $procedureId)->first();
        if (!$link) {
            return;
        }

        $step = \App\Models\Procedure_Step::find($stepId);
        if ($step) {
            $type = QualificationType::whereRaw('lower(name) = ?', [mb_strtolower(trim($step->name))])->first();
            if ($type) {
                $this->markQualificationAcquired($link->employe_id, $type, $userId);
            }
        }

        $open = \App\Models\Procedure_Step::where('procedure_id', $procedureId)->where('done', false)->exists();
        if (!$open && $link->status === ProcedureLinkStatus::Aktiv) {
            $link->update(['status' => ProcedureLinkStatus::Abgeschlossen, 'completed_at' => now()]);
        }
    }

    private function markQualificationAcquired(int $employeId, QualificationType $type, int $userId): void
    {
        $qual = EmployeeQualification::firstOrNew(['employe_id' => $employeId, 'qualification_type_id' => $type->id]);
        if ($qual->exists && $qual->status !== QualificationStatus::Fehlend) {
            return;
        }

        $qual->fill([
            'acquired_date' => today(),
            'expiry_date'   => $type->validity_months ? today()->addMonths($type->validity_months) : null,
            'status'        => QualificationStatus::Gueltig,
            'verified_by'   => $userId ?: null,
            'verified_at'   => now(),
            'notes'         => 'Automatisch aus abgeschlossenem Prozessschritt übernommen.',
        ])->save();
    }

    private function start(Employment $employment, ProcedureLinkType $type, string $settingKey, string $label): ?ProcedureLink
    {
        $templateId = Setting::where('setting', $settingKey)->value('value');
        if ($templateId === null || $templateId === '') {
            return null;
        }

        $template = Procedure::vorlagen()->find((int) $templateId);
        if (!$template) {
            Log::warning("Personal: {$label}-Vorlage #{$templateId} nicht gefunden.");
            return null;
        }

        $employe = $employment->employe;
        $procedure = $this->procedures->startFromTemplate(
            $template,
            ['name' => "{$label}: {$employe->name}"],
            auth()->id() ?? $template->author_id
        );

        return ProcedureLink::create([
            'employe_id'    => $employe->id,
            'employment_id' => $employment->id,
            'procedure_id'  => $procedure->id,
            'type'          => $type,
            'status'        => ProcedureLinkStatus::Aktiv,
        ]);
    }
}
