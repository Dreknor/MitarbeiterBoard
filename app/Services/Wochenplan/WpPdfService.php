<?php

namespace App\Services\Wochenplan;

use App\Models\Wochenplan\WpPlan;
use App\Services\Wochenplan\WpPdfMergeService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Response;

class WpPdfService
{
    /**
     * Generiert ein PDF-Objekt für den gegebenen Plan.
     */
    public function generate(WpPlan $plan): \Barryvdh\DomPDF\PDF
    {
        // Eager-Load aller benötigten Relationen
        $plan->load([
            'planFaecher.aufgaben',
            'planFaecher.fach',
            'taeglicheUebungen',
            'klasse',
            'schueler.klasse',
            'formatvorlage',
        ]);

        $formatvorlage = $plan->getEffectiveFormatvorlage();
        $config        = $formatvorlage->layout_config ?? [];

        // Blade-Template aus Formatvorlage, Fallback auf Standard
        $template = $formatvorlage->sicheresTemplate();

        // Seitenformat und Ränder aus Config
        $papierGroesse    = strtolower($config['papier']['groesse']    ?? 'A4');
        $papierAusrichtung = $config['papier']['ausrichtung'] ?? 'portrait';

        $comicSans = $this->comicSansPfade();
        $pdf = Pdf::loadView($template, [
            'plan'          => $plan,
            'formatvorlage' => $formatvorlage,
            'config'        => $config,
            'schriftCss'    => $this->schriftCss($formatvorlage->schriftart, $comicSans !== null),
            'comicSans'     => $comicSans,
        ]);

        $pdf->setPaper($papierGroesse, $papierAusrichtung);

        $dompdf = $pdf->getDomPDF();
        $dompdf->set_option('defaultPaperSize', 'A4');
        // Lokale Fonts über Dateipfad laden erlauben (isRemoteEnabled ermöglicht file://-Zugriff)
        $dompdf->set_option('isRemoteEnabled', true);
        // Symbole, Smileys und QR-Codes kommen als Data-URI – config/dompdf.php erlaubt data:// nicht
        $dompdf->set_option('allowedProtocols', [
            'data://' => ['rules' => []],
            'file://' => ['rules' => []],
            'http://' => ['rules' => []],
            'https://' => ['rules' => []],
        ]);

        // NotoSansSymbols2 für Emoji/Symbol-Rendering registrieren
        $fontMetrics = $dompdf->getFontMetrics();

        // Comic Sans (Microsoft-Schrift, nicht im Repository): nur wenn die Schule comic.ttf/comicbd.ttf
        // in den Schriftordner der Arbeitsblätter gelegt hat (php artisan arbeitsblaetter:schriften --von=…)
        if ($comicSans !== null) {
            // eingebunden per @font-face im Template; der Ordner darf außerhalb des Projekts liegen
            $dompdf->set_option('chroot', array_values(array_filter([realpath(base_path()), realpath(dirname($comicSans['normal']))])));
        }
        $fontPath = storage_path('fonts/NotoSansSymbols2-Regular.ttf');
        if (file_exists($fontPath)) {
            $fontMetrics->registerFont(
                ['family' => 'NotoSymbols', 'style' => 'normal', 'weight' => 'normal'],
                $fontPath
            );
        }

        // OpenDyslexic für Legasthenie-Unterstützung registrieren
        $openDyslexicPath = storage_path('fonts/OpenDyslexic-Regular.otf');
        if (file_exists($openDyslexicPath)) {
            $fontMetrics->registerFont(
                ['family' => 'OpenDyslexic', 'style' => 'normal', 'weight' => 'normal'],
                $openDyslexicPath
            );
        }

        return $pdf;
    }

    /** @return array{normal:string, bold:?string}|null */
    public function comicSansPfade(): ?array
    {
        $ordner = str_replace('\\', '/', rtrim((string) config('arbeitsblaetter.schriften_pfad'), '/\\'));
        $normal = $ordner.'/comic.ttf';
        if ($ordner === '' || ! is_file($normal)) {
            return null;
        }
        $bold = $ordner.'/comicbd.ttf';

        return ['normal' => $normal, 'bold' => is_file($bold) ? $bold : null];
    }

    /**
     * CSS-Schriftfamilie für DomPDF. DomPDF kennt nur eingebettete bzw. registrierte Schriften –
     * fehlt Comic Sans, wird serifenlos gesetzt statt auf Times zurückzufallen.
     */
    public function schriftCss(?string $schriftart, bool $comicSansVorhanden): string
    {
        $schriftart = trim((string) $schriftart);
        if ($schriftart === '') {
            return 'Helvetica, Arial, sans-serif';
        }
        if (stripos($schriftart, 'comic') !== false) {
            return $comicSansVorhanden ? "'WpComicSans', Helvetica, sans-serif" : 'Helvetica, Arial, sans-serif';
        }
        if (stripos($schriftart, 'arial') !== false) {
            return 'Helvetica, Arial, sans-serif';
        }

        return $schriftart;
    }

    /**
     * Zeigt das PDF direkt im Browser an (stream).
     */
    public function stream(WpPlan $plan): Response
    {
        return $this->generate($plan)->stream($this->filename($plan));
    }

    /**
     * Lädt das PDF als Datei herunter.
     */
    public function download(WpPlan $plan): Response
    {
        return $this->generate($plan)->download($this->filename($plan));
    }

    /**
     * Streamt die PDF mit angehängten Arbeitsblättern.
     */
    public function streamWithAttachments(WpPlan $plan): \Symfony\Component\HttpFoundation\Response
    {
        $mergeService = app(WpPdfMergeService::class);

        // 1. Wochenplan-PDF als temporäre Datei
        $tempDir = storage_path('app/temp');
        if (!is_dir($tempDir)) {
            mkdir($tempDir, 0755, true);
        }

        $tempPdfPath = $tempDir . '/' . uniqid('wp_') . '.pdf';
        file_put_contents($tempPdfPath, $this->generate($plan)->output());

        // 2. Arbeitsblätter anhängen
        $finalPdfPath = $mergeService->mergeWithAttachments($tempPdfPath, $plan);

        // 3. Streamen
        $filename = $this->filename($plan);
        $content  = file_get_contents($finalPdfPath);

        // Aufräumen
        @unlink($tempPdfPath);
        if ($finalPdfPath !== $tempPdfPath) {
            @unlink($finalPdfPath);
        }

        return response($content, 200, [
            'Content-Type'        => 'application/pdf',
            'Content-Disposition' => 'inline; filename="' . $filename . '"',
        ]);
    }

    /**
     * Download der PDF mit angehängten Arbeitsblättern.
     */
    public function downloadWithAttachments(WpPlan $plan): \Symfony\Component\HttpFoundation\Response
    {
        $mergeService = app(WpPdfMergeService::class);

        $tempDir = storage_path('app/temp');
        if (!is_dir($tempDir)) {
            mkdir($tempDir, 0755, true);
        }

        $tempPdfPath = $tempDir . '/' . uniqid('wp_') . '.pdf';
        file_put_contents($tempPdfPath, $this->generate($plan)->output());

        $finalPdfPath = $mergeService->mergeWithAttachments($tempPdfPath, $plan);

        $filename = $this->filename($plan);

        // Wochenplan-Temp aufräumen
        @unlink($tempPdfPath);

        // Download, danach merged-PDF löschen
        return response()->download($finalPdfPath, $filename)->deleteFileAfterSend(true);
    }

    /**
     * Generiert den Dateinamen für den PDF-Export.
     */
    public function filename(WpPlan $plan): string
    {
        $name = str_replace(' ', '_', $plan->name);
        $name = preg_replace('/[^A-Za-z0-9_\-äöüÄÖÜ]/', '', $name);

        if ($plan->isSchuelerplan() && $plan->schueler) {
            $name .= '_' . $plan->schueler->vorname . '_' . $plan->schueler->nachname;
        } elseif ($plan->isKlassenplan() && $plan->klasse) {
            $name .= '_' . $plan->klasse->name;
        }

        return $name . '.pdf';
    }
}

