<?php

namespace App\Http\Controllers;

use App\Http\Requests\createWPRow;
use App\Models\WPRows;
use Illuminate\Http\Request;

class WPRowsController extends Controller
{


    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\RedirectResponse
     */
    public function store(createWPRow $request)
    {
        $row = new WPRows($request->validated());
        $row->save();

        return redirect()->back();
    }


    /**
     * Show the form for editing the specified resource.
     *
     * @param  \App\Models\WPRows  $wPRows
     * @return \Illuminate\Http\Response
     */
    public function edit(WPRows $wPRows)
    {
        //TODO
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Models\WPRows  $wPRows
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, WPRows $wPRows)
    {
        //TODO
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  \App\Models\WPRows  $wprows
     * @return \Illuminate\Http\RedirectResponse
     */
    public function destroy(WPRows $wprow)
    {

        $wprow->delete();
        return redirect()->back()->with([
           'type'=>'success',
           'Meldung'=>"Abschnitt gelöscht."
        ]);
    }
}
