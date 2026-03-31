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
        View::composer('personal.holidays.dashboardCard', \App\View\Composers\UrlaubCardComposer::class);
        View::composer('personal.rosters.homeView', \App\View\Composers\RosterComposer::class);
        View::composer('tasks.tasksCard', \App\View\Composers\TasksComposer::class);
        View::composer('procedure.dashboardCard', \App\View\Composers\ProcedureComposer::class);
        View::composer('wiki.dashboardCard', \App\View\Composers\WikiCardComposer::class);
        View::composer('absences.dashboardCard', \App\View\Composers\AbsenceComposer::class);
        View::composer('personal.time_recording.dashboardCard', \App\View\Composers\TimeRecordingCardComposer::class);
        View::composer('personal.time_recording.dashboardCardOwn', \App\View\Composers\TimeRecordingCardOwnComposer::class);
        View::composer('vertretungsplan.UserVertretungen', \App\View\Composers\VertretungenComposer::class);
        View::composer('rooms.rooms.freeRoomsCard', \App\View\Composers\RoomsComposer::class);
        View::composer('ticketsystem.dashboardCard', \App\View\Composers\TicketsCardComposer::class); // Ticketsystem Card
        View::composer('atom-feed.dashboardCard', \App\View\Composers\AtomFeedComposer::class);
        View::composer('calendar.dashboardCard', \App\View\Composers\CalendarComposer::class);
        View::composer('personal.hort_planung.dashboardCard', \App\View\Composers\HortPlanungComposer::class);
        View::composer('personal.employes._expiring_contracts_card', \App\View\Composers\ExpiringContractsComposer::class);
        // Phase 2 – Dokumentenmanagement
        View::composer('personal.documents._expiring_documents_card', \App\View\Composers\ExpiringDocumentsComposer::class);
        View::composer('personal.documents._nc_sync_fehler_card', \App\View\Composers\SyncFehlerComposer::class);
        // Phase 2 – Qualifikationen
        View::composer('personal.qualifications._missing_qualifications_card', \App\View\Composers\MissingQualificationsComposer::class);
        // Phase 2 – Fortbildungen
        View::composer('personal.trainings._upcoming_trainings_card', \App\View\Composers\UpcomingTrainingsComposer::class);
    }
}
