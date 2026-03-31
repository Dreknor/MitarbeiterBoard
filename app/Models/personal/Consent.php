<?php

namespace App\Models\personal;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use OwenIt\Auditing\Contracts\Auditable;

class Consent extends Model implements Auditable
{
    use HasFactory;
    use \OwenIt\Auditing\Auditable;

    protected $table = 'pers_consents';

    protected $fillable = [
        'employe_id',
        'consent_type_id',
        'granted_at',
        'revoked_at',
        'granted_via',
    ];

    protected $casts = [
        'granted_at' => 'datetime',
        'revoked_at' => 'datetime',
    ];

    // --- Relationships ---

    public function employe(): BelongsTo
    {
        return $this->belongsTo(User::class, 'employe_id');
    }

    public function consentType(): BelongsTo
    {
        return $this->belongsTo(ConsentType::class);
    }

    // --- Helpers ---

    public function isActive(): bool
    {
        return $this->revoked_at === null;
    }
}

