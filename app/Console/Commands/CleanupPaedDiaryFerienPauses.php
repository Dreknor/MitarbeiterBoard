<?php

namespace App\Console\Commands;

use App\Models\PaedDiaryEntry;
use App\Models\PaedDiaryEntryPause;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Schema;

/**
 * Bereinigt fälschlich erzeugte "Ferien"-Auto-Pausen für Pädagogisches-Tagebuch-Einträge.
 *
 * Hintergrund: Vor dem Fix in PaedDiaryController::weekData() wurde ein neu erstellter,
 * offener Eintrag sofort für ALLE Ferientage der aktuell angezeigten Woche pausiert -
 * inklusive seines eigenen Start-Datums (und ggf. sogar für Tage VOR dem Start-Datum,
 * z.B. wenn die Woche bereits teilweise vergangen war). Dadurch verschwand ein gerade
 * erst angelegter Eintrag sofort wieder aus der Wochenansicht.
 *
 * Dieser Command entfernt zwei Arten von fälschlich erzeugten Pausen:
 *   1. reason = 'Ferien' UND pause.date <= entry.datum (rückwirkende Pause am/vor Start-Tag)
 *   2. reason = 'Ferien' UND der Eintrag wurde selbst WÄHREND laufender Ferien erstellt
 *      (created_at liegt in einem Ferienzeitraum) - z.B. Hort-/Ferienbetreuung. Solche
 *      Einträge sollen NIE automatisch pausiert werden, unabhängig vom konkreten Tag.
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
    protected $description = 'Entfernt fälschlich erzeugte Ferien-Auto-Pausen (rückwirkend oder für während der Ferien angelegte Einträge) im Pädagogischen Tagebuch';

    public function handle(): int
    {
        if (!Schema::hasColumn('paed_diary_entry_pauses', 'reason')) {
            $this->error('Spalte "reason" existiert nicht in paed_diary_entry_pauses. Migration ggf. noch nicht gelaufen.');
            return Command::FAILURE;
        }

        // Alle Ferien-Pausen mit zugehörigem Eintrag laden (gruppiert nach Eintrag).
        $pauses = PaedDiaryEntryPause::where('reason', 'Ferien')->get()->groupBy('paed_diary_entry_id');

        if ($pauses->isEmpty()) {
            $this->info('Keine Ferien-Pausen gefunden.');
            return Command::SUCCESS;
        }

        $entries = PaedDiaryEntry::whereIn('id', $pauses->keys())->get()->keyBy('id');

        $toDeleteIds = [];
        $reasonCreatedDuringFerien = 0;
        $reasonBeforeOwnStart = 0;

        foreach ($pauses as $entryId => $entryPauses) {
            $entry = $entries->get($entryId);
            if (!$entry) {
                continue; // Eintrag existiert nicht mehr -> nicht Teil dieses Cleanups
            }

            $createdAt = $entry->created_at ?? $entry->datum;
            $createdDuringFerien = !is_null(is_ferien($createdAt->copy()));

            if ($createdDuringFerien) {
                // Eintrag wurde bewusst während der Ferien angelegt -> ALLE Ferien-Pausen entfernen
                foreach ($entryPauses as $p) {
                    $toDeleteIds[] = $p->id;
                }
                $reasonCreatedDuringFerien += $entryPauses->count();
                continue;
            }

            // Ansonsten: nur rückwirkende Pausen (Datum <= eigenes Start-Datum) entfernen
            $entryStart = $entry->datum->copy()->startOfDay();
            foreach ($entryPauses as $p) {
                if ($p->date->lte($entryStart)) {
                    $toDeleteIds[] = $p->id;
                    $reasonBeforeOwnStart++;
                }
            }
        }

        $count = count($toDeleteIds);
        $this->info("Gefundene fehlerhafte Ferien-Pausen: {$count}");
        $this->line("  - davon 'während der Ferien angelegt': {$reasonCreatedDuringFerien}");
        $this->line("  - davon 'rückwirkend vor/am Start-Datum': {$reasonBeforeOwnStart}");

        if ($count === 0) {
            return Command::SUCCESS;
        }

        if (!$this->option('force')) {
            $this->warn('Trockenlauf - es wurde nichts gelöscht. Mit --force ausführen, um wirklich zu löschen.');
            return Command::SUCCESS;
        }

        $deleted = PaedDiaryEntryPause::whereIn('id', $toDeleteIds)->delete();
        $this->info("{$deleted} fehlerhafte Ferien-Pause(n) gelöscht.");

        return Command::SUCCESS;
    }
}





