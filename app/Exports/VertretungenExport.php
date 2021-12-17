<?php

namespace App\Exports;

use App\Models\Vertretung;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithColumnFormatting;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use PhpOffice\PhpSpreadsheet\Shared\Date;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;


class VertretungenExport implements FromCollection, WithHeadings, WithMapping, WithColumnFormatting
{
    private String $startDate;
    private $endDate;
    private Int $rows = 0;

    public function __construct($startDate, $endDate=null)
    {
        $this->startDate = $startDate;
        $this->endDate = $endDate;
    }

    public function map($row): array
    {
        return [
            ++$this->rows,
            Date::dateTimeToExcel($row->date),
            $row->klasse->name,
            $row->altFach,
            $row->neuFach,
            optional($row->lehrer)->name,
            $row->comment
        ];
    }

    public function headings(): array
    {
        return [
            '#',
            'Datum',
            'Klasse',
            'Fach',
            'Vertretungsfach',
            'Vertretungslehrer',
            'Bemerkung'
        ];
    }

    /**
    * @return \Illuminate\Support\Collection
    */
    public function collection()
    {


        if ($this->endDate != null){
            return Vertretung::whereBetween('date', [$this->startDate, $this->endDate])->orderBy('date')->get();
        }
        return Vertretung::where('date', '>=', $this->startDate)->orderBy('date')->get();
    }

    public function columnFormats(): array
    {
        return [
            'B' => NumberFormat::FORMAT_DATE_DDMMYYYY,
        ];
    }
}
