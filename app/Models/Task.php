<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Aufgabe zu einem Thema.
 *
 * - persönlich: taskable = User
 * - gemeinsam:  taskable = Group oder Meeting, die zuständigen Personen stehen
 *   in group_task_users (je Person mit completed_at)
 *
 * Globaler Scope "active" blendet erledigte Aufgaben aus – für Verläufe
 * `withCompleted()` verwenden.
 */
class Task extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $fillable = ['task', 'date', 'theme_id', 'completed', 'creator_id', 'completed_at', 'completed_by'];

    protected $dates = [];

    protected $casts = [
        'completed'    => 'boolean',
        'date'         => 'date',
        'completed_at' => 'datetime',
    ];

    public function theme(): BelongsTo
    {
        return $this->belongsTo(Theme::class);
    }

    public function taskable()
    {
        return $this->morphTo();
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'creator_id');
    }

    public function completedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'completed_by');
    }

    /**
     * Zuständige Personen einer gemeinsamen Aufgabe (inkl. bereits erledigter).
     */
    public function taskUsers(): HasMany
    {
        return $this->hasMany(GroupTaskUser::class, 'taskable_id');
    }

    /**
     * Noch offene Zuständigkeiten einer gemeinsamen Aufgabe.
     */
    public function openTaskUsers(): HasMany
    {
        return $this->taskUsers()->whereNull('completed_at');
    }

    public function scopeWithCompleted(Builder $query): Builder
    {
        return $query->withoutGlobalScope('active');
    }

    public function isPersonal(): bool
    {
        return $this->taskable_type === User::class;
    }

    public function isCollective(): bool
    {
        return in_array($this->taskable_type, [Group::class, Meeting::class], true);
    }

    /**
     * Anzeigename der Zuständigkeit.
     */
    public function ownerLabel(): string
    {
        return match ($this->taskable_type) {
            User::class    => $this->taskable?->name ?? 'Unbekannt',
            Group::class   => 'Gruppe ' . ($this->taskable?->name ?? ''),
            Meeting::class => 'Meeting „' . ($this->taskable?->title ?? '') . '“',
            default        => '',
        };
    }

    /**
     * Ist die Aufgabe für diese Person noch offen?
     */
    public function isOpenFor(User $user): bool
    {
        if ($this->completed) {
            return false;
        }

        if ($this->isPersonal()) {
            return (int) $this->taskable_id === (int) $user->id;
        }

        $rows = $this->relationLoaded('taskUsers') ? $this->taskUsers : $this->taskUsers()->get();

        return $rows->contains(fn (GroupTaskUser $row) => (int) $row->users_id === (int) $user->id && $row->completed_at === null);
    }

    /**
     * Link zum Thema – bei Meeting-Aufgaben bzw. freien Themen im Meeting-Kontext.
     */
    public function themeUrl(): ?string
    {
        $theme = $this->theme;
        if (! $theme) {
            return null;
        }

        if ($this->taskable_type === Meeting::class && $this->taskable) {
            return route('meetings.themes.show', [$this->taskable, $theme]);
        }

        if ($theme->group) {
            return url($theme->group->name . '/themes/' . $theme->id);
        }

        $meeting = $theme->meetings()->orderByDesc('date')->first();

        return $meeting ? route('meetings.themes.show', [$meeting, $theme]) : null;
    }

    protected static function booted(): void
    {
        static::addGlobalScope('active', function (Builder $builder) {
            $builder->where('completed', 0);
        });
    }
}
