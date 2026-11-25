<?php

namespace App\Console\Commands\Wochenplan;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * Entfernt die Daten des alten, gruppengebundenen Wochenplans
 * (Tabellen wochenplaene, wprows, wp_tasks, wps_klassen, Dateien, Spalte groups.hasWochenplan).
 * Ohne --ausfuehren wird nur angezeigt, was gelöscht würde. Nicht rückgängig zu machen –
 * vorher eine Datenbank-Sicherung anlegen.
 */
class AltsystemEntfernen extends Command
{
    protected $signature = 'wochenplan:alt-entfernen {--ausfuehren : Daten wirklich löschen}';

    protected $description = 'Zeigt bzw. löscht die Daten des alten Wochenplans (Gruppen-Wochenplan)';

    private const TABELLEN = ['wp_tasks', 'wprows', 'wps_klassen', 'wochenplaene'];

    private const MODELL = 'App\\Models\\Wochenplan';

    public function handle(): int
    {
        $ausfuehren = (bool) $this->option('ausfuehren');

        $zeilen = [];
        foreach (self::TABELLEN as $tabelle) {
            $zeilen[] = [$tabelle, Schema::hasTable($tabelle) ? DB::table($tabelle)->count() : 'nicht vorhanden'];
        }
        $medien = Schema::hasTable('media') ? Media::where('model_type', self::MODELL)->get() : collect();
        $zeilen[] = ['Dateien (media)', $medien->count().' ('.number_format($medien->sum('size') / 1048576, 1, ',', '.').' MB)'];
        $zeilen[] = ['Spalte groups.hasWochenplan', Schema::hasColumn('groups', 'hasWochenplan') ? 'vorhanden' : 'nicht vorhanden'];

        $this->table(['Altdaten', 'Umfang'], $zeilen);

        if (! $ausfuehren) {
            $this->info('Lesemodus – es wurde nichts gelöscht. Zum Löschen: php artisan wochenplan:alt-entfernen --ausfuehren');

            return self::SUCCESS;
        }

        if ($this->input->isInteractive() && ! $this->confirm('Alle oben genannten Altdaten endgültig löschen? (Sicherung vorhanden?)')) {
            $this->warn('Abgebrochen.');

            return self::FAILURE;
        }

        foreach ($medien as $media) {
            try {
                $media->delete();
            } catch (\Throwable $e) {
                $this->warn('Datei '.$media->id.' konnte nicht gelöscht werden: '.$e->getMessage());
                DB::table('media')->where('id', $media->id)->delete();
            }
        }

        Schema::disableForeignKeyConstraints();
        foreach (self::TABELLEN as $tabelle) {
            Schema::dropIfExists($tabelle);
        }
        Schema::enableForeignKeyConstraints();

        if (Schema::hasColumn('groups', 'hasWochenplan')) {
            Schema::table('groups', fn ($table) => $table->dropColumn('hasWochenplan'));
        }

        $this->info('Altdaten des Wochenplans wurden gelöscht.');

        return self::SUCCESS;
    }
}
