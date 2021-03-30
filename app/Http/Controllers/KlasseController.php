<?php

namespace App\Http\Controllers;

use App\Http\Requests\CreateKlasseRequest;
use App\Models\Klasse;
use Illuminate\Http\Request;

class KlasseController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        return response()->view('klassen.klassen',[
            'klassen' => Klasse::all()
        ]);
    }


    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(CreateKlasseRequest $request)
    {
        Klasse::create($request->validated());

        return redirect()->back()->with([
            'type'  => "success",
            'Meldung'=> 'Klasse wurde angelegt.'
        ]);
    }


    /**
     * Remove the specified resource from storage.
     *
     * @param  \App\Models\Klasse  $klasse
     * @return \Illuminate\Http\Response
     */
    public function destroy($klasse)
    {
        $klasse = Klasse::find($klasse);
        $name = $klasse->name;
        $klasse->delete();

        return redirect()->back()->with([
            'type' => 'warning',
            'Meldung' => $name.' wurde gelöscht.'
        ]);
    }
}
