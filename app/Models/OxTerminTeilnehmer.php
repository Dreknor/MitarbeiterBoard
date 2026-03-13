<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OxTerminTeilnehmer extends Model
{
    protected $table = 'ox_termin_teilnehmer';

    protected $fillable = [
        'ox_termin_id',
        'email',
        'name',
        'status',
    ];

    // --- Relationen ---

    public function termin(): BelongsTo
    {
        return $this->belongsTo(OxTermin::class, 'ox_termin_id');
    }
}

