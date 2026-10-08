<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Einstellungen der Tagesübersicht („Dein Tag“) einer Person.
 * Fehlt der Datensatz, gelten die Standardwerte aus standard().
 */
class TagesvorschauEinstellung extends Model
{
    protected $table = 'tagesvorschau_einstellungen';

    protected $fillable = [
        'user_id', 'per_mail', 'per_push', 'zeitpunkt', 'uhrzeit',
        'bereiche', 'kalender_ids', 'eingeladene_termine', 'zuletzt_fuer_tag',
    ];

    protected $casts = [
        'per_mail'            => 'boolean',
        'per_push'            => 'boolean',
        'bereiche'            => 'array',
        'kalender_ids'        => 'array',
        'eingeladene_termine' => 'boolean',
        'zuletzt_fuer_tag'    => 'date',
    ];

    public const MORGENS = 'morgens';
    public const VORABEND = 'vorabend';

    /**
     * Standardwerte (ungecastet), z. B. für create().
     */
    public static function standardWerte(): array
    {
        return [
            'per_mail'            => true,
            'per_push'            => false,
            'zeitpunkt'           => self::MORGENS,
            'uhrzeit'             => config('benachrichtigungen.tagesvorschau.standard_uhrzeit', '06:30').':00',
            'bereiche'            => null,
            'kalender_ids'        => [],
            'eingeladene_termine' => true,
        ];
    }

    /**
     * Ungespeicherte Instanz mit Standardwerten.
     */
    public static function standard(User $user): self
    {
        return new self(['user_id' => $user->id] + self::standardWerte());
    }

    /**
     * Uhrzeit als "HH:MM".
     */
    public function uhrzeitKurz(): string
    {
        return substr((string) $this->uhrzeit, 0, 5);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
