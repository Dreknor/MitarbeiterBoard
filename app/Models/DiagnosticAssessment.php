<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DiagnosticAssessment extends Model
{
    use HasFactory;

    protected $fillable = [
        'diagnostic_session_id',
        'diagnostic_goal_id',
        'rating',
        'is_current_goal',
        'saved_at',
    ];

    protected $casts = [
        'is_current_goal' => 'boolean',
        'saved_at' => 'datetime',
    ];

    /**
     * Boot method to update saved_at timestamp
     */
    protected static function boot()
    {
        parent::boot();

        static::saving(function ($assessment) {
            $assessment->saved_at = now();
        });
    }

    /**
     * Session dieser Bewertung
     */
    public function session()
    {
        return $this->belongsTo(DiagnosticSession::class, 'diagnostic_session_id');
    }

    /**
     * Ziel dieser Bewertung
     */
    public function goal()
    {
        return $this->belongsTo(DiagnosticGoal::class, 'diagnostic_goal_id');
    }

    /**
     * Scope für aktuelle Ziele
     */
    public function scopeCurrentGoals($query)
    {
        return $query->where('is_current_goal', true);
    }

    /**
     * Scope für bestimmte Bewertung
     */
    public function scopeWithRating($query, $rating)
    {
        return $query->where('rating', $rating);
    }

    /**
     * Prüft ob dies ein aktuelles Ziel ist
     */
    public function isCurrentGoal(): bool
    {
        return $this->is_current_goal;
    }

    /**
     * Markiert als aktuelles Ziel
     */
    public function markAsCurrentGoal()
    {
        $this->update(['is_current_goal' => true]);
    }

    /**
     * Entfernt Markierung als aktuelles Ziel
     */
    public function unmarkAsCurrentGoal()
    {
        $this->update(['is_current_goal' => false]);
    }
}

