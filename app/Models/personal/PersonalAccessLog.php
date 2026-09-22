<?php

namespace App\Models\personal;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * PersonalAccessLog – immutabler Zugriffs-Log für DSGVO-Audit.
 * Kein SoftDeletes, kein updated_at.
 */
class PersonalAccessLog extends Model
{
    use HasFactory;

    protected $table = 'pers_access_logs';

    // Kein updated_at
    const UPDATED_AT = null;

    protected $fillable = [
        'user_id',
        'action',
        'resource_type',
        'resource_id',
        'route',
        'ip_address',
        'metadata',
    ];

    protected $casts = [
        'metadata'   => 'array',
        'created_at' => 'datetime',
    ];

    // --- Relationships ---

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    // --- Scopes ---

    public function scopeForResource(Builder $query, string $type, int $id): Builder
    {
        return $query->where('resource_type', $type)->where('resource_id', $id);
    }

    public function scopeForUser(Builder $query, int $userId): Builder
    {
        return $query->where('user_id', $userId);
    }
}

