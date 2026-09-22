<?php

namespace App\Http\Controllers\Wochenplan;

use App\Http\Controllers\Controller;
use App\Models\Wochenplan\WpPlan;
use App\Services\Wochenplan\WpPdfService;
use App\Services\Wochenplan\WpWordService;
use Illuminate\Http\Request;

class WpExportController extends Controller
{
    public function __construct(
        private WpPdfService $pdfService,
        private WpWordService $wordService,
    ) {}

    public function pdf(WpPlan $wpPlan, Request $request)
    {
        // Query-Parameter ?attachments=0 erlaubt PDF ohne Anhänge
        $withAttachments = $request->boolean('attachments', true);

        if ($withAttachments && $wpPlan->getMedia('arbeitsblaetter')->isNotEmpty()) {
            return $this->pdfService->streamWithAttachments($wpPlan);
        }

        return $this->pdfService->stream($wpPlan);
    }

    public function word(WpPlan $wpPlan)
    {
        return $this->wordService->download($wpPlan);
    }

    public function vorschau(WpPlan $wpPlan)
    {
        $wpPlan->load([
            'planFaecher.aufgaben',
            'planFaecher.fach',
            'taeglicheUebungen',
            'klasse',
            'schueler',
            'formatvorlage',
        ]);

        $formatvorlage = $wpPlan->getEffectiveFormatvorlage();

        return view('wochenplan.export.vorschau', [
            'plan'          => $wpPlan,
            'formatvorlage' => $formatvorlage,
            'config'        => $formatvorlage->layout_config ?? [],
        ]);
    }
}
