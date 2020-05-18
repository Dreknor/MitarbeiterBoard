<?php

namespace App\Http\Controllers;

use App\Imports\ThemeImport;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;

class ImportController extends Controller
{
    public function show(){
        return view('imports.import');
    }

    public function import(Request $request){
        if ($request->hasFile('file')){


            Excel::import(new ThemeImport(), request()->file('file'));


            return redirect(url('themes'))->with([
                'type'  => "success",
                'Meldung'   => "Erfolgreicher Import"
            ]);
        }
        return redirect()->back()->with([
            "type" => "danger",
            "Meldung" => "Keine Datei ausgewählt"
        ]);
    }
}
