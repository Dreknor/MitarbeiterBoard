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
 * CONTRACT_CHANGE_IN_PERIOD (Arbeitspaket 3.1): Identifiziert, ob im Prüfzeitraum
 * eine Vertragsanpassung (Stundenerhöhung/-reduzierung, Wechsel des Zeitmodells,
 * neue Anstellung, Statuswechsel) in Kraft getreten ist. Severity: WARNING.
 */
class ContractChangeInPeriodRule implements ValidationRuleInterface
{
    public function ruleType(): AnomalyRuleType
    {
        return AnomalyRuleType::ContractChangeInPeriod;
    }

    public function check(User $employe, Carbon $periodStart, Carbon $periodEnd): Collection
    {
        $anomalies = new Collection();

        $audits = ContractAudit::where('employe_id', $employe->id)
            ->where(function ($q) use ($periodStart, $periodEnd) {
                // Neue Vertragsversion, die im Prüfzeitraum in Kraft tritt ...
                $q->whereBetween('valid_from', [$periodStart->toDateString(), $periodEnd->toDateString()])
                  // ... oder eine im Prüfzeitraum vorgenommene Direkt-Änderung (z. B. Stundenanpassung ohne neue Version).
                  ->orWhereBetween('created_at', [$periodStart->startOfDay(), $periodEnd->endOfDay()]);
            })
            ->orderBy('valid_from')
            ->get();

        foreach ($audits as $audit) {
            $changed = collect($audit->changed_fields ?? [])
                ->map(function ($change, $field) {
                    $old = $change['old'] ?? '—';
                    $new = $change['new'] ?? '—';
                    return "{$field}: {$old} → {$new}";
                })
                ->implode('; ');

            // Effektives Datum: Beginn der neuen Vertragsversion, falls im Zeitraum,
            // sonst der Zeitpunkt der Direkt-Änderung (in-place Update ohne neue Version).
            $effectiveDate = $audit->valid_from->between($periodStart, $periodEnd)
                ? $audit->valid_from
                : $audit->created_at->copy()->startOfDay();

            $description = sprintf(
                'Vertragsanpassung zum %s: %s',
                $effectiveDate->format('d.m.Y'),
                $changed !== '' ? $changed : ($audit->action === 'created' ? 'Neue Anstellung' : 'Anstellung geändert')
            );

            $anomalies->push([
                'date'                   => $effectiveDate->format('Y-m-d'),
                'severity'               => AnomalySeverity::Warning,
                'description'            => $description,
                'context'                => [
                    'contract_audit_id' => $audit->id,
                    'action'            => $audit->action,
                    'changed_fields'    => $audit->changed_fields,
                    'hours'             => $audit->hours,
                ],
                'related_employment_id'  => $audit->employment_id,
            ]);
        }

        return $anomalies;
    }
}




