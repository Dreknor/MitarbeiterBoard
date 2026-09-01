<?php

namespace App\Models\personal;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Vertragshistorie / Audit-Log (Arbeitspaket 1.2).
 * Wird von App\Observers\Personal\EmploymentObserver bei jeder Vertragsänderung
 * automatisch geschrieben. Ermöglicht tagesgenaue Soll-Ermittlung und die
 * Erkennung rückwirkender Vertragsänderungen (RETROACTIVE_CONTRACT_CHANGE).
 */
class ContractAudit extends Model
{
    protected $table = 'contract_audits';

    public $timestamps = true;

    protected $fillable = [
        'employment_id', 'employe_id', 'action',
        'valid_from', 'valid_to', 'hours', 'employment_type', 'contract_type', 'status',
        'changed_fields', 'changed_by',
        'is_retroactive', 'affected_period_start', 'affected_period_end',
    ];

    protected $casts = [
        'valid_from'             => 'date',
        'valid_to'               => 'date',
        'changed_fields'         => 'array',
        'is_retroactive'         => 'boolean',
        'affected_period_start'  => 'date',
        'affected_period_end'    => 'date',
    ];

    public function employment(): BelongsTo
    {
        return $this->belongsTo(Employment::class);
    }

    public function employe(): BelongsTo
    {
        return $this->belongsTo(User::class, 'employe_id');
    }

    public function changedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'changed_by');
    }
}

