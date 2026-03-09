<?php
namespace App\Models\Wochenplan;
use Illuminate\Database\Eloquent\Model;
class WpFach extends Model
{
    protected $table = 'wp_faecher';
    protected $fillable = ['name', 'sort_order', 'is_default', 'symbol_typ', 'symbol_wert', 'symbol_farbe'];
    protected $casts = ['is_default' => 'boolean', 'sort_order' => 'integer'];
    public function scopeDefault($query) { return $query->where('is_default', true); }
    public function scopeOrdered($query) { return $query->orderBy('sort_order')->orderBy('name'); }
    public function planFaecher() { return $this->hasMany(WpPlanFach::class, 'wp_fach_id'); }

    /**
     * Gibt fertig gerenderten HTML-String für das Symbol zurück.
     */
    public function getSymbolHtmlAttribute(): string
    {
        if (!$this->symbol_typ || $this->symbol_typ === 'keine') {
            return '';
        }
        $colorStyle = $this->symbol_farbe ? 'color:' . e($this->symbol_farbe) . ';' : '';
        return match ($this->symbol_typ) {
            'emoji' => '<span class="wp-fach-symbol wp-fach-symbol--emoji" style="' . $colorStyle . '">'
                       . e($this->symbol_wert) . '</span>',
            'svg'   => '<span class="wp-fach-symbol wp-fach-symbol--svg">'
                       . $this->symbol_wert . '</span>',
            'bild'  => '<img class="wp-fach-symbol wp-fach-symbol--bild" '
                       . 'src="' . e(\Illuminate\Support\Facades\Storage::url($this->symbol_wert)) . '" alt="" aria-hidden="true" '
                       . 'style="width:1.5em;height:1.5em;object-fit:contain;vertical-align:middle;display:inline-block;">',
            default => '',
        };
    }

    /**
     * Symbol-HTML optimiert für DomPDF (keine Farb-Emojis, NotoSymbols-Font für Emoji, absoluter Pfad für Bilder).
     */
    public function getPdfSymbolHtmlAttribute(): string
    {
        if (!$this->symbol_typ || $this->symbol_typ === 'keine') {
            return '';
        }
        $colorStyle = $this->symbol_farbe ? 'color:' . e($this->symbol_farbe) . ';' : '';
        switch ($this->symbol_typ) {
            case 'emoji':
                return '<span class="wp-fach-symbol wp-fach-symbol--emoji" '
                       . 'style="font-family:\'NotoSymbols\',Arial,sans-serif;' . $colorStyle . '">'
                       . e($this->symbol_wert) . '</span>';
            case 'svg':
                return '<span class="wp-fach-symbol wp-fach-symbol--svg">'
                       . $this->symbol_wert . '</span>';
            case 'bild':
                if (!$this->symbol_wert) return '';
                // Absoluter Dateisystempfad damit DomPDF die Datei lokal laden kann
                $absPath = storage_path('app/public/' . $this->symbol_wert);
                if (!file_exists($absPath)) return '';
                return '<img class="wp-fach-symbol wp-fach-symbol--bild" '
                       . 'src="' . e($absPath) . '" alt="" aria-hidden="true" '
                       . 'style="width:1.2em;height:1.2em;object-fit:contain;vertical-align:middle;">';
            default:
                return '';
        }
    }
}
