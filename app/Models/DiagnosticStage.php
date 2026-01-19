<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DiagnosticStage extends Model
{
    use HasFactory;

    protected $fillable = [
        'diagnostic_area_id',
        'name',
        'code',
        'goal_description',
        'sort_order',
    ];

    protected $casts = [
        'sort_order' => 'integer',
    ];

    /**
     * Bereich dieser Stufe
     */
    public function area()
    {
        return $this->belongsTo(DiagnosticArea::class, 'diagnostic_area_id');
    }

    /**
     * Ziele dieser Stufe
     */
    public function goals()
    {
        return $this->hasMany(DiagnosticGoal::class)->orderBy('sort_order');
    }

    /**
     * Notizen zu dieser Stufe in verschiedenen Sessions
     */
    public function stageNotes()
    {
        return $this->hasMany(DiagnosticStageNote::class);
    }

    /**
     * Scope für sortierte Stufen
     */
    public function scopeOrdered($query)
    {
        return $query->orderBy('sort_order');
    }
}

