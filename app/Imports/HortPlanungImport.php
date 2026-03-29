<?php

namespace App\Imports;

use Maatwebsite\Excel\Concerns\WithMultipleSheets;

/**
 * Haupt-Import-Klasse für die Hortstunden-Planung.
 *
 * Verarbeitet bis zu 3 Sheets:
 *   „Planung"         → HortPlanungPlanungSheet
 *   „Schlüssel"       → HortPlanungSchluesselSheet
 *   „Grundarbeitszeit"→ optional (wird ignoriert)
 *
 * Verwendung (2-Schritt-Prozess, Konzept §8.1):
 *   $import = new HortPlanungImport();
 *   Excel::import($import, $pfad);
 *   $names  = $import->planungSheet->getPersonNamen();
 *   $monate = $import->planungSheet->getMonate();
 */
class HortPlanungImport implements WithMultipleSheets
{
    public HortPlanungPlanungSheet $planungSheet;
    public HortPlanungSchluesselSheet $schluesselSheet;

    public function __construct()
    {
        $this->planungSheet    = new HortPlanungPlanungSheet();
        $this->schluesselSheet = new HortPlanungSchluesselSheet();
    }

    /**
     * Sheet-Mapping nach Index (0-basiert), damit auch Dateien
     * mit leicht abweichenden Sheet-Namen funktionieren.
     */
    public function sheets(): array
    {
        return [
            0 => $this->planungSheet,
            1 => $this->schluesselSheet,
        ];
    }
}

