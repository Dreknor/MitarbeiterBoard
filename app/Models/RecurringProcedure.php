<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class RecurringProcedure extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'procedure_id',
        'active',
        'month',
        'faelligkeit_typ',
        'wochen',
        'ferien',
        'weekday',
        'weekday_interval',
        'schuljahres_tag',
        'schuljahres_monat',
        'last_triggered_at',
        'next_trigger_at',
    ];

    protected $casts = [
        'month'              => 'integer',
        'wochen'             => 'integer',
        'active'             => 'boolean',
        'weekday'            => 'integer',
        'weekday_interval'   => 'integer',
        'schuljahres_tag'    => 'integer',
        'schuljahres_monat'  => 'integer',
        'last_triggered_at'  => 'datetime',
        'next_trigger_at'    => 'datetime',
    ];

    public function procedure()
    {
        return $this->belongsTo(Procedure::class);
    }

    /** Neue Vorlage-Referenz (§8.1): legacy_procedure_id auf Vorlage. */
    public function template()
    {
        return $this->belongsTo(ProcedureTemplate::class, 'procedure_id', 'legacy_procedure_id');
    }
}
