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
    protected $gradingSessions;
    protected $dateFrom;
    protected $dateTo;

    public function __construct(Schueler $schueler, $entries, $columns, $columnValues, $tasks, $gradingSessions, Carbon $dateFrom, Carbon $dateTo)
    {
        $this->schueler = $schueler;
        $this->entries = $entries;
        $this->columns = $columns;
        $this->columnValues = $columnValues;
        $this->tasks = $tasks;
        $this->gradingSessions = $gradingSessions;
        $this->dateFrom = $dateFrom;
        $this->dateTo = $dateTo;
    }

    public function sheets(): array
    {
        return [
            new SchuelerEntriesSheet($this->schueler, $this->entries, $this->columns, $this->columnValues, $this->dateFrom, $this->dateTo),
            new SchuelerTasksSheet($this->schueler, $this->tasks, $this->dateFrom, $this->dateTo),
            new SchuelerGradingDocumentationSheet($this->schueler, $this->gradingSessions, $this->dateFrom, $this->dateTo),
        ];
    }
}

class SchuelerEntriesSheet implements FromArray, WithHeadings, WithStyles, WithColumnWidths, WithTitle
{
    protected $schueler;
    protected $entries;
    protected $columns;
    protected $sortedColumns; // Spalten sortiert nach Kategorien
    protected $columnsByCategory; // Gruppierung nach Kategorien
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

        // Gruppiere Spalten nach Kategorien und sortiere
        $this->groupAndSortColumns();
    }

    protected function groupAndSortColumns()
    {
        $grouped = [];
        foreach ($this->columns as $column) {
            $category = $column['category'] ?? 'Unkategorisiert';
            if (!isset($grouped[$category])) {
                $grouped[$category] = [];
            }
            $grouped[$category][] = $column;
        }

        // Sortiere Kategorien (Unkategorisiert am Ende)
        uksort($grouped, function($a, $b) {
            if ($a === 'Unkategorisiert') return 1;
            if ($b === 'Unkategorisiert') return -1;
            return strcoll($a, $b);
        });

        $this->columnsByCategory = $grouped;

        // Erstelle flaches Array mit sortierten Spalten
        $this->sortedColumns = [];
        foreach ($grouped as $columns) {
            foreach ($columns as $column) {
                $this->sortedColumns[] = $column;
            }
        }
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

                // Spalten-Werte hinzufügen (verwende sortierte Spalten)
                foreach ($this->sortedColumns as $column) {
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
                        foreach ($this->sortedColumns as $column) {
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
                        foreach ($this->sortedColumns as $column) {
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

        foreach ($this->sortedColumns as $column) {
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
        foreach ($this->sortedColumns as $column) {
            $widths[$letter] = 12;
            $letter++;
        }

        return $widths;
    }

    public function styles(Worksheet $sheet)
    {
        // Titel-Zeile hinzufügen (3 Zeilen)
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

        // Kategorien-Header-Zeile hinzufügen (jetzt in Zeile 4)
        $sheet->insertNewRowBefore(4, 1);

        // "Datum" Zelle über beide Header-Zeilen mergen
        $sheet->mergeCells('A4:A5');
        $sheet->setCellValue('A4', 'Datum');
        $sheet->mergeCells('B4:B5');
        $sheet->setCellValue('B4', 'Notizen');
        $sheet->mergeCells('C4:C5');
        $sheet->setCellValue('C4', 'Autor');

        // Kategorien-Header mit Merges für Spalten-Gruppen
        $currentColumn = 68; // ASCII für 'D'
        $categoryBoundaries = []; // Speichere Grenzen zwischen Kategorien

        foreach ($this->columnsByCategory as $category => $columns) {
            $startCol = chr($currentColumn);
            $endCol = chr($currentColumn + count($columns) - 1);

            // Merge cells für Kategorie
            if (count($columns) > 1) {
                $sheet->mergeCells("{$startCol}4:{$endCol}4");
            }
            $sheet->setCellValue("{$startCol}4", $category);

            // Speichere die rechte Grenze dieser Kategorie (außer für die letzte)
            if ($currentColumn > 68) {
                $categoryBoundaries[] = chr($currentColumn - 1);
            }

            $currentColumn += count($columns);
        }

        // Kategorie-Header-Styling (Zeile 4)
        $lastColumn = chr(67 + count($this->sortedColumns)); // C + Anzahl Spalten
        $sheet->getStyle("A4:{$lastColumn}4")->applyFromArray([
            'font' => [
                'bold' => true,
                'color' => ['rgb' => 'FFFFFF'],
            ],
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'startColor' => ['rgb' => '2C5F8D'],
            ],
            'alignment' => [
                'horizontal' => Alignment::HORIZONTAL_CENTER,
                'vertical' => Alignment::VERTICAL_CENTER,
            ],
            'borders' => [
                'allBorders' => [
                    'borderStyle' => Border::BORDER_THIN,
                    'color' => ['rgb' => 'FFFFFF'],
                ],
            ],
        ]);

        // Spalten-Header-Styling (Zeile 5)
        $sheet->getStyle("A5:{$lastColumn}5")->applyFromArray([
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
                    'color' => ['rgb' => 'FFFFFF'], // Weiß statt Schwarz
                ],
            ],
        ]);

        // Daten-Styling
        $lastRow = count($this->array()) + 5; // +5 wegen Titel (3) + Kategorie-Header (1) + Spalten-Header (1)
        if ($lastRow > 5) {
            $sheet->getStyle("A6:{$lastColumn}{$lastRow}")->applyFromArray([
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
            $sheet->getStyle("A6:A{$lastRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        }

        // Optische Trennung zwischen Kategorien: Dickere vertikale Borders
        foreach ($categoryBoundaries as $boundaryCol) {
            // Trennlinie von Zeile 4 bis zur letzten Datenzeile
            $sheet->getStyle("{$boundaryCol}4:{$boundaryCol}{$lastRow}")->applyFromArray([
                'borders' => [
                    'right' => [
                        'borderStyle' => Border::BORDER_MEDIUM,
                        'color' => ['rgb' => 'CCCCCC'], // Hellgrau statt Dunkelblau
                    ],
                ],
            ]);
        }

        // Auch die Trennung zwischen "Autor" und der ersten Spalte
        if (count($this->sortedColumns) > 0) {
            $sheet->getStyle("C4:C{$lastRow}")->applyFromArray([
                'borders' => [
                    'right' => [
                        'borderStyle' => Border::BORDER_MEDIUM,
                        'color' => ['rgb' => 'CCCCCC'], // Hellgrau statt Dunkelblau
                    ],
                ],
            ]);
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

class SchuelerGradingDocumentationSheet implements FromArray, WithHeadings, WithStyles, WithColumnWidths, WithTitle
{
    protected $schueler;
    protected $gradingSessions;
    protected $dateFrom;
    protected $dateTo;

    public function __construct(Schueler $schueler, $gradingSessions, Carbon $dateFrom, Carbon $dateTo)
    {
        $this->schueler = $schueler;
        $this->gradingSessions = $gradingSessions;
        $this->dateFrom = $dateFrom;
        $this->dateTo = $dateTo;
    }

    public function array(): array
    {
        $data = [];

        foreach ($this->gradingSessions as $session) {
            $questions = $session->gradingSystem->questions ?? collect();

            foreach ($questions as $question) {
                // Finde Schüler-Antwort
                $studentAnswer = $session->studentAnswers->firstWhere('question_id', $question->id);

                // Finde Lehrer-Einschätzung
                $teacherAssessment = $session->teacherAssessments->firstWhere('question_id', $question->id);

                $row = [
                    'Datum' => $session->completed_at?->format('d.m.Y H:i') ?? '-',
                    'System' => $session->gradingSystem->name ?? '-',
                    'Typ' => $session->type === 'group' ? 'Gruppe' : 'Einzeln',
                    'Lehrer' => $session->user->name ?? '-',
                    'Frage' => $question->question,
                    'Selbsteinschätzung' => $studentAnswer ? $this->formatRating($studentAnswer->self_rating) : '-',
                    'Lehrereinschätzung' => $teacherAssessment ? $this->formatRating($teacherAssessment->teacher_rating) : '-',
                    'Kommentar' => $teacherAssessment?->comment ?? '',
                ];

                $data[] = $row;
            }
        }

        if (empty($data)) {
            // Wenn keine Daten vorhanden, füge eine leere Zeile hinzu
            $data[] = [
                'Datum' => '-',
                'System' => '-',
                'Typ' => '-',
                'Lehrer' => '-',
                'Frage' => 'Keine Dokumentation im ausgewählten Zeitraum',
                'Selbsteinschätzung' => '-',
                'Lehrereinschätzung' => '-',
                'Kommentar' => '',
            ];
        }

        return $data;
    }

    protected function formatRating($rating): string
    {
        if (is_null($rating)) {
            return '-';
        }

        $ratings = [
            1 => '1 - Trifft nicht zu',
            2 => '2 - Trifft eher nicht zu',
            3 => '3 - Teils/Teils',
            4 => '4 - Trifft eher zu',
            5 => '5 - Trifft voll zu',
        ];

        return $ratings[$rating] ?? (string)$rating;
    }

    public function headings(): array
    {
        return [
            'Datum',
            'System',
            'Typ',
            'Lehrer',
            'Frage',
            'Selbsteinschätzung',
            'Lehrereinschätzung',
            'Kommentar'
        ];
    }

    public function title(): string
    {
        return 'Graduierungsdokumentation';
    }

    public function columnWidths(): array
    {
        return [
            'A' => 18, // Datum
            'B' => 20, // System
            'C' => 10, // Typ
            'D' => 15, // Lehrer
            'E' => 40, // Frage
            'F' => 22, // Selbsteinschätzung
            'G' => 22, // Lehrereinschätzung
            'H' => 35, // Kommentar
        ];
    }

    public function styles(Worksheet $sheet)
    {
        // Titel-Zeile hinzufügen
        $sheet->insertNewRowBefore(1, 3);
        $stageName = $this->schueler->grading_stage?->name ?? '-';
        $sheet->setCellValue('A1', 'Graduierungsdokumentation');
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
        $sheet->getStyle('A4:H4')->applyFromArray([
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
            $sheet->getStyle("A5:H{$lastRow}")->applyFromArray([
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

            // Typ-Spalte zentrieren
            $sheet->getStyle("C5:C{$lastRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        }

        return [];
    }
}

