<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class Absence extends Model
{
    use SoftDeletes;

    protected $fillable = ['users_id', 'creator_id', 'reason', 'start', 'end', 'before', 'showVertretungsplan', 'sick_note_required', 'sick_note_date'];

    protected $casts = [
        'start' =>  'date',
        'end' =>  'date',
        'showVertretungsplan' =>  'boolean',
        'sick_note_required' =>  'boolean',
        'sick_note_date' =>  'date',
    ];

    public function getDaysAttribute() : int
    {

        $days = $this->start->diffInDaysFiltered(function (Carbon $date) {
            return $date->isWeekday() && !is_holiday($date);
        }, $this->end);

        return  $days+1;

    }
    protected static function boot()
    {
        parent::boot();
        static::creating(function ($absence) {
            $absence->creator_id = auth()->id();

            if (Str::contains($absence->reason, config('absences.absence_reason_sick'))){
                if ($absence->start->diffInDays($absence->end) > config('absences.absence_sick_note_days')){
                    $absence->sick_note_required = 1;
                }
            }

            if ($absence->showVertretungsplan != null){
                $vertretungsplanAbsence = new VertretungsplanAbsence([
                    'user_id' => $absence->users_id,
                    'start_date' => $absence->start,
                    'end_date' => $absence->end,
                    'absence_id' => $absence->id,
                ]);

                $vertretungsplanAbsence->save();
            }
        });

        static::updating(function ($absence) {
            if (Str::contains($absence->reason, config('absences.absence_reason_sick'))){
                if ($absence->start->diffInDays($absence->end) > config('absences.absence_sick_note_days')){
                    $absence->sick_note_required = 1;
                }
            }

            if ($absence->showVertretungsplan != null){
                $vertretungsplanAbsence = VertretungsplanAbsence::where('absence_id', $absence->id)->first();
                if ($vertretungsplanAbsence == null){
                    $vertretungsplanAbsence = new VertretungsplanAbsence([
                        'user_id' => $absence->users_id,
                        'start_date' => $absence->start,
                        'end_date' => $absence->end,
                        'absence_id' => $absence->id,
                    ]);
                } else {
                    $vertretungsplanAbsence->start_date = $absence->start;
                    $vertretungsplanAbsence->end_date = $absence->end;
                }

                $vertretungsplanAbsence->save();
            }
        });
    }

    public function user(){
        return $this->belongsTo(User::class, 'users_id')->withDefault([
            'name' => 'System / gelöschter Benutzer']);
    }
}
