<?php

namespace App\Services\Procedure;

use App\Models\RecurringProcedure;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Robuster Runner für wiederkehrende Prozesse (§5.1 + §8.2).
 *
 * Härtungen ggü. der bisherigen `RecurringProcedureController::checkStart`:
 *  - Ferien-API über `Http::get` (testbar), Try/Catch, ohne Stray-Requests.
 *  - Datumsvergleich statt fehleranfälligem `diffInWeeks == X`.
 *  - Idempotenz via `last_triggered_at`.
 *  - Unterstützung der neuen Auslöser-Typen `wochentag` und `schuljahres_stichtag`.
 */
class RecurringProcedureRunner
{
    public function __construct(private readonly ProcedureService $procedureService) {}

    /**
     * Berechnet das nächste voraussichtliche Auslösedatum eines RP.
     */
    public function calculateNextTrigger(RecurringProcedure $rp, ?Carbon $from = null): ?Carbon
    {
        $from = ($from ?? now())->copy()->startOfDay();

        switch ($rp->faelligkeit_typ) {
            case 'datum':
                if (!$rp->month) return null;
                $candidate = Carbon::create($from->year, $rp->month, 1);
                if ($candidate->lt($from)) {
                    $candidate->addYear();
                }
                return $candidate;

            case 'wochentag':
                if ($rp->weekday === null) return null;
                $interval = max(1, (int) ($rp->weekday_interval ?? 1));
                $next = $from->copy();
                while ($next->dayOfWeekIso !== ((int) $rp->weekday + 1)) { // 0=Mo, …, 6=So → ISO 1..7
                    $next->addDay();
                }
                if ($rp->last_triggered_at) {
                    $weeksSince = $rp->last_triggered_at->copy()->startOfDay()->diffInWeeks($next);
                    if ($weeksSince < $interval) {
                        $next->addWeeks($interval - $weeksSince);
                    }
                }
                return $next;

            case 'schuljahres_stichtag':
                if (!$rp->schuljahres_tag || !$rp->schuljahres_monat) return null;
                $candidate = Carbon::create($from->year, $rp->schuljahres_monat, $rp->schuljahres_tag);
                if ($candidate->lt($from)) $candidate->addYear();
                return $candidate;

            case 'vor_ferien':
            case 'nach_ferien':
                $ferien = $this->findFerien($rp, $from->year);
                if (!$ferien) return null;
                $start = Carbon::createFromFormat('Y-m-d', $ferien->start);
                $offsetWeeks = (int) ($rp->wochen ?? 0);
                return $rp->faelligkeit_typ === 'vor_ferien'
                    ? $start->copy()->subWeeks($offsetWeeks)
                    : $start->copy()->addWeeks($offsetWeeks);
        }

        return null;
    }

    /**
     * Täglich aufzurufender Scheduler-Eintrittspunkt.
     */
    public function check(): void
    {
        $today = now()->startOfDay();
        $rps = RecurringProcedure::where('active', true)->with('procedure.steps.position.users')->get();

        foreach ($rps as $rp) {
            try {
                $next = $this->calculateNextTrigger($rp, $today);
                $rp->update(['next_trigger_at' => $next]);

                if (!$next) continue;
                if (!$next->isSameDay($today) && !$this->isMissedRun($rp, $next, $today)) {
                    continue;
                }
                if ($rp->last_triggered_at && $rp->last_triggered_at->isSameDay($today)) {
                    continue;
                }

                $this->trigger($rp);
            } catch (\Throwable $e) {
                Log::error('RecurringProcedureRunner: Fehler beim Prüfen', [
                    'rp'    => $rp->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }

    /** Startet einen RP manuell oder per Scheduler. */
    public function trigger(RecurringProcedure $rp): \App\Models\Procedure
    {
        if (!$rp->procedure) {
            throw new \RuntimeException("RecurringProcedure {$rp->id} hat keine Vorlage.");
        }

        $instance = $this->procedureService->startFromTemplate(
            $rp->procedure,
            [
                'name'       => $rp->name . ' - ' . now()->format('Y'),
                'started_at' => now(),
            ],
            $rp->procedure->author_id
        );

        $rp->update(['last_triggered_at' => now()]);

        return $instance;
    }

    private function isMissedRun(RecurringProcedure $rp, Carbon $next, Carbon $today): bool
    {
        // Toleranz: ein verpasster Tag (z.B. Scheduler-Ausfall) wird nachgeholt.
        if ($next->lt($today) && $next->diffInDays($today) <= 1) {
            return !$rp->last_triggered_at || $rp->last_triggered_at->lt($next);
        }
        return false;
    }

    private function findFerien(RecurringProcedure $rp, int $year): ?object
    {
        if (!$rp->ferien) return null;

        $state = function_exists('settings') ? settings('ferien_state', 'holidays') : 'holidays';

        $list = Cache::remember("ferien_runner_{$state}_{$year}", 60 * 60 * 24, function () use ($state, $year) {
            try {
                $resp = Http::timeout(5)->get("https://ferien-api.de/api/v1/holidays/{$state}/{$year}");
                if (!$resp->ok()) return [];
                return $resp->json() ?? [];
            } catch (\Throwable $e) {
                Log::warning('Ferien-API nicht erreichbar', ['error' => $e->getMessage()]);
                return [];
            }
        });

        foreach ($list as $f) {
            $f = (object) $f;
            if (isset($f->name) && $f->name === $rp->ferien) {
                return $f;
            }
        }
        return null;
    }
}

