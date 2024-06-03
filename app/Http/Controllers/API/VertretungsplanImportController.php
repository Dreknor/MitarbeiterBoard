<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Klasse;
use App\Models\User;
use App\Models\Vertretung;
use App\Support\Collection;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class VertretungsplanImportController extends Controller
{
    public function import(Request $request)
    {

        $data = $request->all();

        $vp_data = $data['Gesamtexport']['Vertretungsplan']['Vertretungsplan'];

        //Log::info('VertretungsplanImportController import vp_data');
        //Log::info($vp_data);

        $date = Carbon::createFromFormat('d.m.Y',$vp_data['Kopf']['Datum']);

        Log::info('Datum: '.$date->format('Y-m-d'));

        $absences_array = $vp_data['Kopf']['Kopfinfo']['AbwesendeLehrer'];

        $absences = User::whereIn('kuerzel',$absences_array['Kurz'])->get();

        Log::info('Abwesende Lehrer: ');
        Log::info($absences);

        $aktionen = $vp_data['Aktionen'];

        foreach ($aktionen as $aktion){
            $klassen = Klasse::whereIn('name',$aktion['Klassen'])->get();

            Log::info('Klassen: ');
            Log::info($klassen);

            foreach ($klassen as $klasse){


                $vertretung = new Vertretung([
                    'date' => $date,
                    'klassen_id' => $klasse->id,
                    'stunde' => $aktion['Ak_StundeVon'],
                    'Doppelstunde' => array_key_exists('Ak_StundenAnz',$aktion) ? true : false,
                    'altFach' => $aktion['Ak_Fach'],
                    'neuFach' => $aktion['Ak_VFach'],
                    'user_id' => array_key_exists('VLehrer', $aktion) ? User::where('kuerzel',$aktion['VLehrer'][0])->first()->id : null,
                    'comment' => array_key_exists('VRaeume', $aktion) ? 'Raum: '.$aktion['VRaeume'][0] : null,
                ]);

                Log::info('Vertretung: ');
                Log::info($vertretung);

            }


        }










    }
}
