<?php

namespace App\Services\Wochenplan;

use App\Models\Wochenplan\WpPlan;
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
            'schueler',
            'formatvorlage',
        ]);

        $formatvorlage = $plan->getEffectiveFormatvorlage();
        $config        = $formatvorlage->layout_config ?? [];

        // Blade-Template aus Formatvorlage, Fallback auf Standard
        $template = $formatvorlage->blade_template ?? 'wochenplan.pdf.standard';

        // Seitenformat und Ränder aus Config
        $papierGroesse    = strtolower($config['papier']['groesse']    ?? 'A4');
        $papierAusrichtung = $config['papier']['ausrichtung'] ?? 'portrait';

        $pdf = Pdf::loadView($template, [
            'plan'          => $plan,
            'formatvorlage' => $formatvorlage,
            'config'        => $config,
        ]);

        $pdf->setPaper($papierGroesse, $papierAusrichtung);

        $dompdf = $pdf->getDomPDF();
        $dompdf->set_option('defaultPaperSize', 'A4');
        // Lokale Fonts über Dateipfad laden erlauben (isRemoteEnabled ermöglicht file://-Zugriff)
        $dompdf->set_option('isRemoteEnabled', true);

        // NotoSansSymbols2 für Emoji/Symbol-Rendering registrieren
        $fontMetrics = $dompdf->getFontMetrics();
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

