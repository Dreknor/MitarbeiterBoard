<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Salahhusa9\Updater\Facades\Updater;

class UpdateController extends Controller
{
    public function __construct()
    {
        $this->middleware('permission:make updates');
    }

    public function index()
    {
        return view('updater.index');
    }

    public function update()
    {
        exec("git pull");

        \Artisan::call('migrate');
        \Artisan::call('cache:clear');
        \Artisan::call('config:clear');
        \Artisan::call('route:clear');
        \Artisan::call('view:clear');
        \Artisan::call('optimize');

        return redirect()->route('updater.index')->with('success', 'Update erfolgreich durchgeführt');
    }
}
