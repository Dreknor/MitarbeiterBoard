<?php

namespace App\Console;

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
        $schedule->call('App\Http\Controllers\MailController@remindTaskMail')->dailyAt('07:15');
        $schedule->call('App\Http\Controllers\ProcedureController@remindStepMail')->mondays()->at('07:30');
        $schedule->call('App\Http\Controllers\GroupController@deleteOldGroups')->daily();
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
