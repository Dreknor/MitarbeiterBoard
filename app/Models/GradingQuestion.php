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

    public function studentAnswers()
    {
        return $this->hasMany(GradingStudentAnswer::class, 'question_id');
    }

    public function teacherAssessments()
    {
        return $this->hasMany(GradingTeacherAssessment::class, 'question_id');
    }
}

