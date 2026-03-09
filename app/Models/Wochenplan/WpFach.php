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
        switch ($this->symbol_typ) {
            case 'emoji':
                // Farb-Emojis werden von DomPDF nicht gerendert → base64-SVG verwenden
                $svgData = self::emojiToSvgDataUri($this->symbol_wert, $this->symbol_farbe);
                if ($svgData) {
                    return '<img src="' . $svgData . '" width="20" height="20" alt="" '
                           . 'style="vertical-align:middle;margin-right:2px;">';
                }
                // Fallback: einfaches Zeichen ohne Farbe (nur für einfache Unicode-Symbole)
                $colorStyle = $this->symbol_farbe ? 'color:' . e($this->symbol_farbe) . ';' : '';
                return '<span style="font-family:\'NotoSymbols\',Arial,sans-serif;' . $colorStyle . '">'
                       . e($this->symbol_wert) . '</span>';
            case 'svg':
                // Inline-SVG in DomPDF-Tabellen nicht unterstützt → als base64 img einbetten
                $svgContent = $this->symbol_wert;
                if ($svgContent) {
                    $dataUri = 'data:image/svg+xml;base64,' . base64_encode($svgContent);
                    return '<img src="' . $dataUri . '" width="20" height="20" alt="" '
                           . 'style="vertical-align:middle;margin-right:2px;">';
                }
                return '';
            case 'bild':
                if (!$this->symbol_wert) return '';
                // Absoluter Dateisystempfad damit DomPDF die Datei lokal laden kann
                $absPath = storage_path('app/public/' . $this->symbol_wert);
                if (!file_exists($absPath)) return '';
                // Bild als base64 Data-URI einbetten (verhindert Pfadprobleme in DomPDF)
                $ext  = strtolower(pathinfo($absPath, PATHINFO_EXTENSION));
                $mime = match($ext) {
                    'jpg', 'jpeg' => 'image/jpeg',
                    'gif'         => 'image/gif',
                    'svg'         => 'image/svg+xml',
                    'webp'        => 'image/webp',
                    default       => 'image/png',
                };
                $b64  = base64_encode(file_get_contents($absPath));
                return '<img src="data:' . $mime . ';base64,' . $b64 . '" width="20" height="20" alt="" '
                       . 'style="vertical-align:middle;margin-right:2px;">';
            default:
                return '';
        }
    }

    /**
     * Gibt eine base64-codierte SVG-Data-URI für bekannte Emoji-Zeichen zurück.
     * Farb-Emojis können von DomPDF nicht gerendert werden – diese Methode
     * erzeugt schlichte, einfarbige Vektorgrafiken als Ersatz.
     */
    public static function emojiToSvgDataUri(string $emoji, ?string $farbe = null): ?string
    {
        $c = $farbe ?: '#444444';
        // Mapping: Emoji → SVG-Pfad-Daten (vereinfachte Symbole)
        $map = [
            // 📖 Buch (Deutsch)
            '📖' => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="24" height="24"><path fill="' . $c . '" d="M12 2C9.5 2 7 2.5 5 4v16c2-1.5 4.5-2 7-2s5 .5 7 2V4c-2-1.5-4.5-2-7-2zm0 2c2 0 4 .4 5.5 1.3V18.2c-1.6-.8-3.5-1.2-5.5-1.2V4zm-1 0v13c-2 0-3.9.4-5.5 1.2V5.3C7 4.4 9 4 11 4z"/></svg>',
            // 🔢 Zahlen (Mathe)
            '🔢' => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="24" height="24"><rect x="2" y="2" width="9" height="9" rx="1" fill="none" stroke="' . $c . '" stroke-width="1.5"/><rect x="13" y="2" width="9" height="9" rx="1" fill="none" stroke="' . $c . '" stroke-width="1.5"/><rect x="2" y="13" width="9" height="9" rx="1" fill="none" stroke="' . $c . '" stroke-width="1.5"/><rect x="13" y="13" width="9" height="9" rx="1" fill="none" stroke="' . $c . '" stroke-width="1.5"/><text x="6.5" y="10.5" text-anchor="middle" font-size="7" font-family="Arial" fill="' . $c . '">1</text><text x="17.5" y="10.5" text-anchor="middle" font-size="7" font-family="Arial" fill="' . $c . '">2</text><text x="6.5" y="21.5" text-anchor="middle" font-size="7" font-family="Arial" fill="' . $c . '">3</text><text x="17.5" y="21.5" text-anchor="middle" font-size="7" font-family="Arial" fill="' . $c . '">4</text></svg>',
            // 🌍 Globus (Sachunterricht)
            '🌍' => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="24" height="24"><circle cx="12" cy="12" r="10" fill="none" stroke="' . $c . '" stroke-width="1.5"/><ellipse cx="12" cy="12" rx="4" ry="10" fill="none" stroke="' . $c . '" stroke-width="1.2"/><line x1="2" y1="12" x2="22" y2="12" stroke="' . $c . '" stroke-width="1.2"/><path d="M4.9 7h14.2M4.9 17h14.2" fill="none" stroke="' . $c . '" stroke-width="1.2"/></svg>',
            // 🇬🇧 Flagge (Englisch) – vereinfachtes Union-Jack-Symbol
            '🇬🇧' => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="24" height="24"><rect x="2" y="4" width="20" height="16" rx="1" fill="none" stroke="' . $c . '" stroke-width="1.5"/><line x1="2" y1="4" x2="22" y2="20" stroke="' . $c . '" stroke-width="1.5"/><line x1="22" y1="4" x2="2" y2="20" stroke="' . $c . '" stroke-width="1.5"/><line x1="12" y1="4" x2="12" y2="20" stroke="' . $c . '" stroke-width="2"/><line x1="2" y1="12" x2="22" y2="12" stroke="' . $c . '" stroke-width="2"/></svg>',
            // 🎨 Palette (Kunst)
            '🎨' => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="24" height="24"><path d="M12 3C7 3 3 7 3 12s4 9 9 9c1 0 2-.5 2-1.5 0-.4-.1-.7-.3-1-.2-.3-.3-.6-.3-.9 0-1.1.9-2 2-2h2.3c2.7 0 4.8-2.1 4.8-4.7C22 7.1 17.5 3 12 3z" fill="none" stroke="' . $c . '" stroke-width="1.5"/><circle cx="8" cy="10" r="1.5" fill="' . $c . '"/><circle cx="12" cy="7" r="1.5" fill="' . $c . '"/><circle cx="16" cy="10" r="1.5" fill="' . $c . '"/></svg>',
            // 🎵 Note (Musik)
            '🎵' => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="24" height="24"><path d="M9 18V5l12-2v13" fill="none" stroke="' . $c . '" stroke-width="1.5" stroke-linecap="round"/><circle cx="6" cy="18" r="3" fill="none" stroke="' . $c . '" stroke-width="1.5"/><circle cx="18" cy="16" r="3" fill="none" stroke="' . $c . '" stroke-width="1.5"/></svg>',
            // ⚽ Fußball (Sport)
            '⚽' => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="24" height="24"><circle cx="12" cy="12" r="10" fill="none" stroke="' . $c . '" stroke-width="1.5"/><polygon points="12,5 15,9 13,13 11,13 9,9" fill="none" stroke="' . $c . '" stroke-width="1"/><line x1="12" y1="5" x2="12" y2="2" stroke="' . $c . '" stroke-width="1"/><line x1="15" y1="9" x2="19" y2="7" stroke="' . $c . '" stroke-width="1"/><line x1="13" y1="13" x2="16" y2="17" stroke="' . $c . '" stroke-width="1"/><line x1="11" y1="13" x2="8" y2="17" stroke="' . $c . '" stroke-width="1"/><line x1="9" y1="9" x2="5" y2="7" stroke="' . $c . '" stroke-width="1"/></svg>',
            // 🕊️ Taube (Ethik/Religion)
            '🕊️' => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="24" height="24"><path d="M16 8c0-2.2-1.8-4-4-4-1.5 0-2.8.8-3.5 2H5l-3 3 3 1 1 3 3-3c.5.1 1 .2 1.5.2C13.6 11 16 9.8 16 8z" fill="none" stroke="' . $c . '" stroke-width="1.5"/><path d="M8.5 11L5 18l4-2 2 4 1-5" fill="none" stroke="' . $c . '" stroke-width="1.5"/></svg>',
            // 🔨 Hammer (Werken)
            '🔨' => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="24" height="24"><rect x="11" y="3" width="8" height="5" rx="1" transform="rotate(45 15 5.5)" fill="' . $c . '"/><line x1="10" y1="10" x2="3" y2="20" stroke="' . $c . '" stroke-width="3" stroke-linecap="round"/></svg>',
            // 📐 Dreieck-Lineal
            '📐' => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="24" height="24"><polygon points="2,22 22,22 2,2" fill="none" stroke="' . $c . '" stroke-width="1.5"/><line x1="2" y1="18" x2="6" y2="18" stroke="' . $c . '" stroke-width="1"/><line x1="2" y1="14" x2="6" y2="14" stroke="' . $c . '" stroke-width="1"/><line x1="2" y1="10" x2="5" y2="10" stroke="' . $c . '" stroke-width="1"/><line x1="7" y1="22" x2="7" y2="18" stroke="' . $c . '" stroke-width="1"/><line x1="12" y1="22" x2="12" y2="18" stroke="' . $c . '" stroke-width="1"/><line x1="17" y1="22" x2="17" y2="18" stroke="' . $c . '" stroke-width="1"/></svg>',
            // 📝 Notizblock / Bleistift
            '📝' => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="24" height="24"><path d="M14 3l7 7-10 10H4v-7L14 3z" fill="none" stroke="' . $c . '" stroke-width="1.5" stroke-linejoin="round"/><line x1="12" y1="5" x2="19" y2="12" stroke="' . $c . '" stroke-width="1.5"/></svg>',
            // ✏️ Bleistift
            '✏️' => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="24" height="24"><path d="M3 17.25V21h3.75L17.81 9.94l-3.75-3.75L3 17.25zm17.71-10.21a1 1 0 0 0 0-1.41l-2.34-2.34a1 1 0 0 0-1.41 0l-1.83 1.83 3.75 3.75 1.83-1.83z" fill="' . $c . '"/></svg>',
            // 🖊️ Stift
            '🖊️' => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="24" height="24"><path d="M3 17.25V21h3.75L17.81 9.94l-3.75-3.75L3 17.25zm17.71-10.21a1 1 0 0 0 0-1.41l-2.34-2.34a1 1 0 0 0-1.41 0l-1.83 1.83 3.75 3.75 1.83-1.83z" fill="' . $c . '"/></svg>',
            // 🧮 Abakus (Mathe-Variante)
            '🧮' => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="24" height="24"><rect x="2" y="2" width="20" height="20" rx="2" fill="none" stroke="' . $c . '" stroke-width="1.5"/><line x1="2" y1="8" x2="22" y2="8" stroke="' . $c . '" stroke-width="1"/><line x1="2" y1="14" x2="22" y2="14" stroke="' . $c . '" stroke-width="1"/><circle cx="7" cy="5" r="1.5" fill="' . $c . '"/><circle cx="12" cy="5" r="1.5" fill="' . $c . '"/><circle cx="17" cy="5" r="1.5" fill="' . $c . '"/><circle cx="7" cy="11" r="1.5" fill="' . $c . '"/><circle cx="12" cy="11" r="1.5" fill="' . $c . '"/><circle cx="7" cy="17" r="1.5" fill="' . $c . '"/></svg>',
            // 🔬 Mikroskop (Naturwissenschaft)
            '🔬' => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="24" height="24"><line x1="12" y1="2" x2="12" y2="10" stroke="' . $c . '" stroke-width="2" stroke-linecap="round"/><circle cx="12" cy="12" r="3" fill="none" stroke="' . $c . '" stroke-width="1.5"/><line x1="8" y1="22" x2="16" y2="22" stroke="' . $c . '" stroke-width="2" stroke-linecap="round"/><line x1="12" y1="15" x2="12" y2="22" stroke="' . $c . '" stroke-width="1.5"/><line x1="6" y1="22" x2="18" y2="22" stroke="' . $c . '" stroke-width="2" stroke-linecap="round"/></svg>',
            // 🧪 Reagenzglas
            '🧪' => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="24" height="24"><path d="M8 2v12l-4 6h16l-4-6V2" fill="none" stroke="' . $c . '" stroke-width="1.5" stroke-linejoin="round"/><line x1="8" y1="2" x2="16" y2="2" stroke="' . $c . '" stroke-width="2"/><line x1="8" y1="13" x2="16" y2="13" stroke="' . $c . '" stroke-width="1" stroke-dasharray="2,2"/></svg>',
            // 🌱 Pflanze (Biologie/Natur)
            '🌱' => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="24" height="24"><path d="M12 22V12M12 12C12 7 7 4 3 5c0 4 3 7 9 7M12 12C12 7 17 4 21 5c0 4-3 7-9 7" fill="none" stroke="' . $c . '" stroke-width="1.5" stroke-linecap="round"/></svg>',
            // 🏃 Laufen / Sport-Variante
            '🏃' => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="24" height="24"><circle cx="15" cy="4" r="2" fill="' . $c . '"/><path d="M14 7l-3 4 3 4-4 6M14 7l4 2 3-2M11 11H7" fill="none" stroke="' . $c . '" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/></svg>',
            // 🎭 Theater
            '🎭' => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="24" height="24"><circle cx="9" cy="10" r="6" fill="none" stroke="' . $c . '" stroke-width="1.5"/><circle cx="15" cy="12" r="6" fill="none" stroke="' . $c . '" stroke-width="1.5"/><path d="M7 11 Q9 13 11 11" fill="none" stroke="' . $c . '" stroke-width="1.5"/><path d="M13 14 Q15 12 17 14" fill="none" stroke="' . $c . '" stroke-width="1.5"/></svg>',
            // 💻 Computer / Informatik
            '💻' => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="24" height="24"><rect x="2" y="4" width="20" height="14" rx="2" fill="none" stroke="' . $c . '" stroke-width="1.5"/><line x1="2" y1="20" x2="22" y2="20" stroke="' . $c . '" stroke-width="2" stroke-linecap="round"/><line x1="8" y1="20" x2="16" y2="20" stroke="' . $c . '" stroke-width="3" stroke-linecap="round"/></svg>',
            // 🌐 Web / Geografie
            '🌐' => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="24" height="24"><circle cx="12" cy="12" r="10" fill="none" stroke="' . $c . '" stroke-width="1.5"/><ellipse cx="12" cy="12" rx="4" ry="10" fill="none" stroke="' . $c . '" stroke-width="1.2"/><line x1="2" y1="12" x2="22" y2="12" stroke="' . $c . '" stroke-width="1.2"/></svg>',
            // 📚 Bücherstapel
            '📚' => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="24" height="24"><rect x="2" y="16" width="14" height="5" rx="1" fill="none" stroke="' . $c . '" stroke-width="1.5"/><rect x="4" y="10" width="14" height="5" rx="1" fill="none" stroke="' . $c . '" stroke-width="1.5"/><rect x="6" y="4" width="14" height="5" rx="1" fill="none" stroke="' . $c . '" stroke-width="1.5"/></svg>',
            // 🎤 Mikrofon (Sprechen / Kommunikation)
            '🎤' => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="24" height="24"><rect x="9" y="2" width="6" height="10" rx="3" fill="none" stroke="' . $c . '" stroke-width="1.5"/><path d="M5 11a7 7 0 0 0 14 0" fill="none" stroke="' . $c . '" stroke-width="1.5"/><line x1="12" y1="18" x2="12" y2="22" stroke="' . $c . '" stroke-width="1.5"/><line x1="8" y1="22" x2="16" y2="22" stroke="' . $c . '" stroke-width="1.5"/></svg>',
            // ➕ Plus (Mathe-Variante)
            '➕' => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="24" height="24"><line x1="12" y1="4" x2="12" y2="20" stroke="' . $c . '" stroke-width="3" stroke-linecap="round"/><line x1="4" y1="12" x2="20" y2="12" stroke="' . $c . '" stroke-width="3" stroke-linecap="round"/></svg>',
            // ⭐ Stern
            '⭐' => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="24" height="24"><polygon points="12,2 15.09,8.26 22,9.27 17,14.14 18.18,21.02 12,17.77 5.82,21.02 7,14.14 2,9.27 8.91,8.26" fill="none" stroke="' . $c . '" stroke-width="1.5" stroke-linejoin="round"/></svg>',
            // ❤️ Herz
            '❤️' => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="24" height="24"><path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z" fill="none" stroke="' . $c . '" stroke-width="1.5"/></svg>',
            // 🏠 Haus
            '🏠' => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="24" height="24"><path d="M3 12L12 3l9 9" fill="none" stroke="' . $c . '" stroke-width="1.5"/><path d="M5 10v9a1 1 0 0 0 1 1h4v-5h4v5h4a1 1 0 0 0 1-1v-9" fill="none" stroke="' . $c . '" stroke-width="1.5"/></svg>',
            // 🌟 Glitzer-Stern
            '🌟' => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="24" height="24"><polygon points="12,2 15.09,8.26 22,9.27 17,14.14 18.18,21.02 12,17.77 5.82,21.02 7,14.14 2,9.27 8.91,8.26" fill="' . $c . '"/></svg>',
        ];

        // Normalisierung: Variation Selector (\uFE0F) entfernen für Vergleich
        $emojiNorm = rtrim($emoji, "\u{FE0F}");
        if (isset($map[$emoji])) {
            return 'data:image/svg+xml;base64,' . base64_encode($map[$emoji]);
        }
        if (isset($map[$emojiNorm])) {
            return 'data:image/svg+xml;base64,' . base64_encode($map[$emojiNorm]);
        }
        return null;
    }
}
