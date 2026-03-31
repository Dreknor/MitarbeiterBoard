<?php

namespace App\Models\personal;

use App\Enums\ContractType;
use App\Enums\EmploymentStatus;
use App\Enums\EmploymentStatusReason;
use App\Enums\EmploymentType;
use App\Enums\TerminationReason;
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

    protected $fillable = [
        'employe_id', 'department_id', 'hour_type_id', 'start', 'end', 'hours', 'comment',
        'salary_type', 'salary_table_id', 'salary', 'replaced_employment_id', 'media_id',
        // Neue Personal-Felder (Phase 0)
        'employment_type', 'contract_type', 'status', 'status_reason', 'termination_reason',
        'probation_end', 'notice_period', 'salary_group', 'salary_level',
        'is_amendment', 'amendment_description', 'is_internal_transfer',
    ];

    protected $casts = [
        'start'             => 'date',
        'end'               => 'date',
        'probation_end'     => 'date',
        'employment_type'   => EmploymentType::class,
        'contract_type'     => ContractType::class,
        'status'            => EmploymentStatus::class,
        'status_reason'     => EmploymentStatusReason::class,
        'termination_reason' => TerminationReason::class,
        'is_amendment'       => 'boolean',
        'is_internal_transfer' => 'boolean',
    ];

    protected $with = ['hour_type'];

    protected $attributes = [
        'status' => 'aktiv',
    ];



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
