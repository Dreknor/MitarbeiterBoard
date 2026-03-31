<?php

namespace App\Models\personal;

use App\Enums\ContractType;
use App\Enums\EmploymentStatus;
use App\Enums\EmploymentStatusReason;
use App\Enums\EmploymentType;
use App\Enums\TerminationReason;
use App\Events\Personal\EmploymentStatusChanged;
use App\Events\Personal\EmploymentTerminated;
use App\Models\Group;
use App\Models\User;
use Carbon\Carbon;
use Carbon\CarbonInterval;
use DateTime;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use OwenIt\Auditing\Contracts\Auditable;
use Spatie\MediaLibrary\InteractsWithMedia;

class Employment extends Model implements Auditable
{
    use HasFactory, InteractsWithMedia, SoftDeletes;
    use \OwenIt\Auditing\Auditable;

    protected $table = 'employments';

    protected $fillable = [
        'employe_id', 'department_id', 'hour_type_id', 'start', 'end', 'hours', 'comment',
        'salary_type', 'salary_table_id', 'salary', 'replaced_employment_id', 'media_id',
        // Neue Personal-Felder (Phase 0)
        'employment_type', 'contract_type', 'status', 'status_reason', 'termination_reason',
        'probation_end', 'notice_period', 'salary_group', 'salary_level',
        'is_amendment', 'amendment_description', 'is_internal_transfer',
    ];

    protected $casts = [
        'start'              => 'date',
        'end'                => 'date',
        'probation_end'      => 'date',
        'employment_type'    => EmploymentType::class,
        'contract_type'      => ContractType::class,
        'status'             => EmploymentStatus::class,
        'status_reason'      => EmploymentStatusReason::class,
        'termination_reason' => TerminationReason::class,
        'is_amendment'       => 'boolean',
        'is_internal_transfer' => 'boolean',
    ];

    protected $with = ['hour_type'];

    protected $attributes = [
        'status' => 'aktiv',
    ];

    // ---- Relationships ----

    public function employe(): BelongsTo
    {
        return $this->belongsTo(User::class, 'employe_id');
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Group::class, 'department_id');
    }

    public function hour_type(): BelongsTo
    {
        return $this->belongsTo(HourType::class, 'hour_type_id');
    }

    public function salaryTable(): BelongsTo
    {
        return $this->belongsTo(SalaryTable::class, 'salary_table_id');
    }

    public function teacherDetails(): HasMany
    {
        return $this->hasMany(TeacherDetail::class);
    }

    /**
     * Liefert aktuell gültiges TeacherDetail (valid_until IS NULL oder >= heute).
     */
    public function currentTeacherDetail(): HasOne
    {
        return $this->hasOne(TeacherDetail::class)
            ->where(function ($q) {
                $q->whereNull('valid_until')
                  ->orWhere('valid_until', '>=', now()->toDateString());
            })
            ->latest('valid_from');
    }

    // ---- Scopes ----

    /**
     * WICHTIG: Scopet auf status = 'aktiv' (ersetzt alten end IS NULL Check).
     * Die Datumsprüfung wird weiterhin unterstützt für abwärtskompatible Aufrufe.
     */
    public function scopeActive(Builder $query, DateTime $start = null, DateTime $end = null): Builder
    {
        $query->where('status', EmploymentStatus::Aktiv->value);

        // Datums-Scope bleibt für Rückwärtskompatibilität erhalten
        if ($start !== null) {
            $end ??= $start;
            $query->where('start', '<=', $end)
                  ->where(function (Builder $q) use ($start) {
                      $q->whereNull('end')->orWhere('end', '>=', $start);
                  });
        }

        return $query;
    }

    public function scopeBeendet(Builder $query): Builder
    {
        return $query->where('status', EmploymentStatus::Beendet->value);
    }

    public function scopeRuhend(Builder $query): Builder
    {
        return $query->where('status', EmploymentStatus::Ruhend->value);
    }

    public function scopeDepartment(Builder $query, Group $department): Builder
    {
        return $query->where('department_id', $department->id);
    }

    // ---- Status-Workflow ----

    /**
     * Setzt Status auf 'ruhend'. Nur von 'aktiv' möglich.
     */
    public function setRuhend(EmploymentStatusReason $reason): void
    {
        if ($this->status !== EmploymentStatus::Aktiv) {
            throw new \LogicException('Nur aktive Anstellungen können auf ruhend gesetzt werden.');
        }
        $oldStatus = $this->status;
        $this->update(['status' => EmploymentStatus::Ruhend, 'status_reason' => $reason]);
        event(new EmploymentStatusChanged($this, $oldStatus, EmploymentStatus::Ruhend));
    }

    /**
     * Setzt Status zurück auf 'aktiv'. Nur von 'ruhend' möglich.
     */
    public function setAktiv(): void
    {
        if ($this->status !== EmploymentStatus::Ruhend) {
            throw new \LogicException('Nur ruhende Anstellungen können wieder aktiviert werden.');
        }
        $oldStatus = $this->status;
        $this->update(['status' => EmploymentStatus::Aktiv, 'status_reason' => null]);
        event(new EmploymentStatusChanged($this, $oldStatus, EmploymentStatus::Aktiv));
    }

    /**
     * Setzt Status auf 'beendet'. Nur von 'aktiv' oder 'ruhend' möglich (final).
     */
    public function setBeendet(TerminationReason $reason, ?Carbon $endDate = null): void
    {
        if ($this->status === EmploymentStatus::Beendet) {
            throw new \LogicException('Bereits beendete Anstellungen können nicht erneut beendet werden.');
        }
        $this->update([
            'status'             => EmploymentStatus::Beendet,
            'termination_reason' => $reason,
            'end'                => $endDate ?? $this->end,
        ]);
        event(new EmploymentTerminated($this));
    }

    // ---- Accessors ----

    public function getPercentAttribute(): float
    {
        $hour_type = $this->hour_type;
        if (!$hour_type || $hour_type->fulltimehours <= 0) return 0.0;
        return ($this->hours / $hour_type->fulltimehours) * 100;
    }


}
