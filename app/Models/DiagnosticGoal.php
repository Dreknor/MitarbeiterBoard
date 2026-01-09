<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DiagnosticGoal extends Model
{
    use HasFactory;

    protected $fillable = [
        'diagnostic_stage_id',
        'code',
        'description',
        'sort_order',
    ];

    protected $casts = [
        'sort_order' => 'integer',
    ];

    /**
     * Stufe dieses Ziels
     */
    public function stage()
    {
        return $this->belongsTo(DiagnosticStage::class, 'diagnostic_stage_id');
    }

    /**
     * Bewertungen dieses Ziels
     */
    public function assessments()
    {
        return $this->hasMany(DiagnosticAssessment::class);
    }

    /**
     * Kommentare zu diesem Ziel
     */
    public function comments()
    {
        return $this->hasMany(DiagnosticGoalComment::class);
    }

    /**
     * Scope für sortierte Ziele
     */
    public function scopeOrdered($query)
    {
        return $query->orderBy('sort_order');
    }

    /**
     * Holt Kommentare für einen spezifischen Schüler
     */
    public function commentsForSchueler($schuelerId)
    {
        return $this->comments()->where('schueler_id', $schuelerId)->orderBy('created_at', 'desc');
    }
}

