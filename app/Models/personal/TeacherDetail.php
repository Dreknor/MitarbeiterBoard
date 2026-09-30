<?php

namespace App\Models\personal;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use OwenIt\Auditing\Contracts\Auditable;

class TeacherDetail extends Model implements Auditable
{
    use HasFactory;
    use \OwenIt\Auditing\Auditable;

    protected $table = 'pers_teacher_details';

    protected $fillable = [
        'employment_id',
        'school_type_id',
        'deputat_hours',
        'reduction_hours',
        'reduction_reason',
        'anrechnungsstunden',
        'valid_from',
        'valid_until',
    ];

    protected $casts = [
        'valid_from'         => 'date',
        'valid_until'        => 'date',
        'deputat_hours'      => 'decimal:2',
        'reduction_hours'    => 'decimal:2',
        'anrechnungsstunden' => 'decimal:2',
    ];

    // --- Relationships ---

    public function employment(): BelongsTo
    {
        return $this->belongsTo(Employment::class);
    }

    public function schoolType(): BelongsTo
    {
        return $this->belongsTo(SchoolType::class, 'school_type_id');
    }

    public function subjects(): HasMany
    {
        return $this->hasMany(TeacherSubject::class);
    }

    // --- Accessors ---

    /**
     * Effektive Unterrichtsstunden pro Woche.
     * Formel: Deputat − Ermäßigung − Anrechnungsstunden
     * (Das Deputat ist bereits die vertraglich vereinbarte Unterrichtsverpflichtung; die Wochenstunden
     * des Vertrags werden daraus abgeleitet – siehe ContractService::wochenstundenAusDeputat.)
     */
    public function getEffectiveHoursAttribute(): float
    {
        return max(0.0, round(
            (float) $this->deputat_hours - (float) $this->reduction_hours - (float) $this->anrechnungsstunden,
            2
        ));
    }

    /**
     * Prüft ob dieses Detail aktuell gültig ist.
     */
    public function isCurrentlyValid(): bool
    {
        return $this->valid_until === null || $this->valid_until->isFuture();
    }
}

