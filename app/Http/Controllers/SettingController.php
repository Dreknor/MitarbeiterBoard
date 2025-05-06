<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreSettingsRequest;
use App\Models\Setting;
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
    public function store(StoreSettingsRequest $request)
    {
        foreach ($request->setting as $key => $value) {
            if ($value == 'on'){
                $value = 1;
            }
            Setting::where('setting', $key)->update(['value' => $value]);
            Log::info('Setting updated', [
                'setting' => $key,
                'value' => $value
            ]);
        }

        Cache::forget('settings');
        return redirectBack('success', __('Einstellungen aktualisiert'));
    }


}
