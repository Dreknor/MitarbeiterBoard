<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DailyNews extends Model
{
    use HasFactory;

    protected $fillable = ['date_start', 'date_end', 'news'];

    protected $casts = [
      'date_start' => 'datetime',
      'date_end' => 'datetime',
    ];

    public function isActive(Carbon $date = NULL): bool {

        if ($date == null){
            $date = Carbon::today();
        }

        $start = Carbon::parse($this->date_start);

        //Datum in der Zukunft
        if ($start->greaterThan($date)) {
            return false ;
        }

        if ($this->date_end == null and $start->lessThanOrEqualTo($date)) {
            return false ;
        }

        if ($this->date_end != null){
            $end = Carbon::parse($this->date_end);

            if ($end->greaterThanOrEqualTo($date)){
                return true;
            }

        }


        return false;
    }
}
