<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DiagnosticSession extends Model
{
    use HasFactory;

    protected $fillable = [
        'schueler_id',
        'diagnostic_area_id',
        'user_id',
        'session_date',
        'started_at',
        'completed_at',
        'is_completed',
        'notes',
    ];

    protected $casts = [
        'session_date' => 'date',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
        'is_completed' => 'boolean',
    ];

    /**
     * Boot method to set defaults
     */
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($session) {
            if (!$session->session_date) {
                $session->session_date = now()->toDateString();
            }
            if (!$session->started_at) {
                $session->started_at = now();
            }
        });
    }

    /**
     * Schüler dieser Session
     */
    public function schueler()
    {
        return $this->belongsTo(Schueler::class, 'schueler_id');
    }

    /**
     * Bereich dieser Session
     */
    public function area()
    {
        return $this->belongsTo(DiagnosticArea::class, 'diagnostic_area_id');
    }

    /**
     * Ersteller dieser Session
     */
    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * Bewertungen in dieser Session
     */
    public function assessments()
    {
        return $this->hasMany(DiagnosticAssessment::class);
    }

    /**
     * Stufen-Notizen in dieser Session
     */
    public function stageNotes()
    {
        return $this->hasMany(DiagnosticStageNote::class);
    }

    /**
     * Scope für offene Sessions
     */
    public function scopeOpen($query)
    {
        return $query->where('is_completed', false);
    }

    /**
     * Scope für abgeschlossene Sessions
     */
    public function scopeCompleted($query)
    {
        return $query->where('is_completed', true);
    }

    /**
     * Scope für Sessions eines bestimmten Schülers
     */
    public function scopeForSchueler($query, $schuelerId)
    {
        return $query->where('schueler_id', $schuelerId);
    }

    /**
     * Scope für Sessions eines bestimmten Bereichs
     */
    public function scopeForArea($query, $areaId)
    {
        return $query->where('diagnostic_area_id', $areaId);
    }

    /**
     * Prüft ob Session abgeschlossen ist
     */
    public function isCompleted(): bool
    {
        return $this->is_completed;
    }

    /**
     * Schließt die Session ab
     */
    public function complete()
    {
        $this->update([
            'is_completed' => true,
            'completed_at' => now(),
        ]);
    }

    /**
     * Öffnet die Session wieder
     */
    public function reopen()
    {
        $this->update([
            'is_completed' => false,
            'completed_at' => null,
        ]);
    }
}

