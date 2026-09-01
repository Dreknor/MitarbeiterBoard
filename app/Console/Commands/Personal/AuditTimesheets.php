<?php

namespace App\Console\Commands\Personal;

use App\Models\User;
use App\Services\Personal\TimeValidationService;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/**
 * Arbeitspaket 4.1: Täglicher Prüflauf der Prüfengine.
 * Auditiert automatisch die Daten des aktuellen und des vergangenen Monats
 * für alle Mitarbeiter mit (irgendeiner) Anstellung.
 */
class AuditTimesheets extends Command
{
    protected $signature = 'personal:audit-timesheets
        {--employe= : Nur einen bestimmten Mitarbeiter (User-ID) prüfen}
        {--month= : Nur einen bestimmten Monat prüfen (Format Y-m), Standard: aktueller + vorheriger Monat}
        {--from= : Beginn eines mehrmonatigen Prüfzeitraums (Format Y-m), zusammen mit --to}
        {--to= : Ende eines mehrmonatigen Prüfzeitraums (Format Y-m), zusammen mit --from}';

    protected $description = 'Führt die automatisierte Prüfengine (Zeiterfassung, Dienstpläne, Vertragsänderungen) für alle Mitarbeiter aus.';

    public function handle(TimeValidationService $validationService): int
    {
        if ($this->option('from') || $this->option('to')) {
            if (!$this->option('from') || !$this->option('to')) {
                $this->error('Bitte sowohl --from als auch --to angeben (Format Y-m).');
                return self::FAILURE;
            }

            $rangeStart = Carbon::createFromFormat('Y-m', $this->option('from'))->startOfMonth();
            $rangeEnd   = Carbon::createFromFormat('Y-m', $this->option('to'))->endOfMonth();
            $months     = null; // Zeitraum wird direkt an runForEmployeeRange übergeben
        } else {
            $months = $this->option('month')
                ? [Carbon::createFromFormat('Y-m', $this->option('month'))]
                : [Carbon::now(), Carbon::now()->subMonth()];
            $rangeStart = $rangeEnd = null;
        }

        $employees = $this->option('employe')
            ? User::where('id', $this->option('employe'))->get()
            : User::whereHas('employments')->get();

        $this->info($months !== null
            ? sprintf('Prüfengine: %d Mitarbeiter, %d Zeiträume.', $employees->count(), count($months))
            : sprintf('Prüfengine: %d Mitarbeiter, Zeitraum %s bis %s.', $employees->count(), $rangeStart->format('Y-m'), $rangeEnd->format('Y-m'))
        );

        $totalAnomalies = 0;

        foreach ($employees as $employe) {
            if ($months !== null) {
                foreach ($months as $month) {
                    if ($employe->employments_date($month->copy()->startOfMonth(), $month->copy()->endOfMonth())->count() < 1) {
                        continue;
                    }

                    try {
                        $anomalies = $validationService->runForEmployee($employe, $month);
                        $totalAnomalies += $anomalies->count();
                    } catch (\Throwable $e) {
                        Log::error('personal:audit-timesheets: Fehler beim Prüflauf', [
                            'employe' => $employe->id,
                            'month'   => $month->format('Y-m'),
                            'error'   => $e->getMessage(),
                        ]);
                        $this->error("Fehler bei Mitarbeiter {$employe->id} / {$month->format('Y-m')}: {$e->getMessage()}");
                    }
                }
                continue;
            }

            if ($employe->employments_date($rangeStart, $rangeEnd)->count() < 1) {
                continue;
            }

            try {
                $anomalies = $validationService->runForEmployeeRange($employe, $rangeStart, $rangeEnd);
                $totalAnomalies += $anomalies->count();
            } catch (\Throwable $e) {
                Log::error('personal:audit-timesheets: Fehler beim Zeitraum-Prüflauf', [
                    'employe' => $employe->id,
                    'von'     => $rangeStart->format('Y-m'),
                    'bis'     => $rangeEnd->format('Y-m'),
                    'error'   => $e->getMessage(),
                ]);
                $this->error("Fehler bei Mitarbeiter {$employe->id} / {$rangeStart->format('Y-m')}–{$rangeEnd->format('Y-m')}: {$e->getMessage()}");
            }
        }

        $this->info("Prüfengine abgeschlossen: {$totalAnomalies} Auffälligkeiten erzeugt.");

        return self::SUCCESS;
    }
}


