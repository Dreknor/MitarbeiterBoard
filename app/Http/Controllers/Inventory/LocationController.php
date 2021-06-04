<?php

namespace App\Http\Controllers\Inventory;

use App\Http\Controllers\Controller;
use App\Http\Requests\createLocationRequest;
use App\Http\Requests\editLocationRequest;
use App\Imports\LocationImport;
use App\Models\Inventory\Location;
use App\Models\Inventory\LocationType;
use App\Models\User;
use Http\Client\Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Maatwebsite\Excel\Facades\Excel;

class LocationController extends Controller
{
    public function __construct()
    {
        $this->middleware('permission:edit inventar');
    }

    /**
     * Display a listing of the resource.
     *
     * @return View
     */
    public function index(Request $request)
    {
        return view('inventory.locations.index', [
           'locations' => Location::all()
        ]);
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return View
     */
    public function create()
    {
        return view('inventory.locations.create',[
            'users' => User::all(),
            'types'=> LocationType::all()
        ]);
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(createLocationRequest $request)
    {
        try {
            $location = new Location($request->validated());
            $location->locationtype_id=$request->type;
            $location->verantwortlicher_id=$request->user;
            $location->uuid = Str::uuid();
            $location->save();

            return redirect(url('inventory/locations'))->with([
               'type' => 'success',
               'Meldung' => 'Ort wurde gespeichert.'
            ]);
        } catch (Exception $exception){
            return redirect()->back()->with([
                'type' => 'danger',
                'Meldung' => 'Ort konnte nicht erstellt werden.'
            ]);
        }
    }


    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function edit($id)
    {
        $location = Location::where('id',$id)->first();

        return view('inventory.locations.edit',[
            'location' => $location,
            'users' => User::all(),
            'types'=> LocationType::all()
        ]);
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update(editLocationRequest $request, Location $location)
    {
        try {
            $location->update($request->validated());
            $location->locationtype_id=$request->type;
            $location->verantwortlicher_id=$request->user;
            $location->save();

            return redirect(url('inventory/locations'))->with([
                'type' => 'success',
                'Meldung' => 'Änderung wurde gespeichert.'
            ]);
        } catch (Exception $exception){

            return redirect()->back()->with([
                'type' => 'danger',
                'Meldung' => 'Ort konnte nicht geändert werden.'
            ]);
        }
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        //ToDo
    }

    public function showImport(){
        return view('inventory.locations.import');
    }

    public function import (Request $request){
        if ($request->hasFile('file')){
            $header = $request->all();
            Arr::forget($header,['_token']);


            foreach ($header  as $key => $value){
                if ($key != 'file'){
                    $header[$key] = str_replace('-','_',strtolower($value));
                }

            }
            //dd($header);
            Excel::import(new LocationImport($header), request()->file('file'));
            return redirect(url('inventory/locations'));
        } else {
            return redirect()->back()->with([
               'type' => 'warning',
               'Meldung' => 'Datei fehlt'
            ]);
        }

    }
}
