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
        'weekday', 'date', 'start', 'end', 'room_id', 'users_id', 'name', 'week', 'is_recurring', 'booking_date'
    ];

    protected $casts = [
        'is_recurring' => 'boolean',
        'booking_date' => 'datetime',
    ];

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
