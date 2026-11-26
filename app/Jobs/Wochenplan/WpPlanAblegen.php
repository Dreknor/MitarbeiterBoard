<?php

namespace App\Jobs\Wochenplan;

use App\Models\Wochenplan\WpPlan;
use App\Services\Wochenplan\WpAblageService;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Legt einen Plan als PDF und Word in der Nextcloud ab. Bündelt Änderungen:
 * Läuft nur, wenn seit dem eigenen Anforderungszeitpunkt keine neuere Anforderung kam.
 */
class WpPlanAblegen implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $timeout = 180;

    public function __construct(public int $planId, public string $angefordertAt)
    {
    }

    public function backoff(): array
    {
        return [60, 300];
    }

    public function handle(WpAblageService $ablage): void
    {
        $plan = WpPlan::find($this->planId);
        if (! $plan || $plan->isVorlage()) {
            return;
        }

        // Neuere Anforderung vorhanden → der spätere Job übernimmt
        if ($plan->ablage_angefordert_at
            && $plan->ablage_angefordert_at->gt(Carbon::parse($this->angefordertAt)->addSecond())) {
            return;
        }

        $ablage->ablegen($plan);
    }
}
