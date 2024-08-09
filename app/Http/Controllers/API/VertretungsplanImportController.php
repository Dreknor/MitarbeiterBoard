<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Klasse;
use App\Models\Setting;
use App\Models\User;
use App\Models\Vertretung;
use App\Models\VertretungsplanAbsence;
use App\Support\Collection;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class VertretungsplanImportController extends Controller
{
    public function import(Request $request)
    {

        $key = $request->route('key');

        $setting = Setting::where('setting', 'indiware_import_key')->first();

        if (!$setting || $setting->value != $key) {
            Log::error('Invalid API Key');
            return response()->json(['error' => 'Invalid API Key'], 401);
        }

        Log::info('Importing Vertretungsplan');
        Log::info('Request: ' . $request->getContent());
        $data = json_decode($request->getContent());

        if (!$data) {
            Log::error('Error while parsing JSON. No data found.');
            return response()->json(['error' => 'Error while parsing JSON'], 400);
        }

        if (isset($data->Gesamtexport->Vertretungsplan->Vertretungsplan)){
            $data = $data->Gesamtexport->Vertretungsplan->Vertretungsplan;
        } else {
            Log::error('Error while parsing JSON. No Vertretungsplan found.');
            return response()->json(['error' => 'Error while parsing JSON. No Vertretungsplan found.'], 400);
        }

        Log::info('Parsing data: ' . count($data) . ' days');
        foreach ($data as $day){
            Log::info($day);
            try {
                $date = Carbon::createFromFormat('d.m.Y', $day['Kopf']['Datum']);
                Log::info('Parsing date: ' . $date);
                //Abwesenheiten
                if (isset($day->Kopf) && isset($day->Kopf->Kopfinfo) && isset($day->Kopf->Kopfinfo->AbwesendeLehrer)) {
                    try {
                        foreach ($day->Kopf->Kopfinfo->AbwesendeLehrer as $abwesender) {
                            $user = User::where('kuerzel', $abwesender['Kurz'])->first();
                            if ($user) {
                                $absence = VertretungsplanAbsence::where('user_id', $user->id)
                                    ->whereDate('start_date', '<=',$date)
                                    ->whereDate('end_date', '>=',$date)
                                    ->first();
                                if (!$absence) {
                                    $absence = new VertretungsplanAbsence([
                                        'user_id' => $user->id,
                                        'start_date' => $date,
                                        'end_date' => $date,
                                    ]);
                                    $absence->save();
                                }
                            } else {
                                Log::info('Lehrer nicht gefunden: ' . $abwesender['Kurz']);
                            }
                        }
                    } catch (\Exception $e) {
                        Log::error('Error while parsing Abwesenheiten: ');
                        Log::error($e->getMessage());
                    }
                }

                //Vertretungen
                if ($day->Aktionen){
                    try {
                        foreach ($day->Aktionen as $aktion){
                            Log::info('Parsing Aktion: ' . $aktion);
                            if (isset($day->Ak_DatumVon)){
                                $date = Carbon::createFromFormat('d.m.Y', $day->Ak_DatumVon);
                            }


                            if (isset($aktion->VLehrer)){
                                $lehrer = User::where('kuerzel', $aktion->VLehrer[0])->first();

                            }
                            if (isset($aktion->VKlassen)){
                                $klassen = Klasse::whereIn('name', $aktion->VKlassen)->get();
                            }

                            $type = '';

                            switch ($aktion->Ak_Art){
                                case 'Änd.':
                                    if (isset($aktion->Ak_Fach) && isset($aktion->Ak_VFach) && $aktion->Ak_Fach != $aktion->Ak_VFach){
                                        $type = 'Vertretung (fachfremd)';
                                    } else {
                                        $type = 'Vertretung (fachgerecht)';
                                    }
                                    break;

                                default:
                                    $type = 'Ausfall';
                                    break;
                            }

                            foreach ($klassen as $klasse) {

                                $vertretung = Vertretung::query()
                                    ->where('klassen_id', $klasse->id)
                                    ->where('date', $date->format('Y-m-d'))
                                    ->where('stunde', $aktion->Ak_StundeVon)
                                    ->first();
                                if ($vertretung) {
                                    $vertretung->update([
                                        'users_id' => $lehrer?->id,
                                        'Doppelstunde' => isset($aktion->Ak_Doppelstunde) ? true : false,
                                        'altFach' => $aktion->Ak_Fach,
                                        'neuFach' => (isset($aktion->Ak_VFach) && $aktion->Ak_VFach != "") ? $aktion->Ak_VFach : 'Ausfall',
                                        'type' => $type,
                                        'comment' => (isset($aktion->Raeume) && isset($aktion->VRaeume) && $aktion->Raeume[0] != $aktion->VRaeume[0]) ? 'Raum: '.$aktion->VRaeume[0]  : null,
                                    ]);
                                } else {
                                    $vertretung = new Vertretung([
                                        'klassen_id' => $klasse->id,
                                        'date' => $date,
                                        'stunde' => $aktion->Ak_StundeVon,
                                        'users_id' => $lehrer?->id,
                                        'Doppelstunde' => isset($aktion->Ak_Doppelstunde) ? true : false,
                                        'altFach' => $aktion['Ak_Fach'],
                                        'neuFach' => (isset($aktion->Ak_VFach)) ? $aktion->Ak_VFach : 'Ausfall',
                                        'created_at' => Carbon::now(),
                                        'akt_id' => $aktion->Ak_Id,
                                        'type' => $type,
                                        'comment' => (isset($aktion->Raeume) && isset($aktion->VRaeume) && $aktion->Raeume[0] != $aktion->VRaeume[0]) ? 'Raum: '.$aktion->VRaeume[0]  : null,
                                    ]);
                                    $vertretung->save();
                                }
                            }
                        }
                    } catch (\Exception $e) {
                        Log::error('Error while parsing Aktionen: ');
                        Log::error($e->getMessage());
                    }




                }
            } catch (\Exception $e) {
                Log::error('Error while parsing: ');
                Log::error($e->getMessage());
                continue;
            }
        }
    }
}
