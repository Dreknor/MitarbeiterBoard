<?php

namespace App\Http\Controllers;

use App\Http\Requests\CreateDailyNewsRequest;
use App\Models\DailyNews;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class DailyNewsController extends Controller
{

    public function index(){

        $news = DailyNews::query()
            ->where(function($query){
                $query->whereDate('date_end', '>=', Carbon::today());
            })
            ->orWhere(function($query) {
                $query->whereNull('date_end');
            })
            ->orderBy('date_start')
            ->get();


        return response()->view('dailyNews.index', [
           'dailyNews'=> $news
        ]);
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return RedirectResponse
     */
    public function store(CreateDailyNewsRequest $request)
    {
        $news = new DailyNews($request->validated());

        if (is_null($request->date_end)){
            $news->date_end = $request->date_start;
        }
        $news->save();

        return redirect()->back()->with([
           'type'=>'success',
           'Meldung'=>'Nachricht wurde gespeichert.'
        ]);
    }




    /**
     * Remove the specified resource from storage.
     *
     * @param  \App\Models\DailyNews  $dailyNews
     * @return \Illuminate\Http\Response
     */
    public function destroy(DailyNews $dailyNews)
    {
        $dailyNews->delete();
        return redirect()->back()->with([
            'type'=>'warning',
            'Meldung'=>'Nachricht wurde gelöscht.'
        ]);
    }
}
