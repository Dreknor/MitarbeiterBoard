<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class GradingCoachingNote extends Model
{
    protected $fillable = [
        'session_id',
        'schueler_id',
        'user_id',
        'note',
        'noted_at',
    ];

    protected $casts = [
        'noted_at' => 'datetime',
    ];

    public function session()
    {
        return $this->belongsTo(GradingDocumentationSession::class, 'session_id');
    }

    public function schueler()
    {
        return $this->belongsTo(Schueler::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}

