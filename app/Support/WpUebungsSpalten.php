<?php

namespace App\Support;

use App\Models\Wochenplan\WpFach;
use Illuminate\Support\Collection;

/**
 * Tägliche Übungen für das PDF nach der Papiervorlage: je Kürzel eine Spalte im Wochenraster
 * (✍ = Deutsch/Schreiben, 5+5 = Mathe). Übungen ohne Kürzel erhalten es aus dem Text,
 * Übungen mit gleichem Kürzel (z. B. „Woche 1: …“ / „Woche 2: …“) werden zu einer Spalte.
 */
class WpUebungsSpalten
{
    public const SCHREIBEN = '✍';

    public const RECHNEN = '5+5';

    private const MATHE = '/mathe|matherad|rechne|rechen|1\s*[x×]\s*1|1\s*:\s*1|plus|minus|zahlen|addition|subtraktion|multiplikation|division|nussknacker|5\s*\+\s*5/iu';

    /**
     * @param  Collection<int, object>  $uebungen  Modelle oder Objekte mit `aufgabe` und `kuerzel`
     * @return Collection<int, object{kuerzel:string, aufgabe:string}>
     */
    public static function gruppieren(Collection $uebungen): Collection
    {
        $gruppen = [];
        foreach ($uebungen as $u) {
            $k = trim((string) $u->kuerzel) !== '' ? trim((string) $u->kuerzel) : self::kuerzelAus((string) $u->aufgabe);
            $gruppen[$k][] = trim((string) $u->aufgabe);
        }

        // Reihenfolge wie in der Vorlage: Schreiben, Rechnen, dann alles Weitere
        uksort($gruppen, fn ($a, $b) => self::rang($a) <=> self::rang($b));

        return collect($gruppen)->map(fn ($texte, $k) => (object) [
            'kuerzel' => (string) $k,
            'aufgabe' => implode("\n", $texte),
        ])->values();
    }

    public static function kuerzelAus(string $text): string
    {
        return preg_match(self::MATHE, $text) ? self::RECHNEN : self::SCHREIBEN;
    }

    private static function rang(string|int $kuerzel): int
    {
        return match ((string) $kuerzel) {
            self::SCHREIBEN => 0,
            self::RECHNEN => 1,
            default => 2,
        };
    }

    /** HTML für DomPDF: Stift als Grafik, „5+5“ mit gepunkteter Unterstreichung (zehn Punkte wie in der Vorlage). */
    public static function html(string $kuerzel, bool $kopf = false): string
    {
        if ($kuerzel === self::RECHNEN) {
            return '<span style="white-space:nowrap;">5+5</span><br><img src="'.self::punkte(10, 30, 3).'" width="22" height="3" alt="" style="vertical-align:top;">';
        }
        if ($kuerzel === self::SCHREIBEN) {
            return '<img src="'.self::stift().'" width="'.($kopf ? 14 : 17).'" height="'.($kopf ? 14 : 17).'" alt="✍" style="vertical-align:middle;">';
        }

        return WpFach::zeichenAlsPdfHtml($kuerzel, null, 14);
    }

    /** Zwei Reihen à fünf Punkte („Rechnen 5+5“ im Fach Mathematik). */
    public static function punktReihen(int $breite = 76): string
    {
        $svg = '<svg xmlns="http://www.w3.org/2000/svg" width="76" height="30" viewBox="0 0 76 30">';
        foreach ([0, 1] as $reihe) {
            for ($i = 0; $i < 5; $i++) {
                $svg .= '<circle cx="'.(6 + $i * 15 + $reihe * 3).'" cy="'.(7 + $reihe * 15).'" r="4.8" fill="#000"/>';
            }
        }
        $svg .= '</svg>';

        return '<img src="data:image/svg+xml;base64,'.base64_encode($svg).'" width="'.$breite.'" height="'.round($breite * 30 / 76).'" alt="">';
    }

    /** Schwarzer Stift (wie das Symbol bei „Sprache“ in der Vorlage). */
    public static function stift(): string
    {
        $svg = '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24">'
            .'<path d="M3 4.5 L5.5 2 L19 15.5 L21.5 21.5 L15.5 19 Z" fill="#000"/>'
            .'<path d="M17.2 17.3 L19 15.5 L21.5 21.5 Z" fill="#fff"/>'
            .'<path d="M19.8 20.2 L21.5 21.5 L20.6 19.4 Z" fill="#000"/>'
            .'</svg>';

        return 'data:image/svg+xml;base64,'.base64_encode($svg);
    }

    private static function punkte(int $anzahl, int $breite, int $hoehe): string
    {
        $svg = '<svg xmlns="http://www.w3.org/2000/svg" width="'.$breite.'" height="'.$hoehe.'" viewBox="0 0 '.$breite.' '.$hoehe.'">';
        $abstand = $breite / ($anzahl + 1);
        for ($i = 1; $i <= $anzahl; $i++) {
            // kleine Lücke in der Mitte: zwei Gruppen à fünf
            $x = $i * $abstand + ($i > $anzahl / 2 ? 1 : -1);
            $svg .= '<circle cx="'.round($x, 2).'" cy="'.($hoehe / 2).'" r="0.9" fill="#000"/>';
        }
        $svg .= '</svg>';

        return 'data:image/svg+xml;base64,'.base64_encode($svg);
    }
}
