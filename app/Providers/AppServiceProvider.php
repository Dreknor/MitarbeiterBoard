<?php

namespace App\Providers;

use App\Models\Employe;
use App\Models\User;
use App\Observers\UserObserver;
use App\Support\Collection;
use Carbon\Carbon;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Pagination\Paginator;
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

        Carbon::setUTF8(true);
        Carbon::setLocale(config('app.locale'));
        setlocale(LC_TIME, config('app.locale'));

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
            return $this->sortBy(function ($datum) use ($column) {
                return strtotime($datum->$column);
            }, SORT_REGULAR, $order == SORT_DESC);
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
