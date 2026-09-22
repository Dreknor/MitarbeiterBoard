<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Klasse extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $table = 'klassen';

    protected $visible = ['name', 'kuerzel', 'color', 'show_vertretungen', 'zeitraster_id'];
    protected $fillable = ['name', 'kuerzel', 'grading_system_id', 'color', 'show_vertretungen', 'zeitraster_id'];

    protected $casts = [
        'show_vertretungen' => 'boolean',
    ];

    /** Scope: Nur Klassen mit aktivem öffentlichem Vertretungsplan */
    public function scopeWithPublicVertretungen($query)
    {
        return $query->where('show_vertretungen', true);
    }

    public function wochenplaene(){
        return $this->hasManyThrough(Wochenplan::class, wps_klassen::class);
    }

    // Schüler Relation
    public function schueler()
    {
        return $this->hasMany(Schueler::class, 'klasse_id');
    }
    // Pädagogisches Tagebuch: zugewiesene Mitarbeiter
    public function paed_users()
    {
        return $this->belongsToMany(User::class, 'klasse_user', 'klasse_id', 'user_id');
    }
    public function paed_diary_entries()
    {
        return $this->hasMany(PaedDiaryEntry::class, 'klasse_id');
    }
    public function paed_diary_columns()
    {
        return $this->hasMany(PaedDiaryColumn::class, 'klasse_id')->orderBy('sort_order');
    }
    public function paed_diary_tasks()
    {
        return $this->hasMany(PaedDiaryTask::class, 'klasse_id');
    }

    public function gradingSystem()
    {
        return $this->belongsTo(GradingSystem::class, 'grading_system_id');
    }

    public function  appointments()
    {
        return $this->belongsToMany(PaedDiaryAppointment::class, 'paed_diary_appointment_klassen');
    }

    // Neue Wochenplan-Relation (neues System)
    public function wpPlaene()
    {
        return $this->hasMany(\App\Models\Wochenplan\WpPlan::class, 'klasse_id');
    }

    public function getTextColorAttribute()
    {
        // Calculate the brightness of the background color
        $color = ltrim($this->color, '#');
        $r = hexdec(substr($color, 0, 2));
        $g = hexdec(substr($color, 2, 2));
        $b = hexdec(substr($color, 4, 2));
        $brightness = ($r * 299 + $g * 587 + $b * 114) / 1000;

        // Return black for light backgrounds and white for dark backgrounds
        return $brightness > 125 ? '#000000' : '#FFFFFF';
    }

    /**
     * Relation: gehört zu einem Zeitraster (nullable = Standard verwenden)
     */
    public function zeitraster()
    {
        return $this->belongsTo(Zeitraster::class, 'zeitraster_id');
    }

    /**
     * Gibt die effektive Zeitraster-ID zurück.
     * Fällt auf das Standard-Zeitraster zurück, wenn keine eigene Zuordnung besteht.
     */
    public function getEffectiveZeitrasterId(): ?int
    {
        return $this->zeitraster_id ?? Zeitraster::getStandard()?->id;
    }

}
