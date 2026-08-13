<?php

namespace App\Console\Commands;

use App\Models\PaedDiaryEntryPause;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Bereinigt fälschlich erzeugte "Ferien"-Auto-Pausen für Pädagogisches-Tagebuch-Einträge.
 *
 * Hintergrund: Vor dem Fix in PaedDiaryController::weekData() wurde ein neu erstellter,
 * offener Eintrag sofort für ALLE Ferientage der aktuell angezeigten Woche pausiert -
 * inklusive seines eigenen Start-Datums (und ggf. sogar für Tage VOR dem Start-Datum,
 * z.B. wenn die Woche bereits teilweise vergangen war). Dadurch verschwand ein gerade
 * erst angelegter Eintrag sofort wieder aus der Wochenansicht.
 *
 * Dieser Command entfernt genau die Pause-Datensätze, die laut der neuen Logik nie
 * hätten angelegt werden dürfen: reason = 'Ferien' UND pause.date <= entry.datum.
 *
 * Nutzung:
 *   php artisan paed-diary:cleanup-ferien-pauses            # Trockenlauf (zeigt nur Anzahl)
 *   php artisan paed-diary:cleanup-ferien-pauses --force     # Löscht tatsächlich
 */
class CleanupPaedDiaryFerienPauses extends Command
{
    /** @var string */
    protected $signature = 'paed-diary:cleanup-ferien-pauses {--force : Löscht tatsächlich, ohne diesen Flag nur Anzeige}';

    /** @var string */
    protected $description = 'Entfernt fälschlich rückwirkend erzeugte Ferien-Auto-Pausen (Pause-Datum <= Eintrags-Startdatum) im Pädagogischen Tagebuch';

    public function handle(): int
    {
        $hasReasonColumn = \Illuminate\Support\Facades\Schema::hasColumn('paed_diary_entry_pauses', 'reason');

        if (!$hasReasonColumn) {
            $this->error('Spalte "reason" existiert nicht in paed_diary_entry_pauses. Migration ggf. noch nicht gelaufen.');
            return Command::FAILURE;
        }

        // Join über die zugehörigen Einträge, um nur Pausen zu finden, deren Datum
        // auf oder vor dem Start-Datum (datum) des Eintrags liegt.
        $affectedIds = DB::table('paed_diary_entry_pauses as p')
            ->join('paed_diary_entries as e', 'e.id', '=', 'p.paed_diary_entry_id')
            ->where('p.reason', 'Ferien')
            ->whereColumn('p.date', '<=', 'e.datum')
            ->pluck('p.id');

        $count = $affectedIds->count();
        $this->info("Gefundene fehlerhafte Ferien-Pausen: {$count}");

        if ($count === 0) {
            return Command::SUCCESS;
        }

        if (!$this->option('force')) {
            $this->warn('Trockenlauf - es wurde nichts gelöscht. Mit --force ausführen, um wirklich zu löschen.');
            return Command::SUCCESS;
        }

        $deleted = PaedDiaryEntryPause::whereIn('id', $affectedIds)->delete();
        $this->info("{$deleted} fehlerhafte Ferien-Pause(n) gelöscht.");

        return Command::SUCCESS;
    }
}



