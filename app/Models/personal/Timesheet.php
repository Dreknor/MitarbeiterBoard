<?php

namespace App\Models\personal;

use App\Models\User;
use App\Services\Personal\Zeit\TimesheetService;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use OwenIt\Auditing\Contracts\Auditable;

/**
 * Arbeitszeitnachweis eines Monats.
 *
 * Berechnung (Saldo, Urlaubsfelder) und Workflow laufen über
 * App\Services\Personal\Zeit\TimesheetService.
 */
class Timesheet extends Model implements Auditable
{
    use HasFactory;
    use SoftDeletes;
    use \OwenIt\Auditing\Auditable;

    public const STATUS_OFFEN = 'offen';
    public const STATUS_EINGEREICHT = 'eingereicht';
    public const STATUS_ABGESCHLOSSEN = 'abgeschlossen';

    public $fillable = [
        'month', 'year', 'employe_id', 'holidays_old', 'holidays_new', 'holidays_rest', 'working_time_account', 'comment', 'locked_at', 'locked_by',
        'requires_review', 'review_reason', 'reviewed_at', 'reviewed_by',
        'submitted_at', 'submitted_by', 'return_reason', 'plan_uebernommen_bis',
    ];

    protected $casts = [
        'requires_review' => 'boolean',
        'reviewed_at'      => 'datetime',
        'locked_at'        => 'datetime',
        'submitted_at'     => 'datetime',
        'plan_uebernommen_bis' => 'date',
        'holidays_old'     => 'float',
        'holidays_new'     => 'float',
        'holidays_rest'    => 'float',
    ];

    public function getWorkingTimeAccountAttribute($value): int
    {
        return (int) ($value ?? 0);
    }

    public function getIsLockedAttribute(): bool
    {
        return $this->locked_at !== null;
    }

    public function getStatusAttribute(): string
    {
        if ($this->is_locked) {
            return self::STATUS_ABGESCHLOSSEN;
        }

        return $this->submitted_at !== null ? self::STATUS_EINGEREICHT : self::STATUS_OFFEN;
    }

    public function getStatusLabelAttribute(): string
    {
        return match ($this->status) {
            self::STATUS_ABGESCHLOSSEN => 'Abgeschlossen',
            self::STATUS_EINGEREICHT => 'Eingereicht',
            default => 'Offen',
        };
    }

    public function monthStart(): Carbon
    {
        return Carbon::create($this->year, $this->month, 1)->startOfDay();
    }

    public function monthEnd(): Carbon
    {
        return $this->monthStart()->endOfMonth();
    }

    public function timesheet_days(){
        return $this->hasMany(TimesheetDays::class);
    }

    /**
     * Alias für Route-Model-Binding mit scopeBindings().
     */
    public function timesheetDays(){
        return $this->timesheet_days();
    }

    public function employe(){
        return $this->belongsTo(User::class, 'employe_id');
    }

    public function locked_by(){
        return $this->belongsTo(User::class, 'locked_by');
    }

    public function lockedBy(){
        return $this->belongsTo(User::class, 'locked_by');
    }

    public function submittedBy(){
        return $this->belongsTo(User::class, 'submitted_by');
    }

    public function reviewed_by(){
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function anomalies(){
        return $this->hasMany(TimesheetAnomaly::class, 'employe_id', 'employe_id')
            ->where('month', $this->month)
            ->where('year', $this->year);
    }

    /**
     * Markiert den Monatsabschluss als erneut prüfungsbedürftig
     * (z. B. wegen einer rückwirkenden Vertragsänderung, Arbeitspaket 3.2).
     */
    public function markRequiresReview(string $reason): void
    {
        $this->update([
            'requires_review' => true,
            'review_reason'   => $reason,
            'reviewed_at'      => null,
            'reviewed_by'      => null,
        ]);
    }

    /**
     * Quittiert die erneute Prüfung durch HR.
     */
    public function markReviewed(User $user): void
    {
        $this->update([
            'requires_review' => false,
            'reviewed_at'      => now(),
            'reviewed_by'      => $user->id,
        ]);
    }

    /**
     * Saldo und Urlaubsfelder neu berechnen (gesperrte Nachweise bleiben unverändert).
     */
    public function updateTime(): bool
    {
        if ($this->is_locked) {
            return false;
        }

        app(TimesheetService::class)->recalculate($this);

        return true;
    }
}
