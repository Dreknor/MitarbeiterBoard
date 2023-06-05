<?php

namespace App\Models\personal;

use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class WorkingTime extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $fillable = ['date', 'start', 'end', 'employe_id', 'roster_id', 'function','googleCalendarId'];
    protected $visible = ['date', 'start', 'end', 'employe_id', 'roster_id', 'function','googleCalendarId'];

    protected $with = ['employe'];



    protected $casts = [
        'date' => 'datetime:Y-m-d'
    ];

    public function roster()
    {
        return $this->belongsTo(Roster::class);
    }

    public function employe()
    {
        return $this->belongsTo(User::class, 'employe_id');
    }


    //Casts Time
    public function getStartAttribute()
    {
        if (!is_null($this->attributes['start'])) {
            return Carbon::createFromFormat('Y-m-d H:i:s', $this->attributes['date'] . ' ' . $this->attributes['start']);
        }
    }

    public function getEndAttribute()
    {
        if (!is_null($this->attributes['end'])) {
            return Carbon::createFromFormat('Y-m-d H:i:s', $this->attributes['date'] . ' ' . $this->attributes['end']);
        }
    }

    public function getDurationAttribute()
    {
        if ($this->attributes['start'] != "" and $this->attributes['end'] != "") {
            return $this->start->diffInMinutes($this->end);
        }

        return null;
    }


    public function needs_break(Collection $events = null)
    {
        if (!is_null($this->attributes['start']) and !is_null($this->attributes['end']) and $this->start->diffInHours($this->end) > 6) {
            if (!is_null($events)) {
                $events = $events->whereInstanceOf(RosterEvents::class);
                $break = $events->filter(function ($event) {
                    if ($event->date->format('Y-m-d') == $this->attributes['date'] and Str::contains($event->event, ['pause', 'Pause']) and $event->employe_id == $this->attributes['employe_id']) {
                        return $event;
                    }
                });

                if (count($break) < 1) {
                    return true;
                }
                return false;

            }

            return true;
        }
        return false;
    }

    public function getICal(){


        return "BEGIN:VEVENT\n
                   DTSTART:" . $this->start->format('Ymd\THis') . "
                   DTEND:" . $this->end->format('Ymd\THis') . "
                   UID:r".$this->roster_id.'w'.$this->id."
                   SUMMARY:" . str_replace(' ', '__', 'Dienst') . "
                END:VEVENT\n";
    }
}
