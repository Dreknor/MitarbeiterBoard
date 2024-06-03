<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Support\Collection;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class VertretungsplanImportController extends Controller
{
    public function import(Request $request)
    {

        $data = $request->all();

        Log::info('VertretungsplanImportController import');

        if (is_array($data)) {
            foreach ($data as $key => $value) {
                Log::info($key . ' => ' . $value);
            }
        }

        $vp_data = $data['Gesamtexport']['Vertretungsplan']['Vertretungsplan'];

        Log::info('VertretungsplanImportController import vp_data');
        Log::info($vp_data);










    }
}
