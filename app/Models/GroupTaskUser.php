<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Zuständigkeit einer Person für eine gemeinsame Aufgabe (Gruppe/Meeting).
 * Erledigt = completed_at gesetzt (die Zeile bleibt für den Verlauf erhalten).
 */
class GroupTaskUser extends Model
{
    protected $fillable = ['taskable_id', 'users_id', 'completed_at'];

    protected $casts = [
        'completed_at' => 'datetime',
    ];

    public function task()
    {
        return $this->belongsTo(Task::class, 'taskable_id', 'id');
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'users_id');
    }
}
