<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Festgeschriebene Stufe eines Schülers in einer Graduierungssession (bestimmt seine Fragen).
 * Wird beim ersten Beantworten/Bewerten bzw. beim Abschluss gesetzt; null = ohne Stufe.
 */
class GradingSessionStage extends Model
{
    protected $fillable = ['session_id', 'schueler_id', 'grading_stage_id'];

    public function session()
    {
        return $this->belongsTo(GradingDocumentationSession::class, 'session_id');
    }

    public function stage()
    {
        return $this->belongsTo(GradingStage::class, 'grading_stage_id');
    }
}
