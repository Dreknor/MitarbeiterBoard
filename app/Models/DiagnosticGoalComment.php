<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DiagnosticGoalComment extends Model
{
    use HasFactory;

    protected $fillable = [
        'diagnostic_goal_id',
        'schueler_id',
        'user_id',
        'comment',
    ];

    /**
     * Ziel dieses Kommentars
     */
    public function goal()
    {
        return $this->belongsTo(DiagnosticGoal::class, 'diagnostic_goal_id');
    }

    /**
     * Schüler dieses Kommentars
     */
    public function schueler()
    {
        return $this->belongsTo(Schueler::class, 'schueler_id');
    }

    /**
     * Autor dieses Kommentars
     */
    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * Scope für Kommentare eines bestimmten Schülers
     */
    public function scopeForSchueler($query, $schuelerId)
    {
        return $query->where('schueler_id', $schuelerId);
    }

    /**
     * Scope für Kommentare eines bestimmten Ziels
     */
    public function scopeForGoal($query, $goalId)
    {
        return $query->where('diagnostic_goal_id', $goalId);
    }
}

