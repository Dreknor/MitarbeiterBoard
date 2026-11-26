<?php

namespace App\Jobs\Wochenplan;

use App\Models\Wochenplan\WpPlan;
use App\Services\Wochenplan\WpAblageService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/** Verschiebt die Ablage eines gelöschten Plans nach `_gelöscht/`. */
class WpPlanAblageEntfernen implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public function __construct(public int $planId)
    {
    }

    public function handle(WpAblageService $ablage): void
    {
        $plan = WpPlan::withTrashed()->find($this->planId);
        if ($plan) {
            $ablage->geloeschtAblegen($plan);
        }
    }
}
