<?php

namespace App\Http\Controllers\Wiki;

use App\Http\Controllers\Controller;
use App\Models\WikiLink;
use App\Models\WikiRevision;
use App\Models\WikiSeite;
use App\Services\Wiki\WikiAnhangService;
use App\Services\Wiki\WikiDiff;
use App\Services\Wiki\WikiService;
use App\Services\Wiki\WikiSuche;
use App\Services\Wiki\WikiZugriff;
use App\Support\PdfLayout;
use App\Support\WikiHtml;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * Wiki – Lesen: Startseite, Seite, Verlauf/Vergleich, Suche, A–Z, letzte Änderungen, PDF, Favoriten.
 */
class WikiController extends Controller
{
    public function __construct(private WikiService $wiki, private WikiAnhangService $anhaenge)
    {
    }

    public function index(Request $request): View
    {
        $user = $request->user();
        $sichtbar = WikiZugriff::sichtbareIds($user);
        $start = WikiSeite::where('slug', WikiSeite::START_SLUG)->first();
        $einleitung = $start ? WikiHtml::aufbereiten($start->inhalt_html, $this->wiki->linkPruefer($user))['html'] : '';

        $favoriten = WikiSeite::query()
            ->whereIn('id', DB::table('wiki_favoriten')->where('user_id', $user->id)->pluck('seite_id'))
            ->whereIn('id', array_keys($sichtbar))
            ->orderBy('titel')
            ->get();

        return view('wiki.index', [
            'start' => $start,
            'einleitung' => $einleitung,
            'favoriten' => $favoriten,
            'zuletzt' => $this->zuletztGeaendert($sichtbar, 8),
            'bereiche' => $this->wiki->baum($user),
            'anzahl' => count($sichtbar),
        ]);
    }

    public function show(Request $request, string $slug, ?string $alteVersion = null): View|RedirectResponse
    {
        [$seite, $umgeleitet] = $this->wiki->finde($slug);
        $user = $request->user();

        if (! $seite) {
            if ($user->can('create', WikiSeite::class)) {
                return redirect()->route('wiki.create', ['titel' => Str::of($slug)->replace(['-', '_'], ' ')->trim()->toString()]);
            }
            abort(404);
        }
        if ($seite->istStartseite()) {
            return redirect()->route('wiki');
        }
        $this->authorize('view', $seite);
        if ($umgeleitet || $alteVersion !== null || $slug !== $seite->slug) {
            return redirect()->route('wiki.show', $seite->slug, 301);
        }

        $aufbereitet = WikiHtml::aufbereiten($seite->inhalt_html, $this->wiki->linkPruefer($user));
        $sichtbar = WikiZugriff::sichtbareIds($user);

        $kinder = $seite->kinder()->get(['id', 'titel', 'slug', 'sortierung'])
            ->filter(fn (WikiSeite $k) => isset($sichtbar[$k->id]))
            ->sort(fn ($a, $b) => [$a->sortierung ?? PHP_INT_MAX, WikiService::sortTitel($a->titel)] <=> [$b->sortierung ?? PHP_INT_MAX, WikiService::sortTitel($b->titel)])
            ->values();

        $backlinks = WikiSeite::query()
            ->whereIn('id', WikiLink::where('ziel_seite_id', $seite->id)->pluck('von_seite_id'))
            ->whereIn('id', array_keys($sichtbar))
            ->where('id', '!=', $seite->id)
            ->orderBy('titel')
            ->get(['id', 'titel', 'slug']);

        $einschraenkend = WikiZugriff::einschraenkendeSeite($seite);

        return view('wiki.show', [
            'seite' => $seite->load('tags', 'geaendert', 'erstellt'),
            'inhalt' => $aufbereitet['html'],
            'toc' => $aufbereitet['toc'],
            'pfad' => $this->wiki->pfad($seite),
            'kinder' => $kinder,
            'backlinks' => $backlinks,
            'dateien' => $this->anhaenge->dateien($seite),
            'favorit' => $this->wiki->istFavorit($seite, $user),
            'einschraenkend' => $einschraenkend,
            'freigaben' => $einschraenkend ? WikiZugriff::freigabenText($einschraenkend) : collect(),
            'revisionen' => $seite->revisionen()->count(),
        ]);
    }

    public function verlauf(Request $request, string $slug): View|RedirectResponse
    {
        $seite = $this->seiteOder404($slug);
        $this->authorize('view', $seite);

        return view('wiki.verlauf', [
            'seite' => $seite,
            'pfad' => $this->wiki->pfad($seite),
            'revisionen' => $seite->revisionen()->with('user:id,name')->paginate(50),
        ]);
    }

    public function revision(Request $request, string $slug, WikiRevision $revision): View
    {
        $seite = $this->seiteOder404($slug);
        $this->authorize('view', $seite);
        abort_unless($revision->seite_id === $seite->id, 404);

        $aufbereitet = WikiHtml::aufbereiten($revision->inhalt_html, $this->wiki->linkPruefer($request->user()));

        return view('wiki.revision', [
            'seite' => $seite,
            'pfad' => $this->wiki->pfad($seite),
            'revision' => $revision->load('user:id,name'),
            'inhalt' => $aufbereitet['html'],
            'vorgaenger' => $seite->revisionen()->where(fn ($q) => $q->where('created_at', '<', $revision->created_at)
                ->orWhere(fn ($w) => $w->where('created_at', $revision->created_at)->where('id', '<', $revision->id)))->first(),
            'aktuell' => $seite->revisionen()->first()?->id === $revision->id,
        ]);
    }

    public function vergleich(Request $request, string $slug, WikiDiff $diff): View
    {
        $seite = $this->seiteOder404($slug);
        $this->authorize('view', $seite);

        $revisionen = $seite->revisionen()->get(['id', 'seite_id', 'titel', 'created_at', 'user_id', 'notiz']);
        $bis = $request->integer('bis') ? $revisionen->firstWhere('id', $request->integer('bis')) : $revisionen->first();
        abort_unless($bis, 404);
        $von = $request->integer('von')
            ? $revisionen->firstWhere('id', $request->integer('von'))
            : $revisionen->first(fn ($r) => $r->created_at < $bis->created_at || ($r->created_at == $bis->created_at && $r->id < $bis->id));

        $alt = $von ? WikiRevision::find($von->id) : null;
        $neu = WikiRevision::find($bis->id);
        $ergebnis = $diff->vergleich($alt?->inhalt, $neu->inhalt);

        return view('wiki.vergleich', [
            'seite' => $seite,
            'pfad' => $this->wiki->pfad($seite),
            'revisionen' => $revisionen->load('user:id,name'),
            'von' => $alt?->load('user:id,name'),
            'bis' => $neu->load('user:id,name'),
            'diff' => $ergebnis,
            'titelGeaendert' => $alt && $alt->titel !== $neu->titel,
        ]);
    }

    public function suche(Request $request, WikiSuche $suche): View|JsonResponse
    {
        $begriff = Str::limit(trim((string) $request->query('q', '')), 100, '');
        $limit = $request->wantsJson() ? 10 : 50;
        $treffer = mb_strlen($begriff) >= 2 ? $suche->suchen($request->user(), $begriff, $limit) : [];

        if ($request->wantsJson()) {
            return response()->json(['treffer' => $treffer]);
        }

        return view('wiki.suche', ['begriff' => $begriff, 'treffer' => $treffer]);
    }

    public function alle(Request $request): View
    {
        $sichtbar = WikiZugriff::sichtbareIds($request->user());
        $seiten = WikiSeite::query()->whereIn('id', array_keys($sichtbar))
            ->where('slug', '!=', WikiSeite::START_SLUG)
            ->get(['id', 'titel', 'slug', 'parent_id', 'bearbeitet_at'])
            ->sortBy(fn ($s) => WikiService::sortTitel($s->titel))
            ->groupBy(function (WikiSeite $s) {
                $erster = mb_strtoupper(mb_substr(Str::ascii($s->titel), 0, 1));

                return preg_match('/[A-Z]/', $erster) ? $erster : '#';
            })
            ->sortKeys();

        if ($seiten->has('#')) {
            $seiten = collect(['#' => $seiten->pull('#')])->merge($seiten);
        }

        return view('wiki.alle', ['gruppen' => $seiten]);
    }

    public function aenderungen(Request $request): View
    {
        $sichtbar = WikiZugriff::sichtbareIds($request->user());

        $revisionen = WikiRevision::query()
            ->whereIn('seite_id', array_keys($sichtbar))
            ->with(['user:id,name', 'seite:id,titel,slug'])
            ->orderByDesc('created_at')->orderByDesc('id')
            ->paginate(50);

        return view('wiki.aenderungen', ['revisionen' => $revisionen]);
    }

    public function pdf(Request $request, string $slug, PdfLayout $layout): Response
    {
        $seite = $this->seiteOder404($slug);
        $this->authorize('view', $seite);

        $aufbereitet = WikiHtml::aufbereiten($seite->inhalt_html, $this->wiki->linkPruefer($request->user()));
        $html = $this->bilderEinbetten($seite, $aufbereitet['html']);

        $pdf = Pdf::loadView('pdf.wiki_seite', [
            'seite' => $seite->load('geaendert'),
            'pfad' => $this->wiki->pfad($seite),
            'inhalt' => $html,
            'kopf' => $layout->schulkopf(),
            'stand' => now(),
        ])->setPaper('a4');

        return response($layout->rendern($pdf), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="'.(Str::slug($seite->titel, '-', 'de') ?: 'wiki').'.pdf"',
            'Cache-Control' => 'no-store, private',
        ]);
    }

    public function favorit(Request $request, WikiSeite $seite): JsonResponse|RedirectResponse
    {
        $this->authorize('view', $seite);
        $favorit = $this->wiki->favoritUmschalten($seite, $request->user());

        if ($request->wantsJson()) {
            return response()->json(['favorit' => $favorit]);
        }

        return back()->with(['type' => 'success', 'Meldung' => $favorit ? 'Zu den Favoriten hinzugefügt.' : 'Aus den Favoriten entfernt.']);
    }

    // ------------------------------------------------------------------

    private function seiteOder404(string $slug): WikiSeite
    {
        [$seite] = $this->wiki->finde($slug);
        abort_unless($seite, 404);

        return $seite;
    }

    private function zuletztGeaendert(array $sichtbar, int $anzahl)
    {
        return WikiSeite::query()
            ->whereIn('id', array_keys($sichtbar))
            ->where('slug', '!=', WikiSeite::START_SLUG)
            ->with('geaendert:id,name')
            ->orderByDesc('bearbeitet_at')
            ->limit($anzahl)
            ->get(['id', 'titel', 'slug', 'bearbeitet_at', 'geaendert_von']);
    }

    /** Für DomPDF: Wiki-Bilder als data-URI einbetten (die Route ist ohne Sitzung nicht erreichbar). */
    private function bilderEinbetten(WikiSeite $seite, string $html): string
    {
        return preg_replace_callback('#src="[^"]*/wiki/anhang/(\d+)[^"]*"#', function ($m) use ($seite) {
            $media = Media::find((int) $m[1]);
            if (! $media || $media->model_type !== WikiSeite::class || (int) $media->model_id !== $seite->id || ! is_file($media->getPath())) {
                return 'src=""';
            }

            return 'src="data:'.$media->mime_type.';base64,'.base64_encode(file_get_contents($media->getPath())).'"';
        }, $html) ?? $html;
    }
}
