<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Cache;

class Room extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $fillable = ['name', 'room_number'];

    public function bookings(){
        return $this->hasMany(RoomBooking::class, 'room_id');
    }

    public function hasBooking($weekday, $time){
        $bookings = Cache::remember('bookings_'.$this->name, 6, function (){
           return $this->bookings;
        });


            $booking = $bookings->filter(function ($booking) use ($weekday, $time){
                if ($booking->weekday == $weekday){

                    $start = Carbon::parse($booking->start);
                    $end =  Carbon::parse($booking->end);
                    if (Carbon::parse($time)->betweenIncluded($start, $end)){
                        return $booking;
                    }
                }
            });

        return $booking->first();
    }

}
