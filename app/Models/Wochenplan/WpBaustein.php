<?php

namespace App\Models\Wochenplan;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;

/** Wiederverwendbare Aufgabe der Aufgaben-Bibliothek (pro Fach und Klassenstufe). */
class WpBaustein extends Model
{
    protected $table = 'wp_bausteine';

    protected $fillable = ['wp_fach_id', 'klassenstufe', 'aufgabe', 'dauer', 'niveau', 'link', 'created_by'];

    protected $casts = [
        'klassenstufe' => 'integer',
        'niveau' => 'integer',
    ];

    public function fach()
    {
        return $this->belongsTo(WpFach::class, 'wp_fach_id');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
