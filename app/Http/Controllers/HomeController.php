<?php

namespace App\Http\Controllers;

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
        $colors = ['#6495ed', 'Orange', '#ffca00', '#d9335c', '#99ff80', 'Persian Green', '#bfffff'];
        $groups = auth()->user()->groups;
        $groups->load('themes');
        return view('home',[
            "groups"    => $groups,
            'colors'    => $colors
        ]);
    }
}
