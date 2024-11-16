<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Cache;

class Room extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $fillable = ['name', 'room_number', 'indiware_shortname'];

    public function bookings(){
        return $this->hasMany(RoomBooking::class, 'room_id');
    }

    public function hasBooking($weekday, $time, $week = null, $date = null){

        $bookings = Cache::remember('bookings_'.$this->name, 6, function (){
           return $this->bookings;
        });


            $booking = $bookings->filter(function ($booking) use ($weekday, $time, $week, $date){
                if ($booking->weekday == $weekday){

                    $start = Carbon::parse($booking->start);
                    $end =  Carbon::parse($booking->end);
                    if (Carbon::parse($time)->betweenIncluded($start, $end) and Carbon::parse($time) != $end and ($booking->week == null or $week == $booking->week)){
                        return $booking;
                    }
                }
            });


        return $booking->first();
    }

    public function availability() : Attribute
    {
        return new Attribute(
            get: function () {
                if ($this->hasBooking(Carbon::now()->format('N'), Carbon::now()->format('H:i'))){
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

        $booking = $bookings->filter(function ($booking) use ($week){
            if ($booking->weekday == Carbon::now()->dayOfWeek){
                $start = Carbon::parse($booking->start);
                $end =  Carbon::parse($booking->end);
                if ($start->gt(Carbon::now()) and ($booking->week == null or $week->week == $booking->week)) {
                    return $booking;
                }
            }
        });

        return $booking->first();
    }


}
