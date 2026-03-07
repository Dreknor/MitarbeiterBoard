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
            'klasse',
            'schueler',
            'formatvorlage',
        ]);

        $formatvorlage = $plan->getEffectiveFormatvorlage();
        $config        = $formatvorlage->layout_config ?? [];

        // Blade-Template aus Formatvorlage, Fallback auf Standard
        $template = $formatvorlage->blade_template ?? 'wochenplan.pdf.standard';

        $pdf = Pdf::loadView($template, [
            'plan'          => $plan,
            'formatvorlage' => $formatvorlage,
            'config'        => $config,
        ]);

        // Seitenformat und Ränder
        $pdf->setPaper('A4', 'portrait');

        // Margins aus Config (in mm), Fallback auf Standardwerte
        $margins = $config['seitenraender'] ?? ['oben' => 15, 'rechts' => 15, 'unten' => 15, 'links' => 15];
        $pdf->getDomPDF()->set_option('defaultPaperSize', 'A4');

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

