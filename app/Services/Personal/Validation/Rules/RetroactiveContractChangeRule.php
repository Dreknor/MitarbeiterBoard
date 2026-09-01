<?php

namespace App\Services\Personal\Validation\Rules;

use App\Enums\AnomalyRuleType;
use App\Enums\AnomalySeverity;
use App\Models\personal\ContractAudit;
use App\Models\User;
use App\Services\Personal\Validation\ValidationRuleInterface;
use Carbon\Carbon;
use Illuminate\Support\Collection;

/**
 * RETROACTIVE_CONTRACT_CHANGE (Arbeitspaket 3.2): Erkennt, wenn Verträge
 * rückwirkend für bereits abgezeichnete Prüfzeiträume geändert wurden.
 * Die betroffenen Zeiträume werden (über EmploymentObserver + Timesheet::markRequiresReview)
 * als "Prüfung erforderlich" markiert. Severity: HIGH.
 */
class RetroactiveContractChangeRule implements ValidationRuleInterface
{
    public function ruleType(): AnomalyRuleType
    {
        return AnomalyRuleType::RetroactiveContractChange;
    }

    public function check(User $employe, Carbon $periodStart, Carbon $periodEnd): Collection
    {
        $anomalies = new Collection();

        $audits = ContractAudit::where('employe_id', $employe->id)
            ->where('is_retroactive', true)
            ->where(function ($q) use ($periodStart, $periodEnd) {
                $q->whereBetween('affected_period_start', [$periodStart->toDateString(), $periodEnd->toDateString()])
                  ->orWhereBetween('affected_period_end', [$periodStart->toDateString(), $periodEnd->toDateString()])
                  ->orWhere(function ($qq) use ($periodStart, $periodEnd) {
                      $qq->where('affected_period_start', '<=', $periodStart->toDateString())
                         ->where('affected_period_end', '>=', $periodEnd->toDateString());
                  });
            })
            ->get();

        foreach ($audits as $audit) {
            $anomalies->push([
                'date'        => $audit->valid_from->format('Y-m-d'),
                'severity'    => AnomalySeverity::High,
                'description' => sprintf(
                    'Rückwirkende Vertragsänderung erkannt: Vertrag wurde am %s nachträglich geändert und betrifft den bereits geprüften Zeitraum %s – %s. Erneute Prüfung erforderlich.',
                    $audit->updated_at->format('d.m.Y'),
                    optional($audit->affected_period_start)->format('d.m.Y'),
                    optional($audit->affected_period_end)->format('d.m.Y')
                ),
                'context' => [
                    'contract_audit_id'      => $audit->id,
                    'affected_period_start'  => optional($audit->affected_period_start)->format('Y-m-d'),
                    'affected_period_end'    => optional($audit->affected_period_end)->format('Y-m-d'),
                ],
                'related_employment_id' => $audit->employment_id,
            ]);
        }

        return $anomalies;
    }
}

