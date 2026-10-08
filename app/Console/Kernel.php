<?php

namespace App\Console;

use App\Http\Controllers\Personal\TimesheetController;
use App\Console\Commands\RemindProcedureUser;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    /**
     * The Artisan commands provided by your application.
     *
     * @var array
     */
    protected $commands = [
        RemindProcedureUser::class,
        \App\Console\Commands\Personal\ReEncryptPersonalData::class,
        \App\Console\Commands\Personal\AuditTimesheets::class,
        \App\Console\Commands\Personal\PruefeZeitUmstellung::class,
        \App\Console\Commands\Personal\BeendeAbgelaufeneVertraege::class,
    ];

    /**
     * Define the application's command schedule.
     *
     * @param  \Illuminate\Console\Scheduling\Schedule  $schedule
     * @return void
     */
    protected function schedule(Schedule $schedule)
    {
        // $schedule->command('inspire')->hourly();->weeklyOn(1, '8:00');
        $schedule->call('App\Http\Controllers\MailController@remind')->fridays()->at('12:00');
        $schedule->call('App\Http\Controllers\MailController@invitation')->dailyAt('12:00');
        $schedule->call('App\Http\Controllers\MailController@remindTaskMail')->mondays()->at('07:15');
        $schedule->call('App\Http\Controllers\ThemeController@remind_assigned_themes')->mondays()->at('07:15');
        // Täglicher Abwesenheitsbericht ist in der Tagesübersicht (benachrichtigungen:tagesvorschau) aufgegangen
        $schedule->call('App\Http\Controllers\ProcedureController@remindStepMail')->weekdays()->at('07:30');
        $schedule->call('App\Http\Controllers\GroupController@deleteOldGroups')->daily();
        $schedule->call('App\Http\Controllers\RecurringThemeController@createNewThemes')->dailyAt('07:00');
        $schedule->call('App\Http\Controllers\PostsController@dailyMail')->dailyAt('20:00');
        $schedule->call('App\Http\Controllers\Personal\TimesheetController@timesheet_mail')->monthlyOn(1, '8:00');

        //Recurring procedures
        $schedule->call('App\Http\Controllers\RecurringProcedureController@checkStart')->dailyAt('01:00');

        //Close old tickets
        $schedule->call('App\Http\Controllers\Ticketsystem\TicketController@closeTicketAfterTime')->dailyAt('02:00');

        // Cleanup expired grading tokens
        $schedule->command('grading:cleanup-tokens')->daily();

        // Pädagogen-App: Idempotency-Keys (48 h) und abgelaufene App-Tokens aufräumen
        $schedule->command('paed-app:prune-idempotency')->hourly();
        $schedule->command('sanctum:prune-expired --hours=24')->daily();

        // VP-Raumbuchungen aufräumen (älter als X Tage, konfigurierbar via settings)
        $schedule->command('room-bookings:cleanup-vp')->weekly();

        // Kalender-Synchronisation (OX CalDAV)
        $syncInterval = (int) (\App\Models\Setting::where('module', 'Kalender')
            ->where('setting', 'calendar_sync_interval')
            ->value('value') ?? 15);

        $schedule->command('ox:sync-calendars')
            ->cron("*/{$syncInterval} * * * *")
            ->withoutOverlapping(30) // Max. Lock-Zeit: 30 Minuten
            ->runInBackground()
            ->appendOutputTo(storage_path('logs/ox-sync.log'));

        // Kalender Sync-Log-Bereinigung (täglich um 03:00)
        $schedule->call(function () {
            $aufbewahrungTage = (int) (\App\Models\Setting::where('module', 'Kalender')
                ->where('setting', 'calendar_log_aufbewahrung_tage')
                ->value('value') ?? 90);

            $deleted = \App\Models\OxSyncLog::where('created_at', '<', now()->subDays($aufbewahrungTage))
                ->delete(); // Hart löschen (kein SoftDeletes)

            if ($deleted > 0) {
                \Illuminate\Support\Facades\Log::info("Kalender: {$deleted} alte Sync-Logs gelöscht (>{$aufbewahrungTage} Tage)");
            }
        })->dailyAt('03:00')->name('calendar-log-cleanup');

        // Personal-Modul: Audit-Log-Bereinigung (monatlich am 1. um 03:30)
        $schedule->call(function () {
            $deleted = app(\App\Services\Personal\PersonalAuditService::class)->cleanupOldLogs();
            \Illuminate\Support\Facades\Log::info("Personal: {$deleted} alte Zugriffs-Logs bereinigt.");
        })->monthlyOn(1, '03:30')->name('personal-audit-cleanup');

        // Phase 2: Nextcloud Konsistenz-Check (täglich um 02:00)
        $schedule->job(new \App\Jobs\Personal\CheckNextcloudConsistency)
            ->dailyAt('02:00')
            ->name('nc-consistency-check')
            ->withoutOverlapping(60);

        // Phase 2: Ablaufende Dokumente prüfen und Erinnerungen versenden (täglich um 07:15)
        $schedule->call(function () {
            app(\App\Services\Personal\PersonalDocumentService::class)->checkExpiringDocuments();
        })->dailyAt('07:15')->name('personal-expiring-documents')->withoutOverlapping();

        // Phase 2: Ablaufende Qualifikationen prüfen und Erinnerungen versenden (täglich um 07:30)
        $schedule->call(function () {
            app(\App\Services\Personal\QualificationService::class)->checkExpiringQualifications();
        })->dailyAt('07:30')->name('personal-expiring-qualifications')->withoutOverlapping();

        // Wiedervorlagen (Probezeit, Vertragsende, Aufbewahrungsfristen) an die Personalverwaltung melden
        $schedule->call(function () {
            app(\App\Services\Personal\PersonalReminderService::class)->notifyDue();
        })->dailyAt('07:45')->name('personal-reminders')->withoutOverlapping();

        // Abgelaufene Verträge vor der Prüfengine auf "beendet" setzen (Offboarding nur ohne Folgevertrag)
        $schedule->command('personal:vertraege-abschliessen')
            ->dailyAt('02:30')
            ->name('personal-vertraege-abschliessen')
            ->withoutOverlapping(30);

        // Benachrichtigungen: Tagesübersicht „Dein Tag“ (Uhrzeit pro Person, morgens oder am Vorabend)
        $schedule->command('benachrichtigungen:tagesvorschau')
            ->everyFifteenMinutes()
            ->name('benachrichtigungen-tagesvorschau')
            ->withoutOverlapping(14);

        // Benachrichtigungen: Nachmittags-Zusammenfassung (Mail-Modus „Zusammenfassung“)
        $schedule->command('benachrichtigungen:zusammenfassung')
            ->weekdays()
            ->at(config('benachrichtigungen.zusammenfassung.uhrzeit', '16:00'))
            ->name('benachrichtigungen-zusammenfassung')
            ->withoutOverlapping(30);

        // Benachrichtigungen: gelesene Einträge nach Aufbewahrungsfrist löschen (täglich um 03:15)
        $schedule->call(function () {
            $tage = (int) config('benachrichtigungen.aufbewahrung_tage', 180);
            $deleted = \Illuminate\Notifications\DatabaseNotification::whereNotNull('read_at')
                ->where('read_at', '<', now()->subDays($tage))
                ->delete();
            if ($deleted > 0) {
                \Illuminate\Support\Facades\Log::info("Benachrichtigungen: {$deleted} alte gelesene Einträge gelöscht (>{$tage} Tage)");
            }
        })->dailyAt('03:15')->name('benachrichtigungen-aufraeumen');

        // Arbeitspaket 4.1: Prüfengine für Zeiterfassung, Dienstpläne & Vertragsänderungen (täglich um 03:00)
        $schedule->command('personal:audit-timesheets')
            ->dailyAt('03:00')
            ->name('personal-audit-timesheets')
            ->withoutOverlapping(60)
            ->appendOutputTo(storage_path('logs/personal-audit-timesheets.log'));
    }

    /**
     * Register the commands for the application.
     *
     * @return void
     */
    protected function commands()
    {
        $this->load(__DIR__.'/Commands');

        require base_path('routes/console.php');
    }
}
