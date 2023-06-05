<?php

namespace App\Models\personal;

use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class RosterEvents extends Model
{
    use SoftDeletes;


    protected $fillable = ['date', 'start', 'end', 'employe_id', 'roster_id', 'event'];
    protected $visible = ['date', 'start', 'end', 'employe_id', 'roster_id', 'event'];

    protected $casts =[
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

    public function getDurationAttribute()
    {
        return $this->start->diffInMinutes($this->end);
    }


    //Casts Time

    public function getStartAttribute()
    {
        if (!is_null($this->attributes['start'])) {
            return Carbon::createFromFormat('Y-m-d H:i:s', $this->date->format('Y-m-d') . " " . $this->attributes['start']);
        }
    }

    public function getEndAttribute()
    {
        if (!is_null($this->attributes['end'])) {
            return Carbon::createFromFormat('Y-m-d H:i:s', $this->date->format('Y-m-d') . " " . $this->attributes['end']);
        }
    }


    public function getICal(){
        $icalObject =
            "BEGIN:VEVENT
               DTSTART:" . $this->start->format('Ymd\THis') . "
               DTEND:" . $this->end->format('Ymd\THis') . "
               UID:r".$this->roster_id.'e'.$this->id."
               SUMMARY:" . str_replace(' ', '__', $this->event) . "
            END:VEVENT\n";

        return$icalObject;
    }
}
