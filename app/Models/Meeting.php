<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Meeting extends Model
{
    use HasFactory;

    protected $fillable = [
        'group_id',
        'date',
        'start_time',
        'end_time',
        'title',
        'description',
        'cancelled',
        'cancelled_at',
        'cancelled_by',
    ];

    protected $casts = [
        'cancelled' => 'boolean',
        'cancelled_at' => 'datetime',
        'invitation_sent_at' => 'datetime',
        'date' => 'date',
    ];

    public function group()
    {
        return $this->belongsTo(Group::class);
    }

    public function themes()
    {
        return $this->belongsToMany(Theme::class, 'meeting_themes');
    }

    public function scopeUpcoming($query)
    {
        return $query->where('date', '>', now()->toDateString())
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


}
