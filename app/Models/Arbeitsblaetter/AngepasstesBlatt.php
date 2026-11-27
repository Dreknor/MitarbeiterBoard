<?php

namespace App\Models\Arbeitsblaetter;

use App\Models\User;
use App\Models\Wochenplan\WpDatei;
use App\Models\Wochenplan\WpFach;
use App\Support\AbStruktur;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

/**
 * Erfasstes Arbeitsblatt als Bausteine ({@see AbStruktur}). Logik nur in
 * {@see \App\Services\Arbeitsblaetter\AngepasstesBlattService}, Zugriff über
 * {@see \App\Policies\AngepasstesBlattPolicy}.
 *
 * Media Library (privater Disk `arbeitsblaetter`): `originale` = Seitenbilder bzw. Original-PDF,
 * `abbildungen` = ausgeschnittene Grafiken (Bausteine verweisen per Media-ID).
 */
class AngepasstesBlatt extends Model implements HasMedia
{
    use InteractsWithMedia, SoftDeletes;

    public const ORIGINALE = 'originale';

    public const ABBILDUNGEN = 'abbildungen';

    public const ENTWURF = 'entwurf';
    public const ERKENNUNG = 'erkennung';
    public const ZU_PRUEFEN = 'zu_pruefen';
    public const FREIGEGEBEN = 'freigegeben';
    public const FEHLER = 'fehler';

    public const STATUS = [
        self::ENTWURF => 'Entwurf',
        self::ERKENNUNG => 'Wird erkannt …',
        self::ZU_PRUEFEN => 'Zur Prüfung',
        self::FREIGEGEBEN => 'Freigegeben',
        self::FEHLER => 'Fehler',
    ];

    public const QUELLEN = ['kamera' => 'Kamera', 'upload' => 'Upload', 'wochenplan' => 'Wochenplan', 'manuell' => 'Manuell'];

    protected $table = 'ab_blaetter';

    protected $fillable = [
        'titel', 'wp_fach_id', 'klassenstufe', 'quelle', 'quelle_wp_datei_id',
        'status', 'struktur', 'version', 'erstellt_von', 'geprueft_von', 'freigegeben_at',
        'ki_anbieter', 'ki_modell', 'ki_seiten', 'textebene', 'fehler', 'erinnert_at',
    ];

    protected $casts = [
        'struktur' => 'array',
        'version' => 'integer',
        'klassenstufe' => 'integer',
        'ki_seiten' => 'integer',
        'freigegeben_at' => 'datetime',
        'erinnert_at' => 'datetime',
    ];

    protected $hidden = ['textebene'];

    protected $attributes = [
        'version' => 1,
        'status' => self::ENTWURF,
        'quelle' => 'upload',
    ];

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection(self::ORIGINALE)->useDisk('arbeitsblaetter');
        $this->addMediaCollection(self::ABBILDUNGEN)->useDisk('arbeitsblaetter');
    }

    public function ersteller()
    {
        return $this->belongsTo(User::class, 'erstellt_von');
    }

    public function pruefer()
    {
        return $this->belongsTo(User::class, 'geprueft_von');
    }

    public function fach()
    {
        return $this->belongsTo(WpFach::class, 'wp_fach_id');
    }

    public function fassungen()
    {
        return $this->hasMany(AbFassung::class, 'blatt_id');
    }

    public function quelleWpDatei()
    {
        return $this->belongsTo(WpDatei::class, 'quelle_wp_datei_id');
    }

    /** Immer eine gültige Struktur. */
    public function strukturGueltig(): array
    {
        return AbStruktur::normalisieren($this->struktur ?? AbStruktur::leer());
    }

    public function istFreigegeben(): bool
    {
        return $this->status === self::FREIGEGEBEN;
    }

    public function getStatusTextAttribute(): string
    {
        return self::STATUS[$this->status] ?? $this->status;
    }
}
