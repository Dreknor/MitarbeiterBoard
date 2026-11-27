<?php

namespace App\Support;

/**
 * Werte eines Sehprofils (Angepasste Arbeitsblätter) mit Standard und Grenzen.
 *
 * Ein Profil ist immer vollständig: {@see normalisieren()} ergänzt fehlende Werte mit
 * dem Standard, begrenzt Zahlen und verwirft unbekannte Felder. Das Layout eines
 * angepassten Blatts entsteht ausschließlich aus diesen Werten – Schrift und Größe
 * werden nie von Hand am Blatt gewählt.
 *
 * Keine Diagnose, kein Visus: nur Darstellungswerte.
 */
class AbProfil
{
    public const SCHRIFTEN = [
        'atkinson' => 'Atkinson Hyperlegible',
        'dejavu' => 'DejaVu Sans',
        'liberation' => 'Liberation Sans',
        'arial' => 'Arial',
        'verdana' => 'Verdana',
    ];

    public const FARBSCHEMATA = [
        'normal' => ['label' => 'Schwarz auf Weiß', 'text' => '#000000', 'hintergrund' => '#ffffff', 'linie' => '#000000'],
        'invertiert' => ['label' => 'Weiß auf Schwarz (invertiert)', 'text' => '#ffffff', 'hintergrund' => '#000000', 'linie' => '#ffffff'],
        'gelb' => ['label' => 'Schwarz auf Gelb', 'text' => '#000000', 'hintergrund' => '#fff36b', 'linie' => '#000000'],
        'creme' => ['label' => 'Schwarz auf Creme', 'text' => '#000000', 'hintergrund' => '#fbf3dc', 'linie' => '#000000'],
    ];

    public const FORMATE = ['A4', 'A3'];

    /**
     * Felder: Typ, Standard, Grenzen, Bezeichnung, Gruppe.
     *
     * @var array<string, array{typ: string, standard: mixed, min?: float, max?: float, schritt?: float, werte?: array, label: string, gruppe: string, einheit?: string}>
     */
    public const FELDER = [
        'schrift' => ['typ' => 'auswahl', 'standard' => 'atkinson', 'werte' => ['atkinson', 'verdana', 'arial', 'dejavu', 'liberation'], 'label' => 'Schrift', 'gruppe' => 'Schrift'],
        'groesse' => ['typ' => 'zahl', 'standard' => 20, 'min' => 14, 'max' => 48, 'schritt' => 1, 'label' => 'Schriftgröße', 'gruppe' => 'Schrift', 'einheit' => 'pt'],
        'fett' => ['typ' => 'schalter', 'standard' => false, 'label' => 'Fett', 'gruppe' => 'Schrift'],

        'zeilenabstand' => ['typ' => 'zahl', 'standard' => 1.5, 'min' => 1.2, 'max' => 2.5, 'schritt' => 0.1, 'label' => 'Zeilenabstand', 'gruppe' => 'Abstände', 'einheit' => '×'],
        'buchstabenabstand' => ['typ' => 'zahl', 'standard' => 0.05, 'min' => 0, 'max' => 0.2, 'schritt' => 0.01, 'label' => 'Buchstabenabstand', 'gruppe' => 'Abstände', 'einheit' => 'em'],

        'farbschema' => ['typ' => 'auswahl', 'standard' => 'normal', 'werte' => ['normal', 'invertiert', 'gelb', 'creme'], 'label' => 'Farbschema', 'gruppe' => 'Farbe'],
        'bild_kontrast' => ['typ' => 'schalter', 'standard' => false, 'label' => 'Abbildungen kontrastreich (Schwarz-Weiß)', 'gruppe' => 'Farbe'],

        'linienstaerke' => ['typ' => 'zahl', 'standard' => 1.0, 'min' => 0.5, 'max' => 3, 'schritt' => 0.25, 'label' => 'Linienstärke', 'gruppe' => 'Schreiben', 'einheit' => 'pt'],
        'linienabstand' => ['typ' => 'zahl', 'standard' => 12, 'min' => 8, 'max' => 20, 'schritt' => 1, 'label' => 'Abstand der Schreiblinien', 'gruppe' => 'Schreiben', 'einheit' => 'mm'],
        'luecke_min' => ['typ' => 'zahl', 'standard' => 30, 'min' => 10, 'max' => 80, 'schritt' => 5, 'label' => 'Lücken-Mindestbreite', 'gruppe' => 'Schreiben', 'einheit' => 'mm'],
        'ankreuz_groesse' => ['typ' => 'zahl', 'standard' => 7, 'min' => 4, 'max' => 15, 'schritt' => 1, 'label' => 'Ankreuzfeld-Größe', 'gruppe' => 'Schreiben', 'einheit' => 'mm'],

        'bildfaktor' => ['typ' => 'zahl', 'standard' => 1.5, 'min' => 1, 'max' => 3, 'schritt' => 0.25, 'label' => 'Abbildungen vergrößern', 'gruppe' => 'Seite', 'einheit' => '×'],
        'format' => ['typ' => 'auswahl', 'standard' => 'A4', 'werte' => ['A4', 'A3'], 'label' => 'Papierformat', 'gruppe' => 'Seite'],
        'rand' => ['typ' => 'zahl', 'standard' => 15, 'min' => 8, 'max' => 30, 'schritt' => 1, 'label' => 'Seitenrand', 'gruppe' => 'Seite', 'einheit' => 'mm'],
        'aufgabe_nicht_teilen' => ['typ' => 'schalter', 'standard' => true, 'label' => 'Aufgabe nie auf zwei Seiten teilen', 'gruppe' => 'Seite'],
    ];

    /** Mitgelieferte Profil-Vorlagen (Migration). */
    public const STANDARD_VORLAGEN = [
        ['Groß', ['groesse' => 20, 'zeilenabstand' => 1.5]],
        ['Sehr groß', ['groesse' => 28, 'zeilenabstand' => 1.6, 'fett' => true, 'linienstaerke' => 1.5, 'linienabstand' => 15, 'luecke_min' => 40, 'ankreuz_groesse' => 10, 'bildfaktor' => 2]],
        ['Kontrast (invertiert)', ['groesse' => 24, 'farbschema' => 'invertiert', 'bild_kontrast' => true, 'fett' => true, 'linienstaerke' => 2]],
        ['A3 groß', ['groesse' => 32, 'format' => 'A3', 'zeilenabstand' => 1.6, 'linienabstand' => 16, 'luecke_min' => 50, 'ankreuz_groesse' => 12, 'bildfaktor' => 2.5, 'rand' => 20]],
    ];

    /** @return array<string, mixed> */
    public static function standard(): array
    {
        return array_map(fn ($f) => $f['standard'], self::FELDER);
    }

    /** Vollständige, begrenzte Werte. Unbekannte Felder fallen weg. */
    public static function normalisieren(?array $werte, ?array $basis = null): array
    {
        $basis = $basis ?? self::standard();
        $ergebnis = [];
        foreach (self::FELDER as $feld => $def) {
            $wert = array_key_exists($feld, (array) $werte) ? $werte[$feld] : ($basis[$feld] ?? $def['standard']);
            $ergebnis[$feld] = self::feldWert($feld, $wert);
        }

        return $ergebnis;
    }

    /**
     * Nur bekannte Felder, die von der Basis abweichen (für `ab_sehprofile.werte`).
     *
     * @return array<string, mixed>
     */
    public static function abweichungen(array $werte, array $basis): array
    {
        $voll = self::normalisieren($werte, $basis);
        $basis = self::normalisieren($basis);

        return array_filter($voll, fn ($wert, $feld) => $wert !== $basis[$feld], ARRAY_FILTER_USE_BOTH);
    }

    public static function feldWert(string $feld, mixed $wert): mixed
    {
        $def = self::FELDER[$feld];

        return match ($def['typ']) {
            'schalter' => is_bool($wert) ? $wert : in_array(strtolower(trim((string) $wert)), ['1', 'true', 'on', 'yes', 'ja'], true),
            'auswahl' => in_array($wert, $def['werte'], true) ? $wert : $def['standard'],
            'zahl' => self::zahl($wert, $def),
        };
    }

    private static function zahl(mixed $wert, array $def): float|int
    {
        if (! is_numeric($wert)) {
            return $def['standard'];
        }
        $zahl = min($def['max'], max($def['min'], (float) $wert));
        $schritt = $def['schritt'] ?? 1;
        // auf zwei Nachkommastellen runden, ganze Werte als int (stabiler Hash)
        $zahl = round($zahl, 2);

        return ($schritt >= 1 && floor($zahl) == $zahl) ? (int) $zahl : $zahl;
    }

    /** Erkennt, wann Fassungen veralten. */
    public static function hash(array $werte): string
    {
        $werte = self::normalisieren($werte);
        ksort($werte);

        return hash('sha256', json_encode($werte));
    }

    /** Lesbare Kurzbeschreibung, z. B. „Atkinson 28 pt fett · A4 · Schwarz auf Gelb“. */
    public static function kurz(array $werte): string
    {
        $w = self::normalisieren($werte);
        $teile = [
            explode(' ', self::SCHRIFTEN[$w['schrift']])[0].' '.$w['groesse'].' pt'.($w['fett'] ? ' fett' : ''),
            $w['format'],
        ];
        if ($w['farbschema'] !== 'normal') {
            $teile[] = self::FARBSCHEMATA[$w['farbschema']]['label'];
        }

        return implode(' · ', $teile);
    }

    /**
     * Abgeleitete Layout-Werte für Blade/DomPDF (alles in pt bzw. mm).
     *
     * @return array<string, mixed>
     */
    public static function layout(array $werte): array
    {
        $w = self::normalisieren($werte);
        $farben = self::FARBSCHEMATA[$w['farbschema']];
        $mmZuPt = 72 / 25.4;

        return array_merge($w, [
            'schrift_familie' => 'ab-'.$w['schrift'],
            'schrift_name' => self::SCHRIFTEN[$w['schrift']],
            'farbe_text' => $farben['text'],
            'farbe_hintergrund' => $farben['hintergrund'],
            'farbe_linie' => $farben['linie'],
            'invertiert' => $w['farbschema'] === 'invertiert',
            'seite_breite_mm' => $w['format'] === 'A3' ? 297 : 210,
            'seite_hoehe_mm' => $w['format'] === 'A3' ? 420 : 297,
            'zeilenhoehe_pt' => round($w['groesse'] * $w['zeilenabstand'], 2),
            'linienabstand_pt' => round($w['linienabstand'] * $mmZuPt, 2),
            'ankreuz_pt' => round($w['ankreuz_groesse'] * $mmZuPt, 2),
            'luecke_pt' => round($w['luecke_min'] * $mmZuPt, 2),
            'ueberschrift_pt' => round($w['groesse'] * 1.35, 1),
            // Rechenkästchen wachsen mit der Schrift (mind. 5 mm); Spalten füllen die Satzbreite
            'raster_pt' => $raster = max(round(5 * $mmZuPt, 1), round($w['groesse'] * 1.3, 1)),
            'raster_spalten' => max(4, (int) floor(((($w['format'] === 'A3' ? 297 : 210) - 2 * $w['rand']) * $mmZuPt - $w['groesse'] * 1.6) / $raster)),
        ]);
    }
}
