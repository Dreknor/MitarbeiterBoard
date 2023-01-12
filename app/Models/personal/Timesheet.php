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
        'month', 'year', 'employe_id', 'holidays_old', 'holidays_new', 'holidays_rest', 'working_time_account', 'comment', 'locked_at', 'locked_by'
    ];

    public function getIsLockedAttribute(){
        if ($this->locked_at != null){
            return true;
        }
        return false;
    }

    public function timesheet_days(){
        return $this->hasMany(TimesheetDays::class);
    }
    public function employe(){
        return $this->belongsTo(User::class, 'employe_id');
    }

    public function locked_by(){
        return $this->belongsTo(User::class);
    }





    public function updateTime(){
        if ($this->is_locked){
            return false;
        }

        $timesheet_days = $this->timesheet_days;
        $start_of_month = Carbon::createFromFormat('m-Y', $this->month.'-'.$this->year)->startOfMonth();
        $monthBefore = $start_of_month->copy()->subMonth();
        $employe = $this->employe;

        $timesheet_old = Cache::remember('timesheet_'.$this->employe_id.'_'.$monthBefore->format('Y_m'), 1, function () use ($employe, $monthBefore){
            return Timesheet::where('month', $monthBefore->month)->where('year', $monthBefore->year)->where('employe_id', $employe->id)->first();
        });

        $working_time = $timesheet_old?->working_time_account;

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

        //Urlaub berechnen
        $this->holidays_new = $timesheet_days->where('comment', 'Urlaub')->count();
        $this->holidays_old = ($timesheet_old == null)? ceil($this->employe->getHolidayClaim($start_of_month)/12*$this->month) :$timesheet_old?->holidays_rest;
        $this->holidays_rest = ceil($this->employe->getHolidayClaim($start_of_month)/12*$this->month) - $this->holidays_old - $this->holidays_new;

        if ($this->month == 1){
            $this->holidays_rest = $this->holidays_old - $this->holidays_new + $this->employe->getHolidayClaim($start_of_month);
        }

        $this->save();
    }
}
