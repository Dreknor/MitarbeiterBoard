<?php

namespace App\Models\personal;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;

/**
 * Änderung an einem bereits veröffentlichten Dienstplan (Grundlage der Änderungsmitteilung).
 */
class RosterChange extends Model
{
    protected $fillable = ['roster_id', 'employe_id', 'date', 'description', 'created_by', 'notified_at'];

    protected $casts = [
        'date' => 'date',
        'notified_at' => 'datetime',
    ];

    public function roster()
    {
        return $this->belongsTo(Roster::class);
    }

    public function employe()
    {
        return $this->belongsTo(User::class, 'employe_id');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
