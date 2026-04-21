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
        View::composer('rooms.rooms.freeRoomsCard-v2', \App\View\Composers\RoomsComposer::class);
        View::composer('ticketsystem.dashboardCard', \App\View\Composers\TicketsCardComposer::class); // Ticketsystem Card
        View::composer('ticketsystem.dashboardCard-v2', \App\View\Composers\TicketsCardComposer::class);
        View::composer('atom-feed.dashboardCard', \App\View\Composers\AtomFeedComposer::class);
        View::composer('atom-feed.dashboardCard-v2', \App\View\Composers\AtomFeedComposer::class);
        View::composer('calendar.dashboardCard', \App\View\Composers\CalendarComposer::class);
        View::composer('calendar.dashboardCard-v2', \App\View\Composers\CalendarComposer::class);
        View::composer('wiki.dashboardCard-v2', \App\View\Composers\WikiCardComposer::class);
        View::composer('personal.hort_planung.dashboardCard', \App\View\Composers\HortPlanungComposer::class);
        View::composer('personal.employes._expiring_contracts_card', \App\View\Composers\ExpiringContractsComposer::class);
        // Phase 1 – Dashboard v2 neue Cards
        View::composer('dashboard.cards.geburtstage', \App\View\Composers\GeburtstageComposer::class);
        View::composer('dashboard.cards.tagesinfos', \App\View\Composers\TagesinfosComposer::class);
        View::composer('dashboard.cards.benachrichtigungen', \App\View\Composers\BenachrichtigungenComposer::class);
        // Phase 2 – v2-Varianten bestehender Cards
        View::composer('posts.dashboardCard-v2', \App\View\Composers\NachrichtenComposer::class);
        View::composer('tasks.tasksCard-v2', \App\View\Composers\TasksComposer::class);
        View::composer('procedure.dashboardCard-v2', \App\View\Composers\ProcedureComposer::class);
        View::composer('absences.dashboardCard-v2', \App\View\Composers\AbsenceComposer::class);
        View::composer('personal.holidays.dashboardCard-v2', \App\View\Composers\UrlaubCardComposer::class);
        View::composer('personal.time_recording.dashboardCard-v2', \App\View\Composers\TimeRecordingCardComposer::class);
        View::composer('personal.time_recording.dashboardCardOwn-v2', \App\View\Composers\TimeRecordingCardOwnComposer::class);
        View::composer('personal.hort_planung.dashboardCard-v2', \App\View\Composers\HortPlanungComposer::class);
        // Phase 2 – neue Dashboard-Cards
        View::composer('dashboard.cards.meetings', \App\View\Composers\MeetingsComposer::class);
        View::composer('dashboard.cards.terminlisten', \App\View\Composers\TerminlistenComposer::class);
        View::composer('dashboard.cards.qualifikationen', \App\View\Composers\QualifikationenComposer::class);
        View::composer('dashboard.cards.schnellzugriff', \App\View\Composers\SchnellzugriffComposer::class);
        View::composer('personal.documents._expiring_documents_card', \App\View\Composers\ExpiringDocumentsComposer::class);
        View::composer('personal.documents._nc_sync_fehler_card', \App\View\Composers\SyncFehlerComposer::class);
        // Phase 2 – Qualifikationen
        View::composer('personal.qualifications._missing_qualifications_card', \App\View\Composers\MissingQualificationsComposer::class);
        // Phase 2 – Fortbildungen
        View::composer('personal.trainings._upcoming_trainings_card', \App\View\Composers\UpcomingTrainingsComposer::class);
        // Phase 2 – Pädagogisches Tagebuch Dashboard-Card
        View::composer('dashboard.cards.paed_diary', \App\View\Composers\PaedDiaryComposer::class);
    }
}
