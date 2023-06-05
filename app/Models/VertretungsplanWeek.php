<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class VertretungsplanWeek extends Model
{

    protected $fillable = ['week', 'type'];

    protected $casts = [
        'week' => 'date'
    ];

    public function getDateAttribute(){
        return $this->week->format('d.m.Y').' - '.$this->week->endOfWeek()->format('d.m.Y');
    }

}
