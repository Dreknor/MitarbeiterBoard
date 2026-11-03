<?php

namespace App\Services\Personal;

use App\Enums\ContractType;
use App\Models\personal\Employment;

/**
 * Validiert Befristungsketten gemäß § 14 TzBfG.
 *
 * Nur die sachgrundlose Befristung (§ 14 Abs. 2) ist begrenzt: höchstens 2 Jahre Gesamtdauer
 * und höchstens 3 Verlängerungen (= 4 aufeinanderfolgende Verträge), außerdem nur, wenn zuvor
 * kein Arbeitsverhältnis bei demselben Arbeitgeber bestand. Befristungen mit Sachgrund
 * (§ 14 Abs. 1) unterliegen diesen Grenzen nicht.
 */
class ContractValidationService
{
    private const MAX_MONTHS = 24;
    private const MAX_VERLAENGERUNGEN = 3;
    /** BAG-Rechtsprechung: Zuvorbeschäftigung ist nach 3 Jahren unschädlich. */
    private const KARENZ_JAHRE = 3;

    /**
     * @param int             $employeId  Mitarbeiter
     * @param int|null        $excludeId  Anstellung, die aus der DB-Abfrage ausgenommen wird (z. B. die gerade bearbeitete)
     * @param Employment|null $current    Die neue/geänderte Anstellung; wird immer in die Kette einbezogen
     */
    public function checkBefristungsketten(int $employeId, ?int $excludeId = null, ?Employment $current = null): array
    {
        $kette = Employment::where('employe_id', $employeId)
            ->where('contract_type', ContractType::Befristet->value)
            ->when($excludeId, fn ($q) => $q->where('id', '!=', $excludeId))
            ->get();

        if ($current && $current->contract_type === ContractType::Befristet) {
            $kette->push($current);
        }

        $totalMonths = (int) $kette->sum(function (Employment $e) {
            if (!$e->start || !$e->end) {
                return 0;
            }
            // Enddatum ist inklusive: 01.01.–31.12. = 12 Monate
            return $e->start->diffInMonths($e->end->copy()->addDay());
        });

        $verlaengerungen = max(0, $kette->count() - 1);

        $nachricht = null;
        if ($totalMonths > self::MAX_MONTHS) {
            $nachricht = "Achtung: Die sachgrundlose Befristung überschreitet 24 Monate (§ 14 Abs. 2 TzBfG). Gesamtdauer: {$totalMonths} Monate.";
        } elseif ($verlaengerungen > self::MAX_VERLAENGERUNGEN) {
            $nachricht = "Achtung: Mehr als 3 Verlängerungen einer sachgrundlosen Befristung (§ 14 Abs. 2 TzBfG). Verlängerungen: {$verlaengerungen}.";
        } elseif ($current && $this->hasZuvorbeschaeftigung($employeId, $excludeId, $current)) {
            $nachricht = 'Achtung: Für diese Person bestand bereits ein Arbeitsverhältnis (Zuvorbeschäftigungsverbot, § 14 Abs. 2 Satz 2 TzBfG). '
                . 'Eine sachgrundlose Befristung ist dann nur mit Sachgrund zulässig.';
        }

        return [
            'total_months'    => $totalMonths,
            'verlaengerungen' => $verlaengerungen,
            'warnung'         => $nachricht !== null,
            'nachricht'       => $nachricht,
        ];
    }

    private function hasZuvorbeschaeftigung(int $employeId, ?int $excludeId, Employment $current): bool
    {
        if ($current->contract_type !== ContractType::Befristet || !$current->start) {
            return false;
        }

        $grenze = $current->start->copy()->subYears(self::KARENZ_JAHRE);

        return Employment::where('employe_id', $employeId)
            ->when($excludeId, fn ($q) => $q->where('id', '!=', $excludeId))
            ->where('contract_type', '!=', ContractType::Befristet->value)
            ->where('start', '<', $current->start)
            ->where(fn ($q) => $q->whereNull('end')->orWhere('end', '>=', $grenze))
            ->exists();
    }
}
