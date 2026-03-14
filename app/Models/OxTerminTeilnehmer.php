<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * OxTerminTeilnehmer – Teilnehmer eines Kalender-Termins (aus ATTENDEE-Property).
 *
 * @property int         $id
 * @property int         $ox_termin_id
 * @property string      $email
 * @property string|null $name
 * @property string|null $status   ACCEPTED|DECLINED|TENTATIVE|NEEDS-ACTION
 * @property-read \App\Models\OxTermin $termin
 */
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

