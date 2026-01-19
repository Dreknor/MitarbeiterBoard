<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class GradingDocumentationSession extends Model
{
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
}

