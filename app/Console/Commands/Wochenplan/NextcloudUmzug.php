<?php

namespace App\Console\Commands\Wochenplan;

use App\Models\Wochenplan\WpDatei;
use App\Models\Wochenplan\WpPlan;
use App\Services\Wochenplan\WpAblageService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Schema;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * Übernimmt die bisherigen Arbeitsblätter (Media Library, Sammlung „arbeitsblaetter“) in die
 * neue Datei-Ablage (`wp_dateien`, Upload in die Nextcloud) und legt alle Pläne einmal neu ab.
 * Kopien in Kinderplänen (synced_from_plan_id) entfallen – Kinderpläne verweisen jetzt auf die
 * Arbeitsblätter ihres Klassenplans.
 */
class NextcloudUmzug extends Command
{
    protected $signature = 'wochenplan:nextcloud-umzug {--dry-run : Nur anzeigen, nichts ändern}';

    protected $description = 'Verschiebt bestehende Wochenplan-Arbeitsblätter in die Nextcloud-Ablage und legt alle Pläne neu ab';

    public function handle(WpAblageService $ablage): int
    {
        $trocken = (bool) $this->option('dry-run');

        $medien = Schema::hasTable('media')
            ? Media::where('model_type', WpPlan::class)->where('collection_name', 'arbeitsblaetter')->get()
            : collect();

        $kopien = $medien->filter(fn (Media $m) => $m->getCustomProperty('synced_from_plan_id') !== null);
        $eigene = $medien->reject(fn (Media $m) => $m->getCustomProperty('synced_from_plan_id') !== null);

        $this->table(['', 'Anzahl'], [
            ['Arbeitsblätter (eigene)', $eigene->count()],
            ['Kopien in Kinderplänen (entfallen)', $kopien->count()],
            ['Pläne zum Neu-Ablegen', WpPlan::where('is_vorlage', false)->count()],
            ['Nextcloud-Ablage aktiv', $ablage->aktiv() ? 'ja' : 'nein (Dateien bleiben im Zwischenspeicher)'],
        ]);

        if ($trocken) {
            $this->info('Lesemodus – es wurde nichts geändert.');

            return self::SUCCESS;
        }

        $uebernommen = 0;
        foreach ($eigene as $media) {
            $plan = WpPlan::withTrashed()->find($media->model_id);
            $pfad = $media->getPath();
            if (! $plan || ! is_file($pfad)) {
                $this->warn('Übersprungen (Plan oder Datei fehlt): '.$media->file_name);

                continue;
            }
            if (WpDatei::where('wp_plan_id', $plan->id)->where('name', $media->name ?: $media->file_name)->exists()) {
                $media->delete();

                continue;
            }
            $ablage->dateiAusInhalt($plan, $media->file_name, (string) file_get_contents($pfad), $media->mime_type, null);
            $media->delete();
            $uebernommen++;
        }

        foreach ($kopien as $media) {
            $media->delete();
        }

        $anzahl = $ablage->alleNeuAblegen();

        $this->info($uebernommen.' Arbeitsblätter übernommen, '.$kopien->count().' Kopien entfernt, '.$anzahl.' Pläne zur Ablage angefordert.');

        return self::SUCCESS;
    }
}
