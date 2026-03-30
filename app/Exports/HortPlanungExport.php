<?php

namespace App\Exports;

use App\Exports\Sheets\HortPlanungGrundarbeitszeitSheet;
use App\Exports\Sheets\HortPlanungPlanungSheet;
use App\Exports\Sheets\HortPlanungRueckblickSheet;
use App\Exports\Sheets\HortPlanungSchluesselSheet;
use App\Exports\Sheets\HortPlanungVertragsaenderungenSheet;
use App\Models\personal\HortPlanung;
use App\Services\HortPlanungService;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;

/**
 * Haupt-Export-Klasse für die Hortstunden-Planung.
 *
 * Erstellt eine Excel-Datei mit bis zu 4 Sheets:
 *   1. „Planung"          – Horizontale Matrix (Kern-Sheet)
 *   2. „Schlüssel"        – Faktor-Definitionen + Werte
 *   3. „Grundarbeitszeit" – VZ-Aufschlüsselung pro Person
 *   4. „Rückblick"        – Soll/Vertrag/Ist vergangener Monate
 *
 * Verwendung im Controller:
 *   return Excel::download(new HortPlanungExport($planung, $service), 'Planung.xlsx');
 */
class HortPlanungExport implements WithMultipleSheets
{
    public function __construct(
        protected HortPlanung $planung,
        protected HortPlanungService $service
    ) {
        // Eager Load für alle Sheets
        $this->planung->load([
            'faktoren.werte',
            'zusatzstundenTypen',
            'monate.personen.user',
            'monate.monatZusatzstunden.typ',
            'department',
        ]);
    }

    public function sheets(): array
    {
        return [
            new HortPlanungPlanungSheet($this->planung, $this->service),
            new HortPlanungSchluesselSheet($this->planung),
            new HortPlanungGrundarbeitszeitSheet($this->planung, $this->service),
            new HortPlanungRueckblickSheet($this->planung, $this->service),
            new HortPlanungVertragsaenderungenSheet($this->planung, $this->service),
        ];
    }
}

