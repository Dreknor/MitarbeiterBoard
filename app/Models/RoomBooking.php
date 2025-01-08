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
        'weekday', 'date', 'start', 'end', 'room_id', 'users_id', 'name', 'week'
    ];

    public function room(){
        return $this->belongsTo(Room::class, 'room_id');
    }

    public function getDurationAttribute(){
        return Carbon::parse($this->start)->diffInMinutes(Carbon::parse($this->end));
    }

    public function getDateAttribute($value){
        return Carbon::parse($value)->format('Y-m-d');
    }
}
