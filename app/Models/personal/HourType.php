<?php

namespace App\Models\personal;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class HourType extends Model
{
    use HasFactory;

    protected $fillable = ['name', 'start', 'end', 'fulltimehours', 'minutes'];

    protected $casts = [
      'start' => 'date',
      'end' => 'date',
    ];
}
