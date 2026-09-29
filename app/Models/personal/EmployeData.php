<?php

namespace App\Models\personal;

use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Hash;

class EmployeData extends Model
{
    use HasFactory;
    protected $fillable = [
        'user_id',
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
        'secret_key',
        'mail_timesheet'
        ];

    protected $table = 'employes_data';

    protected $hidden = ['secret_key'];

    protected $casts = [
        'schwerbehindert'   => "boolean",
        'gebutstag' => 'datetime:Y-m-d',
        'caldav_events' => 'boolean',
        'caldav_working_time' => 'boolean',
        'geburtstag' => 'date',
        'mail_timesheet' => 'boolean'
    ];

    public function user (){
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * Die PIN der Zeiterfassung wird ausschließlich gehasht gespeichert.
     */
    public function setSecretKeyAttribute($value): void
    {
        if ($value === null || $value === '') {
            $this->attributes['secret_key'] = null;
            return;
        }

        $value = (string) $value;
        $this->attributes['secret_key'] = self::isHashed($value) ? $value : Hash::make($value);
    }

    public function hasPin(): bool
    {
        return !empty($this->attributes['secret_key'] ?? null);
    }

    /**
     * Prüft die PIN. Alt-Bestände im Klartext werden beim ersten erfolgreichen
     * Login automatisch in einen Hash umgewandelt.
     */
    public function checkPin(string $pin): bool
    {
        $stored = $this->attributes['secret_key'] ?? null;
        if (empty($stored)) {
            return false;
        }

        if (self::isHashed($stored)) {
            return Hash::check($pin, $stored);
        }

        if (hash_equals($stored, $pin)) {
            $this->secret_key = $pin;
            $this->save();
            return true;
        }

        return false;
    }

    private static function isHashed(string $value): bool
    {
        return str_starts_with($value, '$2y$') || str_starts_with($value, '$argon');
    }

}
