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
        Updater::update();
        return redirect()->route('update.index');
    }
}
