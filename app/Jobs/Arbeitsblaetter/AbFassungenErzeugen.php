<?php

namespace App\Jobs\Arbeitsblaetter;

use App\Models\Arbeitsblaetter\AngepasstesBlatt;
use App\Models\User;
use App\Services\Arbeitsblaetter\AbAusgabeService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/** Fassungen für Kinder/Vorlagen erzeugen und in der Nextcloud ablegen (Ausgabe-Seite). */
class AbFassungenErzeugen implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 2;

    public int $timeout = 600;

    public function __construct(
        public int $blattId,
        public array $schuelerIds,
        public array $vorlageIds,
        public ?int $userId = null,
        public bool $nextcloud = true,
    ) {
    }

    public function handle(AbAusgabeService $ausgabe): void
    {
        $blatt = AngepasstesBlatt::find($this->blattId);
        if (! $blatt) {
            return;
        }
        $fassungen = $ausgabe->erzeugen($blatt, $this->schuelerIds, $this->vorlageIds, $this->userId ? User::find($this->userId) : null);

        if ($this->nextcloud) {
            foreach ($fassungen as $fassung) {
                if (! $ausgabe->ablegen($fassung)) {
                    Log::warning('Angepasste Arbeitsblätter: Fassung nicht abgelegt', ['fassung' => $fassung->id]);
                }
            }
        }
    }
}
