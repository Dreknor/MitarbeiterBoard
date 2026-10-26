<?php

namespace App\Services\Personal;

use App\Models\User;
use Carbon\Carbon;

/**
 * Verwaltet DSGVO-Aufbewahrungsfristen ausgeschiedener Mitarbeiter (Löschkonzept, P5-02).
 *
 * Aufbewahrungsfristen laufen ab dem Ende des Kalenderjahres, in dem der Austritt lag.
 * Sperrfrist und Führungszeugnis-Frist laufen ab dem Austrittsdatum selbst.
 */
class PersonalRetentionService
{
    /**
     * Austrittsdatum als Beginn aller Fristen (Fallback: Datum der Deaktivierung).
     */
    public function leftAt(User $user): Carbon
    {
        if ($user->left_at) {
            return Carbon::parse($user->left_at)->startOfDay();
        }

        return Carbon::parse($user->deleted_at ?? now())->startOfDay();
    }

    public function retentionYears(string $category): int
    {
        return (int) config('user_deletion.retention.'.$category, config('user_deletion.retention.payroll'));
    }

    /**
     * Tag, ab dem Daten der Kategorie gelöscht werden dürfen.
     */
    public function expiresAt(User $user, string $category, ?int $years = null): Carbon
    {
        $years ??= $this->retentionYears($category);

        return $this->leftAt($user)->endOfYear()->addYears($years)->addDay()->startOfDay();
    }

    public function isExpired(User $user, string $category, ?Carbon $now = null, ?int $years = null): bool
    {
        return ($now ?? now())->greaterThanOrEqualTo($this->expiresAt($user, $category, $years));
    }

    /**
     * Ab wann das Konto anonymisiert werden darf (Sperrfrist nach Austritt).
     */
    public function anonymizeDueAt(User $user): Carbon
    {
        return $this->leftAt($user)->addMonths((int) config('user_deletion.anonymize_after_months'));
    }

    /**
     * Ab wann Daten zum Führungszeugnis gelöscht werden müssen (§ 72a Abs. 5 SGB VIII).
     */
    public function fuehrungszeugnisDueAt(User $user): Carbon
    {
        return $this->leftAt($user)->addMonths((int) config('user_deletion.fuehrungszeugnis_months'));
    }
}
