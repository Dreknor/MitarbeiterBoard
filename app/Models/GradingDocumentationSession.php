<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class GradingDocumentationSession extends Model
{
    use HasFactory;
    protected $fillable = [
        'klasse_id',
        'grading_system_id',
        'user_id',
        'type',
        'group_id',
        'schueler_id',
        'started_at',
        'completed_at'
    ];

    protected $casts = [
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    public function klasse()
    {
        return $this->belongsTo(Klasse::class);
    }

    public function gradingSystem()
    {
        return $this->belongsTo(GradingSystem::class, 'grading_system_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function group()
    {
        return $this->belongsTo(PaedDiaryClassGroup::class, 'group_id');
    }

    public function schueler()
    {
        return $this->belongsTo(Schueler::class);
    }

    public function studentAnswers()
    {
        return $this->hasMany(GradingStudentAnswer::class, 'session_id');
    }

    public function teacherAssessments()
    {
        return $this->hasMany(GradingTeacherAssessment::class, 'session_id');
    }

    public function coachingNotes()
    {
        return $this->hasMany(GradingCoachingNote::class, 'session_id');
    }

    public function isCompleted()
    {
        return !is_null($this->completed_at);
    }

    public function isGroupSession()
    {
        return $this->type === 'group';
    }

    public function isIndividualSession()
    {
        return $this->type === 'individual';
    }

    /**
     * Prüft ob Session wiedergeöffnet werden kann
     */
    public function canBeReopened()
    {
        if (!$this->isCompleted()) {
            return false;
        }

        $maxDays = settings('session_reopen_days', 'config.grading_documentation');
        $daysSinceCompleted = $this->completed_at->diffInDays(now());

        return $daysSinceCompleted <= $maxDays;
    }

    /**
     * Öffnet eine abgeschlossene Session wieder
     */
    public function reopen()
    {
        if (!$this->isCompleted()) {
            return false;
        }

        $oldCompletedAt = $this->completed_at;
        $this->completed_at = null;
        $this->save();

        // Logging
        Log::info('Graduierungssystem-Session wiedergeöffnet', [
            'session_id' => $this->id,
            'user_id' => Auth::id(),
            'klasse_id' => $this->klasse_id,
            'type' => $this->type,
            'schueler_id' => $this->schueler_id,
            'group_id' => $this->group_id,
            'original_completed_at' => $oldCompletedAt,
            'reopened_at' => now(),
        ]);

        return true;
    }

    /**
     * Scope für Sessions im aktuellen Schuljahr
     */
    public function scopeCurrentSchoolYear($query)
    {
        $schuljahresbeginn = config('config.schuljahresbeginn');
        $schuljahresende = (clone $schuljahresbeginn)->addYear();

        return $query->whereBetween('started_at', [$schuljahresbeginn, $schuljahresende]);
    }

    /**
     * Scope für abgeschlossene Sessions
     */
    public function scopeCompleted($query)
    {
        return $query->whereNotNull('completed_at');
    }
}

