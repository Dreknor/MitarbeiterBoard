<?php

namespace App\Exports;
use Maatwebsite\Excel\Facades\Excel;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Illuminate\Support\Collection;

class LogExport implements FromCollection, WithHeadings
{
    protected $logs;

    public function __construct($logs)
    {
        $this->logs = $logs;
    }

    public function collection()
    {
        return new Collection($this->logs->map(function ($log) {
            return [
                'zeitstempel' => $log->created_at->format('d.m.Y H:i:s'),
                'level' => $log->level,
                'nachricht' => $log->message,
                'kontext' => is_array($log->context) ? json_encode($log->context, JSON_UNESCAPED_UNICODE) : $log->context,
                'datei' => $log->file ?? '',
                'zeile' => $log->line ?? ''
            ];
        }));
    }

    public function headings(): array
    {
        return [
            'Zeitstempel',
            'Level',
            'Nachricht',
            'Kontext',
            'Datei',
            'Zeile'
        ];
    }
}

