<?php

namespace App\Http\Controllers\Vertretungsplan;

use App\Http\Controllers\Controller;
use App\Models\Absence;
use App\Models\DailyNews;
use App\Models\Klasse;
use App\Models\Vertretung;
use App\Models\VertretungsplanWeek;
use Carbon\Carbon;
use Illuminate\Support\Str;

class VertretungsplanSendController extends Controller
{

    public function toJSON($key, $gruppen = null): bool|\Illuminate\Http\JsonResponse|string
    {

        if ($key != config('config.vertretungsplan_api_key')){
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        $plan = $this->make($gruppen);

        $vertretungen = [];
        foreach ($plan['vertretungen'] as $vertretung){
            $vertretungen[]=[
                'id' => $vertretung->id,
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
