<?php

namespace App\Models\personal;

use App\Enums\ProcedureLinkStatus;
use App\Enums\ProcedureLinkType;
use App\Models\Procedure;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Verknüpft einen Mitarbeiter mit einem gestarteten Prozess (Onboarding, Offboarding, Versetzung).
 */
class ProcedureLink extends Model
{
    protected $table = 'pers_procedure_links';

    protected $fillable = ['employe_id', 'employment_id', 'procedure_id', 'type', 'status', 'completed_at'];

    protected $casts = [
        'type'         => ProcedureLinkType::class,
        'status'       => ProcedureLinkStatus::class,
        'completed_at' => 'datetime',
    ];

    public function employe(): BelongsTo
    {
        return $this->belongsTo(User::class, 'employe_id');
    }

    public function employment(): BelongsTo
    {
        return $this->belongsTo(Employment::class);
    }

    public function procedure(): BelongsTo
    {
        return $this->belongsTo(Procedure::class);
    }
}
