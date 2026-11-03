<?php

namespace App\Models;

use App\Support\HtmlSanitizer;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

class Ticket extends Model implements HasMedia
{
    use HasFactory;
    use SoftDeletes;
    use InteractsWithMedia;

    public const STATUS_OPEN = 'open';
    public const STATUS_WAITING = 'waiting';
    public const STATUS_CLOSED = 'closed';

    public const STATUS_LABELS = [
        self::STATUS_OPEN => 'offen',
        self::STATUS_WAITING => 'wartend',
        self::STATUS_CLOSED => 'geschlossen',
    ];

    public const PRIORITY_LABELS = [
        'low' => 'niedrig',
        'medium' => 'normal',
        'high' => 'hoch',
    ];

    protected $fillable = [
        'title',
        'description',
        'status',
        'user_id',
        'assigned_to',
        'priority',
        'category_id',
        'waiting_until',
        'closed_at',
        'closed_by',
    ];

    protected $casts = [
        'waiting_until' => 'datetime',
        'closed_at' => 'datetime',
    ];

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('ticket_files')->useDisk('tickets');
    }

    public function user()
    {
        return $this->belongsTo(User::class)->withTrashed();
    }

    public function comments()
    {
        return $this->hasMany(TicketComment::class);
    }

    public function category()
    {
        return $this->belongsTo(TicketCategory::class);
    }

    public function assigned()
    {
        return $this->belongsTo(User::class, 'assigned_to')->withTrashed();
    }

    public function closedBy()
    {
        return $this->belongsTo(User::class, 'closed_by')->withTrashed();
    }

    public function pinnedBy()
    {
        return $this->belongsToMany(User::class, 'tickets_pinned', 'ticket_id', 'user_id')->withTimestamps();
    }

    public function scopeOpen(Builder $query): Builder
    {
        return $query->whereIn('status', [self::STATUS_OPEN, self::STATUS_WAITING]);
    }

    public function scopeClosed(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_CLOSED);
    }

    /**
     * Bearbeiter (edit tickets) sehen alle Tickets, alle anderen nur ihre eigenen.
     */
    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        if ($user->can('edit tickets')) {
            return $query;
        }

        return $query->where($query->qualifyColumn('user_id'), $user->id);
    }

    /**
     * Zeitpunkt der letzten Aktivität (Ticket-Änderung oder neuester Kommentar).
     * Nutzt – falls vorhanden – das per withMax() geladene Aggregat.
     */
    public function getLastActivityAttribute()
    {
        $lastComment = $this->comments_max_created_at ?? null;
        if ($lastComment !== null) {
            $lastComment = \Illuminate\Support\Carbon::parse($lastComment);
        }

        if ($lastComment && $this->updated_at && $lastComment->greaterThan($this->updated_at)) {
            return $lastComment;
        }

        return $this->updated_at ?? $lastComment;
    }

    public function getStatusLabelAttribute(): string
    {
        return self::STATUS_LABELS[$this->status] ?? (string) $this->status;
    }

    public function getPriorityLabelAttribute(): string
    {
        return self::PRIORITY_LABELS[$this->priority] ?? (string) $this->priority;
    }

    public function getDescriptionHtmlAttribute(): string
    {
        return HtmlSanitizer::clean($this->description);
    }

    public function isClosed(): bool
    {
        return $this->status === self::STATUS_CLOSED;
    }

    public function isWaiting(): bool
    {
        return $this->status === self::STATUS_WAITING;
    }

    /**
     * Wartezeit abgelaufen, ohne dass das Ticket geschlossen wurde.
     */
    public function isWaitingOverdue(): bool
    {
        return $this->isWaiting() && $this->waiting_until !== null && $this->waiting_until->isPast();
    }
}
