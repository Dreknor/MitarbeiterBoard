<?php

namespace App\Models\personal;

use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class EmployeData extends Model
{
    use HasFactory;
    protected $fillable = [
        'familienname',
        'geburtsname',
        'vorname',
        'geburtstag',
        'geschlecht',
        'sozialversicherungsnummer',
        'geburtsort',
        'staatsangehoerigkeit',
        'schwerbehindert',
        'google_calendar_link',
        'caldav_working_time',
        'caldav_events',
        'caldav_uuid',
        'time_recording_key',
        'secret_key'
        ];

    protected $table = 'employes_data';

    protected $hidden = [];

    protected $casts = [
        'schwerbehindert'   => "boolean",
        'gebutstag' => 'datetime:Y-m-d',
        'caldav_events' => 'boolean',
        'caldav_working_time' => 'boolean',
        'geburtstag' => 'date'
    ];

    public function user (){
        return $this->belongsTo(User::class, 'user_id');
    }

}
