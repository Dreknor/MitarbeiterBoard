<?php

namespace App\Console\Commands\Personal;

use App\Services\Personal\ContractService;
use Illuminate\Console\Command;

class BeendeAbgelaufeneVertraege extends Command
{
    protected $signature   = 'personal:vertraege-abschliessen';
    protected $description = 'Setzt Anstellungen, deren Enddatum überschritten ist, auf "beendet" (Offboarding nur ohne Folgevertrag)';

    public function handle(ContractService $contracts): int
    {
        $count = $contracts->endExpired();
        $this->info("{$count} abgelaufene Anstellung(en) beendet.");

        return self::SUCCESS;
    }
}
