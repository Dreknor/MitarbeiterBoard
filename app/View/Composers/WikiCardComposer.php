<?php

namespace App\View\Composers;

use App\Models\WikiSeite;
use App\Services\Wiki\WikiZugriff;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * Dashboard-Karte Wiki (v1/v2): zuletzt geänderte und eigene Favoriten – nur sichtbare Seiten.
 */
class WikiCardComposer
{
    public function compose(View $view): void
    {
        $user = auth()->user();
        if (! $user) {
            $view->with(['sites' => collect(), 'favoriten' => collect()]);

            return;
        }

        $sichtbar = array_keys(WikiZugriff::sichtbareIds($user));

        $view->with([
            'sites' => WikiSeite::query()
                ->whereIn('id', $sichtbar)
                ->where('slug', '!=', WikiSeite::START_SLUG)
                ->orderByDesc('bearbeitet_at')->orderByDesc('id')
                ->limit(6)
                ->get(['id', 'titel', 'slug', 'bearbeitet_at']),
            'favoriten' => WikiSeite::query()
                ->whereIn('id', $sichtbar)
                ->whereIn('id', DB::table('wiki_favoriten')->where('user_id', $user->id)->pluck('seite_id'))
                ->orderBy('titel')
                ->limit(8)
                ->get(['id', 'titel', 'slug', 'bearbeitet_at']),
        ]);
    }
}
