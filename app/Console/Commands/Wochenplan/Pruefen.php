<?php

namespace App\Console\Commands\Wochenplan;

use App\Models\User;
use App\Models\Wochenplan\WpDatei;
use App\Models\Wochenplan\WpPlan;
use App\Services\Wochenplan\Nextcloud\WpNextcloudSpeicher;
use App\Services\Wochenplan\WpStatusService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Schema;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * Prüft nach dem Deploy (nur lesend, bis auf einen Test-Ordner in der Nextcloud mit --verbindung):
 * Klassenzuordnungen, Nextcloud-Konfiguration und -Verbindung, Schalter, offene Ablagen,
 * noch nicht umgezogene Arbeitsblätter und Altdaten. Exit-Code 1, wenn etwas zu tun ist.
 */
class Pruefen extends Command
{
    protected $signature = 'wochenplan:pruefen {--verbindung : Verbindung zur Nextcloud testen (legt den Basisordner an)}';

    protected $description = 'Prüft Klassenzuordnungen, Nextcloud-Ablage und Umzug des Wochenplans';

    public function handle(WpNextcloudSpeicher $nextcloud): int
    {
        $probleme = 0;

        // 1. Personen mit Wochenplan-Recht ohne Klasse (sehen nach der Umstellung keine Pläne)
        $ohneKlasse = User::permission(['view wochenplan', 'create wochenplan'])->get()
            ->reject(fn (User $u) => WpPlan::darfAlles($u) || $u->paedKlassenIds()->isNotEmpty())
            ->sortBy('name');
        if ($ohneKlasse->isEmpty()) {
            $this->info('✓ Alle Personen mit Wochenplan-Recht sind mindestens einer Klasse zugeordnet.');
        } else {
            $probleme++;
            $this->warn('✗ '.$ohneKlasse->count().' Person(en) mit Wochenplan-Recht ohne Klasse (sehen keine Pläne):');
            $this->table(['ID', 'Name'], $ohneKlasse->map(fn ($u) => [$u->id, $u->name])->all());
        }

        // 2. Pläne ohne Klasse (nur noch für die erstellende Person sichtbar)
        $ohne = WpPlan::where('is_vorlage', false)->whereNull('klasse_id')->whereNull('schueler_id')->count();
        $ohne
            ? $this->warn("! {$ohne} Plan/Pläne ohne Klasse – nur noch für die erstellende Person sichtbar.")
            : $this->info('✓ Keine Pläne ohne Klasse.');

        // 3. Nextcloud
        $konfiguriert = config('nextcloud.wochenplan.url') && config('nextcloud.wochenplan.username');
        $this->line(($konfiguriert ? '✓' : '·').' Nextcloud-Zugangsdaten '.($konfiguriert ? 'eingetragen ('.config('nextcloud.wochenplan.url').')' : 'fehlen (NEXTCLOUD_WOCHENPLAN_*)'));
        $this->line(($nextcloud->aktiv() ? '✓' : '·').' Nextcloud-Ablage '.($nextcloud->aktiv() ? 'aktiv' : 'aus'));
        if ($this->option('verbindung')) {
            if (! $nextcloud->aktiv()) {
                $probleme++;
                $this->warn('✗ Verbindungstest nicht möglich – Ablage ist aus oder nicht konfiguriert.');
            } elseif ($nextcloud->ordnerAnlegen('') && $nextcloud->existiert('')) {
                $this->info('✓ Verbindung zur Nextcloud funktioniert (Basisordner vorhanden).');
            } else {
                $probleme++;
                $this->error('✗ Nextcloud nicht erreichbar oder Anmeldung abgelehnt – siehe Log.');
            }
        }

        // 4. Offene/fehlgeschlagene Ablagen
        $fehler = WpPlan::where('ablage_status', 'fehler')->count();
        $dateiFehler = WpDatei::whereIn('status', [WpDatei::FEHLER, WpDatei::FEHLT])->count();
        if ($fehler || $dateiFehler) {
            $probleme++;
            $this->warn("✗ {$fehler} Plan-Ablage(n) und {$dateiFehler} Arbeitsblatt/-blätter mit Fehler.");
        } else {
            $this->info('✓ Keine fehlgeschlagenen Ablagen.');
        }
        $this->line('· Lokal (ohne Nextcloud): '.WpDatei::where('status', WpDatei::LOKAL)->count().' Arbeitsblätter, warten auf Upload: '.WpDatei::where('status', WpDatei::AUSSTEHEND)->count());

        // 5. Umzug
        $alt = Schema::hasTable('media') ? Media::where('model_type', WpPlan::class)->where('collection_name', 'arbeitsblaetter')->count() : 0;
        if ($alt) {
            $probleme++;
            $this->warn("✗ {$alt} Arbeitsblätter im alten Speicher – php artisan wochenplan:nextcloud-umzug ausführen.");
        } else {
            $this->info('✓ Keine Arbeitsblätter im alten Speicher.');
        }

        // 6. Schalter und Altsystem
        $this->line((WpStatusService::tagebuchAktiv() ? '✓' : '·').' Tagebuch-Anbindung '.(WpStatusService::tagebuchAktiv() ? 'an' : 'aus'));
        if (Schema::hasTable('wochenplaene')) {
            $this->line('· Altdaten des Gruppen-Wochenplans vorhanden – nach einer Sicherung: php artisan wochenplan:alt-entfernen --ausfuehren');
        }

        return $probleme ? self::FAILURE : self::SUCCESS;
    }
}
