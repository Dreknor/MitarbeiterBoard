<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;

class PaedDiaryExport implements FromArray, WithHeadings, WithStyles, WithColumnWidths, WithTitle
{
    protected $data;
    protected $title;
    protected $includeClass;

    public function __construct(array $data, string $title = 'Pädagogisches Tagebuch', bool $includeClass = false)
    {
        $this->data = $data;
        $this->title = $title;
        $this->includeClass = $includeClass;
    }

    public function array(): array
    {
        return $this->data;
    }

    public function headings(): array
    {
        if ($this->includeClass) {
            return [
                'Datum',
                'Klasse',
                'Schüler',
                'Autor',
                'Notiz',
                'Stufe'
            ];
        }
        return [
            'Datum',
            'Schüler',
            'Autor',
            'Notiz',
            'Stufe'
        ];
    }

    public function title(): string
    {
        return $this->title;
    }

    public function columnWidths(): array
    {
        if ($this->includeClass) {
            return [
                'A' => 12, // Datum
                'B' => 16, // Klasse
                'C' => 20, // Schüler
                'D' => 15, // Autor
                'E' => 50, // Notiz
                'F' => 12, // Stufe
            ];
        }
        return [
            'A' => 12, // Datum
            'B' => 20, // Schüler
            'C' => 15, // Autor
            'D' => 50, // Notiz
            'E' => 12, // Stufe
        ];
    }

    public function styles(Worksheet $sheet)
    {
        $lastCol = $this->includeClass ? 'F' : 'E';
        // Header
        $sheet->getStyle("A1:{$lastCol}1")->applyFromArray([
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

        $lastRow = count($this->data) + 1;
        if ($lastRow > 1) {
            $sheet->getStyle("A2:{$lastCol}{$lastRow}")->applyFromArray([
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
            $sheet->getStyle("A2:A{$lastRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            if ($this->includeClass) {
                $sheet->getStyle("B2:B{$lastRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);
            }
        }
        return [];
    }
}
