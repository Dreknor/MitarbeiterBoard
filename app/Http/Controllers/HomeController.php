<?php

namespace App\Http\Controllers;

use App\Models\Absence;
use App\Models\Group;
use App\Models\Procedure_Step;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

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

    /**
     * Show the application dashboard.
     *
     * @return \Illuminate\Contracts\Support\Renderable
     */
    public function index()
    {
        $colors = ['#6495ed', 'Orange', '#ffca00', '#d9335c', '#99ff80', 'Persian Green', '#bfffff', '#bf7660', '#b3b017', '#149ab5'];
        $rand = ['0', '1', '2', '3', '4', '5', '6', '7', '8', '9', 'a', 'b', 'c', 'd', 'e', 'f'];

        for ($x = 0; $x++; $x < 10) {
            $color[] = '#'.$rand[rand(0, 15)].$rand[rand(0, 15)].$rand[rand(0, 15)].$rand[rand(0, 15)].$rand[rand(0, 15)].$rand[rand(0, 15)];
        }

        $groups = auth()->user()->groups();
        $groups->load('themes', 'tasks', 'themes.group');

        $tasks = auth()->user()->tasks->where('completed', 0);

        foreach ($groups as $group) {
            if ($group->proteced or auth()->user()->groups_rel->contains('id', $group->id)) {
                $tasks = $tasks->concat($group->tasks()->whereDate('date', '>=', Carbon::now())->get());
            }
        }

        $procedures = [];

        $steps = auth()->user()->steps()->where('done', 0)->whereNotNull('endDate')->get();

        //absences
        if (auth()->user()->can('view absences')){
            $absences = Absence::whereDate('end', '>=', Carbon::now()->startOfDay())->orderBy('start')->get();
        } else {
            $absences = [];
        }

        return view('home', [
            'groups'    => $groups,
            'tasks'     => $tasks,
            'colors'    => $colors,
            'steps' => $steps,
            'absences' => $absences
        ]);
    }
}
