<?php

namespace App\Models\personal;

use App\Models\Absence;
use App\Models\User;
use App\Services\Personal\Zeit\ArbeitszeitService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use OwenIt\Auditing\Contracts\Auditable;

/**
 * Buchung eines Tages im Arbeitszeitnachweis.
 *
 * - Zeitbuchung: start/end (+ Pause in Minuten)
 * - Gutschrift: percent_of_workingtime (Anteil der Tages-Sollzeit, z. B. Urlaub 100 %)
 * source: NULL = manuell, "terminal", "dienstplan", "urlaub", "abwesenheit"
 * ("urlaub"/"abwesenheit" werden automatisch abgeglichen, siehe TimesheetService).
 */
class TimesheetDays extends Model implements Auditable
{
    use SoftDeletes;
    use \Znck\Eloquent\Traits\BelongsToThrough;
    use \OwenIt\Auditing\Auditable;

    public const SOURCE_URLAUB = 'urlaub';
    public const SOURCE_ABWESENHEIT = 'abwesenheit';
    public const SOURCE_DIENSTPLAN = 'dienstplan';
    public const SOURCE_TERMINAL = 'terminal';

    protected $fillable = [
        'timesheet_id', 'date', 'start', 'end', 'pause', 'percent_of_workingtime', 'comment',
        'source', 'holiday_id', 'absence_id',
    ];

    protected $casts = [
      'date' => 'datetime:Y-m-d',
      'start' => 'datetime:H:i',
      'end' => 'datetime:H:i',
    ];

    public function timesheet(){
        return $this->belongsTo(Timesheet::class);
    }

    public function holiday(){
        return $this->belongsTo(Holiday::class);
    }

    public function absence(){
        return $this->belongsTo(Absence::class);
    }

    public function employe(){
        return $this->belongsToThrough(User::class,Timesheet::class,'timesheet_id', '',[
            'App\Models\personal\Timesheet' => 'timesheet_id',
            'App\Models\User' => 'employe_id',
        ]);
    }

    public function getIsCreditAttribute(): bool
    {
        return $this->percent_of_workingtime !== null && $this->percent_of_workingtime !== '';
    }

    public function getIsAutomaticAttribute(): bool
    {
        return in_array($this->source, [self::SOURCE_URLAUB, self::SOURCE_ABWESENHEIT], true);
    }

    /**
     * Dauer in Sekunden: gearbeitete Zeit abzüglich Pause bzw. Gutschrift
     * (Anteil der Tages-Sollzeit laut Arbeitszeitmodell – an freien Tagen 0).
     */
    public function getDurationAttribute(): float
    {
        if ($this->is_credit) {
            $employe = $this->timesheet?->employe;
            if ($employe === null || $this->date === null) {
                return 0.0;
            }

            $soll = app(ArbeitszeitService::class)->sollSekunden($employe, $this->date);

            return $soll / 100 * (float) $this->percent_of_workingtime;
        }

        if ($this->start === null || $this->end === null) {
            return 0.0;
        }

        return max(0, $this->start->diffInSeconds($this->end) - ((int) $this->pause * 60));
    }
}
