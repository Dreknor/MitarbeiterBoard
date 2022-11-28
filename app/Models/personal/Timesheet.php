<?php

namespace App\Models\personal;

use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Cache;

class Timesheet extends Model
{
    use SoftDeletes;

    public $fillable = [
        'month', 'year', 'employe_id', 'holidays_old', 'holidays_new', 'holidays_rest', 'working_time_account', 'comment'
    ];

    public function timesheet_days(){
        return $this->hasMany(TimesheetDays::class);
    }
    public function employe(){
        return $this->belongsTo(User::class, 'employe_id');
    }

    public function updateTime(){
        $timesheet_days = $this->timesheet_days;
        $start_of_month = Carbon::createFromFormat('m-Y', $this->month.'-'.$this->year)->startOfMonth();
        $monthBefore = $start_of_month->copy()->subMonth();
        $employe = $this->employe;


        $working_time = Cache::remember('timesheet_'.$this->employe_id.'_'.$monthBefore->format('Y_m'), 1, function () use ($employe, $monthBefore){
            return Timesheet::where('month', $monthBefore->month)->where('year', $monthBefore->year)->where('employe_id', $employe->id)->first();
        })?->working_time_account;

        for ($x = $start_of_month->copy(); $x->lessThanOrEqualTo($start_of_month->endOfMonth()); $x->addDay()){
            $timesheet_day = $timesheet_days->filterDay($x);
            $employment = $employe->employments_date($x);

            if($x->isWeekday() and !is_holiday($x)){

                $working_time += $timesheet_day->sum('duration')-percent_to_minutes($employment->sum('percent'))/5;
            } else{
                $working_time += $timesheet_day->sum('duration');
            }
        }

        $this->working_time_account = $working_time;

        $this->holidays_new = $timesheet_days->where('comment', 'Urlaub')->count();

        $this->save();
    }
}
