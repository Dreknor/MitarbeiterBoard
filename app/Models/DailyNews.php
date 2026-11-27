<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Tagesinfo (Vertretungsplan). `quelle` = 'vp_import' für Infos aus dem Vertretungsplan-Import,
 * null für manuell angelegte.
 */
class DailyNews extends Model
{
    use HasFactory;

    public const QUELLE_VP_IMPORT = 'vp_import';

    protected $fillable = ['date_start', 'date_end', 'news', 'quelle'];

    protected $casts = [
      'date_start' => 'datetime',
      'date_end' => 'datetime',
    ];

    /** Text vergleichbar machen: Leerzeichen am Rand entfernen, mehrfache zusammenfassen. */
    public static function normalisieren(?string $text): string
    {
        return trim((string) preg_replace('/\s+/u', ' ', (string) $text));
    }

    /** Schlüssel für „dieselbe Info“: Zeitraum + normalisierter Text. */
    public static function schluessel($start, $ende, ?string $text): string
    {
        $start = $start ? Carbon::parse($start)->toDateString() : '';
        $ende = $ende ? Carbon::parse($ende)->toDateString() : '';

        return $start.'|'.$ende.'|'.self::normalisieren($text);
    }

    public function isActive(Carbon $date = NULL): bool {

        $start = Carbon::parse($this->date_start)->startOfDay();
        if ($this->date_end != null) {
            $end = Carbon::parse($this->date_end);
        }

        //Datum in der Zukunft
        if ($start->greaterThan($date)) {
            return false ;
        }

        if ($start->lessThanOrEqualTo($date) and is_null($this->date_end)) {
            return true ;
        }

        if ($start->lessThanOrEqualTo($date) and $end->startOfDay()->greaterThanOrEqualTo($date)) {
            return true ;
        }


        return false;
    }
}
