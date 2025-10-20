<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class GradingTeacherAssessment extends Model
{
    protected $fillable = [
        'session_id',
        'schueler_id',
        'question_id',
        'teacher_rating',
        'comment',
        'assessed_at'
    ];

    protected $casts = [
        'assessed_at' => 'datetime',
    ];

    public function session()
    {
        return $this->belongsTo(GradingDocumentationSession::class, 'session_id');
    }

    public function schueler()
    {
        return $this->belongsTo(Schueler::class);
    }

    public function question()
    {
        return $this->belongsTo(GradingQuestion::class, 'question_id');
    }
}
