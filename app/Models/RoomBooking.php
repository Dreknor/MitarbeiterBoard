<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class RoomBooking extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $fillable = [
        'weekday', 'date', 'start', 'end', 'room_id', 'users_id', 'name', 'week', 'is_recurring', 'booking_date',
        'source', 'source_id', 'cancelled',
    ];

    protected $casts = [
        'is_recurring' => 'boolean',
        'booking_date' => 'datetime',
        'cancelled'    => 'boolean',
    ];

    // ── Scopes ────────────────────────────────────────────────────────────────

    /** Nur aktive (nicht stornierte) Buchungen */
    public function scopeActive($query)
    {
        return $query->where('cancelled', false);
    }

    /** Nur VP-Buchungen (Quelle: Vertretungsplan-API) */
    public function scopeFromVertretungsplan($query)
    {
        return $query->where('source', 'indiware_vp');
    }

    /** Nur Stornierungseinträge (Raum durch VP freigegeben) */
    public function scopeCancelledByVp($query)
    {
        return $query->where('cancelled', true)->where('source', 'indiware_vp');
    }

    public function user(){
        return $this->belongsTo(User::class, 'users_id');
    }

    public function room(){
        return $this->belongsTo(Room::class, 'room_id');
    }

    public function getDurationAttribute(){
        return Carbon::parse($this->start)->diffInMinutes(Carbon::parse($this->end));
    }

    public function getDateAttribute($value){
        return $value ? Carbon::parse($value)->format('Y-m-d') : null;
    }

    /**
     * Prüft ob die Buchung an einem bestimmten Datum stattfindet
     */
    public function appliesToDate(Carbon $date, $week = null)
    {
        // Individuelle Buchung
        if (!$this->is_recurring && $this->booking_date) {
            return $this->booking_date->isSameDay($date);
        }

        // Wiederkehrende Buchung
        if ($this->is_recurring && $this->weekday) {
            $matchesWeekday = $this->weekday == $date->dayOfWeek;
            $matchesWeek = $this->week === null || $this->week === $week;
            return $matchesWeekday && $matchesWeek;
        }

        return false;
    }
}
