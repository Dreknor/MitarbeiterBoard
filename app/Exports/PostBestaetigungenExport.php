<?php

namespace App\Exports;

use App\Models\Post;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithTitle;

/**
 * Bestätigungsliste einer Nachricht als Excel (Nachweis). Daten aus PostService::bestaetigungsliste().
 */
class PostBestaetigungenExport implements FromCollection, WithHeadings, WithMapping, ShouldAutoSize, WithTitle
{
    public const STATUS = [
        'bestaetigt' => 'bestätigt',
        'offen' => 'offen',
        'ueberfaellig' => 'überfällig',
    ];

    public function __construct(private readonly Post $post, private readonly Collection $liste)
    {
    }

    public function collection(): Collection
    {
        return $this->liste;
    }

    /**
     * @param  array{user: \App\Models\User, status: string, bestaetigt_at: ?\Carbon\Carbon, frist: ?\Carbon\Carbon, ausgeschieden: bool}  $zeile
     */
    public function map($zeile): array
    {
        return [
            $zeile['user']->name,
            self::STATUS[$zeile['status']] ?? $zeile['status'],
            $zeile['bestaetigt_at']?->format('d.m.Y H:i') ?? '',
            $zeile['frist']?->format('d.m.Y') ?? '',
            $zeile['ausgeschieden'] ? 'nicht mehr in der Gruppe' : '',
        ];
    }

    public function headings(): array
    {
        return ['Name', 'Status', 'bestätigt am', 'Frist', 'Hinweis'];
    }

    public function title(): string
    {
        return 'Lesebestätigungen';
    }
}
