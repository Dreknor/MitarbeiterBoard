<?php

namespace App\Http\Controllers\Wochenplan;

use App\Http\Controllers\Controller;
use App\Models\Wochenplan\WpPlan;
use App\Services\Wochenplan\WpExportService;
use App\Services\Wochenplan\WpPdfService;
use App\Services\Wochenplan\WpWordService;
use Illuminate\Http\Request;

class WpExportController extends Controller
{
    public function __construct(
        private WpPdfService $pdfService,
        private WpWordService $wordService,
        private WpExportService $exportService,
    ) {
    }

    public function pdf(WpPlan $wpPlan, Request $request)
    {
        $this->authorize('view', $wpPlan);

        // ?attachments=0 → PDF ohne Arbeitsblätter
        if ($request->boolean('attachments', true) && $wpPlan->alleDateien()->isNotEmpty()) {
            return $this->pdfService->streamWithAttachments($wpPlan);
        }

        return $this->pdfService->stream($wpPlan);
    }

    public function word(WpPlan $wpPlan)
    {
        $this->authorize('view', $wpPlan);

        return $this->wordService->download($wpPlan);
    }

    public function vorschau(WpPlan $wpPlan)
    {
        $this->authorize('view', $wpPlan);

        $wpPlan->load(['planFaecher.aufgaben', 'planFaecher.fach', 'taeglicheUebungen', 'klasse', 'schueler', 'formatvorlage']);
        $formatvorlage = $wpPlan->getEffectiveFormatvorlage();

        return view('wochenplan.export.vorschau', [
            'plan' => $wpPlan,
            'formatvorlage' => $formatvorlage,
            'config' => $formatvorlage->layout_config ?? [],
        ]);
    }

    /** Alle Kinderpläne eines Klassenplans als ein PDF (ohne Arbeitsblätter). */
    public function kinderplaenePdf(WpPlan $wpPlan)
    {
        $this->authorize('view', $wpPlan);
        abort_unless($wpPlan->isKlassenplan(), 404);

        $pfad = $this->exportService->kinderplaenePdf($wpPlan, request()->user());
        abort_if($pfad === null, 404, 'Zu diesem Plan gibt es keine Kinderpläne.');

        return response()->download($pfad, $this->exportService->sammelName($wpPlan).'.pdf', [
            'Content-Type' => 'application/pdf',
        ])->deleteFileAfterSend(true);
    }

    /** Alle Kinderpläne eines Klassenplans als ZIP (je Kind PDF und Word). */
    public function kinderplaeneZip(WpPlan $wpPlan)
    {
        $this->authorize('view', $wpPlan);
        abort_unless($wpPlan->isKlassenplan(), 404);

        $pfad = $this->exportService->kinderplaeneZip($wpPlan, request()->user());
        abort_if($pfad === null, 404, 'Zu diesem Plan gibt es keine Kinderpläne.');

        return response()->download($pfad, $this->exportService->sammelName($wpPlan).'.zip', [
            'Content-Type' => 'application/zip',
        ])->deleteFileAfterSend(true);
    }
}
