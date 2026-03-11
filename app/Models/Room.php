<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

class Room extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $fillable = ['name', 'room_number', 'indiware_shortname', 'feed_token', 'feed_expires_at', 'bookable'];

    protected $casts = [
        'feed_expires_at' => 'datetime',
        'bookable' => 'boolean',
    ];

    public function bookings(){
        return $this->hasMany(RoomBooking::class, 'room_id');
    }

    public function hasBooking($weekday, $time, $week = null, $date = null){

        $bookings = Cache::remember('bookings_'.$this->name, 6, function (){
           return $this->bookings;
        });

        // VP-Stornierungen für dieses Datum vorab aus der gecachten Collection filtern
        $vpCancellations = $date
            ? $bookings->filter(fn($b) => $b->cancelled && $b->source === 'indiware_vp'
                && $b->booking_date && $b->booking_date->isSameDay($date))
            : collect();

        $booking = $bookings->filter(function ($booking) use ($weekday, $time, $week, $date, $vpCancellations){

            // Stornierte Buchungen nie als "belegt" werten
            if ($booking->cancelled) {
                return false;
            }

            // Wenn Datum angegeben ist, prüfe individuelle Buchungen
            if ($date && !$booking->is_recurring && $booking->booking_date) {
                if (!$booking->booking_date->isSameDay($date)) {
                    return false;
                }
            } elseif ($booking->weekday != $weekday) {
                return false;
            }

            $start = Carbon::parse($booking->start);
            $end = Carbon::parse($booking->end);

            if (Carbon::parse($time)->betweenIncluded($start, $end) && Carbon::parse($time) != $end) {
                // Prüfe Woche für wiederkehrende Buchungen
                if ($booking->is_recurring && ($booking->week == null || $week == $booking->week)) {
                    // Bei konkretem Datum: Prüfen ob VP diese Stunde storniert hat
                    if ($date && $vpCancellations->isNotEmpty()) {
                        $isCancelledByVp = $vpCancellations->first(function ($c) use ($start, $end) {
                            return Carbon::parse($c->start)->eq($start) || Carbon::parse($c->end)->eq($end);
                        });
                        if ($isCancelledByVp) {
                            return false;
                        }
                    }
                    return true;
                }
                // Individuelle Buchungen
                if (!$booking->is_recurring) {
                    return true;
                }
            }

            return false;
        });

        return $booking->first();
    }

    /**
     * Prüft ob es eine Kollision mit einer neuen Buchung gibt
     */
    public function hasBookingCollision($start, $end, $weekday = null, $date = null, $week = null, $excludeBookingId = null)
    {
        $startTime = Carbon::parse($start);
        $endTime = Carbon::parse($end);

        $query = $this->bookings()
            ->where('id', '!=', $excludeBookingId ?? 0)
            ->where('cancelled', false); // Stornierungen nie als Konflikt werten

        if ($date) {
            // Prüfe individuelle Buchungen für dieses Datum
            $query->where(function($q) use ($date, $weekday, $week) {
                $q->where(function($subQ) use ($date) {
                    $subQ->where('is_recurring', false)
                         ->whereDate('booking_date', $date->format('Y-m-d'));
                })
                ->orWhere(function($subQ) use ($weekday, $week) {
                    $subQ->where('is_recurring', true)
                         ->where('weekday', $weekday)
                         ->where(function($weekQ) use ($week) {
                             $weekQ->whereNull('week')
                                   ->orWhere('week', $week);
                         });
                });
            });
        } else {
            // Prüfe wiederkehrende Buchungen
            $query->where('is_recurring', true)
                  ->where('weekday', $weekday)
                  ->where(function($q) use ($week) {
                      $q->whereNull('week')->orWhere('week', $week);
                  });
        }

        $bookings = $query->get();

        foreach ($bookings as $booking) {
            $bookingStart = Carbon::parse($booking->start);
            $bookingEnd = Carbon::parse($booking->end);

            // Prüfe Überschneidung
            if ($startTime->lt($bookingEnd) && $endTime->gt($bookingStart)) {
                return $booking;
            }
        }

        return null;
    }

    public function availability() : Attribute
    {
        return new Attribute(
            get: function () {
                $now = Carbon::now();
                $today = $now->copy()->startOfDay();

                // Prüfe wiederkehrende Buchungen
                if ($this->hasBooking($now->format('N'), $now->format('H:i'))){
                    return false;
                }

                // Prüfe individuelle Buchungen für heute
                $individualBooking = $this->bookings()
                    ->where('is_recurring', false)
                    ->whereDate('booking_date', $today)
                    ->get()
                    ->first(function ($booking) use ($now) {
                        $start = Carbon::parse($booking->start);
                        $end = Carbon::parse($booking->end);
                        return $now->betweenIncluded($start, $end) && !$now->eq($end);
                    });

                if ($individualBooking) {
                    return false;
                }

                return true;
            }
        );
    }

    public function nextBooking()
    {
        $bookings = Cache::remember('bookings_'.$this->name, 6, function (){
            return $this->bookings->sortBy('start');
        });

        $week = Cache::remember('vp_week', Carbon::now()->endOfWeek()->diffInSeconds(), function (){
            return VertretungsplanWeek::where('week', Carbon::now()->startOfWeek())->first();
        });

        $now = Carbon::now();
        $today = $now->copy()->startOfDay();

        $booking = $bookings->filter(function ($booking) use ($week, $now, $today){
            // Individuelle Buchung heute
            if (!$booking->is_recurring && $booking->booking_date) {
                if ($booking->booking_date->isSameDay($today)) {
                    $start = Carbon::parse($booking->start);
                    if ($start->gt($now)) {
                        return true;
                    }
                }
                return false;
            }

            // Wiederkehrende Buchung
            if ($booking->is_recurring && $booking->weekday == $now->dayOfWeek){
                $start = Carbon::parse($booking->start);
                if ($start->gt($now) && ($booking->week == null || $week->week == $booking->week)) {
                    return true;
                }
            }

            return false;
        });

        return $booking->first();
    }

    /**
     * Generates and persists a new feed token. Optionally set an expiry DateTime.
     */
    public function generateFeedToken(?\DateTimeInterface $expiresAt = null)
    {
        $this->feed_token = Str::random(48);
        $this->feed_expires_at = $expiresAt ? Carbon::instance($expiresAt) : null;
        $this->save();
        return $this->feed_token;
    }

    /**
     * Invalidates the current feed token.
     */
    public function revokeFeedToken()
    {
        $this->feed_token = null;
        $this->feed_expires_at = null;
        $this->save();
    }

    /**
     * Returns the public feed URL for this room (if token exists).
     */
    public function getFeedUrlAttribute()
    {
        if (!$this->feed_token) return null;
        return url('/rooms/'.$this->id.'/calendar/'.$this->feed_token.'.ics');
    }

}
