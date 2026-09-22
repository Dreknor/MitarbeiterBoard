<?php

namespace App\Models\personal;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use OwenIt\Auditing\Contracts\Auditable;

class OrgPosition extends Model implements Auditable
{
    use HasFactory;
    use \OwenIt\Auditing\Auditable;

    protected $table = 'pers_org_positions';

    protected $fillable = [
        'name',
        'department_id',
        'parent_position_id',
        'level',
        'is_leadership',
        'sort_order',
        'color',
    ];

    protected $casts = [
        'is_leadership' => 'boolean',
    ];

    // --- Relationships ---

    public function parent(): BelongsTo
    {
        return $this->belongsTo(OrgPosition::class, 'parent_position_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(OrgPosition::class, 'parent_position_id')
            ->orderBy('sort_order');
    }

    /**
     * Alle Nachfahren rekursiv laden (Eager Loading für kompletten Baum).
     */
    public function allChildren(): HasMany
    {
        return $this->children()->with('allChildren.currentUsers', 'allChildren.currentDeputy');
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(\App\Models\Group::class, 'department_id');
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'pers_org_position_user', 'pers_org_position_id', 'user_id')
            ->withPivot(['is_deputy', 'valid_from', 'valid_until'])
            ->withTimestamps();
    }

    /**
     * Aktuell aktive Stelleninhaber (valid_until IS NULL oder >= heute, kein Stellvertreter).
     */
    public function currentUsers(): BelongsToMany
    {
        return $this->users()
            ->wherePivot('is_deputy', false)
            ->where(function ($q) {
                $q->whereNull('pers_org_position_user.valid_until')
                  ->orWhere('pers_org_position_user.valid_until', '>=', now()->toDateString());
            });
    }

    /**
     * Aktuell aktive Stellvertreter.
     */
    public function currentDeputy(): BelongsToMany
    {
        return $this->users()
            ->wherePivot('is_deputy', true)
            ->where(function ($q) {
                $q->whereNull('pers_org_position_user.valid_until')
                  ->orWhere('pers_org_position_user.valid_until', '>=', now()->toDateString());
            });
    }
}

