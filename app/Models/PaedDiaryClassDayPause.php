<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Manuelle Tages-Pause für eine gesamte Klasse (z.B. bei Veranstaltungen).
 * Wenn ein Eintrag für eine Klasse an einem Datum existiert, werden alle
 * offenen Tagebuch-Einträge aller Schüler dieser Klasse für diesen Tag pausiert.
 */
class PaedDiaryClassDayPause extends Model
{
    protected $fillable = ['klasse_id', 'date', 'reason', 'paused_by'];

    protected $casts = ['date' => 'date'];

    public function klasse()
    {
        return $this->belongsTo(Klasse::class, 'klasse_id');
    }

    public function pausedBy()
    {
        return $this->belongsTo(\App\Models\User::class, 'paused_by');
    }
}

