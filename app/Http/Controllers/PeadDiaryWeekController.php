<?php

namespace App\Http\Controllers;

use App\Models\Klasse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class PeadDiaryWeekController extends Controller
{



    public function displayWeek($secret = null){

        if ((!$secret || $secret !== config('app.paed_diary_secret') ) && !auth()->user()->can('view paed diary')) {
            abort(403, 'Unauthorized action.');
        }



        $startOfWeek = Carbon::now()->startOfWeek(Carbon::MONDAY);
        $endOfWeek = Carbon::now()->endOfWeek(Carbon::SUNDAY);

        $klassen = Klasse::query()->where('color', '!=', '#ffffff')->with('appointments')->orderBy('name')->get();

        $klassengruppen = \App\Models\PaedDiaryClassGroup::query()->whereHas('klassen', function($q){
            $q->where('color', '!=', '#ffffff');
        })->with(['klassen' => function($q){
            $q->where('color', '!=', '#ffffff')->orderBy('name');
        }])->orderBy('name')->get();

        foreach ($klassengruppen as $gruppe){
            foreach ($gruppe->klassen as $klasse){
                if (!$klassen->contains($klasse)){
                    $klassen->push($klasse);
                }
            }
        }


        $appointmentsByDay = [];

        foreach ($klassen as $klasse) {
            foreach ($klasse->appointments as $appointment) {
                $occurrences = $appointment->getOccurrencesInRange($startOfWeek, $endOfWeek);
                foreach ($occurrences as $occurrence) {
                   $date = Carbon::parse($occurrence['date']);
                    $appointmentsByDay[$klasse->id][$date->dayOfWeek][] = $occurrence;
                }
            }
        }

        return view('paedDiary.week.displayWeek',[
            'klassen' => $klassen,
            'appointmentsByDay' => $appointmentsByDay,
        ]);
    }
}
