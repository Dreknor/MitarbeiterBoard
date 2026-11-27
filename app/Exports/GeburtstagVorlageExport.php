<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;

/**
 * Beispieldatei für den Geburtstags-Import (Benutzerverwaltung).
 */
class GeburtstagVorlageExport implements FromArray, WithHeadings, ShouldAutoSize
{
    public function headings(): array
    {
        return ['E-Mail', 'Name', 'Geburtstag'];
    }

    public function array(): array
    {
        return [
            ['vorname.nachname@example.org', 'Vorname Nachname (optional)', '24.12.1980'],
        ];
    }
}
