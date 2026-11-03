<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Builder;

class Procedure extends Model
{
    use SoftDeletes, HasFactory;

    protected $fillable = ['name', 'description', 'author_id', 'category_id', 'started_at', 'ended_at', 'ended_reason', 'template_id'];
    protected $visible = ['name', 'description', 'author_id', 'category_id', 'started_at', 'ended_at', 'ended_reason', 'template_id'];

    protected $casts = [
        'started_at' => 'date',
        'ended_at' => 'date'
    ];

    public function category()
    {
        return $this->belongsTo(Procedure_Category::class);
    }

    public function author()
    {
        return $this->belongsTo(User::class);
    }

    public function steps()
    {
        return $this->hasMany(Procedure_Step::class);
    }

    public function template()
    {
        return $this->belongsTo(ProcedureTemplate::class, 'template_id');
    }

    // ─── Helper ──────────────────────────────────────────────────────────────

    /** Gibt zurück, ob dieser Eintrag eine (noch nicht gestartete) Vorlage ist. */
    public function isTemplate(): bool
    {
        return is_null($this->started_at);
    }

    // ─── Query-Scopes ────────────────────────────────────────────────────────

    /** Filtert auf Vorlagen (started_at IS NULL). */
    public function scopeVorlagen(Builder $query): Builder
    {
        return $query->whereNull('started_at');
    }

    /** Filtert auf gestartete (laufende oder beendete) Prozesse. */
    public function scopeGestartet(Builder $query): Builder
    {
        return $query->whereNotNull('started_at');
    }

    /** Filtert auf aktuell laufende Prozesse (gestartet, noch nicht beendet). */
    public function scopeLaufend(Builder $query): Builder
    {
        return $query->whereNotNull('started_at')->whereNull('ended_at');
    }

    /**
     * Filtert auf Prozesse, die der Nutzer sehen darf:
     *  - `manage procedures` → alle
     *  - `view assigned procedures` → Prozesse mit mindestens einem Schritt, dem der
     *    Nutzer direkt zugewiesen ist oder dessen Position er innehat.
     */
    public function scopeSichtbarFuer(Builder $query, User $user): Builder
    {
        if ($user->can('manage procedures')) {
            return $query;
        }

        if (!$user->can('view assigned procedures')) {
            return $query->whereRaw('1 = 0');
        }

        $positionIds = $user->positions()->pluck('positions.id');

        return $query->whereHas('steps', function (Builder $steps) use ($user, $positionIds) {
            $steps->where(function (Builder $q) use ($user, $positionIds) {
                $q->whereHas('users', fn (Builder $u) => $u->where('users.id', $user->id));
                if ($positionIds->isNotEmpty()) {
                    $q->orWhereIn('position_id', $positionIds);
                }
            });
        });
    }
}
