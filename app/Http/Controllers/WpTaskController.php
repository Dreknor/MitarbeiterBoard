<?php

namespace App\Http\Controllers;

use App\Http\Requests\createWPTaskRequest;
use App\Models\WPRows;
use App\Models\WpTask;
use Illuminate\Http\Request;

class WpTaskController extends Controller
{

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create(WPRows $wprow)
    {
        return response()->view('wochenplan.createTask', [
            'row' => $wprow
            ]
        );
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(WPRows $wprow, createWPTaskRequest $request)
    {
        $wprow->tasks()->create($request->validated());
        return redirect(url($wprow->wochenplan->group->name.'/wochenplan/'.$wprow->wochenplan->id))->with([
            'type'  => 'success',
            'Meldung'=>'Aufgabe gespeichert.'
        ]);
    }

    /**
     * Display the specified resource.
     *
     * @param  \App\Models\WpTask  $wpTask
     * @return \Illuminate\Http\Response
     */
    public function show(WpTask $wpTask)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  \App\Models\WpTask  $wpTask
     * @return \Illuminate\Http\Response
     */
    public function edit(WpTask $wpTask)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Models\WpTask  $wpTask
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, WpTask $wpTask)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  \App\Models\WpTask  $wpTask
     * @return \Illuminate\Http\Response
     */
    public function destroy(WpTask $wpTask)
    {
        //
    }
}
