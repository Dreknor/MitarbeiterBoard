<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;

/**
 * Elterngespräch in der Schülerakte: geplant (inkl. Vorbereitung und Protokollentwurf),
 * abgeschlossen (Protokoll festgeschrieben) oder abgesagt.
 *
 * Inhalte sind verschlüsselt gespeichert (wie der Inhalt von Tagebucheinträgen).
 * Vertrauliche Gespräche sehen nur Ersteller, eingetragene Kolleg:innen und Personen mit
 * "view confidential diary entries" – anders als vertrauliche Tagebucheinträge in der Schülerakte.
 */
class PaedDiaryElterngespraech extends Model
{
    public const STATUS_GEPLANT = 'geplant';
    public const STATUS_ABGESCHLOSSEN = 'abgeschlossen';
    public const STATUS_ABGESAGT = 'abgesagt';

    public const STATUS = [
        self::STATUS_GEPLANT => 'Geplant',
        self::STATUS_ABGESCHLOSSEN => 'Abgeschlossen',
        self::STATUS_ABGESAGT => 'Abgesagt',
    ];

    public const FORMEN = [
        'vor_ort' => 'Vor Ort',
        'telefon' => 'Telefon',
        'video' => 'Video',
    ];

    protected $table = 'paed_diary_elterngespraeche';

    protected $fillable = [
        'schueler_id', 'klasse_id', 'user_id', 'paed_diary_appointment_id',
        'datum', 'beginn', 'ende', 'ort', 'form', 'status', 'vertraulich', 'schueler_anwesend',
        'eltern_teilnehmende', 'weitere_teilnehmende', 'anlass', 'gespraechspunkte',
        'vorbereitung_notizen', 'vorbereitung_im_protokoll', 'verlauf', 'naechster_termin',
        'abgeschlossen_at', 'abgeschlossen_von', 'abgesagt_at', 'aktualisiert_von', 'version',
    ];

    protected $casts = [
        'datum' => 'date',
        'naechster_termin' => 'date',
        'vertraulich' => 'boolean',
        'schueler_anwesend' => 'boolean',
        'vorbereitung_im_protokoll' => 'boolean',
        'abgeschlossen_at' => 'datetime',
        'abgesagt_at' => 'datetime',
        'version' => 'integer',
        'eltern_teilnehmende' => 'encrypted',
        'weitere_teilnehmende' => 'encrypted',
        'anlass' => 'encrypted',
        'gespraechspunkte' => 'encrypted:array',
        'vorbereitung_notizen' => 'encrypted',
        'verlauf' => 'encrypted',
    ];

    public function schueler()
    {
        return $this->belongsTo(Schueler::class, 'schueler_id');
    }

    public function klasse()
    {
        return $this->belongsTo(Klasse::class, 'klasse_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function abgeschlossenVon()
    {
        return $this->belongsTo(User::class, 'abgeschlossen_von');
    }

    public function aktualisiertVon()
    {
        return $this->belongsTo(User::class, 'aktualisiert_von');
    }

    public function appointment()
    {
        return $this->belongsTo(PaedDiaryAppointment::class, 'paed_diary_appointment_id');
    }

    /** Eingetragene Kolleg:innen */
    public function teilnehmer()
    {
        return $this->belongsToMany(User::class, 'paed_diary_elterngespraech_user', 'elterngespraech_id', 'user_id');
    }

    public function vereinbarungen()
    {
        return $this->hasMany(PaedDiaryElterngespraechVereinbarung::class, 'elterngespraech_id')
            ->orderBy('sort_order')
            ->orderBy('id');
    }

    /**
     * Gespräche, die der Benutzer sehen darf (der Zugriff auf den Schüler wird separat geprüft).
     */
    public function scopeSichtbarFuer($query, User $user)
    {
        if ($user->canViewConfidentialDiaryEntries()) {
            return $query;
        }

        return $query->where(function ($q) use ($user) {
            $q->where($this->qualifyColumn('vertraulich'), false)
                ->orWhere($this->qualifyColumn('user_id'), $user->id)
                ->orWhereHas('teilnehmer', fn ($t) => $t->where('users.id', $user->id));
        });
    }

    public function istSichtbarFuer(User $user): bool
    {
        return !$this->vertraulich
            || (int) $this->user_id === (int) $user->id
            || $user->canViewConfidentialDiaryEntries()
            || $this->teilnehmer()->where('users.id', $user->id)->exists();
    }

    public function istGeplant(): bool
    {
        return $this->status === self::STATUS_GEPLANT;
    }

    /** Geplant und Datum liegt in der Vergangenheit: Protokoll fehlt noch. */
    public function protokollOffen(): bool
    {
        return $this->istGeplant() && $this->datum && $this->datum->lt(Carbon::today());
    }
}
