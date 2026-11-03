<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Collection;
use Spatie\Permission\Models\Role;

class Meeting extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'group_id',
        'creator_id',
        'date',
        'start_time',
        'end_time',
        'location',
        'meeting_url',
        'title',
        'description',
        'cancelled',
        'cancelled_at',
        'cancelled_by',
        'invitation_sent_at',
        'invitation_sent_by',
    ];

    protected $casts = [
        'cancelled' => 'boolean',
        'cancelled_at' => 'datetime',
        'invitation_sent_at' => 'datetime',
        'date' => 'date',
    ];

    public function group(): BelongsTo
    {
        return $this->belongsTo(Group::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'creator_id');
    }

    /**
     * Alle Teilnehmer-Zeilen (Personen, Gruppen, Rollen).
     */
    public function participants(): HasMany
    {
        return $this->hasMany(MeetingParticipant::class);
    }

    /**
     * Explizit eingeladene Personen.
     */
    public function participantUsers(): MorphToMany
    {
        return $this->morphedByMany(User::class, 'participant', 'meeting_participants')
            ->withPivot('is_organizer')
            ->withTimestamps()
            ->orderBy('name');
    }

    /**
     * Eingeladene Gruppen / Bereiche – alle Mitglieder nehmen teil.
     */
    public function participantGroups(): MorphToMany
    {
        return $this->morphedByMany(Group::class, 'participant', 'meeting_participants')
            ->withTimestamps()
            ->orderBy('name');
    }

    /**
     * Eingeladene Rollen – alle Personen mit dieser Rolle nehmen teil.
     */
    public function participantRoles(): MorphToMany
    {
        return $this->morphedByMany(Role::class, 'participant', 'meeting_participants')
            ->withTimestamps()
            ->orderBy('name');
    }

    /**
     * Meeting ohne feste Gruppe (freie Besprechung).
     */
    public function isFree(): bool
    {
        return $this->group_id === null;
    }

    /**
     * Anzeigename des Kontextes: Gruppe oder "Freies Meeting".
     */
    public function contextLabel(): string
    {
        return $this->group?->name ?? 'Freies Meeting';
    }

    /**
     * Video-/Meeting-Link: eigener Link hat Vorrang vor dem Gruppen-Link.
     */
    public function effectiveMeetingUrl(): ?string
    {
        return $this->meeting_url ?: $this->group?->meeting_url;
    }

    /**
     * Ist der Nutzer Organisator (Ersteller oder als Organisator eingeladen)?
     */
    public function isOrganizer(User $user): bool
    {
        if ((int) $this->creator_id === (int) $user->id) {
            return true;
        }

        return $this->participants()
            ->where('participant_type', User::class)
            ->where('participant_id', $user->id)
            ->where('is_organizer', true)
            ->exists();
    }

    /**
     * Nimmt der Nutzer (direkt, über Gruppe/Bereich oder Rolle) teil?
     */
    public function hasParticipant(User $user): bool
    {
        return static::query()->whereKey($this->id)->visibleTo($user)->exists();
    }

    /**
     * Alle teilnehmenden Personen (Gruppenmitglieder, eingeladene Personen,
     * Mitglieder eingeladener Gruppen, Personen eingeladener Rollen, Ersteller).
     */
    public function resolvedParticipants(): Collection
    {
        $users = collect();

        if ($this->group) {
            $users = $users->concat($this->group->users);
        }

        $users = $users->concat($this->participantUsers);

        foreach ($this->participantGroups as $group) {
            $users = $users->concat($group->users);
        }

        $roleNames = $this->participantRoles->pluck('name')->all();
        if (! empty($roleNames)) {
            $users = $users->concat(User::role($roleNames)->get());
        }

        if ($this->creator) {
            $users->push($this->creator);
        }

        return $users->unique('id')->sortBy('name')->values();
    }

    /**
     * Meetings, die ein Nutzer sehen darf: eigene Gruppen, eigene Einladungen
     * (direkt, über Gruppen/Bereiche oder Rollen) sowie selbst erstellte.
     */
    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        // User::groups() berücksichtigt auch "see unprotected groups" (wie die Gruppen-Routen)
        $groupIds = $user->groups()->pluck('id')->filter()->values()->all();
        $roleIds  = $user->roles()->pluck('id')->all();

        return $query->where(function (Builder $q) use ($user, $groupIds, $roleIds) {
            $q->where('creator_id', $user->id)
                ->when(! empty($groupIds), fn (Builder $q) => $q->orWhereIn('group_id', $groupIds))
                ->orWhereHas('participants', function (Builder $p) use ($user, $groupIds, $roleIds) {
                    $p->where(function (Builder $p) use ($user) {
                        $p->where('participant_type', User::class)->where('participant_id', $user->id);
                    });
                    if (! empty($groupIds)) {
                        $p->orWhere(function (Builder $p) use ($groupIds) {
                            $p->where('participant_type', Group::class)->whereIn('participant_id', $groupIds);
                        });
                    }
                    if (! empty($roleIds)) {
                        $p->orWhere(function (Builder $p) use ($roleIds) {
                            $p->where('participant_type', Role::class)->whereIn('participant_id', $roleIds);
                        });
                    }
                });
        });
    }

    public function themes()
    {
        return $this->belongsToMany(Theme::class, 'meeting_themes');
    }

    public function scopeUpcoming($query)
    {
        // Heutige Meetings gehören zu "upcoming" – ansonsten verschwinden
        // sie zu früh aus der Dashboard-Card.
        return $query->where('date', '>=', now()->toDateString())
                     ->orderBy('date')
                     ->orderBy('start_time');
    }

    public function scopePast($query)
    {
        return $query->where('date', '<', now()->toDateString())
                     ->orderBy('date', 'desc')
                     ->orderBy('start_time', 'desc');
    }

    public function scopeToday($query)
    {
        return $query->where('date', now()->toDateString())
                     ->orderBy('start_time');
    }

    public function startTime(): Attribute
    {
        return Attribute::make(
            get: fn ($value) => \Carbon\Carbon::parse($this->date->format('Y-m-d').' '.$value)->format('H:i'),
            set: fn ($value) => \Carbon\Carbon::createFromFormat('H:i', $value)->toTimeString()
        );
    }
    public function endTime(): Attribute
    {
        return Attribute::make(
            get: fn ($value) => \Carbon\Carbon::parse($this->date->format('Y-m-d').' '.$value)->format('H:i'),
            set: fn ($value) => \Carbon\Carbon::createFromFormat('H:i', $value)->toTimeString()
        );
    }

    public function invitationSender()
    {
        return $this->belongsTo(User::class, 'invitation_sent_by');
    }

    /**
     * Gemeinsame Aufgaben für alle Teilnehmenden (taskable = Meeting).
     */
    public function tasks()
    {
        return $this->morphMany(Task::class, 'taskable');
    }

    public function meetingTasks()
    {
        return $this->hasMany(MeetingTask::class);
    }

    public function roomBooking()
    {
        return $this->hasOne(RoomBooking::class)->where('cancelled', false)->whereNull('deleted_at');
    }

    public function roomBookings()
    {
        return $this->hasMany(RoomBooking::class);
    }


}
