<?php

namespace App\Services\Arbeitsblaetter;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use PhpOffice\PhpWord\Element\AbstractContainer;
use PhpOffice\PhpWord\Element\Image;
use PhpOffice\PhpWord\Element\ListItem;
use PhpOffice\PhpWord\Element\ListItemRun;
use PhpOffice\PhpWord\Element\Table;
use PhpOffice\PhpWord\Element\Text;
use PhpOffice\PhpWord\Element\TextBreak;
use PhpOffice\PhpWord\Element\TextRun;
use PhpOffice\PhpWord\Element\Title;
use PhpOffice\PhpWord\IOFactory;
use setasign\Fpdi\Fpdi;
use Smalot\PdfParser\Parser as PdfParser;

/**
 * Normalisiert eingehende Dateien für die Erfassung.
 *
 * - JPG/PNG: mit GD neu kodiert (EXIF/Metadaten weg, Ausrichtung übernommen), max. 2400 px.
 * - PDF: unverändert; vorhandene Textebene wird mit smalot/pdfparser als Hinweis für die KI gelesen.
 * - DOC/DOCX/ODT: Umwandlung in PDF ({@see AbKonvertierung}); ohne Umwandlung liest PHPWord
 *   bei DOCX Text, Tabellen und Bilder als Rohstruktur. ODT/DOC ohne Umwandlung → Meldung.
 *
 * Ergebnis: ['art' => bilder|pdf|struktur, 'seiten' => [[inhalt, mime, name]], 'textebene' => ?string,
 *            'struktur' => ?array, 'abbildungen' => [[inhalt, mime]], 'seitenzahl' => int]
 */
class AbQuellenService
{
    public const BILD_ENDUNGEN = ['jpg', 'jpeg', 'png'];

    public const WORD_ENDUNGEN = ['doc', 'docx', 'odt'];

    public const ERLAUBTE_ENDUNGEN = 'pdf,jpg,jpeg,png,doc,docx,odt';

    public function __construct(private AbKonvertierung $konvertierung)
    {
    }

    /**
     * Mehrere Dateien (z. B. Kamera-Seiten oder Upload) zu einem Eingang zusammenführen.
     * Mehrere Bilder ergeben mehrere Seiten; PDF/Word nur einzeln.
     *
     * @param  array<int, UploadedFile>  $dateien
     */
    public function ausUploads(array $dateien): array
    {
        $dateien = array_values(array_filter($dateien));
        if (! $dateien) {
            throw new AbQuellenFehler('Bitte mindestens eine Datei auswählen.');
        }

        $bilder = array_filter($dateien, fn (UploadedFile $d) => in_array($this->endung($d->getClientOriginalName()), self::BILD_ENDUNGEN, true));
        if (count($bilder) === count($dateien)) {
            return $this->ausBildern(array_map(fn (UploadedFile $d) => [
                'inhalt' => (string) file_get_contents($d->getRealPath()),
                'name' => $d->getClientOriginalName(),
            ], $dateien));
        }
        if (count($dateien) > 1) {
            throw new AbQuellenFehler('PDF- und Word-Dateien bitte einzeln hochladen (mehrere Dateien gehen nur bei Fotos).');
        }

        $datei = $dateien[0];

        return $this->ausInhalt((string) file_get_contents($datei->getRealPath()), $datei->getClientOriginalName());
    }

    /** Eine Datei aus vorhandenem Inhalt (z. B. Arbeitsblatt eines Wochenplans). */
    public function ausInhalt(string $inhalt, string $name): array
    {
        $endung = $this->endung($name);

        if (in_array($endung, self::BILD_ENDUNGEN, true)) {
            return $this->ausBildern([['inhalt' => $inhalt, 'name' => $name]]);
        }
        if ($endung === 'pdf' || str_starts_with($inhalt, '%PDF')) {
            return $this->ausPdf($inhalt, $name);
        }
        if (in_array($endung, self::WORD_ENDUNGEN, true)) {
            return $this->ausWord($inhalt, $name, $endung);
        }

        throw new AbQuellenFehler('Dieses Dateiformat wird nicht unterstützt. Erlaubt sind PDF, Fotos (JPG/PNG) und Word.');
    }

    /** @param array<int, array{inhalt: string, name: string}> $bilder */
    public function ausBildern(array $bilder): array
    {
        $max = (int) config('arbeitsblaetter.max_seiten', 10);
        if (count($bilder) > $max) {
            throw new AbQuellenFehler('Ein Blatt darf höchstens '.$max.' Seiten haben.');
        }

        $seiten = [];
        foreach (array_values($bilder) as $i => $bild) {
            $seiten[] = [
                'inhalt' => $this->bildNormalisieren($bild['inhalt']),
                'mime' => 'image/jpeg',
                'name' => 'seite-'.($i + 1).'.jpg',
            ];
        }

        return ['art' => 'bilder', 'seiten' => $seiten, 'textebene' => null, 'struktur' => null, 'abbildungen' => [], 'seitenzahl' => count($seiten)];
    }

    public function ausPdf(string $inhalt, string $name): array
    {
        $seitenzahl = $this->pdfSeiten($inhalt);
        $max = (int) config('arbeitsblaetter.max_seiten', 10);
        if ($seitenzahl > $max) {
            throw new AbQuellenFehler('Das PDF hat '.$seitenzahl.' Seiten – erlaubt sind höchstens '.$max.'.');
        }

        return [
            'art' => 'pdf',
            'seiten' => [['inhalt' => $inhalt, 'mime' => 'application/pdf', 'name' => $this->pdfName($name)]],
            'textebene' => $this->textebene($inhalt),
            'struktur' => null,
            'abbildungen' => [],
            'seitenzahl' => max(1, $seitenzahl),
        ];
    }

    public function ausWord(string $inhalt, string $name, string $endung): array
    {
        if ($this->konvertierung->verfuegbar()) {
            $pdf = $this->konvertierung->zuPdf($inhalt, $name);
            if ($pdf !== null) {
                return $this->ausPdf($pdf, $name);
            }
        }

        if ($endung === 'docx') {
            return $this->ausDocx($inhalt);
        }

        throw new AbQuellenFehler('Diese Datei kann gerade nicht umgewandelt werden. Bitte als PDF speichern und erneut hochladen.');
    }

    /** Ersatzweg ohne Umwandlung: PHPWord liest Text, Tabellen und Bilder als Rohstruktur. */
    public function ausDocx(string $inhalt): array
    {
        $pfad = storage_path('app/temp/'.uniqid('ab_docx_', true).'.docx');
        if (! is_dir(dirname($pfad))) {
            @mkdir(dirname($pfad), 0755, true);
        }
        file_put_contents($pfad, $inhalt);

        try {
            $word = IOFactory::load($pfad, 'Word2007');
        } catch (\Throwable $e) {
            Log::info('DOCX konnte nicht gelesen werden', ['fehler' => $e->getMessage()]);
            throw new AbQuellenFehler('Die Word-Datei konnte nicht gelesen werden. Bitte als PDF speichern und erneut hochladen.');
        } finally {
            @unlink($pfad);
        }

        $bausteine = [];
        $abbildungen = [];
        foreach ($word->getSections() as $abschnitt) {
            $this->docxElemente($abschnitt, $bausteine, $abbildungen);
        }

        return [
            'art' => 'struktur',
            'seiten' => [],
            'textebene' => null,
            'struktur' => ['seiten' => 1, 'bausteine' => $bausteine],
            'abbildungen' => $abbildungen,
            'seitenzahl' => 1,
        ];
    }

    // ─── intern ───────────────────────────────────────────────────────────────

    /** Neu kodieren ohne Metadaten, Ausrichtung übernehmen, längste Seite max. 2400 px. */
    public function bildNormalisieren(string $inhalt): string
    {
        if (! function_exists('imagecreatefromstring')) {
            throw new AbQuellenFehler('Bilder können auf diesem Server nicht verarbeitet werden (PHP-GD fehlt).');
        }
        $bild = @imagecreatefromstring($inhalt);
        if (! $bild) {
            throw new AbQuellenFehler('Das Bild konnte nicht gelesen werden.');
        }

        $bild = $this->ausrichten($bild, $inhalt);

        $max = (int) config('arbeitsblaetter.max_bild_px', 2400);
        $b = imagesx($bild);
        $h = imagesy($bild);
        if (max($b, $h) > $max) {
            $faktor = $max / max($b, $h);
            $neu = imagescale($bild, (int) round($b * $faktor), (int) round($h * $faktor), IMG_BICUBIC);
            imagedestroy($bild);
            $bild = $neu;
        }

        // weißer Hintergrund (Transparenz aus PNG)
        $ziel = imagecreatetruecolor(imagesx($bild), imagesy($bild));
        imagefill($ziel, 0, 0, imagecolorallocate($ziel, 255, 255, 255));
        imagecopy($ziel, $bild, 0, 0, 0, 0, imagesx($bild), imagesy($bild));
        imagedestroy($bild);

        ob_start();
        imagejpeg($ziel, null, 85);
        $jpeg = (string) ob_get_clean();
        imagedestroy($ziel);

        return $jpeg;
    }

    private function ausrichten(\GdImage $bild, string $inhalt): \GdImage
    {
        if (! function_exists('exif_read_data') || ! str_starts_with($inhalt, "\xFF\xD8")) {
            return $bild;
        }
        $exif = @exif_read_data('data://image/jpeg;base64,'.base64_encode($inhalt));
        $grad = match ((int) ($exif['Orientation'] ?? 1)) {
            3 => 180,
            6 => -90,
            8 => 90,
            default => 0,
        };
        if ($grad === 0) {
            return $bild;
        }
        $gedreht = imagerotate($bild, $grad, 0);
        imagedestroy($bild);

        return $gedreht;
    }

    public function pdfSeiten(string $inhalt): int
    {
        $pfad = storage_path('app/temp/'.uniqid('ab_pdf_', true).'.pdf');
        if (! is_dir(dirname($pfad))) {
            @mkdir(dirname($pfad), 0755, true);
        }
        file_put_contents($pfad, $inhalt);
        try {
            return (new Fpdi())->setSourceFile($pfad);
        } catch (\Throwable) {
            // FPDI (ohne Parser-Erweiterung) kann komprimierte Querverweise nicht lesen
            try {
                return count((new PdfParser())->parseContent($inhalt)->getPages());
            } catch (\Throwable) {
                if (! str_starts_with($inhalt, '%PDF')) {
                    throw new AbQuellenFehler('Die PDF-Datei ist beschädigt oder kein PDF.');
                }

                return max(1, preg_match_all('/\/Type\s*\/Page[^s]/', $inhalt));
            }
        } finally {
            @unlink($pfad);
        }
    }

    /** Textebene eines PDFs (max. 20 000 Zeichen) oder null. */
    public function textebene(string $inhalt): ?string
    {
        if (! class_exists(PdfParser::class)) {
            return null;
        }
        try {
            $text = (new PdfParser())->parseContent($inhalt)->getText();
        } catch (\Throwable) {
            return null;
        }
        $text = trim(preg_replace("/[ \t]+/u", ' ', (string) $text) ?? '');

        return mb_strlen($text) >= 20 ? mb_substr($text, 0, 20000) : null;
    }

    private function docxElemente(AbstractContainer $container, array &$bausteine, array &$abbildungen): void
    {
        foreach ($container->getElements() as $element) {
            if (count($bausteine) >= 300) {
                return;
            }
            if ($element instanceof Title) {
                $text = $this->elementText($element->getText());
                if ($text !== '') {
                    $bausteine[] = ['typ' => 'ueberschrift', 'text' => $text];
                }
            } elseif ($element instanceof Table) {
                $zeilen = [];
                foreach ($element->getRows() as $zeile) {
                    $zeilen[] = array_map(fn ($zelle) => $this->elementText($zelle), $zeile->getCells());
                }
                if ($zeilen) {
                    $bausteine[] = ['typ' => 'tabelle', 'zeilen' => $zeilen, 'kopfzeile' => false];
                }
            } elseif ($element instanceof Image) {
                $this->docxBild($element, $bausteine, $abbildungen);
            } elseif ($element instanceof TextRun || $element instanceof ListItemRun) {
                foreach ($element->getElements() as $kind) {
                    if ($kind instanceof Image) {
                        $this->docxBild($kind, $bausteine, $abbildungen);
                    }
                }
                $text = $this->elementText($element);
                if ($text !== '') {
                    $bausteine[] = $this->istUeberschrift($element) ? ['typ' => 'ueberschrift', 'text' => $text] : ['typ' => 'text', 'text' => $text];
                }
            } elseif ($element instanceof Text || $element instanceof ListItem) {
                $text = $this->elementText($element);
                if ($text !== '') {
                    $bausteine[] = ['typ' => 'text', 'text' => $text];
                }
            } elseif ($element instanceof AbstractContainer) {
                $this->docxElemente($element, $bausteine, $abbildungen);
            }
        }
    }

    /** Absatzformat „Überschrift …“/„Heading …“/„Titel“ → Überschrift. */
    private function istUeberschrift(mixed $element): bool
    {
        $stil = method_exists($element, 'getParagraphStyle') ? $element->getParagraphStyle() : null;
        $name = is_string($stil) ? $stil : (is_object($stil) && method_exists($stil, 'getStyleName') ? (string) $stil->getStyleName() : '');

        return (bool) preg_match('/^(heading|title|titel|.*berschrift)/i', $name);
    }

    private function docxBild(Image $bild, array &$bausteine, array &$abbildungen): void
    {
        try {
            $daten = $bild->getImageStringData();
        } catch (\Throwable) {
            $daten = null;
        }
        if (! $daten) {
            return;
        }
        $abbildungen[] = ['inhalt' => $daten, 'mime' => $bild->getImageType() ?: 'image/png'];
        $bausteine[] = ['typ' => 'bild', 'beschreibung' => '', 'abbildung' => count($abbildungen) - 1];
    }

    private function elementText(mixed $element): string
    {
        if (is_string($element)) {
            return trim($element);
        }
        if ($element instanceof Text) {
            return trim((string) $element->getText());
        }
        if ($element instanceof ListItem) {
            return trim((string) $element->getTextObject()->getText());
        }
        if ($element instanceof TextBreak) {
            return "\n";
        }
        if ($element instanceof AbstractContainer) {
            $teile = [];
            foreach ($element->getElements() as $kind) {
                $teile[] = $kind instanceof Text ? (string) $kind->getText() : ($kind instanceof TextBreak ? "\n" : ($kind instanceof AbstractContainer ? $this->elementText($kind) : ''));
            }

            return trim(implode('', $teile));
        }

        return '';
    }

    private function endung(string $name): string
    {
        return strtolower(pathinfo($name, PATHINFO_EXTENSION));
    }

    private function pdfName(string $name): string
    {
        $basis = pathinfo($name, PATHINFO_FILENAME) ?: 'original';

        return preg_replace('/[^A-Za-z0-9._-]+/', '_', $basis).'.pdf';
    }
}
