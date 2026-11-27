<?php

namespace App\Models\Wochenplan;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;

/**
 * Arbeitsblatt eines Wochenplans. Zentral in der Nextcloud abgelegt
 * (`nextcloud_pfad`, relativ zum Basisordner); bis zum erfolgreichen Upload
 * – oder dauerhaft, wenn die Nextcloud-Ablage aus ist – im Zwischenspeicher
 * (Disk `wochenplan`, `lokaler_pfad`). Ausgeliefert nur über `wp.dateien.show`.
 */
class WpDatei extends Model
{
    public const LOKAL = 'lokal';          // keine Nextcloud-Ablage aktiv
    public const AUSSTEHEND = 'ausstehend'; // wartet auf Upload
    public const ABGELEGT = 'abgelegt';     // liegt in der Nextcloud
    public const FEHLER = 'fehler';         // Upload fehlgeschlagen
    public const FEHLT = 'fehlt';           // in der Nextcloud nicht mehr gefunden

    protected $table = 'wp_dateien';

    protected $fillable = [
        'wp_plan_id', 'name', 'dateiname', 'mime_type', 'groesse',
        'nextcloud_pfad', 'lokaler_pfad', 'status', 'fehler', 'hochgeladen_von', 'geprueft_at',
        'ab_fassung_id', 'ersetzt_wp_datei_id',
    ];

    protected $casts = [
        'groesse' => 'integer',
        'geprueft_at' => 'datetime',
    ];

    public function plan()
    {
        return $this->belongsTo(WpPlan::class, 'wp_plan_id');
    }

    public function uploader()
    {
        return $this->belongsTo(User::class, 'hochgeladen_von');
    }

    public function istPdf(): bool
    {
        return str_contains((string) $this->mime_type, 'pdf');
    }

    public function istBild(): bool
    {
        return str_starts_with((string) $this->mime_type, 'image/');
    }

    public function istWord(): bool
    {
        return in_array($this->mime_type, [
            'application/msword',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        ], true);
    }

    public function getGroesseTextAttribute(): string
    {
        return $this->groesse >= 1048576
            ? number_format($this->groesse / 1048576, 1, ',', '.').' MB'
            : max(1, (int) round($this->groesse / 1024)).' KB';
    }
}
