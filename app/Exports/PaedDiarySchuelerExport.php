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
use Maatwebsite\Excel\Concerns\WithCustomValueBinder;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Cell\StringValueBinder;
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

/**
 * Blatt „Einträge“: eine Zeile je Tag (bzw. je Eintrag), danach die Zusatzspalten nach Spaltengruppen.
 * Kopfzeilen und Daten werden aus derselben Spaltenliste erzeugt; Zellwerte werden als Text
 * geschrieben (StringValueBinder), damit Notizen wie „=…“ nicht als Formel ausgeführt werden.
 */
class SchuelerEntriesSheet extends StringValueBinder implements FromArray, WithHeadings, WithStyles, WithColumnWidths, WithTitle, WithCustomValueBinder
{
    /** Feste Spalten vor den Zusatzspalten: Überschrift => Breite */
    private const FESTE_SPALTEN = [
        'Datum' => 22,
        'Kategorie' => 20,
        'Notizen' => 50,
        'Autor' => 18,
        'Status' => 12,
        'Vertraulich' => 12,
    ];

    protected $schueler;
    protected $entries;
    protected $columns;
    protected $sortedColumns; // Spalten sortiert nach Kategorien
    protected $columnsByCategory; // Gruppierung nach Kategorien
    protected $columnValues;
    protected $dateFrom;
    protected $dateTo;
    private ?array $rows = null;

    public function __construct(Schueler $schueler, $entries, $columns, $columnValues, Carbon $dateFrom, Carbon $dateTo)
    {
        $this->schueler = $schueler;
        $this->entries = $entries;
        $this->columns = $columns;
        $this->columnValues = $columnValues;
        $this->dateFrom = $dateFrom;
        $this->dateTo = $dateTo;

        $this->groupAndSortColumns();
    }

    protected function groupAndSortColumns()
    {
        $grouped = [];
        foreach ($this->columns as $column) {
            $category = $column['category'] ?? null ?: 'Unkategorisiert';
            $grouped[$category][] = $column;
        }

        // Sortiere Kategorien (Unkategorisiert am Ende)
        uksort($grouped, function ($a, $b) {
            if ($a === 'Unkategorisiert') return 1;
            if ($b === 'Unkategorisiert') return -1;
            return strcoll($a, $b);
        });

        $this->columnsByCategory = $grouped;
        $this->sortedColumns = array_merge(...array_values($grouped ?: [[]]));
    }

    /** Spaltenbuchstabe zu einer 1-basierten Spaltennummer (auch jenseits von Z). */
    private function spalte(int $index): string
    {
        return Coordinate::stringFromColumnIndex($index);
    }

    public function array(): array
    {
        if ($this->rows !== null) {
            return $this->rows;
        }

        // Gruppierte Einträge nach ihrem Startdatum indexieren
        $entriesByStartDate = collect($this->entries)->groupBy(fn ($entry) => $entry['date_from'] ?? $entry['date']);

        $data = [];
        $period = new \DatePeriod($this->dateFrom->copy()->startOfDay(), new \DateInterval('P1D'), $this->dateTo->copy()->startOfDay()->addDay());

        foreach ($period as $date) {
            $dateString = $date->format('Y-m-d');
            $dayEntries = $entriesByStartDate->get($dateString, collect())->values();
            $werte = $this->columnValuesFor($dateString);

            if ($dayEntries->isEmpty()) {
                // Tage ohne (startende) Einträge – nur Spaltenwerte
                $data[] = array_merge([$date->format('d.m.Y'), '', '', '', '', ''], $werte);
                continue;
            }

            foreach ($dayEntries as $index => $entry) {
                $from = $entry['date_from'] ?? $entry['date'];
                $to = $entry['date_to'] ?? $entry['date'];
                $datumLabel = $from !== $to
                    ? Carbon::parse($from)->format('d.m.Y') . ' – ' . Carbon::parse($to)->format('d.m.Y')
                    : $date->format('d.m.Y');

                $data[] = array_merge([
                    $datumLabel,
                    $entry['category'] ?? '',
                    $entry['content'] ?? '',
                    $entry['user'] ?? '',
                    !empty($entry['completed_at']) ? 'Erledigt' : 'Offen',
                    !empty($entry['dossier_only']) ? 'Ja' : '',
                ], $index === 0 ? $werte : array_fill(0, count($this->sortedColumns), ''));
            }
        }

        return $this->rows = $data;
    }

    /**
     * Werte der Zusatzspalten für ein Datum (in der Reihenfolge von sortedColumns).
     */
    protected function columnValuesFor(string $dateString): array
    {
        $dayValues = $this->columnValues->get($dateString) ?? $this->columnValues->get($dateString . ' 00:00:00');

        return array_map(function ($column) use ($dayValues) {
            $value = $dayValues && $dayValues->has($column['id']) ? ($dayValues->get($column['id'])->value ?? '') : '';

            return $this->formatColumnValue($column['type'] ?? 'text', $value);
        }, $this->sortedColumns);
    }

    /**
     * Formatiert einen Spaltenwert für die Excel-Ausgabe abhängig vom Spaltentyp.
     */
    protected function formatColumnValue(string $type, $value): string
    {
        if ($type === 'boolean') {
            $isTrue = ($value === '1' || $value === 1 || $value === true || $value === 'true');
            return $isTrue ? 'Ja' : 'Nein';
        }
        if ($type === 'ampel') {
            if ($value === '1') return 'Ja';
            if ($value === '2') return 'In Bearbeitung';
            if ($value === '3') return 'Nein';
            return 'Unbeantwortet';
        }
        return (string) $value;
    }

    public function headings(): array
    {
        return array_merge(array_keys(self::FESTE_SPALTEN), array_map(fn ($c) => $c['name'], $this->sortedColumns));
    }

    public function title(): string
    {
        // Excel erlaubt max. 31 Zeichen im Blattnamen
        return mb_substr('Einträge ' . $this->schueler->vorname . ' ' . $this->schueler->nachname, 0, 31);
    }

    public function columnWidths(): array
    {
        $widths = [];
        $i = 1;
        foreach (self::FESTE_SPALTEN as $breite) {
            $widths[$this->spalte($i++)] = $breite;
        }
        foreach ($this->sortedColumns as $_) {
            $widths[$this->spalte($i++)] = 12;
        }

        return $widths;
    }

    public function styles(Worksheet $sheet)
    {
        $fest = count(self::FESTE_SPALTEN);
        $lastColumn = $this->spalte($fest + count($this->sortedColumns));

        // Titel-Zeilen (1–3)
        $sheet->insertNewRowBefore(1, 3);
        $sheet->setCellValue('A1', 'Pädagogisches Tagebuch');
        $stageName = $this->schueler->grading_stage?->name ?? '-';
        $sheet->setCellValue('A2', $this->schueler->vorname . ' ' . $this->schueler->nachname . ' (' . ($this->schueler->klasse?->name ?? '-') . ') - Stufe: ' . $stageName);
        $sheet->setCellValue('A3', 'Zeitraum: ' . $this->dateFrom->format('d.m.Y') . ' - ' . $this->dateTo->format('d.m.Y'));
        $sheet->getStyle('A1:A3')->applyFromArray(['font' => ['bold' => true, 'size' => 14]]);
        $sheet->getStyle('A1')->applyFromArray(['font' => ['size' => 16]]);

        // Zeile 4: Spaltengruppen; Zeile 5: Spaltenüberschriften (von headings())
        $sheet->insertNewRowBefore(4, 1);
        $i = 1;
        foreach (array_keys(self::FESTE_SPALTEN) as $titel) {
            $col = $this->spalte($i++);
            $sheet->mergeCells("{$col}4:{$col}5");
            $sheet->setCellValue("{$col}4", $titel);
        }

        $categoryBoundaries = [$this->spalte($fest)];
        foreach ($this->columnsByCategory as $category => $columns) {
            $startCol = $this->spalte($i);
            $endCol = $this->spalte($i + count($columns) - 1);
            if (count($columns) > 1) {
                $sheet->mergeCells("{$startCol}4:{$endCol}4");
            }
            $sheet->setCellValue("{$startCol}4", $category);
            $categoryBoundaries[] = $endCol;
            $i += count($columns);
        }

        $kopf = fn (string $farbe) => [
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => $farbe]],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
            'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'FFFFFF']]],
        ];
        $sheet->getStyle("A4:{$lastColumn}4")->applyFromArray($kopf('2C5F8D'));
        $sheet->getStyle("A5:{$lastColumn}5")->applyFromArray($kopf('4A90E2'));

        $lastRow = count($this->array()) + 5;
        if ($lastRow > 5) {
            $sheet->getStyle("A6:{$lastColumn}{$lastRow}")->applyFromArray([
                'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'CCCCCC']]],
                'alignment' => ['vertical' => Alignment::VERTICAL_TOP, 'wrapText' => true],
            ]);
            $sheet->getStyle("A6:A{$lastRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        }

        // Optische Trennung zwischen festen Spalten und Spaltengruppen
        foreach (array_slice($categoryBoundaries, 0, -1) as $boundaryCol) {
            $sheet->getStyle("{$boundaryCol}4:{$boundaryCol}{$lastRow}")->applyFromArray([
                'borders' => ['right' => ['borderStyle' => Border::BORDER_MEDIUM, 'color' => ['rgb' => 'CCCCCC']]],
            ]);
        }

        return [];
    }
}

class SchuelerTasksSheet extends StringValueBinder implements FromArray, WithHeadings, WithStyles, WithColumnWidths, WithTitle, WithCustomValueBinder
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

class SchuelerGradingDocumentationSheet extends StringValueBinder implements FromArray, WithHeadings, WithStyles, WithColumnWidths, WithTitle, WithCustomValueBinder
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

