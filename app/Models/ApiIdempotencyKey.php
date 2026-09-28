<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * API v1: gespeicherte Antwort zu einem Idempotency-Key (siehe EnsureIdempotency-Middleware).
 */
class ApiIdempotencyKey extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = [
        'user_id',
        'key',
        'method',
        'path',
        'request_hash',
        'response_status',
        'response_body',
    ];

    protected $casts = [
        'response_status' => 'integer',
    ];
}
