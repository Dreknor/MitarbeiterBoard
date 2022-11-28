<?php

namespace App\Http\Controllers\Personal;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class HourTypeController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        return view('settings.HourType.index', [
           'types' => HourType::all(),
        ]);
    }


    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(CreateHourTypeRequest $request)
    {
        $type = new HourType($request->validated());
        $type->save();

        activity()->causedBy(auth()->user())->on($type)->useLog('settings')->log('Stundentype wurde erstellt.');

        return redirectBack('success', 'Stundentyp wurde angelegt');
    }


    /**
     * Remove the specified resource from storage.
     *
     * @param  \App\Models\HourType  $hourType
     * @return \Illuminate\Http\Response
     */
    public function destroy(HourType $hourType)
    {
        #Todo
    }
}
