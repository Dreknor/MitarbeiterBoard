<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\DailyNews;
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
            Log::error('Vertretungsplan: Invalid API Key', [
                'key' => $key,
                'setting' => $setting,
            ]);
            return response()->json(['error' => 'Invalid API Key'], 401);
        }

        // Robustere Datenstruktur-Erkennung
        $rawData = json_decode($request->getContent());
        if (!$rawData) {
            Log::error('Vertretungsplan: Error while parsing JSON. No data found.', [
                'request' => $request->all(),
            ]);
            return response()->json(['error' => 'Error while parsing JSON'], 400);
        }

        // Finde den Einstiegspunkt für die Daten
        $data = null;
        if (isset($rawData->request)) {
            $data = $rawData->request;
        } elseif (isset($rawData->data)) {
            $data = $rawData->data;
        } else {
            $data = $rawData;
        }

        // Suche nach Gesamtexport->Vertretungsplan->Vertretungsplan
        if (isset($data->Gesamtexport) && isset($data->Gesamtexport->Vertretungsplan) && isset($data->Gesamtexport->Vertretungsplan->Vertretungsplan)) {
            $vertretungsplan = $data->Gesamtexport->Vertretungsplan->Vertretungsplan;
        } elseif (isset($data->Gesamtexport) && isset($data->Gesamtexport->Vertretungsplan) && is_array($data->Gesamtexport->Vertretungsplan)) {
            $vertretungsplan = $data->Gesamtexport->Vertretungsplan;
        } elseif (isset($data->Vertretungsplan) && isset($data->Vertretungsplan->Vertretungsplan)) {
            $vertretungsplan = $data->Vertretungsplan->Vertretungsplan;
        } elseif (isset($data->Vertretungsplan) && is_array($data->Vertretungsplan)) {
            $vertretungsplan = $data->Vertretungsplan;
        } else {
            Log::error('Vertretungsplan: Error while parsing JSON. No Vertretungsplan found.', [
                'data' => $data,
            ]);
            return response()->json(['error' => 'Error while parsing JSON. No Vertretungsplan found.'], 400);
        }

        foreach ($vertretungsplan as $day){
            try {
                $date = isset($day->Kopf->Datum) ? Carbon::createFromFormat('d.m.Y', $day->Kopf->Datum) : null;
                //Abwesenheiten
                if (isset($day->Kopf) && isset($day->Kopf->Kopfinfo) && isset($day->Kopf->Kopfinfo->AbwesendeLehrer)) {
                    try {
                        foreach ($day->Kopf->Kopfinfo->AbwesendeLehrer as $abwesender) {
                            $user = User::where('kuerzel', $abwesender->Kurz)->first();
                            Log::info('Parsing Abwesender: ' . $abwesender->Kurz);
                            Log::info('User: ' . $user);
                            if ($user && $date) {
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
                                Log::info('Vertretungsplan: Lehrer nicht gefunden: ' . $abwesender->Kurz,
                                    [
                                        'date' => $date,
                                        'abwesender' => $abwesender,
                                    ]);
                            }
                        }
                    } catch (\Exception $e) {
                        Log::error('Vertretungsplan: Error while parsing Abwesenheiten: ',
                            [
                                'date' => $date,
                                'exception' => $e->getMessage(),
                            ]);
                    }
                }

                //Vertretungen
                if (isset($day->Aktionen) && is_array($day->Aktionen)){
                    try {
                        foreach ($day->Aktionen as $aktion){
                            $aktion= (object) $aktion;
                            if (isset($aktion->InfoK)){
                                $nachricht = new DailyNews([
                                    'date_start' => $date,
                                    'date_end' => $date,
                                    'news' => $aktion->InfoK,
                                ]);
                                $nachricht->save();
                            }
                            if (isset($aktion->Ak_DatumVon)){
                                $date = Carbon::createFromFormat('d.m.Y', $aktion->Ak_DatumVon);
                            }
                            $lehrer = null;
                            if (isset($aktion->VLehrer) && is_array($aktion->VLehrer) && count($aktion->VLehrer) > 0){
                                $lehrer = User::where('kuerzel', $aktion->VLehrer[0])->first();
                            }
                            $klassen = null;
                            if (isset($aktion->Klassen) && is_array($aktion->Klassen)){
                                $klassen = Klasse::whereIn('name', $aktion->Klassen)->get();
                                Log::info('gefundene Klassen: ' . count($klassen));
                                Log::info($klassen);
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
                            if (!is_null($klassen)){
                                foreach ($klassen as $klasse) {
                                    $vertretung = Vertretung::query()
                                        ->where('klassen_id', $klasse->id)
                                        ->where('date', $date ? $date->format('Y-m-d') : null)
                                        ->where('stunde', $aktion->Ak_StundeVon)
                                        ->first();
                                    if ($vertretung) {
                                        $vertretung->update([
                                            'users_id' => $lehrer?->id,
                                            'Doppelstunde' =>  (isset($aktion->Ak_Doppelstunde) || (isset($aktion->Ak_StundenAnz) && $aktion->Ak_StundenAnz == 2)) ? true : false,
                                            'altFach' => $aktion->Ak_Fach ?? null,
                                            'neuFach' => (isset($aktion->Ak_VFach) && $aktion->Ak_VFach != "") ? $aktion->Ak_VFach : 'Ausfall',
                                            'type' => $type,
                                            'comment' => (isset($aktion->Raeume) && isset($aktion->VRaeume) && $aktion->Raeume[0] != $aktion->VRaeume[0]) ? 'Raum: '.$aktion->VRaeume[0]  : null,
                                        ]);
                                    } else {
                                        $vertretung = new Vertretung([
                                            'klassen_id' => $klasse->id,
                                            'date' => $date,
                                            'stunde' => $aktion->Ak_StundeVon ?? null,
                                            'users_id' => $lehrer?->id,
                                            'Doppelstunde' => (isset($aktion->Ak_Doppelstunde) || (isset($aktion->Ak_StundenAnz) && $aktion->Ak_StundenAnz == 2)) ? true : false,
                                            'altFach' => $aktion->Ak_Fach ?? null,
                                            'neuFach' => (isset($aktion->Ak_VFach)) ? $aktion->Ak_VFach : 'Ausfall',
                                            'created_at' => Carbon::now(),
                                            'akt_id' => $aktion->Ak_Id ?? null,
                                            'type' => $type,
                                            'comment' => (isset($aktion->Raeume) && isset($aktion->VRaeume) && $aktion->Raeume[0] != $aktion->VRaeume[0]) ? 'Raum: '.$aktion->VRaeume[0]  : null,
                                        ]);
                                        $vertretung->save();
                                    }
                                }
                            } else {
                                Log::info('Vertretungsplan: Klassen nicht gefunden ' );
                            }
                        }
                    } catch (\Exception $e) {
                        Log::error('Vertretungsplan: Error while parsing Aktionen: ',[
                            'date' => $date,
                            'exception' => $e->getMessage(),
                        ]);
                    }
                }
            } catch (\Exception $e) {
                Log::error('Vertretungsplan: Error while parsing: ', [
                    'date' => $date,
                    'exception' => $e->getMessage(),
                ]);
                continue;
            }
        }
    }
}
