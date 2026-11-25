<?php

namespace App\Models\Wochenplan;

use App\Models\Schueler;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

/**
 * Bearbeitungsstand einer Wochenplan-Aufgabe für ein Kind (ohne Verlauf).
 * Schreiben nur über {@see \App\Services\Wochenplan\WpStatusService}.
 */
class WpAufgabeStatus extends Model
{
    public const OFFEN = 'offen';
    public const TEILWEISE = 'teilweise';
    public const ERLEDIGT = 'erledigt';

    public const STATUS = [self::OFFEN, self::TEILWEISE, self::ERLEDIGT];

    protected $table = 'wp_aufgabe_status';

    protected $fillable = ['wp_aufgabe_id', 'schueler_id', 'status', 'notiz', 'user_id'];

    public function aufgabe()
    {
        return $this->belongsTo(WpAufgabe::class, 'wp_aufgabe_id');
    }

    public function schueler()
    {
        return $this->belongsTo(Schueler::class, 'schueler_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
