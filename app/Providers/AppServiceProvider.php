<?php

namespace App\Providers;

use App\Models\DailyNews;
use App\Models\User;
use App\Models\Vertretung;
use App\Models\VertretungsplanAbsence;
use App\Models\VertretungsplanWeek;
use App\Observers\UserObserver;
use App\Observers\VertretungNewsObserver;
use App\Observers\VertretungObserver;
use App\Observers\VertretungsplanAbsenceObserver;
use App\Observers\VertretungWeekObserver;
use App\Support\Collection;
use Carbon\Carbon;
use danielme85\LaravelLogToDB\LogToDB;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Pagination\Paginator;
use Illuminate\Queue\Events\JobFailed;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     *
     * @return void
     */
    public function register()
    {
        //
    }

    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot()
    {
        Paginator::useBootstrap();

        User::observe(UserObserver::class);
        Vertretung::observe(VertretungObserver::class);
        VertretungsplanAbsence::observe(VertretungsplanAbsenceObserver::class);
        DailyNews::observe(VertretungNewsObserver::class);
        VertretungsplanWeek::observe(VertretungWeekObserver::class);

        Carbon::setUTF8(true);
        Carbon::setLocale(config('app.locale'));
        setlocale(LC_TIME, config('app.locale'));


        Queue::failing(function (JobFailed $event) {
            $job = $event->job;
            $exception = $event->exception;

            Log::error('Job failed: ' . $job->resolveName(), [
                'job' => $job,
                'exception' => $exception,
                'payload' => $job->payload(),
            ]);


        });

        Collection::macro('paginate', function ($perPage, $total = null, $page = null, $pageName = 'page') {
            $page = $page ?: LengthAwarePaginator::resolveCurrentPage($pageName);

            return new LengthAwarePaginator(
                $this->forPage($page, $perPage),
                $total ?: $this->count(),
                $perPage,
                $page,
                [
                    'path' => LengthAwarePaginator::resolveCurrentPath(),
                    'pageName' => $pageName,
                ]
            );
        });

        Collection::macro('sortByDate', function ($column = 'created_at', $order = SORT_DESC) {
            /* @var $this Collection */
            try {
                return $this->sortBy(function ($datum) use ($column) {
                    if (!is_null($datum) and !is_null($datum->$column)){
                        return strtotime($datum->$column);
                    } else {
                        return 0;
                    }

                }, SORT_REGULAR, $order == SORT_DESC);
            } catch (\Exception $e) {
                Log::error('sortByDate failed: ', [
                    'column' => $column,
                    'order' => $order,
                    'exception' => $e->getMessage(),
                ]);
                return $this;
            }

        });

        /**
         * Filter RosterEvents for Employe and Time
         *
         * @param User $employe Carbon $carbon
         * @param Carbon $carbon
         * @return \Illuminate\Support\Collection
         */
        Collection::macro('searchRosterEvent', function (User $employe, Carbon $carbon) {
            return $this->filter(function ($roster_event) use ($employe, $carbon) {
                if ($roster_event->employe_id == $employe->id and $roster_event->start->lessThanOrEqualTo($carbon) and $roster_event->end->greaterThan($carbon)) {
                    return $roster_event;
                }
            });
        });
        /**
         * Filter WorkingTimeCollectionForDay for Employe and Time
         *
         * @param User $employe Carbon $carbon
         * @param Carbon $carbon
         * @return Collection
         */
        Collection::macro('searchWorkingTime', function (User $employe, Carbon $carbon) {
            return $this->filter(function ($working_time) use ($employe, $carbon) {
                if ($working_time->employe_id == $employe->id and $working_time->date->isSameDay($carbon)) {
                    return $working_time;
                }
            });
        });

        Collection::macro('filterDay', function(Carbon $day){
            return $this->filter(function ($item) use ($day){
                if (array_key_exists('date', $item->toArray())){
                    return $item->date?->format('Y-m-d') == $day->format('Y-m-d');
                }
            });
        });
    }
}
