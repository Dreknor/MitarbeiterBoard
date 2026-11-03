<?php

namespace App\Http\Controllers;

use App\Http\Requests\API\v1\DossierRequest;
use App\Models\Schueler;
use App\Services\Api\DossierService;

/**
 * Pädagogisches Tagebuch (Web): Dossier als PDF – gleiche Daten und View wie die API (/dossier.pdf).
 */
class PaedDiaryDossierController extends Controller
{
    public function pdf(DossierRequest $request, Schueler $schueler, DossierService $dossier)
    {
        $this->authorize('view', $schueler);

        return $dossier->pdfResponse($dossier->build($schueler, $request->user(), $request));
    }
}
