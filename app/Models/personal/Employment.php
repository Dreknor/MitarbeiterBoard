<?php

namespace App\Models\personal;

use App\Models\Group;
use App\Models\User;
use Carbon\Carbon;
use Carbon\CarbonInterval;
use DateTime;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\MediaLibrary\InteractsWithMedia;

class Employment extends Model
{
    use HasFactory, InteractsWithMedia, SoftDeletes;

    protected $fillable = ['employe_id', 'department_id', 'hour_type_id', 'start', 'end', 'hours', 'comment', 'salary_type', 'salary_table_id', 'salary', 'replaced_employment_id','media_id'];

    protected $casts = [
        'start' => 'date',
        'end' => 'date',
    ];

    protected $with = ['hour_type'];



    public function employe()
    {
        return $this->belongsTo(User::class, 'employe_id');
    }

    public function department()
    {
        return $this->belongsTo(Group::class, 'department_id');
    }

    public function hour_type()
    {
        return $this->belongsTo(HourType::class, 'hour_type_id');
    }


    /**
     * @param $query
     * @param Department $department
     * @return mixed
     */
    public function scopeDepartment($query, Group $department)
    {
        return $query->where('department_id', '==', $department->id);
    }

    public function scopeActive($query, DateTime $start = null, DateTime $end = null)
    {
        if ($start == null) {
            $start = Carbon::now();
        }

        if ($end == null) {
            $end = $start;
        }

        return $query
            ->where([
                ['start', '<=', $end],
                ['employments.end', '=', null]
            ])
            ->orWhere(
                [
                    ['start', '<=', $end],
                    ['end', '>=', $start]
                ]);
    }


    public function getPercentAttribute()
    {
        $hour_type = $this->hour_type;

        return ($this->hours / $hour_type->fulltimehours) * 100;
    }

    /**
     * @param Carbon $day
     * @return CarbonInterval
     */

}
