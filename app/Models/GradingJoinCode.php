<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Beitrittscode eines Schülers für die Selbsteinschätzung auf einem Schüler-iPad.
 */
class GradingJoinCode extends Model
{
    /** Ohne verwechselbare Zeichen (0/O, 1/I/L) */
    public const ALPHABET = 'ABCDEFGHJKMNPQRSTUVWXYZ23456789';
    public const LENGTH = 6;

    protected $fillable = [
        'session_id',
        'schueler_id',
        'code',
        'expires_at',
        'created_by',
    ];

    protected $casts = [
        'expires_at' => 'datetime',
    ];

    public function session()
    {
        return $this->belongsTo(GradingDocumentationSession::class, 'session_id');
    }

    public function schueler()
    {
        return $this->belongsTo(Schueler::class);
    }

    public function devices()
    {
        return $this->hasMany(GradingStudentDevice::class, 'join_code_id');
    }

    public function scopeActive($query)
    {
        return $query->where('expires_at', '>', now());
    }

    /** "K7M4QX" → "K7M-4QX" */
    public function getDisplayCodeAttribute(): string
    {
        return substr($this->code, 0, 3) . '-' . substr($this->code, 3);
    }

    /** Eingabe normalisieren: Groß-/Kleinschreibung, Bindestriche und Leerzeichen ignorieren */
    public static function normalize(?string $code): string
    {
        return strtoupper(preg_replace('/[^A-Za-z0-9]/', '', (string) $code));
    }

    public static function generateUniqueCode(): string
    {
        do {
            $code = '';
            for ($i = 0; $i < self::LENGTH; $i++) {
                $code .= self::ALPHABET[random_int(0, strlen(self::ALPHABET) - 1)];
            }
        } while (static::where('code', $code)->exists());

        return $code;
    }
}
