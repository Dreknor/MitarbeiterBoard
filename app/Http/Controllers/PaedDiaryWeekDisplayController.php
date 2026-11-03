<?php

namespace App\Http\Controllers;

use App\Models\Klasse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class PaedDiaryWeekDisplayController extends Controller
{



    public function displayWeek($secret = null){

        // Kiosk-Anzeige: Zugriff mit gültigem Secret (PAED_DIARY_SECRET) oder als angemeldete Person
        // mit "view paed diary". Vergleich zeitkonstant; ohne konfiguriertes Secret nur angemeldet.
        $konfiguriert = (string) config('app.paed_diary_secret');
        $secretOk = $secret !== null && $konfiguriert !== '' && hash_equals($konfiguriert, (string) $secret);
        if (!$secretOk && !auth()->user()?->can('view paed diary')) {
            abort(403, 'Kein Zugriff auf die Wochenanzeige.');
        }



        $startOfWeek = Carbon::now()->startOfWeek(Carbon::MONDAY);
        $endOfWeek = Carbon::now()->endOfWeek(Carbon::SUNDAY);

        $klassen = Klasse::query()->where('color', '!=', '#ffffff')->orderBy('name')->get();

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

        foreach ($klassengruppen as $gruppe) {

            foreach ($gruppe->appointments as $appointment) {
                $occurrences = $appointment->getOccurrencesInRange($startOfWeek, $endOfWeek);
                foreach ($occurrences as $occurrence) {
                   $date = Carbon::parse($occurrence['date']);
                   foreach ($gruppe->klassen as $klasse) {
                       if (!array_key_exists($klasse->id, $appointmentsByDay)) {
                           $appointmentsByDay[$klasse->id] = [];
                       }

                       $appointmentsByDay[$klasse->id][$date->dayOfWeek][] = $occurrence;
                   }
                }
            }
        }

        return view('paedDiary.week.displayWeek',[
            'klassen' => $klassen,
            'appointmentsByDay' => $appointmentsByDay,
        ]);
    }
}
