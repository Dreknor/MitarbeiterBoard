<?php

use Carbon\Carbon;
use Carbon\CarbonInterval;
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

function redirectBack(string $type = null, string $meldung = null, $anchor = null)
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

function is_holiday(Carbon $date)
{
    $holidays = Cache::remember('holidays_'.$date->format('Y'), 1200, function () use ($date) {
        return collect(json_decode(file_get_contents("https://ipty.de/feiertag/api.php?do=getFeiertage&jahr=" . $date->format('Y') . "&outformat=Y-m-d&loc=SN")));
    });

    return $holidays->first(function ($item) use ($date) {
        return $item->date == $date->format('Y-m-d');
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






