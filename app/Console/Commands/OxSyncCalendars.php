<?php

namespace App\Console\Commands;

use App\Models\OxCalendar;
use App\Services\OxCalendarService;
use Illuminate\Console\Command;

class OxSyncCalendars extends Command
{
    protected $signature = 'ox:sync-calendars
                            {--calendar= : Nur einen bestimmten Kalender synchronisieren (Name oder ID)}
                            {--force : Sync auch wenn Modul deaktiviert ist}';

    protected $description = 'Synchronisiert Kalender-Termine von Open-Xchange via CalDAV';

    public function handle(OxCalendarService $service): int
    {
        if (!$service->isEnabled() && !$this->option('force')) {
            $this->warn('Kalender-Modul ist deaktiviert. Nutze --force zum Erzwingen.');
            return Command::SUCCESS;
        }

        $this->info('🔄 Starte Kalender-Synchronisation...');
        $this->newLine();

        $startTime = microtime(true);
        $results   = [];

        // Einzelner Kalender?
        if ($calendarFilter = $this->option('calendar')) {
            $calendar = OxCalendar::where('name', $calendarFilter)
                ->orWhere('id', $calendarFilter)
                ->first();

            if (!$calendar) {
                $this->error("❌ Kalender '{$calendarFilter}' nicht gefunden.");
                return Command::FAILURE;
            }

            $results = [$calendar->name => $service->syncCalendar($calendar)];

        } elseif ($this->option('force')) {
            // --force: isEnabled()-Prüfung in syncAll() umgehen
            $calendars = OxCalendar::where('sichtbar', true)->get();
            foreach ($calendars as $calendar) {
                $results[$calendar->name] = $service->syncCalendar($calendar);
            }

        } else {
            $results = $service->syncAll();
        }

        if (empty($results)) {
            $this->warn('Keine Kalender zum Synchronisieren gefunden.');
            return Command::SUCCESS;
        }

        // Ergebnis-Tabelle anzeigen
        $rows        = [];
        $totalErrors = 0;

        foreach ($results as $name => $result) {
            $rows[] = [
                $name,
                $result['method'] ?: '-',
                $result['created'],
                $result['updated'],
                $result['deleted'],
                $result['errors'],
            ];
            $totalErrors += $result['errors'];
        }

        $this->table(
            ['Kalender', 'Methode', 'Neu', 'Aktualisiert', 'Gelöscht', 'Fehler'],
            $rows
        );

        $duration = round(microtime(true) - $startTime, 2);
        $this->newLine();
        $this->info("✅ Synchronisation abgeschlossen ({$duration}s)");

        if ($totalErrors > 0) {
            $this->warn("⚠️  {$totalErrors} Fehler aufgetreten. Details in ox_sync_log.");
            return Command::FAILURE;
        }

        return Command::SUCCESS;
    }
}

