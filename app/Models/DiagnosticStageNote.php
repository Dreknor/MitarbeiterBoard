<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DiagnosticStageNote extends Model
{
    use HasFactory;

    protected $fillable = [
        'diagnostic_session_id',
        'diagnostic_stage_id',
        'notes',
    ];

    /**
     * Session dieser Notiz
     */
    public function session()
    {
        return $this->belongsTo(DiagnosticSession::class, 'diagnostic_session_id');
    }

    /**
     * Stufe dieser Notiz
     */
    public function stage()
    {
        return $this->belongsTo(DiagnosticStage::class, 'diagnostic_stage_id');
    }
}

