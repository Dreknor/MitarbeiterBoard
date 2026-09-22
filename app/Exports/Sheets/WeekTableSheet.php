<?php

namespace App\Exports\Sheets;

use App\Models\Klasse;
use App\Models\PaedDiaryAppointment;
use App\Models\PaedDiaryEntry;
use App\Models\PaedDiarySchuelerAbsence;
use App\Models\PaedDiaryTask;
use App\Models\Schueler;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;

class WeekTableSheet implements FromArray, WithHeadings, WithStyles, WithColumnWidths, WithTitle
{
    protected Collection $klassen;
    protected Carbon $weekStart;
    protected Carbon $weekEnd;

    /** @var Carbon[] */
    protected array $days = [];

    /** @var array<int, string> 1-based index → 'header'|'student'|'empty' */
    protected array $rowTypes = [];

    // Bulk-geladene Daten
    protected Collection $allEntries;
    protected array $appointmentsByKlasseDate = [];   // [klasse_id][date]['class'] = [...]
    protected array $appointmentsByStuDate   = [];   // [schueler_id][date] = [...]
    protected array $tasksBySchueler          = [];   // [schueler_id] = [Task]
    protected array $absenceIndex             = [];   // [schueler_id][date] = true

    public function __construct(Collection $klassen, Carbon $weekStart, Carbon $weekEnd)
    {
        $this->klassen   = $klassen;
        $this->weekStart = $weekStart->copy()->startOfDay();
        $this->weekEnd   = $weekEnd->copy()->startOfDay();

        for ($i = 0; $i < 5; $i++) {
            $this->days[] = $this->weekStart->copy()->addDays($i);
        }

        $this->loadData();
    }

    // ── Daten in 4 Bulk-Queries laden ────────────────────────────────────────

    protected function loadData(): void
    {
        $klassenIds = $this->klassen->pluck('id');

        // 1) Einträge (aktuelle Woche + alle offenen früheren)
        $this->allEntries = PaedDiaryEntry::with(['schueler:id', 'category:id,name'])
            ->whereIn('klasse_id', $klassenIds)
            ->where('dossier_only', false)
            ->where(function ($q) {
                $q->whereBetween('datum', [
                    $this->weekStart->toDateString(),
                    $this->weekEnd->toDateString(),
                ])->orWhereNull('completed_at');
            })
            ->get();

        // 2) Termine
        $appointments = PaedDiaryAppointment::with(['klassen:id', 'schueler:id', 'groups:id'])
            ->where(function ($q) use ($klassenIds) {
                $q->whereHas('klassen', fn ($kq) => $kq->whereIn('klassen.id', $klassenIds))
                  ->orWhereHas('schueler', fn ($sq) => $sq->whereIn('schueler.klasse_id', $klassenIds));
            })
            ->whereDate('start_date', '<=', $this->weekEnd->toDateString())
            ->get();

        foreach ($appointments as $apt) {
            $occurrences = $apt->getOccurrencesInRange(
                $this->weekStart->copy(),
                $this->weekEnd->copy()
            );
            $klassenArr  = $apt->klassen->pluck('id')->toArray();
            $schuelerArr = $apt->schueler->pluck('id')->toArray();

            foreach ($occurrences as $occ) {
                $date = $occ['date'];
                $occData = [
                    'title'      => $occ['title'],
                    'start_time' => $occ['start_time'] ?? null,
                    'end_time'   => $occ['end_time']   ?? null,
                ];

                if (!empty($klassenArr)) {
                    foreach ($klassenArr as $kid) {
                        $this->appointmentsByKlasseDate[$kid][$date]['class'][] = $occData;
                    }
                }

                if (!empty($schuelerArr)) {
                    foreach ($schuelerArr as $sid) {
                        $this->appointmentsByStuDate[$sid][$date][] = $occData;
                    }
                }
            }
        }

        // 3) Offene Aufgaben
        $tasks = PaedDiaryTask::whereIn('klasse_id', $klassenIds)
            ->open()
            ->get(['id', 'schueler_id', 'title', 'due_date', 'klasse_id']);

        foreach ($tasks as $task) {
            $this->tasksBySchueler[$task->schueler_id][] = $task;
        }

        // 4) Abwesenheiten
        $absences = PaedDiarySchuelerAbsence::whereIn('klasse_id', $klassenIds)
            ->whereBetween('datum', [
                $this->weekStart->toDateString(),
                $this->weekEnd->toDateString(),
            ])
            ->get(['schueler_id', 'klasse_id', 'datum']);

        foreach ($absences as $ab) {
            $this->absenceIndex[$ab->schueler_id][$ab->datum->toDateString()] = true;
        }
    }

    // ── Zeilen aufbauen ──────────────────────────────────────────────────────

    public function array(): array
    {
        $rows           = [];
        $rowIndex       = 1; // 1-basiert (ohne Heading-Zeile)
        $this->rowTypes = [];

        foreach ($this->klassen as $klasse) {
            // ── Klassen-Header-Zeile ─────────────────────────────────────────
            $headerRow = [$klasse->name, ''];
            foreach ($this->days as $day) {
                $dateStr    = $day->toDateString();
                $classApts  = $this->appointmentsByKlasseDate[$klasse->id][$dateStr]['class'] ?? [];
                $cellLines  = [];
                foreach ($classApts as $apt) {
                    $time = $this->formatTime($apt['start_time']);
                    $cellLines[] = ($time ? $time . ' ' : '') . $apt['title'];
                }
                $headerRow[] = implode("\n", $cellLines);
            }
            $headerRow[] = ''; // Noch zu erledigen → leer im Header
            $rows[]      = $headerRow;
            $this->rowTypes[$rowIndex++] = 'header';

            // ── Schüler-Zeilen ────────────────────────────────────────────────
            $schueler = Schueler::where('klasse_id', $klasse->id)
                ->orderBy('nachname')
                ->orderBy('vorname')
                ->get(['id', 'vorname', 'nachname']);

            // Entries dieser Klasse vorfiltern und nach schueler_id indexieren
            $klasseEntries = $this->allEntries
                ->filter(fn ($e) => $e->klasse_id === $klasse->id);

            // Map: schueler_id → Collection von Entries
            $entryByStu = [];
            foreach ($klasseEntries as $entry) {
                foreach ($entry->schueler as $s) {
                    $entryByStu[$s->id][] = $entry;
                }
            }

            foreach ($schueler as $stu) {
                $stuRow = [$stu->nachname, $stu->vorname];

                foreach ($this->days as $day) {
                    $dateStr   = $day->toDateString();
                    $cellLines = [];

                    // Abwesenheit
                    if (!empty($this->absenceIndex[$stu->id][$dateStr])) {
                        $cellLines[] = '🚫 ABWESEND';
                    }

                    // Individuelle Schüler-Termine
                    $stuApts = $this->appointmentsByStuDate[$stu->id][$dateStr] ?? [];
                    foreach ($stuApts as $apt) {
                        $time        = $this->formatTime($apt['start_time']);
                        $cellLines[] = '[Termin] ' . ($time ? $time . ' ' : '') . $apt['title'];
                    }

                    // Klassen-Termine auch in Schüler-Zellen
                    $classApts = $this->appointmentsByKlasseDate[$klasse->id][$dateStr]['class'] ?? [];
                    foreach ($classApts as $apt) {
                        $time        = $this->formatTime($apt['start_time']);
                        $cellLines[] = $apt['title'];
                    }

                    // Notizen (offen und abgeschlossen)
                    $stuEntries = $entryByStu[$stu->id] ?? [];
                    foreach ($stuEntries as $entry) {
                        $isCompleted  = !is_null($entry->completed_at);
                        $entryDateStr = $entry->datum instanceof Carbon
                            ? $entry->datum->toDateString()
                            : Carbon::parse($entry->datum)->toDateString();

                        if ($isCompleted) {
                            // Abgeschlossene Einträge nur am jeweiligen Datum
                            if ($entryDateStr !== $dateStr) {
                                continue;
                            }
                        } else {
                            // Offene Einträge: ab Start-Datum bis Wochenende
                            if (Carbon::parse($entryDateStr)->gt(Carbon::parse($dateStr))) {
                                continue;
                            }
                        }


                        $category = $entry->category?->name ?? '';
                        $content  = $entry->content ?? '';
                        if (mb_strlen($content) > 200) {
                            $content = mb_substr($content, 0, 197) . '…';
                        }
                        $cellLines[] = "{$content}";
                    }

                    $stuRow[] = implode("\n", $cellLines);
                }

                // Noch zu erledigen
                $stuTasks  = $this->tasksBySchueler[$stu->id] ?? [];
                $taskLines = [];
                foreach ($stuTasks as $task) {
                    $due         = $task->due_date ? ' (fällig: ' . $task->due_date->format('d.m.') . ')' : '';
                    $taskLines[] = $task->title . $due;
                }
                $stuRow[] = implode("\n", $taskLines);

                $rows[]                      = $stuRow;
                $this->rowTypes[$rowIndex++] = 'student';
            }

            // ── Leerzeile ────────────────────────────────────────────────────
            $rows[]                      = array_fill(0, 8, '');
            $this->rowTypes[$rowIndex++] = 'empty';
        }

        return $rows;
    }

    // ── Headings ─────────────────────────────────────────────────────────────

    public function headings(): array
    {
        $dayNames = ['Montag', 'Dienstag', 'Mittwoch', 'Donnerstag', 'Freitag'];
        $headings = ['Nachname', 'Vorname'];
        foreach ($this->days as $i => $day) {
            $headings[] = $dayNames[$i] . ' ' . $day->format('d.m.');
        }
        $headings[] = 'Noch zu erledigen';
        return $headings;
    }

    public function title(): string
    {
        $kw = $this->weekStart->weekOfYear;
        return 'KW' . $kw . ' ' . $this->weekStart->year;
    }

    public function columnWidths(): array
    {
        return [
            'A' => 18, // Nachname
            'B' => 15, // Vorname
            'C' => 35, // Montag
            'D' => 35, // Dienstag
            'E' => 35, // Mittwoch
            'F' => 35, // Donnerstag
            'G' => 35, // Freitag
            'H' => 30, // Noch zu erledigen
        ];
    }

    public function styles(Worksheet $sheet): array
    {
        $lastCol = 'H';

        // Heading-Zeile (Row 1)
        $sheet->getStyle("A1:{$lastCol}1")->applyFromArray([
            'font'      => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '2C3E50']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
        ]);

        // Daten-Zeilen: excelRow = dataIndex + 2 (wegen Heading-Zeile = Row 1)
        foreach ($this->rowTypes as $dataIndex => $type) {
            $excelRow = $dataIndex + 1; // rowTypes ist 1-basiert, +1 für Heading

            if ($type === 'header') {
                $sheet->getStyle("A{$excelRow}:{$lastCol}{$excelRow}")->applyFromArray([
                    'font'      => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
                    'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '4A90E2']],
                    'alignment' => ['vertical' => Alignment::VERTICAL_CENTER, 'wrapText' => true],
                    'borders'   => [
                        'bottom' => ['borderStyle' => Border::BORDER_MEDIUM, 'color' => ['rgb' => 'FFFFFF']],
                    ],
                ]);
            } elseif ($type === 'student') {
                $sheet->getStyle("A{$excelRow}:{$lastCol}{$excelRow}")->applyFromArray([
                    'alignment' => ['vertical' => Alignment::VERTICAL_TOP, 'wrapText' => true],
                    'borders'   => [
                        'allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'DDDDDD']],
                    ],
                ]);
                $sheet->getStyle("A{$excelRow}:B{$excelRow}")->getFont()->setBold(true);
            }
        }

        return [];
    }

    // ── Hilfsfunktion ─────────────────────────────────────────────────────────

    protected function formatTime(mixed $timeStr): string
    {
        if (empty($timeStr)) {
            return '';
        }
        $str = (string) $timeStr;
        if (str_contains($str, 'T')) {
            try {
                $dt = new \DateTime($str);
                return $dt->format('H:i');
            } catch (\Exception) {
            }
        }
        if (str_contains($str, ':')) {
            $parts = explode(':', $str);
            return sprintf('%02d:%02d', (int) $parts[0], (int) ($parts[1] ?? 0));
        }
        return $str;
    }
}

