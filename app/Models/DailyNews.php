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

        $start = Carbon::parse($this->date_start)->startOfDay();
        if ($this->date_end != null) {
            $end = Carbon::parse($this->date_end);
        } else {
            $end = Carbon::parse($this->date_start);
        }

        //Datum in der Zukunft
        if ($start->greaterThan($date)) {
            return false ;
        }

        if ($start->lessThanOrEqualTo($date) and $end->startOfDay()->greaterThanOrEqualTo($date)) {
            return true ;
        }


        return false;
    }
}
