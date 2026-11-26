<?php

namespace App\View\Composers;

use App\Models\WikiSeite;
use App\Services\Wiki\WikiService;
use Illuminate\View\View;

/**
 * Rahmen aller Wiki-Seiten (wiki.layout): Seitenbaum der sichtbaren Seiten, aktive Seite + ihre Vorfahren
 * (aufgeklappt), Rechte für die Kopfleiste.
 */
class WikiLayoutComposer
{
    public function __construct(private WikiService $wiki)
    {
    }

    public function compose(View $view): void
    {
        $user = auth()->user();
        $seite = $view->getData()['seite'] ?? null;
        $aktiv = $seite instanceof WikiSeite ? $seite : null;

        $offen = [];
        if ($aktiv) {
            $offen = $this->wiki->pfad($aktiv)->pluck('id')->push($aktiv->id)->all();
        }

        $view->with([
            'wikiBaum' => $user ? $this->wiki->baum($user) : collect(),
            'wikiAktivId' => $aktiv?->id,
            'wikiOffen' => array_flip($offen),
            'wikiDarfAnlegen' => $user?->can('create', WikiSeite::class) ?? false,
            'wikiDarfVerwalten' => $user?->can('manage wiki') ?? false,
        ]);
    }
}
