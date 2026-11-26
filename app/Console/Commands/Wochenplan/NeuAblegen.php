<?php

namespace App\Console\Commands\Wochenplan;

use App\Services\Wochenplan\WpAblageService;
use Illuminate\Console\Command;

/** Legt alle Wochenpläne neu in der Nextcloud ab (z. B. nach geänderter Ordnerstruktur). */
class NeuAblegen extends Command
{
    protected $signature = 'wochenplan:neu-ablegen';

    protected $description = 'Fordert für alle Wochenpläne eine neue Nextcloud-Ablage an';

    public function handle(WpAblageService $ablage): int
    {
        if (! $ablage->aktiv()) {
            $this->warn('Die Nextcloud-Ablage ist nicht aktiv (Einstellung „Nextcloud-Ablage aktiv“ und NEXTCLOUD_WOCHENPLAN_* prüfen).');

            return self::FAILURE;
        }

        $this->info($ablage->alleNeuAblegen().' Pläne zur Ablage angefordert.');

        return self::SUCCESS;
    }
}
