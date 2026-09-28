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

    protected $fillable = ['ticket_id', 'user_id', 'comment', 'internal'];

    protected $casts = [
        'internal' => 'boolean',
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
     * Systemkommentare (automatisches Schließen etc.) haben keinen Autor.
     */
    public function isSystem(): bool
    {
        return $this->user_id === null;
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
