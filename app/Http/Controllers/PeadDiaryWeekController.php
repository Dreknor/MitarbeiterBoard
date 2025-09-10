<?php

namespace App\Http\Controllers;

use App\Models\Klasse;
use Illuminate\Http\Request;

class PeadDiaryWeekController extends Controller
{



    public function displayWeek($secret = null){

        if ((!$secret || $secret !== config('app.paed_diary_secret') ) && !auth()->user()->can('view paed diary')) {
            abort(403, 'Unauthorized action.');
        }

        $Klassen = Klasse::with('appointments')->orderBy('name')->get();



        return view('paedDiary.week.displayWeek');
    }
}
