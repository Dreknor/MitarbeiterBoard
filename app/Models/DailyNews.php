<?php

namespace App\Models;

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
}
