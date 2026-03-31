<?php

namespace App\Services\Personal;

use App\Models\personal\Employment;

/**
 * Validiert Befristungsketten gemäß § 14 TzBfG.
 */
class ContractValidationService
{
    /**
     * Prüft ob Befristungsketten-Warnung ausgelöst werden soll.
     * § 14 TzBfG: Max. 24 Monate Gesamtdauer, max. 3 Verlängerungen.
     */
    public function checkBefristungsketten(int $employeId, ?int $excludeId = null): array
    {
        $befristete = Employment::where('employe_id', $employeId)
            ->where(function ($q) {
                $q->where('contract_type', 'befristet')
                  ->orWhere('contract_type', 'befristet_sachgrund');
            })
            ->when($excludeId, fn($q) => $q->where('id', '!=', $excludeId))
            ->get();

        $totalMonths = $befristete->sum(function ($e) {
            $start = $e->start;
            $end   = $e->end ?? now();
            return (int) $start->diffInMonths($end);
        });

        $verlaengerungen = $befristete->count();

        $warnung  = $totalMonths > 24 || $verlaengerungen > 3;
        $nachricht = null;

        if ($totalMonths > 24) {
            $nachricht = "Achtung: Gesamtbefristungsdauer überschreitet 24 Monate (§ 14 TzBfG). Aktuelle Dauer: {$totalMonths} Monate.";
        } elseif ($verlaengerungen > 3) {
            $nachricht = "Achtung: Mehr als 3 Verlängerungen des befristeten Vertrags (§ 14 TzBfG). Anzahl: {$verlaengerungen}.";
        }

        return [
            'total_months'    => $totalMonths,
            'verlaengerungen' => $verlaengerungen,
            'warnung'         => $warnung,
            'nachricht'       => $nachricht,
        ];
    }
}

