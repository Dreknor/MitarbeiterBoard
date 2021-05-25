<?php

namespace App\Http\Controllers;

use App\Http\Requests\createWPTaskRequest;
use App\Models\WPRows;
use App\Models\WpTask;

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
     * Show the form for editing the specified resource.
     *
     * @param  \App\Models\WpTask  $wpTask
     * @return \Illuminate\Http\Response
     */
    public function edit($wpTask)
    {
        $task = WpTask::where('id', $wpTask)->first();

        return response()->view('wochenplan.edittask', [
           'task' => $task
        ]);
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Models\WpTask  $wpTask
     * @return \Illuminate\Http\RedirectResponse
     */
    public function update(createWPTaskRequest $request, WpTask $wpTask)
    {
        $wpTask->update($request->validated());
        return redirect(url($wpTask->wprow->wochenplan->group->name.'/wochenplan/'.$wpTask->wprow->wochenplan->id));
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  \App\Models\WpTask  $wptask
     * @return \Illuminate\Http\Response
     */
    public function destroy(WpTask $wptask)
    {
        $wptask->delete();
        return redirect()->back()->with([
           'type' => 'warning',
           'Meldung'=>"Aufgabe wurde gelöscht."
        ]);
    }
}
