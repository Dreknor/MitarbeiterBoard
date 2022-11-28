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
    return redirect()->to(url()->previous())->with([
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
    $holidays = Cache::remember('holidays', 2628000, function () use ($date) {
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
function convertTime(String|Int $time, units $unit = units::minutes)
{
    $hours = 0;
    $minutes = 0;
    $seconds = 0;
    $sign = null;

    if ($unit != units::mixed and $time < 0){
        $sign = true;
        $time = abs($time);
    }

    switch ($unit){
        case units::mixed:
            $parsed =  explode(':', $time);
            $hours = $parsed[0];
            $minutes = (array_key_exists(1, $parsed))? $parsed[1] : 0;
            $seconds = (array_key_exists(2, $parsed))? $parsed[2] : 0;
            break;
        case units::hours:
            $hours = $time;
            break;
        case units::minutes:
            $minutes = $time;
            break;
        case units::seconds:
            $seconds = $time;
            break;

    }

    //seconds  to minutes
    $minutes += floor($seconds/60);
    $seconds = $seconds%60;

    //minutes to hour
    $hours += floor($minutes/60);
    $minutes = $minutes%60;



    // return the time formatted HH:MM:SS
    return ($sign == true)? '-'.format_number($hours).":".format_number($minutes): format_number($hours).":".format_number($minutes);
}

function percent_to_minutes(Int $percent, $full_hours = 40){
    $full_minutes = $full_hours*60;

    return round(($percent*$full_minutes)/100);
}






