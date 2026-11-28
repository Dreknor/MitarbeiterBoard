<?php

namespace App\Jobs\Arbeitsblaetter;

use App\Services\Arbeitsblaetter\AbWochenplanService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/** Neues/geändertes Sehprofil: Fassungen in laufenden und künftigen Wochenplänen nachziehen. */
class AbKindNachziehen implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 2;

    public int $timeout = 600;

    public function __construct(public int $schuelerId)
    {
    }

    public function handle(AbWochenplanService $service): void
    {
        $service->kindNachziehen($this->schuelerId);
    }
}
