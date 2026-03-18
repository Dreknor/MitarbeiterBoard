<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PaedDiarySchuelerAbsence extends Model
{
    use HasFactory;

    protected $table = 'paed_diary_schueler_absences';

    protected $fillable = ['schueler_id', 'klasse_id', 'datum', 'marked_by'];

    protected $casts = ['datum' => 'date'];

    public function schueler()
    {
        return $this->belongsTo(Schueler::class);
    }

    public function klasse()
    {
        return $this->belongsTo(Klasse::class);
    }

    public function markedByUser()
    {
        return $this->belongsTo(User::class, 'marked_by');
    }

    /**
     * Scope: Alle Abwesenheiten für eine Klasse in einem Zeitraum.
     */
    public function scopeForKlasseInRange($query, int $klasseId, string $from, string $to)
    {
        return $query->where('klasse_id', $klasseId)
                     ->whereBetween('datum', [$from, $to]);
    }
}

