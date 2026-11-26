<?php

namespace App\Models\Monitoring;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Ein überwachter Dienst.
 *
 * Typen:
 *  - quelle    – der Heartbeat-Absender selbst (z. B. Home Assistant); „fehler“ = Heartbeat bleibt aus
 *  - heartbeat – Status wird von der Quelle gemeldet (VPN, RDP, interne Server …)
 *  - http      – Webserver, den die Anwendung selbst per HTTP(S) prüft
 *
 * Alle Statusänderungen laufen über App\Services\Monitoring\MonitoringService.
 */
class MonitoringDienst extends Model
{
    public const TYP_QUELLE = 'quelle';
    public const TYP_HEARTBEAT = 'heartbeat';
    public const TYP_HTTP = 'http';

    public const STATUS_OK = 'ok';
    public const STATUS_FEHLER = 'fehler';
    public const STATUS_UNBEKANNT = 'unbekannt';

    /** Schlüssel des Heartbeat-Absenders */
    public const QUELLE = 'heartbeat';

    protected $table = 'monitoring_dienste';

    protected $fillable = [
        'schluessel', 'name', 'typ', 'url', 'abhaengig_von_id', 'aktiv', 'sortierung',
        'status', 'status_seit', 'letzte_pruefung', 'meldung', 'latenz_ms', 'fehlversuche', 'gemeldet',
    ];

    protected $casts = [
        'aktiv' => 'boolean',
        'gemeldet' => 'boolean',
        'sortierung' => 'integer',
        'latenz_ms' => 'integer',
        'fehlversuche' => 'integer',
        'status_seit' => 'datetime',
        'letzte_pruefung' => 'datetime',
    ];

    public function abhaengigVon(): BelongsTo
    {
        return $this->belongsTo(self::class, 'abhaengig_von_id');
    }

    public function ereignisse(): HasMany
    {
        return $this->hasMany(MonitoringEreignis::class, 'dienst_id');
    }

    public function istQuelle(): bool
    {
        return $this->typ === self::TYP_QUELLE;
    }

    public function typLabel(): string
    {
        return match ($this->typ) {
            self::TYP_QUELLE => 'Heartbeat-Absender',
            self::TYP_HEARTBEAT => 'Heartbeat',
            self::TYP_HTTP => 'Webserver',
            default => $this->typ,
        };
    }
}
