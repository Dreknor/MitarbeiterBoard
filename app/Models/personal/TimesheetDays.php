<?php

namespace App\Models\personal;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Cache;

class TimesheetDays extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'timesheet_id','date', 'start', 'end', 'pause', 'percent_of_workingtime', 'comment'
    ];

    protected $casts = [
      'date' => 'datetime:Y-m-d',
      'start' => 'datetime:H:i',
      'end' => 'datetime:H:i',
    ];
    public function timesheet(){
        return $this->belongsTo(Timesheet::class);
    }

    public function getDurationAttribute()
    {
        $minutes = 0;
        if (!is_null($this->start) and !is_null($this->end)){
            $minutes = $this->start->diffInMinutes($this->end);
        }



        if ($this->percent_of_workingtime != null){
            $employment =  Cache::remember('employments_date_'.$this->timesheet->id.'_'.$this->date,60, function (){
                return $this->timesheet->employe->employments_date($this->date)->sum('percent');
            });
            $minutes = (percent_to_minutes($employment)/5)/100*$this->percent_of_workingtime;
        }

        return $minutes-$this->pause;
    }

}
