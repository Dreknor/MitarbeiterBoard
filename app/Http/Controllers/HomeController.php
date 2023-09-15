<?php

namespace App\Http\Controllers;

use App\Models\Absence;
use App\Models\DashboardCard;
use App\Models\personal\Roster;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;


class HomeController extends Controller
{
    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct()
    {
        $this->middleware('auth');
    }


    public function index(){

        $defaultCards = DashboardCard::all();
        $cards = auth()->user()->dashboardCards;

        foreach ($defaultCards as $card){

            if ($cards->where('dashboard_card_id', $card->id)->count() < 1 and ($card->permission == null or auth()->user()->can($card->permission))){

                if ($cards->where('row', $card->default_row)->where('col', $card->default_col)->count() == 0){
                    $row = $card->default_row;
                    $col = $card->default_col;
                } else {
                    $row = $cards->last()->row + 1;
                    $col = $cards->last()->col + 1;
                }

                auth()->user()->dashboardCards()->insert([
                    'dashboard_card_id' => $card->id,
                    'user_id' => auth()->id(),
                    'row' => $card->default_row,
                    'col' => $card->default_col,
                    'active' => true
                ]);
            }
        }

        return view('dashboard.dashboard', [
            'cards' => auth()->user()->dashboardCards()->active()->order()->get()
                ]);
    }
    /**
     * Show the application dashboard.
     *
     * @return \Illuminate\Contracts\Support\Renderable
     */
    /*public function index()
    {
        $colors = ['#6495ed', 'Orange', '#ffca00', '#d9335c', '#99ff80', 'Persian Green', '#bfffff', '#bf7660', '#b3b017', '#149ab5'];
        $rand = ['0', '1', '2', '3', '4', '5', '6', '7', '8', '9', 'a', 'b', 'c', 'd', 'e', 'f'];

        for ($x = 0; $x++; $x < 10) {
            $color[] = '#'.$rand[rand(0, 15)].$rand[rand(0, 15)].$rand[rand(0, 15)].$rand[rand(0, 15)].$rand[rand(0, 15)].$rand[rand(0, 15)];
        }

        $groups = auth()->user()->groups();
        $groups->load('themes', 'themes.group', 'tasks', 'tasks.taskUsers');


        $tasks = auth()->user()->tasks;
        $group_tasks =  auth()->user()->group_tasks;

        foreach ($group_tasks as $group_task){
            $tasks = $tasks->push($group_task->task);
        }

        foreach ($groups as $group) {
            if ($group->proteced or auth()->user()->groups_rel->contains('id', $group->id)) {
                $group_tasks=$group->tasks()->whereDate('date', '>=', Carbon::now())->whereHas('taskUsers', function (Builder $query) {
                    $query->where('users_id', auth()->id());
                })->get();;

                $tasks = $tasks->concat($group_tasks);
            }
        }

        $procedures = [];

        $steps = auth()->user()->steps()->where('done', 0)->whereNotNull('endDate')->get();

        //absences
        if (auth()->user()->can('view absences')){
            $absences = Absence::whereDate('end', '>=', Carbon::now()->startOfDay())->orderBy('start')->get();
            $oldAbsences = Absence::whereDate('end', '<', Carbon::now()->startOfDay())->orderByDesc('end')->paginate(5);
        } else {
            $absences = [];
            $oldAbsences = [];
        }

        //Roster
        $groups_arr = [];
        foreach ($groups as $group){
            $groups_arr[] = $group->id;
        }

        $rosters = Roster::whereIn('department_id', $groups_arr)
            ->whereDate('start_date', '>=' ,Carbon::now()->startOfWeek()->format('Y-m-d'))
            ->where('type', '!=', 'template')
            ->get();

        return view('home', [
            'tasks'     => $tasks,
            'steps' => $steps,
            'absences' => $absences,
            'oldAbsences' => $oldAbsences,
            'posts' => auth()->user()->posts()->orderByDesc('created_at')->paginate(15),
            'rosters' => $rosters
        ]);
    }*/
}
