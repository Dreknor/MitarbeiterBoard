<?php

namespace App\Console\Commands\Personal;

use App\Models\personal\Holiday;
use App\Models\personal\Timesheet;
use App\Services\Personal\Zeit\TimesheetService;
use Illuminate\Console\Command;

/**
 * Einmalige Bereinigung: Urlaubsgutschriften im Arbeitszeitnachweis, deren Antrag storniert oder
 * abgelehnt wurde, die aber stehen geblieben sind (v. a. Übergangsmonat und Altbestand, in denen
 * Urlaub als unverknüpfte "Urlaub"-Zeile übernommen wurde).
 *
 * Standard ist eine reine Vorschau – erst mit --ausfuehren wird gelöscht und neu berechnet.
 * Abgeschlossene Nachweise werden nicht verändert, sondern zur Prüfung markiert.
 */
class BereinigeStornierteUrlaube extends Command
{
    protected $signature = 'personal:urlaub-bereinigen
                            {--ausfuehren : Zeilen tatsächlich löschen und Nachweise neu berechnen (sonst nur Vorschau)}';

    protected $description = 'Entfernt Urlaubsgutschriften stornierter/abgelehnter Anträge aus den Arbeitszeitnachweisen';

    public function handle(TimesheetService $timesheets): int
    {
        $ausfuehren = (bool) $this->option('ausfuehren');

        // Nur Monate, in denen überhaupt ein stornierter oder abgelehnter Antrag liegt
        $monate = Holiday::withTrashed()
            ->where(fn ($q) => $q->whereNotNull('deleted_at')->orWhere('rejected', true))
            ->get(['employe_id', 'start_date', 'end_date'])
            ->flatMap(function (Holiday $h) {
                $schluessel = [];
                for ($monat = $h->start_date->copy()->startOfMonth(); $monat->lte($h->end_date); $monat->addMonth()) {
                    $schluessel[] = $h->employe_id.'|'.$monat->year.'|'.$monat->month;
                }

                return $schluessel;
            })
            ->unique();

        $zeilen = [];
        $gesamt = 0;
        $gesperrt = 0;

        // Chronologisch, damit Folgemonate nach der Neuberechnung des Vormonats stimmen
        $nachweise = $monate->map(function (string $schluessel) {
            [$employe, $jahr, $monat] = explode('|', $schluessel);

            return Timesheet::with('employe')->where('employe_id', $employe)->where('year', $jahr)->where('month', $monat)->first();
        })->filter()->sortBy(fn (Timesheet $t) => sprintf('%04d%02d', $t->year, $t->month));

        foreach ($nachweise as $timesheet) {
            $verwaist = $timesheets->verwaisteUrlaubszeilen($timesheet);
            if ($verwaist->isEmpty()) {
                continue;
            }

            $gesamt += $verwaist->count();
            if ($timesheet->is_locked) {
                $gesperrt++;
            }

            $zeilen[] = [
                $timesheet->employe?->name ?? '#'.$timesheet->employe_id,
                $timesheet->monthStart()->format('m/Y'),
                $verwaist->map(fn ($z) => $z->date->format('d.m.'))->implode(', '),
                $timesheet->is_locked ? 'abgeschlossen → Prüfung' : ($ausfuehren ? 'bereinigt' : 'wird bereinigt'),
            ];

            if ($ausfuehren) {
                $timesheets->verwaisteUrlaubEntfernen($timesheet);
            }
        }

        if ($zeilen === []) {
            $this->info('Keine verwaisten Urlaubsgutschriften gefunden.');
            return self::SUCCESS;
        }

        $this->table(['Person', 'Monat', 'Tage', 'Aktion'], $zeilen);
        $this->info($gesamt.' Zeile(n) in '.count($zeilen).' Nachweis(en), davon '.$gesperrt.' abgeschlossen.');

        if (!$ausfuehren) {
            $this->warn('Vorschau – es wurde nichts geändert. Zum Bereinigen: php artisan personal:urlaub-bereinigen --ausfuehren');
        }

        return self::SUCCESS;
    }
}
