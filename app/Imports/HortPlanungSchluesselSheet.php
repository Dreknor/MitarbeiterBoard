<?php

namespace App\Imports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\ToCollection;

/**
 * Parst das „Schlüssel"-Sheet der Excel-Vorlage.
 *
 * Erwartete Struktur (analog Konzept §2.2):
 *   Spalte A – Bezeichnung des Schlüssels
 *   Spalte B – Wert
 *   Bekannte Schlüssel: Kinderschlüssel, Leitung, Vorbereitung, Mentor
 */
class HortPlanungSchluesselSheet implements ToCollection
{
    /** @var array<string, float>  kuerzel → wert */
    private array $schluessel = [];

    public function collection(Collection $rows): void
    {
        foreach ($rows as $row) {
            $bezeichnung = strtolower(trim((string) ($row[0] ?? '')));
            $wert        = $row[1] ?? null;

            if (empty($bezeichnung) || !is_numeric($wert)) {
                continue;
            }

            $wert = (float) $wert;

            if (str_contains($bezeichnung, 'kind')) {
                $this->schluessel['kinderschluessel'] = $wert;
            } elseif (str_contains($bezeichnung, 'leit')) {
                $this->schluessel['leitung'] = $wert;
            } elseif (str_contains($bezeichnung, 'vorber')) {
                $this->schluessel['vorbereitung'] = $wert;
            } elseif (str_contains($bezeichnung, 'anpass')) {
                $this->schluessel['anpassung'] = $wert;
            } elseif (str_contains($bezeichnung, 'mentor')) {
                $this->schluessel['mentor'] = $wert;
            } else {
                // Beliebige weitere Schlüssel
                $kuerzel = preg_replace('/[^a-z0-9_]/', '_', $bezeichnung);
                $this->schluessel[$kuerzel] = $wert;
            }
        }
    }

    public function getSchluessel(): array
    {
        return $this->schluessel;
    }
}

