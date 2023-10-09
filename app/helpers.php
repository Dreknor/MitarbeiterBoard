<?php

use App\Models\Setting;
use Carbon\Carbon;
use Carbon\CarbonInterval;
use Illuminate\Config\Repository;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

enum units
{
    case seconds;
    case minutes;
    case hours;
    case mixed;
}

function random_color_part()
{
    return str_pad(dechex(mt_rand(0, 255)), 2, '0', STR_PAD_LEFT);
}

function random_color()
{
    return random_color_part() . random_color_part();
}

/**
 * @param string|null $type
 * @param string|null $meldung
 * @param string|null $anchor
 * @return RedirectResponse
 */
function redirectBack(string $type = null, string $meldung = null, string $anchor = null): RedirectResponse
{
    return redirect()->to(url()->previous().$anchor)->with([
        'type' => $type,
        'Meldung' => $meldung
    ]);
}

function money($money = null, $symbol = true)
{
    if ($money != null) {
        if ($symbol == true) {
            return number_format($money, 2) . " €";
        } else {
            return number_format($money, 2);
        }
    }
}

/**
 * @param Carbon $date
 * @return string
 */
function is_holiday(Carbon $date)
{
    $holidays = Cache::remember('holidays_'.$date->format('Y'), 5000, function () use ($date) {
        return collect(json_decode(file_get_contents("https://ipty.de/feiertag/api.php?do=getFeiertage&jahr=" . $date->format('Y') . "&outformat=Y-m-d&loc=SN")));
    });

    return $holidays->first(function ($item) use ($date) {
        return $item->date == $date->format('Y-m-d');
    });
}

function is_ferien(Carbon $date, $state = null, $year = null)
{
    if (is_null($year)){
        $year = $date->format('Y');
    }

    if (is_null($state)){
        $state = settings('ferien_state', 'holidays');
    }

    $ferien = Cache::remember('ferien_'.$year, 5000, function () use ($year, $state) {
        return collect(json_decode(file_get_contents("https://ferien-api.de/api/v1/holidays/".$state."/".$year)));
    });
    return $ferien->first(function ($item) use ($date) {
        $start = Carbon::createFromFormat('Y-m-d', $item->start);
        $end = Carbon::createFromFormat('Y-m-d', $item->end);
        return $date->between($start->subDay(), $end->addDay());
    });
}

function calculateWorkingTime(Collection $working_times, Collection $roster_events = null)
{
    if (!is_null($roster_events)) {
        $break = $roster_events->filter(function ($event) {
            return strtolower($event->event) == strtolower('Pause') ? $event : false;
        })->sum('duration');
    }

    $time = $working_times->sum('duration');
    $interval = CarbonInterval::minutes($time - $break)->cascade();


    return CarbonInterval::hours(($interval->d*24)+$interval->h)->minutes($interval->minutes)->seconds(0);

}

/**
 * leading zero
 * @param $number
 * @return string
 */
function format_number($number){
    if ($number == null){
        return "00";
    }
    return (strlen($number) < 2) ? "0{$number}" : $number;
}

/**
 * Converting decimal to time formatted HH:MM
 * @param $dec
 * @return string
 */

function convertTime($dec)
{
    $sign = false;
    if ($dec < 0){
        $sign = true;
        $dec = abs($dec);
    }

    // start by converting to seconds
    $seconds = $dec;
    $minutes = floor($dec / 60);

    //rest Sekunden
    $seconds -= $minutes * 60;
    $hours = floor($minutes / 60);
    $minutes -= $hours *60;

    $seconds = round($seconds);

    // return the time formatted HH:MM:SS
    if ($sign) {
        return '-'.lz($hours).":".lz($minutes).":".lz($seconds);
    }
    return lz($hours).":".lz($minutes).":".lz($seconds);
}

// lz = leading zero
function lz($num)
{
    return (strlen($num) < 2) ? "0{$num}" : $num;
}
function percent_to_seconds($percent, $full_hours = 40){
    $hours = $percent * $full_hours/100;
    $minutes = $hours*60;
    $seconds = $minutes*60;

    return ($seconds);
}

/**
 * @param $key
 * @return Repository|Application|\Illuminate\Foundation\Application|mixed
 */
function settings($key, $config_file = null)
{
    $settings = Cache::remember('setting_'.$key, 60, function() use ($key) {
        return Setting::where('setting', $key)->first()?->value;
    });


    if (is_null($settings)){

        if (!is_null($config_file)){
            return config($config_file.'.'.$key);
        }

        return config('config.'.$key);
    }

    return $settings;
}






