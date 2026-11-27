<?php

namespace App\Http\Controllers\Wochenplan;

use App\Http\Controllers\Controller;
use App\Models\Wochenplan\WpAufgabe;
use App\Models\Wochenplan\WpBaustein;
use App\Models\Wochenplan\WpFach;
use App\Models\Wochenplan\WpPlan;
use App\Services\Wochenplan\WochenplanService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Aufgaben-Bibliothek: wiederverwendbare Aufgaben pro Fach und Klassenstufe.
 * Alle mit `create wochenplan` dürfen suchen und Bausteine anlegen; löschen dürfen
 * Ersteller:in und Personen mit `manage wochenplan-faecher`.
 */
class WpBibliothekController extends Controller
{
    public function __construct(private WochenplanService $service)
    {
    }

    public function index(Request $request)
    {
        $this->authorize('create', WpPlan::class);

        if (! $request->expectsJson()) {
            return view('wochenplan.new.bibliothek', [
                'faecher' => WpFach::ordered()->get(['id', 'name']),
            ]);
        }

        $query = WpBaustein::with(['fach:id,name', 'creator:id,name']);
        if ($request->filled('wp_fach_id')) {
            $query->where('wp_fach_id', $request->integer('wp_fach_id'));
        }
        if ($request->filled('klassenstufe')) {
            $stufe = $request->integer('klassenstufe');
            $query->where(fn ($q) => $q->whereNull('klassenstufe')->orWhere('klassenstufe', $stufe));
        }
        if ($request->filled('q')) {
            $suche = '%'.str_replace(['%', '_'], ['\%', '\_'], trim((string) $request->input('q'))).'%';
            $query->where('aufgabe', 'like', $suche);
        }

        $user = $request->user();

        return response()->json([
            'bausteine' => $query->orderBy('wp_fach_id')->orderBy('klassenstufe')->orderBy('aufgabe')->limit(200)->get()
                ->map(fn (WpBaustein $b) => [
                    'id' => $b->id,
                    'aufgabe' => $b->aufgabe,
                    'dauer' => $b->dauer,
                    'niveau' => $b->niveau,
                    'link' => $b->link,
                    'klassenstufe' => $b->klassenstufe,
                    'wp_fach_id' => $b->wp_fach_id,
                    'fach' => $b->fach?->name,
                    'von' => $b->creator?->name,
                    'darf_loeschen' => $this->darfLoeschen($user, $b),
                ]),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $this->authorize('create', WpPlan::class);
        $daten = $request->validate([
            'aufgabe' => 'required|string|max:2000',
            'dauer' => 'nullable|string|max:50',
            'niveau' => 'nullable|integer|in:1,2,3',
            'link' => 'nullable|url:http,https|max:500',
            'wp_fach_id' => 'nullable|integer|exists:wp_faecher,id',
            'klassenstufe' => 'nullable|integer|min:1|max:13',
        ]);

        $baustein = WpBaustein::create(array_merge($daten, ['created_by' => $request->user()->id]));

        return response()->json(['id' => $baustein->id, 'meldung' => 'In die Bibliothek übernommen.'], 201);
    }

    /** Aufgabe eines Plans in die Bibliothek übernehmen (Fach und Klassenstufe aus dem Plan). */
    public function ausAufgabe(Request $request, WpPlan $wpPlan, WpAufgabe $wpAufgabe): JsonResponse
    {
        $this->authorize('view', $wpPlan);
        $this->authorize('create', WpPlan::class);
        abort_unless($wpAufgabe->planFach && (int) $wpAufgabe->planFach->wp_plan_id === (int) $wpPlan->id, 404);

        $klasse = $wpPlan->klasse?->name ?? $wpPlan->schueler?->klasse?->name;
        $baustein = WpBaustein::firstOrCreate([
            'wp_fach_id' => $wpAufgabe->planFach->wp_fach_id,
            'klassenstufe' => WochenplanService::klassenstufe($klasse),
            'aufgabe' => $wpAufgabe->aufgabe,
        ], [
            'dauer' => $wpAufgabe->dauer,
            'niveau' => $wpAufgabe->niveau,
            'link' => $wpAufgabe->link,
            'created_by' => $request->user()->id,
        ]);

        return response()->json([
            'id' => $baustein->id,
            'meldung' => $baustein->wasRecentlyCreated ? 'In die Bibliothek übernommen.' : 'Diese Aufgabe ist bereits in der Bibliothek.',
        ]);
    }

    public function destroy(Request $request, WpBaustein $wpBaustein): JsonResponse
    {
        $this->authorize('create', WpPlan::class);
        abort_unless($this->darfLoeschen($request->user(), $wpBaustein), 403);

        $wpBaustein->delete();

        return response()->json(['meldung' => 'Aus der Bibliothek entfernt.']);
    }

    private function darfLoeschen($user, WpBaustein $baustein): bool
    {
        return (int) $baustein->created_by === (int) $user->id
            || $user->can('manage wochenplan-faecher')
            || WpPlan::darfAlles($user);
    }
}
