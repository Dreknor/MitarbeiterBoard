<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ListenTermin extends Model
{
    protected $table = 'listen_termine';

    protected $fillable = ['listen_id', 'termin', 'comment', 'reserviert_fuer'];
    protected $visible = ['listen_id', 'termin', 'comment', 'reserviert_fuer'];
    protected $casts = [
        'termin' => 'datetime',
    ];

    public function eingetragenePerson()
    {
        return $this->belongsTo(User::class, 'reserviert_fuer');
    }

    public function liste()
    {
        return $this->belongsTo(Liste::class, 'listen_id');
    }
}
