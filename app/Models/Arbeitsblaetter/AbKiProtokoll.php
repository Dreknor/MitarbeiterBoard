<?php

namespace App\Models\Arbeitsblaetter;

use Illuminate\Database\Eloquent\Model;

/** Ein KI-Aufruf (Anfrage ohne Bilddaten, rohe Antwort) – zur Fehlersuche, 30 Tage aufbewahrt. */
class AbKiProtokoll extends Model
{
    public const UPDATED_AT = null;

    protected $table = 'ab_ki_protokolle';

    protected $fillable = ['blatt_id', 'anbieter', 'modell', 'url', 'http_status', 'dauer_ms', 'anfrage', 'antwort', 'fehler'];

    protected $casts = [
        'http_status' => 'integer',
        'dauer_ms' => 'integer',
    ];

    public function blatt()
    {
        return $this->belongsTo(AngepasstesBlatt::class, 'blatt_id')->withTrashed();
    }
}
