<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * "Ziel, an dem ich arbeiten möchte" – wird für einen Schüler im
 * Pädagogischen Tagebuch erfasst. Jeder neue Eintrag bildet automatisch
 * eine Historie, da ältere Ziele nicht überschrieben, sondern als
 * eigene Zeilen erhalten bleiben.
 */
class PaedDiaryGoal extends Model
{
    protected $table = 'paed_diary_goals';

    protected $fillable = [
        'schueler_id',
        'user_id',
        'goal_text',
        'achieved_at',
        'achieved_by',
    ];

    protected $casts = [
        'achieved_at' => 'datetime',
    ];

    public function schueler()
    {
        return $this->belongsTo(Schueler::class, 'schueler_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function achievedByUser()
    {
        return $this->belongsTo(User::class, 'achieved_by');
    }
}

