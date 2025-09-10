<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Klasse extends Model
{
    use SoftDeletes;

    protected $table = 'klassen';

    protected $visible = ['name', 'kuerzel', 'color'];
    protected $fillable = ['name', 'kuerzel', 'grading_system_id', 'color'];

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

    // Relation: Welches Graduierungssystem gilt für die Klasse
    public function grading_system()
    {
        return $this->belongsTo(GradingSystem::class, 'grading_system_id');
    }

    public function  appointments()
    {
        return $this->belongsToMany(PaedDiaryAppointment::class, 'paed_diary_appointment_klassen');
    }
}
