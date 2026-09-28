<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Individuelles Entwicklungsziel eines Schülers (Diagnose & Förderung).
 *
 * Im Gegensatz zu DiagnosticGoal (Kriterium im Katalog einer Diagnosestufe) ist ein
 * Entwicklungsziel schülerbezogen und besitzt Zieldatum, Status und Abschlussnotiz.
 */
class DiagnosticDevelopmentGoal extends Model
{
    use HasFactory;

    public const STATUS_OPEN = 'open';
    public const STATUS_IN_PROGRESS = 'in_progress';
    public const STATUS_ACHIEVED = 'achieved';
    public const STATUS_NOT_ACHIEVED = 'not_achieved';
    public const STATUS_ARCHIVED = 'archived';

    public const STATUSES = [
        self::STATUS_OPEN,
        self::STATUS_IN_PROGRESS,
        self::STATUS_ACHIEVED,
        self::STATUS_NOT_ACHIEVED,
        self::STATUS_ARCHIVED,
    ];

    /** Status, die als "aktiv" gelten */
    public const ACTIVE_STATUSES = [
        self::STATUS_OPEN,
        self::STATUS_IN_PROGRESS,
    ];

    /** Status, die ein Abschlussdatum erhalten */
    public const COMPLETED_STATUSES = [
        self::STATUS_ACHIEVED,
        self::STATUS_NOT_ACHIEVED,
    ];

    protected $table = 'diagnostic_development_goals';

    protected $fillable = [
        'schueler_id',
        'diagnostic_session_id',
        'diagnostic_area_id',
        'diagnostic_stage_id',
        'diagnostic_goal_id',
        'title',
        'target_date',
        'status',
        'completion_notes',
        'completed_at',
        'archived_at',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'target_date' => 'date',
        'completed_at' => 'date',
        'archived_at' => 'datetime',
    ];

    protected $attributes = [
        'status' => self::STATUS_OPEN,
    ];

    public function schueler()
    {
        return $this->belongsTo(Schueler::class, 'schueler_id');
    }

    public function session()
    {
        return $this->belongsTo(DiagnosticSession::class, 'diagnostic_session_id');
    }

    public function area()
    {
        return $this->belongsTo(DiagnosticArea::class, 'diagnostic_area_id');
    }

    public function stage()
    {
        return $this->belongsTo(DiagnosticStage::class, 'diagnostic_stage_id');
    }

    /** Optionales Katalogziel */
    public function catalogGoal()
    {
        return $this->belongsTo(DiagnosticGoal::class, 'diagnostic_goal_id');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater()
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function scopeActive($query)
    {
        return $query->whereIn('status', self::ACTIVE_STATUSES);
    }

    public function scopeNotArchived($query)
    {
        return $query->where('status', '!=', self::STATUS_ARCHIVED);
    }

    public function scopeForSchueler($query, int $schuelerId)
    {
        return $query->where('schueler_id', $schuelerId);
    }

    public function isActive(): bool
    {
        return in_array($this->status, self::ACTIVE_STATUSES, true);
    }
}
