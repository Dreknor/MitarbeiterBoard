<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Models\GradingStage;
use App\Models\SchuelerGradingHistory;

class Schueler extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'schueler';

    protected $fillable = [
        'vorname',
        'nachname',
        'geburtsdatum',
        'klasse_id',
        'import_key',
        'grading_stage_id'
    ];

    protected $casts = [
        'geburtsdatum' => 'date'
    ];

    public function klasse()
    {
        return $this->belongsTo(Klasse::class, 'klasse_id');
    }

    // Neue Relation: aktuelle Stufe
    public function grading_stage()
    {
        return $this->belongsTo(GradingStage::class, 'grading_stage_id');
    }

    // Historie der Stufenänderungen
    public function grading_history()
    {
        return $this->hasMany(SchuelerGradingHistory::class, 'schueler_id')->orderByDesc('created_at');
    }

    public function getNameAttribute(): string
    {
        return $this->vorname.' '.$this->nachname;
    }

    // Neue Wochenplan-Relation (neues System)
    public function wpPlaene()
    {
        return $this->hasMany(\App\Models\Wochenplan\WpPlan::class, 'schueler_id');
    }

    // Optionaler Accessor für Symbol (falls Stufe gesetzt)
    public function getStageSymbolAttribute(): ?string
    {
        return $this->grading_stage?->symbol ?? null;
    }

    // PaedDiary-Abwesenheiten
    public function paedDiaryAbsences()
    {
        return $this->hasMany(\App\Models\PaedDiarySchuelerAbsence::class);
    }

    // Ziele ("Ziel an dem ich arbeiten möchte") im Pädagogischen Tagebuch – neueste zuerst
    public function paedDiaryGoals()
    {
        return $this->hasMany(\App\Models\PaedDiaryGoal::class, 'schueler_id')->orderByDesc('created_at');
    }

    // ── API v1 (Pädagogen-App) ───────────────────────────────────────────

    /** Tagebucheinträge (Pivot paed_diary_entry_schueler) */
    public function paedDiaryEntries()
    {
        return $this->belongsToMany(\App\Models\PaedDiaryEntry::class, 'paed_diary_entry_schueler');
    }

    /** Diagnosesitzungen */
    public function diagnosticSessions()
    {
        return $this->hasMany(\App\Models\DiagnosticSession::class, 'schueler_id');
    }

    /** Individuelle Entwicklungsziele (Diagnose) */
    public function developmentGoals()
    {
        return $this->hasMany(\App\Models\DiagnosticDevelopmentGoal::class, 'schueler_id');
    }

    /** Individuelle Graduierungs-Sessions */
    public function gradingDocumentationSessions()
    {
        return $this->hasMany(\App\Models\GradingDocumentationSession::class, 'schueler_id');
    }

    /**
     * Scope: Schüler einer Klasse.
     */
    public function scopeForClass($query, int $classId)
    {
        return $query->where($this->qualifyColumn('klasse_id'), $classId);
    }

    /**
     * Scope: Schüler aus den Klassen, denen die Lehrkraft (klasse_user) zugeordnet ist.
     */
    public function scopeForTeacher($query, int $teacherId)
    {
        return $query->whereIn($this->qualifyColumn('klasse_id'), function ($sub) use ($teacherId) {
            $sub->select('klasse_id')->from('klasse_user')->where('user_id', $teacherId);
        });
    }

    /**
     * Scope: Für den Benutzer sichtbare Schüler (alle bei klassenübergreifenden Rechten).
     */
    public function scopeVisibleFor($query, User $user)
    {
        return $user->canAccessAllStudents() ? $query : $query->forTeacher($user->id);
    }
}
