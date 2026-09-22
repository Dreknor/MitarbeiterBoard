<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserQuicklink extends Model
{
    protected $table = 'user_quicklinks';

    protected $fillable = [
        'user_id',
        'label',
        'url',
        'icon',
        'order',
    ];

    protected $casts = [
        'order' => 'integer',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}

