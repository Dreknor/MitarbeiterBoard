<?php

namespace App\Models\personal;

use App\Enums\QualificationStatus;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use OwenIt\Auditing\Contracts\Auditable;

class EmployeeQualification extends Model implements Auditable
{
    use HasFactory, SoftDeletes;
    use \OwenIt\Auditing\Auditable;

    protected $table = 'pers_employee_qualifications';

    protected $fillable = [
        'employe_id',
        'qualification_type_id',
        'acquired_date',
        'expiry_date',
        'document_id',
        'status',
        'notes',
        'verified_by',
        'verified_at',
    ];

    protected $casts = [
        'acquired_date' => 'date',
        'expiry_date'   => 'date',
        'verified_at'   => 'datetime',
        'status'        => QualificationStatus::class,
    ];

    // ---- Relationships ----

    public function employe(): BelongsTo
    {
        return $this->belongsTo(User::class, 'employe_id');
    }

    public function qualificationType(): BelongsTo
    {
        return $this->belongsTo(QualificationType::class, 'qualification_type_id');
    }

    public function document(): BelongsTo
    {
        return $this->belongsTo(PersonalDocument::class, 'document_id');
    }

    public function verifiedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'verified_by');
    }
}

