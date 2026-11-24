<?php

namespace App\Models;

use App\Support\ErgebnisboardVorlagen;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Permission\Models\Role;

/**
 * Ergebnisboard: Raster aus Bereichen (aus einer Vorlage kopiert) mit Karten.
 * Zugriff über ergebnis_board_zugriffe (Person/Gruppe/Rolle × lesen/schreiben/verwalten),
 * Regeln in Services/Ergebnisboards/ErgebnisboardService.
 */
class Ergebnisboard extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $table = 'ergebnis_boards';

    public const RECHTE = ['lesen' => 1, 'schreiben' => 2, 'verwalten' => 3];

    protected $fillable = [
        'titel', 'untertitel', 'kopfzeile', 'fusszeile', 'vorlage', 'farbschema', 'spalten', 'bereiche',
        'achsen', 'legende', 'bereiche_frei', 'auto_status', 'meeting_id', 'meeting_lesen', 'theme_id',
        'version', 'created_by', 'archiviert_at',
    ];

    protected $casts = [
        'bereiche' => 'array',
        'achsen' => 'array',
        'legende' => 'array',
        'bereiche_frei' => 'boolean',
        'auto_status' => 'boolean',
        'meeting_lesen' => 'boolean',
        'version' => 'integer',
        'archiviert_at' => 'datetime',
    ];

    public function karten(): HasMany
    {
        return $this->hasMany(ErgebnisKarte::class, 'board_id')->orderBy('sort')->orderBy('id');
    }

    /** Für scopeBindings(): {karte} gehört immer zu diesem Board. */
    public function resolveChildRouteBinding($childType, $value, $field)
    {
        if ($childType === 'karte') {
            return ErgebnisKarte::query()->where('board_id', $this->id)->where($field ?? 'id', $value)->firstOrFail();
        }

        return parent::resolveChildRouteBinding($childType, $value, $field);
    }

    public function zugriffe(): HasMany
    {
        return $this->hasMany(ErgebnisBoardZugriff::class, 'board_id');
    }

    public function ersteller(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function meeting(): BelongsTo
    {
        return $this->belongsTo(Meeting::class);
    }

    public function theme(): BelongsTo
    {
        return $this->belongsTo(Theme::class);
    }

    public function istArchiviert(): bool
    {
        return $this->archiviert_at !== null;
    }

    /** Bereich nach Schlüssel. */
    public function bereich(string $schluessel): ?array
    {
        foreach ($this->bereiche ?? [] as $b) {
            if (($b['schluessel'] ?? null) === $schluessel) {
                return $b;
            }
        }

        return null;
    }

    public function bereichsSchluessel(): array
    {
        return array_column($this->bereiche ?? [], 'schluessel');
    }

    /** Erster Bereich mit dem Status (offen|in_arbeit|erledigt). */
    public function bereichMitStatus(string $status): ?array
    {
        foreach ($this->bereiche ?? [] as $b) {
            if (($b['status'] ?? null) === $status) {
                return $b;
            }
        }

        return null;
    }

    public function vorlageTitel(): string
    {
        return ErgebnisboardVorlagen::finden($this->vorlage)['titel'] ?? $this->vorlage;
    }

    /**
     * Boards, die die Person mindestens lesen darf (Person, Gruppe, Rolle, Ersteller, Sitzung).
     * `manage result boards` sieht alles.
     */
    public function scopeSichtbarFuer(Builder $query, User $user): Builder
    {
        if ($user->can(ErgebnisboardVorlagen::PERMISSION_MANAGE)) {
            return $query;
        }

        $groupIds = $user->groups()->pluck('id')->filter()->values()->all();
        $roleIds = $user->roles()->pluck('id')->all();
        $meetingIds = Meeting::query()->visibleTo($user)->pluck('id')->all();

        return $query->where(function (Builder $q) use ($user, $groupIds, $roleIds, $meetingIds) {
            $q->where('created_by', $user->id)
                ->when(! empty($meetingIds), fn (Builder $q) => $q->orWhere(
                    fn (Builder $m) => $m->where('meeting_lesen', true)->whereIn('meeting_id', $meetingIds)
                ))
                ->orWhereHas('zugriffe', function (Builder $z) use ($user, $groupIds, $roleIds) {
                    $z->where(fn (Builder $p) => $p->where('berechtigter_type', User::class)->where('berechtigter_id', $user->id));
                    if (! empty($groupIds)) {
                        $z->orWhere(fn (Builder $p) => $p->where('berechtigter_type', Group::class)->whereIn('berechtigter_id', $groupIds));
                    }
                    if (! empty($roleIds)) {
                        $z->orWhere(fn (Builder $p) => $p->where('berechtigter_type', Role::class)->whereIn('berechtigter_id', $roleIds));
                    }
                });
        });
    }
}
