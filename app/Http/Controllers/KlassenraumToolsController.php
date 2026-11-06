<?php

namespace App\Http\Controllers;

use App\Support\KlassenraumTools;

/**
 * Klassenraum-Tools: Übersicht und eigenständige Vollbildseiten (Beamer/Tafel).
 *
 * Zugriff über die Permission "use classroom tools" (Route-Middleware).
 */
class KlassenraumToolsController extends Controller
{
    public function index()
    {
        return view('klassenraum.index', [
            'kategorien' => KlassenraumTools::nachKategorie(),
        ]);
    }

    public function show(string $tool)
    {
        $eintrag = KlassenraumTools::finde($tool);

        abort_if($eintrag === null, 404);

        return view('klassenraum.tool', [
            'schluessel' => $tool,
            'tool' => $eintrag,
        ]);
    }
}
