<?php

namespace App\Models\personal;

use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
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


    /**
     * Zeiten immer als H:i:s speichern (Formulare liefern H:i, die Getter erwarten H:i:s).
     */
    public function setStartAttribute($value): void
    {
        $this->attributes['start'] = self::normalisiereZeit($value);
    }

    public function setEndAttribute($value): void
    {
        $this->attributes['end'] = self::normalisiereZeit($value);
    }

    private static function normalisiereZeit($value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }
        if ($value instanceof \DateTimeInterface) {
            return $value->format('H:i:s');
        }
        $value = trim((string) $value);
        if (preg_match('/^\d{1,2}:\d{2}$/', $value)) {
            return str_pad($value, 5, '0', STR_PAD_LEFT).':00';
        }
        if (preg_match('/^\d{1,2}:\d{2}:\d{2}$/', $value)) {
            return str_pad($value, 8, '0', STR_PAD_LEFT);
        }

        return \Carbon\Carbon::parse($value)->format('H:i:s');
    }

    //Casts Time
    public function getStartAttribute()
    {
        if (!is_null($this->attributes['start'])) {
            return Carbon::createFromFormat('Y-m-d H:i:s', $this->date->format('Y-m-d') . ' ' . $this->attributes['start']);
        }
    }

    public function getEndAttribute()
    {
        if (!is_null($this->attributes['end'])) {
            return Carbon::createFromFormat('Y-m-d H:i:s', $this->date->format('Y-m-d') . ' ' . $this->attributes['end']);
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
                    if ($event->date->format('Y-m-d') == $this->date->format('Y-m-d') and Str::contains($event->event, ['pause', 'Pause']) and $event->employe_id == $this->attributes['employe_id']) {
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

    public function diff_start_first_event(Collection $events = null)
    {

        $events_filtered = $events->whereInstanceOf(RosterEvents::class);

        $events_filtered = $events_filtered->filter(function ($event) {

            return ($event->date->format('Y-m-d') == $this->date->format('Y-m-d')) && ($event->start->format('H:i:s') < $this->attributes['start']);

        });


        return ($events_filtered->count() > 0)? true : false;
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
