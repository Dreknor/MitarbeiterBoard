<?php

namespace App\Http\Controllers;

use App\Models\Vertretung;
use App\Models\VertretungsplanWeek;
use Carbon\Carbon;
use Illuminate\Http\Request;

class VertretungsplanWeekController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        $weeks = VertretungsplanWeek::where('week', '>=', Carbon::now()->startOfWeek())->get();

        if (is_null($weeks) or $weeks->count() == 0){
            for ($week = Carbon::now()->startOfWeek(); $week->lessThan(Carbon::createFromFormat('d.m.Y', '31.07.'.Carbon::now()->format('Y'))); $week->addWeek()){
                $newWeek = new VertretungsplanWeek([
                  'week' => $week,
                  'type' => ($week->weekOfYear%2 == 0)? 'A' : 'B'
                ]);
                $newWeek->save();
            }
            $weeks = VertretungsplanWeek::where('week', '>=', Carbon::now()->startOfWeek())->get();

        }


        return response()->view('vertretungsplan.wochentype',[
            'weeks' => $weeks
        ]);
    }


    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Models\VertretungsplanWeek  $vertretungsplanWeek
     * @return \Illuminate\Http\RedirectResponse
     */
    public function update(Request $request, VertretungsplanWeek $week)
    {
        $weeks = VertretungsplanWeek::where('week', '>=', $week->week)->get();

        $week->update([
           'type' => ($week->type == "A")? 'B' : 'A'
        ]);

        foreach ($weeks as $week){
            $week->update([
                'type' => ($week->type == "A")? 'B' : 'A'
            ]);
        }

        return redirect()->back()->with([
            'type' => 'success',
            'Meldung' => "Wochentype wurde getauscht"
        ]);

    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  \App\Models\VertretungsplanWeek  $vertretungsplanWeek
     * @return \Illuminate\Http\Response
     */
    public function destroy(VertretungsplanWeek $week)
    {
        $week->delete();

        return redirect()->back()->with([
            'type' => 'info',
            'Meldung' => "Wochentype wurde gelöscht"
        ]);
    }
}
