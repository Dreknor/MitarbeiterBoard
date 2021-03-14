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



        return response()->view('vertretungsplan.index',[
            'vertretungen_heute' => Vertretung::whereDate('date', '=', Carbon::today())->whereIn('klassen_id',$klassen)->orderBy('klassen_id')->orderBy('stunde')->get(),
            'vertretungen_morgen' => Vertretung::whereDate('date', '=', Carbon::tomorrow())->whereIn('klassen_id',$klassen)->orderBy('klassen_id')->orderBy('stunde')->get(),
            'news_heute'=> DailyNews::whereDate('date_start', '=', Carbon::today())->orWhereDate('date_end', '>=', Carbon::today())->orderBy('date_start')->get(),
            'news_morgen'=> DailyNews::whereDate('date_start', '=', Carbon::tomorrow())->orWhereDate('date_end', '>=', Carbon::tomorrow())->orderBy('date_start')->get()

        ])
            ->header('Content-Security-Policy', config('cors.Content-Security-Policy'))
            ->header('X-Frame-Options', config('cors.X-Frame-Options'));
    }

}
