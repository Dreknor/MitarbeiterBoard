<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Teilnehmender Schüler einer Gruppen-Graduierungssession (inkl. Abschluss je Schüler).
 */
class GradingSessionStudent extends Model
{
    protected $fillable = [
        'session_id',
        'schueler_id',
        'finalized_at',
        'finalized_by',
    ];

    protected $casts = [
        'finalized_at' => 'datetime',
    ];

    public function session()
    {
        return $this->belongsTo(GradingDocumentationSession::class, 'session_id');
    }

    public function schueler()
    {
        return $this->belongsTo(Schueler::class);
    }
}
