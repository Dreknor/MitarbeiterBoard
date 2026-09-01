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
        {--month= : Nur einen bestimmten Monat prüfen (Format Y-m), Standard: aktueller + vorheriger Monat}';

    protected $description = 'Führt die automatisierte Prüfengine (Zeiterfassung, Dienstpläne, Vertragsänderungen) für alle Mitarbeiter aus.';

    public function handle(TimeValidationService $validationService): int
    {
        $months = $this->option('month')
            ? [Carbon::createFromFormat('Y-m', $this->option('month'))]
            : [Carbon::now(), Carbon::now()->subMonth()];

        $employees = $this->option('employe')
            ? User::where('id', $this->option('employe'))->get()
            : User::whereHas('employments')->get();

        $this->info(sprintf('Prüfengine: %d Mitarbeiter, %d Zeiträume.', $employees->count(), count($months)));

        $totalAnomalies = 0;

        foreach ($employees as $employe) {
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
        }

        $this->info("Prüfengine abgeschlossen: {$totalAnomalies} Auffälligkeiten erzeugt.");

        return self::SUCCESS;
    }
}

