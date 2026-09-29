<?php

namespace App\Models\personal;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;

/**
 * Manuelle Buchung auf dem Urlaubskonto (Korrektur, Sonderurlaub, Übertrag).
 */
class HolidayAccountEntry extends Model
{
    public const TYPES = [
        'korrektur' => 'Korrektur',
        'sonderurlaub' => 'Sonderurlaub',
        'uebertrag' => 'Übertrag (manuell)',
    ];

    protected $fillable = ['employe_id', 'year', 'days', 'type', 'reason', 'created_by'];

    protected $casts = [
        'days' => 'float',
        'year' => 'integer',
    ];

    public function employe()
    {
        return $this->belongsTo(User::class, 'employe_id');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function getTypeLabelAttribute(): string
    {
        return self::TYPES[$this->type] ?? $this->type;
    }
}
