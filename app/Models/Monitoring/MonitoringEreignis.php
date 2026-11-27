<?php

namespace App\Models\Monitoring;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Statuswechsel eines Dienstes (Verlauf, 90 Tage).
 */
class MonitoringEreignis extends Model
{
    public const AUFBEWAHRUNG_TAGE = 90;

    protected $table = 'monitoring_ereignisse';

    protected $fillable = ['dienst_id', 'status_alt', 'status_neu', 'meldung'];

    public function dienst(): BelongsTo
    {
        return $this->belongsTo(MonitoringDienst::class, 'dienst_id');
    }
}
