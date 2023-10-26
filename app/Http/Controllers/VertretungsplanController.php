<?php

namespace App\Http\Controllers;

use App\Models\Absence;
use App\Models\DailyNews;
use App\Models\Klasse;
use App\Models\Vertretung;
use App\Models\VertretungsplanWeek;
use Carbon\Carbon;
use Illuminate\Support\Str;

class VertretungsplanController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return array
     */

    public function make($gruppen = null){
        if ($gruppen != null){
            $gruppen_arr = explode('/', $gruppen);
            $klassen = Klasse::whereIn('name',$gruppen_arr)->get();
            if ($klassen->count() == 0){
                $gruppen_arr = explode(',', $gruppen);
                foreach ($gruppen_arr as $key => $gruppe){
                    $gruppe = Str::replace('dc=','',$gruppe);
                    $gruppe = Str::replace('ou=','',$gruppe);
                    $gruppe = Str::replace('cn=','',$gruppe);
                    $gruppe = Str::replace(settings('vertretungsplan_remove').'-','',$gruppe);
                    $gruppe = settings('vertretungsplan_add_prefix').' '.$gruppe;
                    $gruppen_arr[$key] = $gruppe;
                }

                $klassen = Klasse::whereIn('name',$gruppen_arr)->get();
            }
        } else {
            $klassen=Klasse::all();
        }

        $klassen= $klassen->pluck('id');

        $addDays=settings('show_vertretungen_days');
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
        $vertretungen = Vertretung::whereBetween('date', [Carbon::today()->format('Y-m-d'), $targetDate->format('Y-m-d')])
            ->whereIn('klassen_id',$klassen)
            ->orderBy('date')
            ->orderBy('stunde')->get();

        $news = DailyNews::query()
            ->where(function($query) use ($targetDate){
                $query ->whereDate('date_start', '<=', $targetDate);
                $query->whereDate('date_end', '<=', $targetDate);
                $query->whereDate('date_end', '>=', Carbon::today());
            })
            ->orWhere(function($query) use ($targetDate){
                $query ->whereDate('date_start', '<=', $targetDate);
                $query->whereDate('date_end', '>=', Carbon::today());
            })
            ->orWhere(function($query) use ($targetDate){
                $query ->whereDate('date_start', '<=', $targetDate);
                $query->whereNull('date_end');
            })
            ->orderBy('date_start')
            ->get();

        //Wochentyp

        $weeks = VertretungsplanWeek::where('week',  Carbon::today()->copy()->startOfWeek()->format('Y-m-d'))
            ->orWhere('week', $targetDate->copy()->startOfWeek()->format('Y-m-d'))
            ->orderBy('week')
            ->get();

        //Absences
        $absences = Absence::whereDate('start', '<=', $targetDate)
            ->whereDate('end', '>=', Carbon::today())
            ->whereHas('user', function ($query){
                $query->whereNotNull('kuerzel');
            })
            ->where('showVertretungsplan',1)
            ->get()->unique('users_id')->sortBy('user.name');

        return [
            'vertretungen' => $vertretungen,
            'news'          => $news,
            'targetDate' => $targetDate,
            'absences' => $absences,
            'weeks' => $weeks
        ];


    }

    public function allowAllIndex($key){
        if ($key == env('VERTRETUNGSPLAN_ALLOW_IFRAME_KEY')){
            return response()->view('vertretungsplan.index',$this->make())
                ->header('Content-Security-Policy', '*')
                ->header('X-Frame-Options', 'allow-from *');
        }
    }

    public function index($gruppen = null)
    {

        return response()->view('vertretungsplan.index',$this->make($gruppen))
            ->header('Content-Security-Policy', config('cors.Content-Security-Policy'))
            ->header('X-Frame-Options', config('cors.X-Frame-Options'))
            ->header("Cache-Control","no-cache, no-store, must-revalidate");
    }

    public function toJSON($gruppen = null){
        $plan = $this->make($gruppen);

        $vertretungen = [];
        foreach ($plan['vertretungen'] as $vertretung){
            $vertretungen[]=[
              'date' => $vertretung->date->format('Y-m-d'),
              'klasse' => $vertretung->klasse->name,
              'stunde' => $vertretung->stunde,
              'altFach' => $vertretung->altFach,
              'neuFach' => $vertretung->neuFach,
              'lehrer' => optional($vertretung->lehrer)->shortname,
              'comment' => $vertretung->comment,
            ];
        }

        $news = [];
        foreach ($plan['news'] as $News){
            $news[]=[
              'date_start' => $News->date_start,
              'date_end' => $News->date_end,
              'news' => $News->news,
            ];
        }

        $absences = [];
        foreach ($plan['absences'] as $absence){
            $absences[]=[
              'start' => $absence->start,
              'end' => $absence->end,
              'user' => $absence->user->shortname,
            ];
        }

        $weeks = [];
        foreach ($plan['weeks'] as $week){
            $weeks[$week->week->format('Y-m-d')]=$week->type;
        }

        $array = [
            'vertretungen' => $vertretungen,
            'news' => $news,
            'absences' => $absences,
            'targetDate' => $plan['targetDate'],
            'weeks' => $weeks
        ];
        return json_encode($array);
    }

}
