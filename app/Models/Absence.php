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
    use HasFactory;
    use SoftDeletes;

    protected $fillable = ['users_id', 'holiday_id', 'creator_id', 'reason', 'start', 'end', 'before', 'showVertretungsplan', 'sick_note_required', 'sick_note_date'];

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
    /**
     * Ab diesem Arbeitstag einer Krankheit ist ein Krankenschein nötig (Regel: ab dem 3. Krankheitstag) –
     * Setting `absence_sick_note_days` (Modul absences), sonst config/absences.php.
     */
    public static function krankenscheinTage(): int
    {
        return (int) (settings('absence_sick_note_days', 'absences') ?? config('absences.absence_sick_note_days'));
    }

    protected static function boot()
    {
        parent::boot();

        static::creating(function (Absence $absence) {
            // Im Scheduler/Queue gibt es keinen angemeldeten Benutzer – dann den übergebenen Ersteller behalten
            $absence->creator_id = auth()->id() ?? $absence->creator_id;
            $absence->krankenscheinPruefen();
        });

        static::updating(function (Absence $absence) {
            $absence->krankenscheinPruefen();
        });

        // Erst nach dem Speichern gibt es eine ID – vorher blieb absence_id am Vertretungsplan-Eintrag leer
        static::saved(function (Absence $absence) {
            $absence->vertretungsplanAbgleichen();
        });

        static::deleted(function (Absence $absence) {
            $absence->vertretungsplanEintrag()?->delete();
        });
    }

    private function krankenscheinPruefen(): void
    {
        // Gründe aus config absences.absence_sick_note ("krank", "Kind krank"); vorher stand hier ein
        // nicht existierender Config-Schlüssel, die Regel griff deshalb nie.
        if (Str::contains((string) $this->reason, (array) config('absences.absence_sick_note'))
            && $this->days >= self::krankenscheinTage()) {
            $this->sick_note_required = 1;
        }
    }

    /**
     * Eintrag am Vertretungsplan; ältere Einträge ohne absence_id werden über Person + Beginn gefunden.
     */
    public function vertretungsplanEintrag(): ?VertretungsplanAbsence
    {
        return VertretungsplanAbsence::where('absence_id', $this->id)->first()
            ?? VertretungsplanAbsence::whereNull('absence_id')
                ->where('user_id', $this->users_id)
                ->whereDate('start_date', $this->getOriginal('start') ?? $this->start)
                ->first();
    }

    private function vertretungsplanAbgleichen(): void
    {
        if (!$this->wasRecentlyCreated && !$this->wasChanged(['start', 'end', 'users_id', 'showVertretungsplan'])) {
            return;
        }

        $eintrag = $this->wasRecentlyCreated ? null : $this->vertretungsplanEintrag();

        if (!$this->showVertretungsplan) {
            $eintrag?->delete();

            return;
        }

        $eintrag ??= new VertretungsplanAbsence();
        $eintrag->fill([
            'user_id' => $this->users_id,
            'start_date' => $this->start,
            'end_date' => $this->end,
            'absence_id' => $this->id,
        ]);

        if (!$eintrag->exists || $eintrag->isDirty()) {
            $eintrag->save();
        }
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'creator_id')->withDefault(['name' => 'System']);
    }

    public function holiday(){
        return $this->belongsTo(\App\Models\personal\Holiday::class, 'holiday_id');
    }

    public function user(){
        return $this->belongsTo(User::class, 'users_id')->withDefault([
            'name' => 'System / gelöschter Benutzer']);
    }
}
