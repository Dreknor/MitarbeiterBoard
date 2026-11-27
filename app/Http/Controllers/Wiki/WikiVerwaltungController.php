<?php

namespace App\Http\Controllers\Wiki;

use App\Http\Controllers\Controller;
use App\Models\Group;
use App\Models\WikiSeite;
use App\Models\WikiTag;
use App\Services\Wiki\WikiService;
use App\Services\Wiki\WikiZugriff;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Spatie\Permission\Models\Role;

/**
 * Wiki – Verwaltung (`manage wiki`): Papierkorb, Schutz, Einschränkung auf Rollen/Gruppen, Struktur/Sortierung,
 * Tags umbenennen/zusammenführen/löschen. Tag-Übersicht und Tag-Seiten sind für alle Lesenden.
 */
class WikiVerwaltungController extends Controller
{
    public function __construct(private WikiService $wiki)
    {
    }

    // ------------------------------------------------------------------
    // Seiteneinstellungen
    // ------------------------------------------------------------------

    public function einstellungen(Request $request, WikiSeite $seite): View
    {
        $this->authorize('manage', $seite);

        return view('wiki.einstellungen', [
            'seite' => $seite,
            'pfad' => $this->wiki->pfad($seite),
            'rollen' => Role::orderBy('name')->get(['id', 'name']),
            'gruppen' => Group::orderBy('name')->get(['id', 'name']),
            'gewaehlteRollen' => $seite->einschraenkungen()->where('berechtigter_type', Role::class)->pluck('berechtigter_id')->all(),
            'gewaehlteGruppen' => $seite->einschraenkungen()->where('berechtigter_type', Group::class)->pluck('berechtigter_id')->all(),
            'geerbtVon' => $this->geerbtVon($seite),
        ]);
    }

    public function schutz(Request $request, WikiSeite $seite): RedirectResponse
    {
        $this->authorize('manage', $seite);
        $this->wiki->schuetzen($seite, $request->boolean('geschuetzt'));

        return back()->with(['type' => 'success', 'Meldung' => $seite->geschuetzt ? 'Seite geschützt.' : 'Schutz aufgehoben.']);
    }

    public function einschraenkung(Request $request, WikiSeite $seite): RedirectResponse
    {
        $this->authorize('manage', $seite);
        $daten = $request->validate([
            'rollen' => ['nullable', 'array'],
            'rollen.*' => ['integer'],
            'gruppen' => ['nullable', 'array'],
            'gruppen.*' => ['integer'],
        ]);

        $this->wiki->einschraenken($seite, $daten['rollen'] ?? [], $daten['gruppen'] ?? []);
        $aktiv = $seite->einschraenkungen()->exists();

        return back()->with(['type' => 'success', 'Meldung' => $aktiv ? 'Einschränkung gespeichert.' : 'Die Seite ist wieder für alle Lesenden sichtbar.']);
    }

    // ------------------------------------------------------------------
    // Papierkorb
    // ------------------------------------------------------------------

    public function loeschen(Request $request, WikiSeite $seite): RedirectResponse
    {
        $this->authorize('delete', $seite);
        $this->wiki->loeschen($seite, $request->user());

        return redirect()->route('wiki')->with(['type' => 'success', 'Meldung' => '„'.$seite->titel.'“ liegt jetzt im Papierkorb.']);
    }

    public function papierkorb(Request $request): View
    {
        abort_unless($request->user()->can('manage wiki'), 403);

        return view('wiki.papierkorb', [
            'seiten' => WikiSeite::onlyTrashed()->with('geaendert:id,name')
                ->leftJoin('users', 'users.id', '=', 'wiki_seiten.geloescht_von')
                ->orderByDesc('wiki_seiten.deleted_at')
                ->get(['wiki_seiten.*', 'users.name as geloescht_von_name']),
        ]);
    }

    public function ausPapierkorb(Request $request, int $id): RedirectResponse
    {
        $seite = WikiSeite::onlyTrashed()->findOrFail($id);
        $this->authorize('restore', $seite);
        $this->wiki->ausPapierkorbHolen($seite);

        return redirect()->to($seite->url())->with(['type' => 'success', 'Meldung' => 'Seite wiederhergestellt.']);
    }

    // ------------------------------------------------------------------
    // Struktur
    // ------------------------------------------------------------------

    public function struktur(Request $request): View
    {
        abort_unless($request->user()->can('manage wiki'), 403);

        return view('wiki.struktur', ['baum' => $this->wiki->baum($request->user())]);
    }

    public function strukturSpeichern(Request $request): JsonResponse
    {
        abort_unless($request->user()->can('manage wiki'), 403);
        $daten = $request->validate([
            'knoten' => ['required', 'array'],
            'knoten.*.id' => ['required', 'integer'],
            'knoten.*.parent_id' => ['nullable', 'integer'],
            'knoten.*.sortierung' => ['required', 'integer', 'min:0'],
        ]);

        $this->wiki->strukturSpeichern($daten['knoten']);

        return response()->json(['message' => 'Struktur gespeichert.']);
    }

    public function alphabetisch(Request $request): RedirectResponse
    {
        abort_unless($request->user()->can('manage wiki'), 403);
        $this->wiki->alphabetischSortieren();

        return back()->with(['type' => 'success', 'Meldung' => 'Alle Ebenen werden wieder alphabetisch sortiert.']);
    }

    // ------------------------------------------------------------------
    // Tags
    // ------------------------------------------------------------------

    public function tags(Request $request): View
    {
        $sichtbar = array_keys(WikiZugriff::sichtbareIds($request->user()));
        $anzahl = DB::table('wiki_seite_tag')->whereIn('seite_id', $sichtbar)
            ->select('tag_id', DB::raw('count(*) as anzahl'))->groupBy('tag_id')->pluck('anzahl', 'tag_id');

        $tags = WikiTag::orderBy('name')->get()
            ->each(fn (WikiTag $t) => $t->anzahl = (int) ($anzahl[$t->id] ?? 0))
            ->filter(fn (WikiTag $t) => $t->anzahl > 0 || $request->user()->can('manage wiki'))
            ->values();

        return view('wiki.tags', ['tags' => $tags]);
    }

    public function tag(Request $request, WikiTag $tag): View
    {
        $sichtbar = array_keys(WikiZugriff::sichtbareIds($request->user()));

        return view('wiki.tag', [
            'tag' => $tag,
            'seiten' => $tag->seiten()->whereIn('wiki_seiten.id', $sichtbar)->orderBy('titel')->get(),
            'alleTags' => $request->user()->can('manage wiki') ? WikiTag::where('id', '!=', $tag->id)->orderBy('name')->get(['id', 'name']) : collect(),
        ]);
    }

    public function tagUmbenennen(Request $request, WikiTag $tag): RedirectResponse
    {
        abort_unless($request->user()->can('manage wiki'), 403);
        $name = $request->validate(['name' => ['required', 'string', 'max:40']])['name'];
        $ziel = $this->wiki->tagUmbenennen($tag, $name);

        return redirect()->route('wiki.tag', $ziel)->with(['type' => 'success', 'Meldung' => $ziel->id === $tag->id ? 'Tag umbenannt.' : 'Tags zusammengeführt.']);
    }

    public function tagZusammenfuehren(Request $request, WikiTag $tag): RedirectResponse
    {
        abort_unless($request->user()->can('manage wiki'), 403);
        $ziel = WikiTag::findOrFail($request->validate(['ziel' => ['required', 'integer']])['ziel']);
        $this->wiki->tagZusammenfuehren($tag, $ziel);

        return redirect()->route('wiki.tag', $ziel)->with(['type' => 'success', 'Meldung' => 'Tags zusammengeführt.']);
    }

    public function tagLoeschen(Request $request, WikiTag $tag): RedirectResponse
    {
        abort_unless($request->user()->can('manage wiki'), 403);
        $tag->delete();

        return redirect()->route('wiki.tags')->with(['type' => 'success', 'Meldung' => 'Tag gelöscht.']);
    }

    // ------------------------------------------------------------------

    private function geerbtVon(WikiSeite $seite): ?WikiSeite
    {
        if (! $seite->parent_id) {
            return null;
        }
        $eltern = WikiSeite::find($seite->parent_id);

        return $eltern ? WikiZugriff::einschraenkendeSeite($eltern) : null;
    }
}
