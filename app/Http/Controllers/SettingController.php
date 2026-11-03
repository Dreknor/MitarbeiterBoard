<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreSettingsRequest;
use App\Models\Setting;
use App\Services\Personal\Zeit\HolidayService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class SettingController extends Controller
{
    public function __construct()
    {
        $this->middleware('permission:edit settings');
    }

    /**
     * Display a listing of the resource.
     */
    public function index($modul = null)
    {

        $setting_module = Cache::remember('settings', 60, function (){
            return Setting::all()->groupBy('module');
        });

        return view('settings.index',[
           'module' => $setting_module,
            'modul' => ($modul != null)? $modul : array_key_first($setting_module->toArray())
        ]);
    }


    /**
     * Store changed Settings
     */
    public function store(StoreSettingsRequest $request, HolidayService $holidays)
    {
        $geaendert = [];
        foreach ($request->setting as $key => $value) {
            if ($value == 'on'){
                $value = 1;
            }
            if ((string) Setting::where('setting', $key)->value('value') !== (string) $value) {
                $geaendert[] = $key;
            }
            Setting::where('setting', $key)->update(['value' => $value]);
            Cache::forget('setting_'.$key);
            Log::info('Setting updated', [
                'setting' => $key,
                'value' => $value
            ]);
        }

        Cache::forget('settings');

        // Wertung von Heiligabend/Silvester geändert → Urlaubstage des laufenden Jahres neu berechnen
        if (array_intersect($geaendert, ['heiligabend_feiertag', 'silvester_feiertag'])) {
            $anzahl = $holidays->tageNeuBerechnen(now()->year);

            return redirectBack('success', __('Einstellungen aktualisiert').' – Urlaubstage von '.$anzahl.' Antrag/Anträgen neu berechnet.');
        }

        return redirectBack('success', __('Einstellungen aktualisiert'));
    }


}
