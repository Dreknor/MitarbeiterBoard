<?php

namespace App\Services\Arbeitsblaetter;

use App\Models\Arbeitsblaetter\AngepasstesBlatt;
use App\Support\AbProfil;
use App\Support\AbStruktur;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Log;
use PhpOffice\PhpWord\PhpWord;
use PhpOffice\PhpWord\Shared\Converter;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * Setzt ein Blatt (Struktur + Profilwerte) neu: PDF über DomPDF, HTML-Vorschau aus derselben
 * Blade-Vorlage (`pdf/arbeitsblatt.blade.php`) und optional DOCX über PHPWord.
 *
 * Schrift, Größe, Abstände, Farben und Format kommen ausschließlich aus dem Profil.
 */
class AbSatzService
{
    public const SCHRIFT_DATEIEN = [
        'atkinson' => ['AtkinsonHyperlegible-Regular.ttf', 'AtkinsonHyperlegible-Bold.ttf'],
        'liberation' => ['LiberationSans-Regular.ttf', 'LiberationSans-Bold.ttf'],
        'dejavu' => ['DejaVuSans.ttf', 'DejaVuSans-Bold.ttf'],
        // Microsoft-Schriften: dürfen nicht mitgeliefert werden – die Schule legt ihre lizenzierten
        // Dateien in `config('arbeitsblaetter.schriften_pfad')` ab, sonst greift die Ersatzschrift.
        'arial' => ['arial.ttf', 'arialbd.ttf'],
        'verdana' => ['verdana.ttf', 'verdanab.ttf'],
    ];

    /** Ersatz, wenn die Microsoft-Schrift fehlt: Liberation Sans ist maßgleich zu Arial, DejaVu Sans ähnelt Verdana. */
    public const ERSATZ = [
        'arial' => 'liberation',
        'verdana' => 'dejavu',
    ];

    /** Schriften, die die Schule selbst bereitstellen kann. */
    public static function eigeneSchriften(): array
    {
        return array_keys(self::ERSATZ);
    }

    /**
     * Pfad einer Schriftdatei (eigene Schriften in resources/fonts, DejaVu von DomPDF, Arial/Verdana aus dem
     * Schriftordner der Schule). Fehlt Arial/Verdana, wird die Ersatzschrift geliefert.
     */
    public static function schriftPfad(string $schrift, int $schnitt = 0): ?string
    {
        if (isset(self::ERSATZ[$schrift])) {
            return self::originalPfad($schrift, $schnitt) ?? self::schriftPfad(self::ERSATZ[$schrift], $schnitt);
        }
        $datei = self::SCHRIFT_DATEIEN[$schrift][$schnitt] ?? null;
        if (! $datei) {
            return null;
        }
        $pfad = $schrift === 'dejavu'
            ? base_path('vendor/dompdf/dompdf/lib/fonts/'.$datei)
            : resource_path('fonts/arbeitsblaetter/'.$datei);

        return is_file($pfad) ? $pfad : null;
    }

    /** Datei der Microsoft-Schrift im Schriftordner der Schule (Groß-/Kleinschreibung egal) oder null. */
    public static function originalPfad(string $schrift, int $schnitt = 0): ?string
    {
        $datei = self::SCHRIFT_DATEIEN[$schrift][$schnitt] ?? null;
        $ordner = rtrim((string) config('arbeitsblaetter.schriften_pfad'), '/');
        if (! $datei || $ordner === '' || ! is_dir($ordner)) {
            return null;
        }
        foreach ([$datei, strtoupper($datei), ucfirst($datei), strtolower(pathinfo($datei, PATHINFO_FILENAME)).'.TTF'] as $name) {
            if (is_file($ordner.'/'.$name)) {
                return $ordner.'/'.$name;
            }
        }

        return null;
    }

    /**
     * Interner Familienname für @font-face. DomPDF merkt sich Familie → Datei dauerhaft (storage/fonts);
     * der Name enthält deshalb die genutzte Datei, damit z. B. nach dem Laden der echten Verdana nicht
     * weiter die zuvor genutzte Ersatzschrift erscheint.
     */
    public static function familie(string $schrift): string
    {
        $pfad = (string) self::schriftPfad($schrift, 0).'|'.(string) self::schriftPfad($schrift, 1);

        return 'ab-'.$schrift.'-'.substr(md5($pfad), 0, 8);
    }

    /** Wird für die Schrift die Ersatzschrift verwendet? */
    public static function nutztErsatz(string $schrift): bool
    {
        return isset(self::ERSATZ[$schrift]) && (! self::originalPfad($schrift, 0) || ! self::originalPfad($schrift, 1));
    }

    /** Bezeichnung für die Auswahl, z. B. „Verdana (Ersatz: DejaVu Sans)“. */
    public static function schriftLabel(string $schrift): string
    {
        $name = AbProfil::SCHRIFTEN[$schrift] ?? $schrift;

        return self::nutztErsatz($schrift) ? $name.' (Ersatz: '.AbProfil::SCHRIFTEN[self::ERSATZ[$schrift]].')' : $name;
    }

    /** Sind alle mitgelieferten Schriften vorhanden? Liefert die fehlenden Dateien (Arial/Verdana zählen nicht). */
    public static function fehlendeSchriften(): array
    {
        $fehlt = [];
        foreach (self::SCHRIFT_DATEIEN as $schrift => $dateien) {
            if (isset(self::ERSATZ[$schrift])) {
                continue;
            }
            foreach ($dateien as $i => $datei) {
                if (! self::schriftPfad($schrift, $i)) {
                    $fehlt[] = $datei;
                }
            }
        }

        return $fehlt;
    }

    /** HTML (Vorschau im Editor bzw. Grundlage für das PDF). */
    public function html(AngepasstesBlatt $blatt, array $profilWerte, string $modus = 'html', ?array $struktur = null, ?string $fusszeile = null, ?string $titel = null): string
    {
        $struktur = AbStruktur::normalisieren($struktur ?? $blatt->strukturGueltig());
        $layout = AbProfil::layout($profilWerte);
        $layout['schrift_familie'] = self::familie($layout['schrift']);

        return view('pdf.arbeitsblatt', [
            'titel' => $titel ?? (string) $blatt->titel,
            'struktur' => $struktur,
            'l' => $layout,
            'modus' => $modus,
            'schriften' => $this->schriften($layout['schrift'], $modus),
            'bild' => $this->bildQuelle($blatt, $layout, $modus),
            'fusszeile' => $fusszeile,
        ])->render();
    }

    /** PDF als Binärinhalt. */
    public function pdf(AngepasstesBlatt $blatt, array $profilWerte, ?array $struktur = null, ?string $fusszeile = null): string
    {
        $layout = AbProfil::layout($profilWerte);
        $this->schriftCacheSicherstellen();

        $pdf = Pdf::loadHTML($this->html($blatt, $profilWerte, 'pdf', $struktur, $fusszeile));
        $pdf->setPaper($layout['format'], 'portrait');
        // Schriftordner der Schule (Arial/Verdana) darf außerhalb des Projekts liegen → in den chroot aufnehmen
        $chroot = array_values(array_filter([realpath(base_path()), realpath((string) config('arbeitsblaetter.schriften_pfad')) ?: null]));
        $pdf->setOption(['isRemoteEnabled' => false, 'isFontSubsettingEnabled' => true, 'defaultFont' => 'DejaVu Sans', 'chroot' => $chroot], true);

        return $pdf->output();
    }

    /** DOCX als Binärinhalt (dieselbe Struktur, Profilschrift und -größe). */
    public function docx(AngepasstesBlatt $blatt, array $profilWerte, ?array $struktur = null, ?string $fusszeile = null): string
    {
        $struktur = AbStruktur::normalisieren($struktur ?? $blatt->strukturGueltig());
        $l = AbProfil::layout($profilWerte);

        $word = new PhpWord();
        // Word nutzt immer den echten Namen (Arial/Verdana sind auf Schulrechnern vorhanden)
        $word->setDefaultFontName($l['schrift_name']);
        $word->setDefaultFontSize((int) round($l['groesse']));
        $word->setDefaultParagraphStyle([
            'spacing' => Converter::pointToTwip(round($l['groesse'] * 0.5)),
            'lineHeight' => $l['zeilenabstand'],
        ]);
        $schrift = ['bold' => $l['fett'], 'spacing' => (int) round($l['buchstabenabstand'] * $l['groesse'] * 20)];

        $rand = Converter::cmToTwip($l['rand'] / 10);
        $abschnitt = $word->addSection([
            'paperSize' => $l['format'],
            'marginTop' => $rand, 'marginBottom' => $rand, 'marginLeft' => $rand, 'marginRight' => $rand,
        ]);
        if ($fusszeile) {
            $abschnitt->addFooter()->addText($fusszeile, ['size' => 9]);
        }

        if (trim((string) $blatt->titel) !== '') {
            $abschnitt->addText($blatt->titel, array_merge($schrift, ['bold' => true, 'size' => (int) round($l['ueberschrift_pt'])]));
        }

        $temp = [];
        foreach ($struktur['bausteine'] as $b) {
            $this->docxBaustein($abschnitt, $b, $l, $schrift, $blatt, $temp, 0);
        }

        $pfad = $this->tempDatei('ab_docx_', 'docx');
        $word->save($pfad, 'Word2007');
        $inhalt = (string) file_get_contents($pfad);
        @unlink($pfad);
        foreach ($temp as $datei) {
            @unlink($datei);
        }

        return $inhalt;
    }

    // ─── intern: HTML/PDF ────────────────────────────────────────────────────

    /** @return array<string, array{0: string, 1: string}> Familie → [normal, fett] */
    private function schriften(string $schrift, string $modus): array
    {
        $ergebnis = [];
        foreach (array_keys(self::SCHRIFT_DATEIEN) as $name) {
            if ($name !== $schrift) {
                continue;
            }
            $dateien = [];
            foreach ([0, 1] as $schnitt) {
                $pfad = self::schriftPfad($name, $schnitt);
                if (! $pfad) {
                    continue 2;
                }
                $dateien[] = $modus === 'pdf'
                    ? $pfad
                    : route('arbeitsblaetter.schrift', ['schrift' => $name, 'schnitt' => $schnitt ? 'fett' : 'normal']);
            }
            $ergebnis[self::familie($name)] = $dateien;
        }

        return $ergebnis;
    }

    private function bildQuelle(AngepasstesBlatt $blatt, array $layout, string $modus): \Closure
    {
        $medien = $blatt->exists ? $blatt->getMedia(AngepasstesBlatt::ABBILDUNGEN)->keyBy('id') : collect();

        return function (int $mediaId) use ($medien, $blatt, $layout, $modus): ?string {
            /** @var Media|null $media */
            $media = $medien->get($mediaId);
            if (! $media) {
                return null;
            }
            if ($modus !== 'pdf') {
                return route('arbeitsblaetter.abbildungen.show', [$blatt, $media->id]);
            }
            $inhalt = $this->bildInhalt($media, $layout['bild_kontrast']);

            return $inhalt ? 'data:image/png;base64,'.base64_encode($inhalt) : null;
        };
    }

    /** Bildinhalt als PNG, bei Bedarf kontrastreich (Graustufen + Schwellwert). */
    public function bildInhalt(Media $media, bool $kontrast): ?string
    {
        try {
            $roh = file_get_contents($media->getPath());
        } catch (\Throwable) {
            return null;
        }
        if ($roh === false || $roh === '') {
            return null;
        }
        if (! function_exists('imagecreatefromstring')) {
            return $roh;
        }
        $bild = @imagecreatefromstring($roh);
        if (! $bild) {
            return null;
        }
        if ($kontrast) {
            imagefilter($bild, IMG_FILTER_GRAYSCALE);
            imagefilter($bild, IMG_FILTER_CONTRAST, -60);
        }
        ob_start();
        imagepng($bild, null, 6);
        $png = (string) ob_get_clean();
        imagedestroy($bild);

        return $png;
    }

    /** DomPDF braucht ein beschreibbares Schrift-Verzeichnis für die Metriken. */
    private function schriftCacheSicherstellen(): void
    {
        foreach ([config('dompdf.options.font_dir'), config('dompdf.options.font_cache')] as $verzeichnis) {
            if ($verzeichnis && ! is_dir($verzeichnis)) {
                @mkdir($verzeichnis, 0755, true);
            }
        }
    }

    // ─── intern: DOCX ─────────────────────────────────────────────────────────

    private function docxBaustein($ziel, array $b, array $l, array $schrift, AngepasstesBlatt $blatt, array &$temp, int $ebene): void
    {
        $einzug = ['indentation' => ['left' => Converter::cmToTwip($ebene * 1.0)]];
        $farbe = $l['farbe_linie'] === '#ffffff' ? '000000' : ltrim($l['farbe_linie'], '#');
        $linie = (int) round($l['linienstaerke'] * 8); // Rahmen in Achtel-Punkt

        switch ($b['typ']) {
            case 'ueberschrift':
                $ziel->addText($b['text'], array_merge($schrift, ['bold' => true, 'size' => (int) round($l['ueberschrift_pt'])]), $einzug);
                break;

            case 'anweisung':
                $ziel->addText($b['text'], array_merge($schrift, ['bold' => true]), $einzug);
                break;

            case 'text':
            case 'aufgabe':
                $absatz = $ziel->addTextRun(array_merge($einzug, ['keepNext' => $b['typ'] === 'aufgabe' && $l['aufgabe_nicht_teilen']]));
                if (($b['nummer'] ?? '') !== '') {
                    $absatz->addText($b['nummer'].'  ', array_merge($schrift, ['bold' => true]));
                }
                foreach ($b['inhalt'] ?? [] as $s) {
                    $absatz->addText(
                        ($s['typ'] ?? '') === 'luecke' ? ' '.str_repeat('_', max(6, (int) round(max($l['luecke_min'] / 4, $s['laenge'] * 1.4)))).' ' : (string) $s['text'],
                        $schrift
                    );
                }
                foreach ($b['kinder'] ?? [] as $kind) {
                    $this->docxBaustein($ziel, $kind, $l, $schrift, $blatt, $temp, $ebene + 1);
                }
                break;

            case 'ankreuzen':
                if (($b['frage'] ?? '') !== '') {
                    $ziel->addText($b['frage'], $schrift, array_merge($einzug, ['keepNext' => true]));
                }
                foreach ($b['optionen'] ?? [] as $option) {
                    $ziel->addText((! empty($b['mehrfach']) ? '☐' : '○').'  '.$option, $schrift, $einzug);
                }
                break;

            case 'tabelle':
                $tabelle = $ziel->addTable(['borderSize' => max(4, $linie), 'borderColor' => $farbe, 'cellMargin' => 80, 'width' => 100 * 50, 'unit' => 'pct']);
                foreach ($b['zeilen'] ?? [] as $i => $zeile) {
                    $tabelle->addRow(Converter::pointToTwip($l['zeilenhoehe_pt'] * 1.2), ['cantSplit' => true]);
                    foreach ($zeile as $zelle) {
                        $tabelle->addCell()->addText($zelle, array_merge($schrift, ['bold' => $schrift['bold'] || ($i === 0 && ! empty($b['kopfzeile']))]));
                    }
                }
                break;

            case 'schreiblinien':
                for ($i = 0; $i < (int) $b['anzahl']; $i++) {
                    $ziel->addText('', $schrift, array_merge($einzug, [
                        'borderBottomSize' => max(4, $linie), 'borderBottomColor' => $farbe,
                        'spaceBefore' => Converter::pointToTwip(max(0, $l['linienabstand_pt'] - $l['groesse'])),
                        'spaceAfter' => 0, 'lineHeight' => 1.0,
                    ]));
                }
                break;

            case 'bild':
                $media = ! empty($b['media_id']) ? $blatt->getMedia(AngepasstesBlatt::ABBILDUNGEN)->firstWhere('id', (int) $b['media_id']) : null;
                $inhalt = $media ? $this->bildInhalt($media, $l['bild_kontrast']) : null;
                if ($inhalt) {
                    $pfad = $this->tempDatei('ab_bild_', 'png');
                    file_put_contents($pfad, $inhalt);
                    $temp[] = $pfad;
                    $breiteMm = ($l['seite_breite_mm'] - 2 * $l['rand']) * min(1, max(0.15, ($b['quelle']['box'][2] ?? 0.45) - ($b['quelle']['box'][0] ?? 0)) * $l['bildfaktor']);
                    try {
                        $ziel->addImage($pfad, array_merge($einzug, ['width' => Converter::cmToPoint($breiteMm / 10)]));
                    } catch (\Throwable $e) {
                        Log::info('Abbildung konnte nicht in DOCX eingefügt werden', ['blatt' => $blatt->id, 'fehler' => $e->getMessage()]);
                    }
                } else {
                    $ziel->addText('[Abbildung'.(($b['beschreibung'] ?? '') !== '' ? ': '.$b['beschreibung'] : '').']', $schrift, $einzug);
                }
                break;
        }
    }

    private function tempDatei(string $praefix, string $endung): string
    {
        $verzeichnis = storage_path('app/temp');
        if (! is_dir($verzeichnis)) {
            @mkdir($verzeichnis, 0755, true);
        }

        return $verzeichnis.'/'.uniqid($praefix, true).'.'.$endung;
    }
}
