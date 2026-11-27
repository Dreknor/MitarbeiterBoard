<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class GradingQuestion extends Model
{
    protected $fillable = ['grading_system_id', 'question', 'sort_order', 'active'];

    protected $casts = [
        'active' => 'boolean',
    ];

    public function gradingSystem()
    {
        return $this->belongsTo(GradingSystem::class, 'grading_system_id');
    }

    /**
     * Stufen, denen die Frage zugeordnet ist. Ohne Zuordnung gilt die Frage für alle Stufen.
     */
    public function stages()
    {
        return $this->belongsToMany(GradingStage::class, 'grading_question_stage', 'grading_question_id', 'grading_stage_id')
            ->withTimestamps();
    }

    /**
     * Fragen, die für die Stufe gelten: ohne Zuordnung (alle Stufen) oder dieser Stufe zugeordnet.
     * Ohne Stufe (null) nur die Fragen für alle Stufen.
     */
    public function scopeFuerStufe($query, ?int $stageId)
    {
        return $query->where(function ($q) use ($stageId) {
            $q->whereDoesntHave('stages');
            if ($stageId !== null) {
                $q->orWhereHas('stages', fn ($s) => $s->whereKey($stageId));
            }
        });
    }

    /** Gilt die Frage für die Stufe? (Relation stages muss geladen sein oder wird nachgeladen) */
    public function giltFuerStufe(?int $stageId): bool
    {
        $ids = $this->stages->pluck('id')->map(fn ($id) => (int) $id);

        return $ids->isEmpty() || ($stageId !== null && $ids->contains($stageId));
    }

    public function studentAnswers()
    {
        return $this->hasMany(GradingStudentAnswer::class, 'question_id');
    }

    public function teacherAssessments()
    {
        return $this->hasMany(GradingTeacherAssessment::class, 'question_id');
    }
}

