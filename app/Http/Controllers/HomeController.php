<?php

namespace App\Http\Controllers;

use Carbon\Carbon;
use Illuminate\Http\Request;

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
        $groups = auth()->user()->groups;
        $groups->load('themes', 'tasks', 'themes.group');

        $tasks = auth()->user()->tasks->where('completed', 0);

        foreach ($groups as $group){
            $tasks = $tasks->concat($group->tasks()->whereDate('date', '>=', Carbon::now())->get());
        }

        return view('home',[
            "groups"    => $groups,
            'tasks'     => $tasks,
            'colors'    => $colors
        ]);
    }
}
