<?php

namespace App\Http\Controllers;

use App\Models\DailyNews;
use App\Models\Klasse;
use App\Models\Vertretung;
use Carbon\Carbon;

class VertretungsplanController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index($gruppen = null)
    {

        if ($gruppen != null){
            $gruppen = explode('/', $gruppen);
            $klassen = Klasse::whereIn('name',$gruppen)->get();

        } else {
            $klassen=Klasse::all();
        }

        $klassen= $klassen->pluck('id');

        $addDays=config('config.show_vertretungen_days');
        $addWeekendDays = false;

        for ($days=0; $days < $addDays; $days++){
            if (Carbon::today()->addDays($days)->isWeekend()){
                $addWeekendDays = true;
            }
        }

        if ($addWeekendDays){
            $addDays+=2;
        }

        $targetDate = Carbon::today()->addDays($addDays);
        $vertretungen = Vertretung::whereBetween('date', [Carbon::today()->format('Y-m-d'), $targetDate->format('Y-m-d')])->whereIn('klassen_id',$klassen)->orderBy('date')->orderBy('klassen_id')->orderBy('stunde')->get();
/*
        $news = DailyNews::whereBetween('date_start', [Carbon::today()->format('Y-m-d'),$targetDate->format('Y-m-d')])
            ->orWhereDate('date_end', '>=', $targetDate->format('Y-m-d'))->orderBy('date_start')->get();
*/

        $news = DailyNews::whereDate('date_start', '<=', Carbon::today())->whereDate('date_end', '>=', $targetDate)->whereDate('date_end', '<=', Carbon::today())->orderBy('date_start')->get();

        return response()->view('vertretungsplan.index',[
            'vertretungen' => $vertretungen,
            'news'          => $news,
            'targetDate' => $targetDate
        ])
            ->header('Content-Security-Policy', config('cors.Content-Security-Policy'))
            ->header('X-Frame-Options', config('cors.X-Frame-Options'));
    }

}
