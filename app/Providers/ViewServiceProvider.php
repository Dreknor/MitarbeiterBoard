<?php

namespace App\Providers;

use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class ViewServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        View::composer('posts.dashboardCard', \App\View\Composers\NachrichtenComposer::class);
        View::composer('personal.rosters.homeView', \App\View\Composers\RosterComposer::class);
        View::composer('tasks.tasksCard', \App\View\Composers\TasksComposer::class);
        View::composer('procedure.dashboardCard', \App\View\Composers\ProcedureComposer::class);
        View::composer('wiki.dashboardCard', \App\View\Composers\WikiCardComposer::class);
        View::composer('absences.index', \App\View\Composers\AbsenceComposer::class);
        View::composer('absences.index', \App\View\Composers\AbsenceComposer::class);
        View::composer('personal.time_recording.dashboardCard', \App\View\Composers\TimeRecordingCardComposer::class);
    }
}
