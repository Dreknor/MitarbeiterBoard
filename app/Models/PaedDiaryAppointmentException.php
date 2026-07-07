<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PaedDiaryAppointmentException extends Model
{
    protected $fillable = [
        'appointment_id',
        'exception_date',
    ];

    protected $casts = [
        'exception_date' => 'date',
    ];

    public function appointment()
    {
        return $this->belongsTo(PaedDiaryAppointment::class, 'appointment_id');
    }
}

