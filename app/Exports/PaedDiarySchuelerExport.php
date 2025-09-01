<?php

namespace App\Exports;

use App\Models\Schueler;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Font;

class PaedDiarySchuelerExport implements WithMultipleSheets
{
    protected $schueler;
    protected $entries;
    protected $columns;
    protected $columnValues;
    protected $tasks;
    protected $dateFrom;
    protected $dateTo;

    public function __construct(Schueler $schueler, $entries, $columns, $columnValues, $tasks, Carbon $dateFrom, Carbon $dateTo)
    {
        $this->schueler = $schueler;
        $this->entries = $entries;
        $this->columns = $columns;
        $this->columnValues = $columnValues;
        $this->tasks = $tasks;
        $this->dateFrom = $dateFrom;
        $this->dateTo = $dateTo;
    }

    public function sheets(): array
    {
        return [
            new SchuelerEntriesSheet($this->schueler, $this->entries, $this->columns, $this->columnValues, $this->dateFrom, $this->dateTo),
            new SchuelerTasksSheet($this->schueler, $this->tasks, $this->dateFrom, $this->dateTo),
        ];
    }
}

class SchuelerEntriesSheet implements FromArray, WithHeadings, WithStyles, WithColumnWidths, WithTitle
{
    protected $schueler;
    protected $entries;
    protected $columns;
    protected $columnValues;
    protected $dateFrom;
    protected $dateTo;

    public function __construct(Schueler $schueler, $entries, $columns, $columnValues, Carbon $dateFrom, Carbon $dateTo)
    {
        $this->schueler = $schueler;
        $this->entries = $entries;
        $this->columns = $columns;
        $this->columnValues = $columnValues;
        $this->dateFrom = $dateFrom;
        $this->dateTo = $dateTo;
    }

    public function array(): array
    {
        $data = [];

        // Gruppiere Einträge nach Datum
        $entriesByDate = $this->entries->groupBy('date');

        // Erstelle eine Zeile für jeden Tag im Zeitraum
        $period = new \DatePeriod(
            $this->dateFrom,
            new \DateInterval('P1D'),
            $this->dateTo->copy()->addDay()
        );

        foreach ($period as $date) {
            $dateString = $date->format('Y-m-d');
            $dayEntries = $entriesByDate->get($dateString, collect());

            if ($dayEntries->isEmpty()) {
                // Leere Zeile für Tage ohne Einträge
                $row = [
                    'Datum' => $date->format('d.m.Y'),
                    'Notizen' => '',
                    'Autor' => '',
                ];

                // Spalten-Werte hinzufügen
                foreach ($this->columns as $column) {
                    $columnId = $column['id'];
                    $columnName = $column['name'];

                    // Hole den Wert für diese Spalte an diesem Datum
                    $value = '';

                    // Suche sowohl nach reinem Datum als auch nach Datum mit Timestamp
                    $searchKeys = [
                        $dateString,  // "2025-08-20"
                        $dateString . ' 00:00:00'  // "2025-08-20 00:00:00"
                    ];

                    foreach ($searchKeys as $searchKey) {
                        if ($this->columnValues->has($searchKey)) {
                            $dayValues = $this->columnValues->get($searchKey);
                            if ($dayValues->has($columnId)) {
                                $valueModel = $dayValues->get($columnId);
                                $value = $valueModel->value ?? '';
                                break; // Gefunden, stoppe die Suche
                            }
                        }
                    }

                    $row[$columnName] = $value;
                }

                $data[] = $row;
            } else {
                // Eine Zeile pro Eintrag
                foreach ($dayEntries as $index => $entry) {
                    $row = [
                        'Datum' => $date->format('d.m.Y'),
                        'Notizen' => $entry['content'] ?? '',
                        'Autor' => $entry['user'] ?? '',
                    ];

                    // Spalten-Werte hinzufügen (nur bei erstem Eintrag des Tages)
                    if ($index === 0) {
                        foreach ($this->columns as $column) {
                            $columnId = $column['id'];
                            $columnName = $column['name'];

                            // Hole den Wert für diese Spalte an diesem Datum
                            $value = '';

                            // Suche sowohl nach reinem Datum als auch nach Datum mit Timestamp
                            $searchKeys = [
                                $dateString,  // "2025-08-20"
                                $dateString . ' 00:00:00'  // "2025-08-20 00:00:00"
                            ];

                            foreach ($searchKeys as $searchKey) {
                                if ($this->columnValues->has($searchKey)) {
                                    $dayValues = $this->columnValues->get($searchKey);
                                    if ($dayValues->has($columnId)) {
                                        $valueModel = $dayValues->get($columnId);
                                        $value = $valueModel->value ?? '';
                                        break; // Gefunden, stoppe die Suche
                                    }
                                }
                            }

                            $row[$columnName] = $value;
                        }
                    } else {
                        // Leere Spalten für weitere Einträge des gleichen Tages
                        foreach ($this->columns as $column) {
                            $row[$column['name']] = '';
                        }
                    }

                    $data[] = $row;
                }
            }
        }

        return $data;
    }

    public function headings(): array
    {
        $headings = ['Datum', 'Notizen', 'Autor'];

        foreach ($this->columns as $column) {
            $headings[] = $column['name'];
        }

        return $headings;
    }

    public function title(): string
    {
        return 'Einträge - ' . $this->schueler->vorname . ' ' . $this->schueler->nachname;
    }

    public function columnWidths(): array
    {
        $widths = [
            'A' => 12, // Datum
            'B' => 50, // Notizen
            'C' => 15, // Autor
        ];

        // Dynamische Breite für Spalten
        $letter = 'D';
        foreach ($this->columns as $column) {
            $widths[$letter] = 12;
            $letter++;
        }

        return $widths;
    }

    public function styles(Worksheet $sheet)
    {
        // Titel-Zeile hinzufügen
        $sheet->insertNewRowBefore(1, 3);
        $sheet->setCellValue('A1', 'Pädagogisches Tagebuch');
        $stageName = $this->schueler->grading_stage?->name ?? '-';
        $sheet->setCellValue('A2', $this->schueler->vorname . ' ' . $this->schueler->nachname . ' (' . $this->schueler->klasse->name . ') - Stufe: ' . $stageName);
        $sheet->setCellValue('A3', 'Zeitraum: ' . $this->dateFrom->format('d.m.Y') . ' - ' . $this->dateTo->format('d.m.Y'));

        // Titel-Styling
        $sheet->getStyle('A1:A3')->applyFromArray([
            'font' => [
                'bold' => true,
                'size' => 14,
            ],
        ]);

        $sheet->getStyle('A1')->applyFromArray([
            'font' => ['size' => 16],
        ]);

        // Header-Styling (jetzt in Zeile 4)
        $lastColumn = chr(67 + count($this->columns)); // C + Anzahl Spalten
        $sheet->getStyle("A4:{$lastColumn}4")->applyFromArray([
            'font' => [
                'bold' => true,
                'color' => ['rgb' => 'FFFFFF'],
            ],
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'startColor' => ['rgb' => '4A90E2'],
            ],
            'alignment' => [
                'horizontal' => Alignment::HORIZONTAL_CENTER,
                'vertical' => Alignment::VERTICAL_CENTER,
            ],
            'borders' => [
                'allBorders' => [
                    'borderStyle' => Border::BORDER_THIN,
                    'color' => ['rgb' => '000000'],
                ],
            ],
        ]);

        // Daten-Styling
        $lastRow = count($this->array()) + 4;
        if ($lastRow > 4) {
            $sheet->getStyle("A5:{$lastColumn}{$lastRow}")->applyFromArray([
                'borders' => [
                    'allBorders' => [
                        'borderStyle' => Border::BORDER_THIN,
                        'color' => ['rgb' => 'CCCCCC'],
                    ],
                ],
                'alignment' => [
                    'vertical' => Alignment::VERTICAL_TOP,
                    'wrapText' => true,
                ],
            ]);

            // Datum-Spalte zentrieren
            $sheet->getStyle("A5:A{$lastRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        }

        return [];
    }
}

class SchuelerTasksSheet implements FromArray, WithHeadings, WithStyles, WithColumnWidths, WithTitle
{
    protected $schueler;
    protected $tasks;
    protected $dateFrom;
    protected $dateTo;

    public function __construct(Schueler $schueler, $tasks, Carbon $dateFrom, Carbon $dateTo)
    {
        $this->schueler = $schueler;
        $this->tasks = $tasks;
        $this->dateFrom = $dateFrom;
        $this->dateTo = $dateTo;
    }

    public function array(): array
    {
        return $this->tasks->map(function ($task) {
            return [
                'Erstellt am' => $task['created_at'],
                'Titel' => $task['title'],
                'Beschreibung' => $task['description'] ?? '',
                'Fällig am' => $task['due_date'] ?? '',
                'Status' => $task['status'] === 'open' ? 'Offen' : 'Geschlossen',
                'Hervorgehoben' => $task['highlighted'] ? 'Ja' : 'Nein',
            ];
        })->toArray();
    }

    public function headings(): array
    {
        return [
            'Erstellt am',
            'Titel',
            'Beschreibung',
            'Fällig am',
            'Status',
            'Hervorgehoben'
        ];
    }

    public function title(): string
    {
        return 'Aufgaben - ' . $this->schueler->vorname . ' ' . $this->schueler->nachname;
    }

    public function columnWidths(): array
    {
        return [
            'A' => 15, // Erstellt am
            'B' => 25, // Titel
            'C' => 40, // Beschreibung
            'D' => 12, // Fällig am
            'E' => 12, // Status
            'F' => 15, // Hervorgehoben
        ];
    }

    public function styles(Worksheet $sheet)
    {
        // Titel-Zeile hinzufügen
        $sheet->insertNewRowBefore(1, 3);
        $stageName = $this->schueler->grading_stage?->name ?? '-';
        $sheet->setCellValue('A1', 'Aufgaben');
        $sheet->setCellValue('A2', $this->schueler->vorname . ' ' . $this->schueler->nachname . ' (' . $this->schueler->klasse->name . ') - Stufe: ' . $stageName);
        $sheet->setCellValue('A3', 'Zeitraum: ' . $this->dateFrom->format('d.m.Y') . ' - ' . $this->dateTo->format('d.m.Y'));

        // Titel-Styling
        $sheet->getStyle('A1:A3')->applyFromArray([
            'font' => [
                'bold' => true,
                'size' => 14,
            ],
        ]);

        $sheet->getStyle('A1')->applyFromArray([
            'font' => ['size' => 16],
        ]);

        // Header-Styling (jetzt in Zeile 4)
        $sheet->getStyle('A4:F4')->applyFromArray([
            'font' => [
                'bold' => true,
                'color' => ['rgb' => 'FFFFFF'],
            ],
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'startColor' => ['rgb' => '4A90E2'],
            ],
            'alignment' => [
                'horizontal' => Alignment::HORIZONTAL_CENTER,
                'vertical' => Alignment::VERTICAL_CENTER,
            ],
            'borders' => [
                'allBorders' => [
                    'borderStyle' => Border::BORDER_THIN,
                    'color' => ['rgb' => '000000'],
                ],
            ],
        ]);

        // Daten-Styling
        $lastRow = count($this->array()) + 4;
        if ($lastRow > 4) {
            $sheet->getStyle("A5:F{$lastRow}")->applyFromArray([
                'borders' => [
                    'allBorders' => [
                        'borderStyle' => Border::BORDER_THIN,
                        'color' => ['rgb' => 'CCCCCC'],
                    ],
                ],
                'alignment' => [
                    'vertical' => Alignment::VERTICAL_TOP,
                    'wrapText' => true,
                ],
            ]);
        }

        return [];
    }
}
