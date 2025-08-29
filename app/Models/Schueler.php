<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Schueler extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'schueler';

    protected $fillable = [
        'vorname',
        'nachname',
        'geburtsdatum',
        'klasse_id',
        'import_key'
    ];

    protected $casts = [
        'geburtsdatum' => 'date'
    ];

    public function klasse()
    {
        return $this->belongsTo(Klasse::class, 'klasse_id');
    }

    public function getNameAttribute(): string
    {
        return $this->vorname.' '.$this->nachname;
    }
}
