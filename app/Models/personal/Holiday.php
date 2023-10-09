<?php

namespace App\Models\personal;

use App\Models\Absence;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Holiday extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $fillable = [
        'employe_id',
        'start_date',
        'end_date',
        'approved',
        'approved_by',
        'approved_at',
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'approved' => 'boolean'
    ];

    public function employe()
    {
        return $this->belongsTo(User::class);
    }

       public function approved_by()
        {
            return $this->belongsTo(User::class, 'approved_by');
        }

    protected function days(): Attribute
    {
        return Attribute::make(
            get: function () {
                $days = $this->start_date->diffInDaysFiltered(function (Carbon $date) {
                    return $date->isWeekday() && !is_holiday($date);
                }, $this->end_date);

                return  $days+1;
            },
        );
    }
}
