<?php

namespace App\Http\Controllers;

use Carbon\Carbon;
use Illuminate\Http\Request;

class KioskController extends Controller
{
    public function index(){
        $colors = ['#6495ed', 'Orange', '#ffca00', '#d9335c', '#99ff80', 'Persian Green', '#bfffff', '#bf7660', '#b3b017', '#149ab5'];


        $groups = auth()->user()->groups;
        $groups->load('themes', 'tasks', 'themes.group');



        foreach ($groups as $group){
            if (!isset($tasks)){
                $tasks = $group->tasks()->whereDate('date', '>=', Carbon::now())->get();
            } else {
                $tasks = $tasks->concat($group->tasks()->whereDate('date', '>=', Carbon::now())->get());
            }

        }

        return view('home',[
            "groups"    => $groups,
            'tasks'     => $tasks,
            'colors'    => $colors,
            'menue'     => "false"
        ]);
    }
}
