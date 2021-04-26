<?php

namespace App\Http\Controllers;

use App\Http\Requests\createWPRequest;
use App\Models\Group;
use App\Models\Klasse;
use App\Models\Wochenplan;
use Illuminate\Http\Request;

class WochenplanController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index($groupname)
    {
        $group = Group::where('name', $groupname)->first();

        $wochenplaene = $group->wochenplaene()->paginate(6);

        return response()->view('wochenplan.index',[
            'wochenplaene' => $wochenplaene
        ]);
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        return response()->view('wochenplan.create', [
            'klassen' => Klasse::all()
        ]);
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\RedirectResponse
     */
    public function store($groupname, createWPRequest $request)
    {
        $group = Group::where('name', $groupname)->first();

        $wochenplan = new Wochenplan($request->validated());
        $wochenplan->group_id = $group->id;
        $wochenplan->save();

        $klassen = $request->input('klassen');

        $klassen = Klasse::whereIn('id', $klassen)->get();
        $wochenplan->klassen()->attach($klassen);

        return redirect(url($groupname.'/wochenplan'))->with([
            'type' => 'success',
            'Meldung'   => 'Wochenplan wurde erstellt.'
        ]);
    }

    /**
     * Display the specified resource.
     *
     * @param  \App\Models\Wochenplan  $wochenplan
     * @return \Illuminate\Http\Response
     */
    public function show($groupname, Wochenplan $wochenplan)
    {
        return response()->view('wochenplan.editWP', [
            "wochenplan" => $wochenplan
        ]);
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  \App\Models\Wochenplan  $wochenplan
     * @return \Illuminate\Http\Response
     */
    public function edit(Wochenplan $wochenplan)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Models\Wochenplan  $wochenplan
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, Wochenplan $wochenplan)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  \App\Models\Wochenplan  $wochenplan
     * @return \Illuminate\Http\Response
     */
    public function destroy(Wochenplan $wochenplan)
    {
        //
    }
}
