<?php

namespace App\Models\personal;

use App\Enums\AnomalyRuleType;
use App\Enums\AnomalySeverity;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Auffälligkeiten-Protokoll der Prüfengine (siehe TimeValidationService).
 *
 * @property AnomalyRuleType $rule_type
 * @property AnomalySeverity $severity
 */
class TimesheetAnomaly extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'timesheet_anomalies';

    protected $fillable = [
        'employe_id', 'date', 'month', 'year', 'rule_type', 'severity',
        'description', 'context', 'related_employment_id',
        'resolved_at', 'resolved_by', 'resolution_comment',
    ];

    protected $casts = [
        'date'        => 'date',
        'rule_type'   => AnomalyRuleType::class,
        'severity'    => AnomalySeverity::class,
        'context'     => 'array',
        'resolved_at' => 'datetime',
    ];

    public function employe(): BelongsTo
    {
        return $this->belongsTo(User::class, 'employe_id');
    }

    public function resolvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'resolved_by');
    }

    public function relatedEmployment(): BelongsTo
    {
        return $this->belongsTo(Employment::class, 'related_employment_id');
    }

    public function getIsResolvedAttribute(): bool
    {
        return $this->resolved_at !== null;
    }

    public function resolve(User $user, ?string $comment = null): void
    {
        $this->update([
            'resolved_at'         => now(),
            'resolved_by'         => $user->id,
            'resolution_comment'  => $comment,
        ]);
    }

    // ---- Scopes ----

    public function scopeUnresolved($query)
    {
        return $query->whereNull('resolved_at');
    }

    public function scopeForPeriod($query, int $month, int $year)
    {
        return $query->where('month', $month)->where('year', $year);
    }

    /**
     * Erweiterung: Auffälligkeiten über einen mehrmonatigen Zeitraum (z. B. Quartal/Jahr).
     * $from/$to sind Carbon-Instanzen (nur Monat/Jahr relevant).
     */
    public function scopeForPeriodRange($query, \Carbon\Carbon $from, \Carbon\Carbon $to)
    {
        return $query->where(function ($q) use ($from, $to) {
            $q->where('year', '>', $from->year)
              ->orWhere(function ($qq) use ($from) {
                  $qq->where('year', $from->year)->where('month', '>=', $from->month);
              });
        })->where(function ($q) use ($from, $to) {
            $q->where('year', '<', $to->year)
              ->orWhere(function ($qq) use ($to) {
                  $qq->where('year', $to->year)->where('month', '<=', $to->month);
              });
        });
    }

    public function scopeForEmploye($query, int $employeId)
    {
        return $query->where('employe_id', $employeId);
    }
}


