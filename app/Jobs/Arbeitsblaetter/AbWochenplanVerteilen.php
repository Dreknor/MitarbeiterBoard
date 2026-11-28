<?php

namespace App\Jobs\Arbeitsblaetter;

use App\Models\Arbeitsblaetter\AngepasstesBlatt;
use App\Services\Arbeitsblaetter\AbWochenplanService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/** Nach der Freigabe: Fassungen für alle Profilkinder der Klasse an deren Kinderpläne hängen. */
class AbWochenplanVerteilen implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 2;

    public int $timeout = 600;

    public function __construct(public int $blattId, public ?int $userId = null)
    {
    }

    public function handle(AbWochenplanService $service): void
    {
        $blatt = AngepasstesBlatt::find($this->blattId);
        if ($blatt && $blatt->istFreigegeben()) {
            $service->fassungenVerteilen($blatt, $this->userId);
        }
    }
}
