<?php

namespace App\Models\personal;

use App\Models\OxTermin;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Log;

class RosterEvents extends Model
{
    use HasFactory;
    use SoftDeletes;


    protected $fillable = ['date', 'start', 'end', 'employe_id', 'roster_id', 'event', 'ox_termin_id', 'source'];

    /** Automatisch gesetzte Abwesenheits-Markierung (Urlaub, krank …) */
    public const SOURCE_ABWESENHEIT = 'abwesenheit';
    protected $visible = ['id', 'date', 'start', 'end', 'employe_id', 'roster_id', 'event', 'ox_termin_id', 'source'];

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

    public function oxTermin()
    {
        return $this->belongsTo(OxTermin::class, 'ox_termin_id');
    }

    public function getIsAbwesenheitAttribute(): bool
    {
        return $this->source === self::SOURCE_ABWESENHEIT;
    }

    public function getDurationAttribute()
    {
        return $this->start->diffInMinutes($this->end);
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
            try {
                return Carbon::createFromFormat('Y-m-d H:i:s', $this->date->format('Y-m-d') . " " . $this->attributes['start']);
            } catch (\Exception $e) {
                Log::error('Error parsing start time: ', [
                    'date' => $this->date,
                    'start' => $this->attributes['start'],
                    'error' => $e->getMessage()
                ]);
                return null;
            }

        } else {
            return null;
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
