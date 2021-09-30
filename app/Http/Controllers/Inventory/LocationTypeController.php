<?php

namespace App\Http\Controllers\Inventory;


use App\Http\Controllers\Controller;
use App\Http\Requests\createLocationTypeRequest;
use App\Models\Inventory\LocationType;
use Illuminate\Http\Request;

class LocationTypeController extends Controller
{
    public function __construct()
    {
        $this->middleware('permission:edit inventar');
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\RedirectResponse
     */
    public function store(createLocationTypeRequest $request)
    {
        $type = new LocationType($request->validated());
        $type->save();

        return redirect()->back()->with([
           'type'=>'success',
           'Meldung'=> 'Typ wurde angelegt.'
        ]);
    }


    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function edit($id)
    {
        //ToDo
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, $id)
    {
        //ToDo
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
}
