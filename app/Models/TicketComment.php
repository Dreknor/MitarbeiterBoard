<?php

namespace App\Models;

use App\Support\HtmlSanitizer;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

class TicketComment extends Model implements HasMedia
{
    use HasFactory;
    use InteractsWithMedia;

    protected $fillable = ['ticket_id', 'user_id', 'comment', 'internal', 'system'];

    protected $casts = [
        'internal' => 'boolean',
        'system' => 'boolean',
    ];

    public function ticket()
    {
        return $this->belongsTo(Ticket::class);
    }

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('comment_files')->useDisk('tickets');
    }

    public function user()
    {
        return $this->belongsTo(User::class)->withTrashed();
    }

    public function scopeInternal($query)
    {
        return $query->where('internal', true);
    }

    public function scopeExternal($query)
    {
        return $query->where('internal', false);
    }

    /**
     * Automatische Verlaufseinträge (Status, Zuweisung, automatisches Schließen …).
     */
    public function isSystem(): bool
    {
        return (bool) $this->system || $this->user_id === null;
    }

    public function getAuthorNameAttribute(): string
    {
        return $this->user?->name ?? 'System';
    }

    public function getCommentHtmlAttribute(): string
    {
        return HtmlSanitizer::clean($this->comment);
    }
}
