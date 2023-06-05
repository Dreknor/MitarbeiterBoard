<?php

namespace App\Models\personal;

use App\Models\Group;
use DateTime;
use Dyrynda\Database\Support\CascadeSoftDeletes;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Roster extends Model
{
    use HasFactory;
    use CascadeSoftDeletes;
    use SoftDeletes;

    protected $fillable = ['start_date', 'type', 'comment', 'department_id'];
    protected $visible = ['start_date', 'type', 'comment'];

    protected $cascadeDeletes = ['working_times', 'events'];

    protected $casts = [
        'start_date' => 'datetime'
    ];

    public function department()
    {
        return $this->belongsTo(Group::class, 'department_id');
    }

    public function working_times()
    {
        return $this->hasMany(WorkingTime::class);
    }

    public function events()
    {
        return $this->hasMany(RosterEvents::class);
    }

    public function news()
    {
        return $this->hasMany(RosterNews::class);
    }

    public function working_times_day(DateTime $day)
    {
        return $this->hasMany(WorkingTime::class)->where('date', $day)->get();
    }

    public function getIsTemplateAttribute()
    {
        if ($this->attributes['type'] == 'template') {
            return true;
        }

        return false;
    }


}
