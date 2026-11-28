<?php

namespace App\Jobs\Arbeitsblaetter;

use App\Models\Wochenplan\WpDatei;
use App\Services\Arbeitsblaetter\AbWochenplanService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/** Arbeitsblatt eines Klassenplans als Angepasstes Blatt übernehmen und erkennen. */
class AbWochenplanDateiUebernehmen implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 2;

    public int $timeout = 300;

    public function __construct(public int $wpDateiId, public ?int $userId = null, public bool $erzwingen = false)
    {
    }

    public function handle(AbWochenplanService $service): void
    {
        $datei = WpDatei::find($this->wpDateiId);
        if ($datei) {
            $service->dateiUebernehmen($datei, $this->userId, $this->erzwingen);
        }
    }
}
