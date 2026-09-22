<?php

namespace App\Models\personal;

use App\Enums\TeacherQualificationLevel;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TeacherSubject extends Model
{
    use HasFactory;

    protected $table = 'pers_teacher_subjects';

    protected $fillable = [
        'teacher_detail_id',
        'subject',
        'qualification_level',
        'hours_per_week',
    ];

    protected $casts = [
        'qualification_level' => TeacherQualificationLevel::class,
        'hours_per_week'      => 'decimal:2',
    ];

    public function teacherDetail(): BelongsTo
    {
        return $this->belongsTo(TeacherDetail::class);
    }
}

