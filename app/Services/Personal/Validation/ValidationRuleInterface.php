<?php

namespace App\Services\Personal\Validation;

use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Collection;

/**
 * Vertrag für entkoppelte Prüfregeln der Prüfengine (Pipeline-Muster, Arbeitspaket 2.2).
 * Jede Regel prüft einen Mitarbeiter für einen Zeitraum und liefert eine Collection
 * von Auffälligkeiten-Datensätzen (assoziative Arrays), die vom TimeValidationService
 * in `timesheet_anomalies` persistiert werden.
 */
interface ValidationRuleInterface
{
    /**
     * Eindeutiger Regel-Typ (App\Enums\AnomalyRuleType).
     */
    public function ruleType(): \App\Enums\AnomalyRuleType;

    /**
     * Führt die Prüfung für einen Mitarbeiter im angegebenen Zeitraum durch.
     *
     * @return Collection<int, array{
     *     date?: string|null,
     *     severity?: \App\Enums\AnomalySeverity,
     *     description: string,
     *     context?: array,
     *     related_employment_id?: int|null,
     * }>
     */
    public function check(User $employe, Carbon $periodStart, Carbon $periodEnd): Collection;
}

