<?php

namespace App\Models\Arbeitsblaetter;

use App\Models\Schueler;
use App\Models\User;
use App\Models\Wochenplan\WpDatei;
use Illuminate\Database\Eloquent\Model;

/**
 * Gesetzte Fassung eines Blatts für ein Kind (oder – ohne Kind – für eine Profil-Vorlage).
 * Dateien auf dem privaten Disk `arbeitsblaetter`, Auslieferung nur über
 * `arbeitsblaetter.fassungen.datei` (Policy). Wiederverwendet, solange `profil_hash`
 * und `struktur_version` passen.
 */
class AbFassung extends Model
{
    public const AKTUELL = 'aktuell';

    public const VERALTET = 'veraltet';

    protected $table = 'ab_fassungen';

    /** Wurde die Fassung beim letzten Aufruf neu gesetzt (statt wiederverwendet)? Kein DB-Feld. */
    public bool $neuGesetzt = false;

    protected $fillable = [
        'blatt_id', 'schueler_id', 'vorlage_id', 'profil_werte', 'profil_hash', 'struktur_version',
        'pdf_pfad', 'docx_pfad', 'nextcloud_pfad', 'wp_datei_id', 'status', 'erzeugt_von',
    ];

    protected $attributes = ['status' => self::AKTUELL];

    protected $casts = [
        'profil_werte' => 'array',
        'struktur_version' => 'integer',
    ];

    public function blatt()
    {
        return $this->belongsTo(AngepasstesBlatt::class, 'blatt_id')->withTrashed();
    }

    public function schueler()
    {
        return $this->belongsTo(Schueler::class, 'schueler_id');
    }

    public function vorlage()
    {
        return $this->belongsTo(AbProfilVorlage::class, 'vorlage_id');
    }

    public function wpDatei()
    {
        return $this->belongsTo(WpDatei::class, 'wp_datei_id');
    }

    public function ersteller()
    {
        return $this->belongsTo(User::class, 'erzeugt_von');
    }

    public function istAktuell(): bool
    {
        return $this->status === self::AKTUELL;
    }

    /** Name für Anzeige: Kind oder Vorlage. */
    public function getEmpfaengerAttribute(): string
    {
        if ($this->schueler) {
            return $this->schueler->vorname.' '.$this->schueler->nachname;
        }

        return 'Vorlage „'.($this->vorlage?->name ?? 'Profil').'“';
    }
}
