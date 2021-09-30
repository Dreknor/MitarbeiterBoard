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

        $vertretungen = Vertretung::whereBetween('date', [Carbon::today()->format('Y-m-d'), Carbon::today()->addDays(config('config.show_vertretungen_days'))->format('Y-m-d')])->whereIn('klassen_id',$klassen)->orderBy('date')->orderBy('klassen_id')->orderBy('stunde')->get();
        $news = DailyNews::whereBetween('date_start', [Carbon::today()->format('Y-m-d'), Carbon::today()->addDays(config('config.show_vertretungen_days'))->format('Y-m-d')])
            ->orWhereDate('date_end', '>=', Carbon::today()->addDays(config('config.show_vertretungen_days')))->orderBy('date_start')->get();

        return response()->view('vertretungsplan.index',[
            'vertretungen' => $vertretungen,
            'news'          => $news
        ])
            ->header('Content-Security-Policy', config('cors.Content-Security-Policy'))
            ->header('X-Frame-Options', config('cors.X-Frame-Options'));
    }

}
