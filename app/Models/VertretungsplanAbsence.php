<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class VertretungsplanAbsence extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'user_id',
        'reason',
        'start_date',
        'end_date',
        'absence_id'
    ];

    protected $casts = [
        'start_date' => 'datetime',
        'end_date' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class)->withDefault([
            'name' => 'System / gelöschter Benutzer',
        ]);
    }

    public function absence()
    {
        return $this->belongsTo(Absence::class);
    }

}
