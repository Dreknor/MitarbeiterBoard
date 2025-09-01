<?php

namespace App\Models\personal;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class RosterTaskRequirement extends Model
{
    use HasFactory;

    protected $fillable = [
        'department_id',
        'event_name',
        'required_start',
        'required_end',
        'adjust_working_time'
    ];

    protected $casts = [
        'required_start' => 'datetime:H:i',
        'required_end' => 'datetime:H:i',
        'adjust_working_time' => 'boolean'
    ];
}

