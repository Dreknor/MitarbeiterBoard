<?php

namespace App\Console;

use App\Http\Controllers\Personal\TimesheetController;
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
        //
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
        $schedule->call('App\Http\Controllers\MailController@invitation')->dailyAt('23:00');
        $schedule->call('App\Http\Controllers\MailController@remindTaskMail')->mondays()->at('07:15');
        $schedule->call('App\Http\Controllers\ThemeController@remind_assigned_themes')->mondays()->at('07:15');
        $schedule->call('App\Http\Controllers\AbsenceController@dailyReport')->weekdays()->at('07:30');
        $schedule->call('App\Http\Controllers\ProcedureController@remindStepMail')->weekdays()->at('07:30');
        $schedule->call('App\Http\Controllers\GroupController@deleteOldGroups')->daily();
        $schedule->call('App\Http\Controllers\RecurringThemeController@createNewThemes')->dailyAt('07:00');
        $schedule->call('App\Http\Controllers\PostsController@dailyMail')->dailyAt('20:00');
        $schedule->call('App\Http\Controllers\Personal\TimesheetController@timesheet_mail')->monthlyOn(1, '8:00');
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
