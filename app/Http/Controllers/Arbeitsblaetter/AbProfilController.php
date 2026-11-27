<?php

namespace App\Http\Controllers\Arbeitsblaetter;

use App\Http\Controllers\Controller;
use App\Models\Arbeitsblaetter\AbProfilVorlage;
use App\Models\Arbeitsblaetter\AngepasstesBlatt;
use App\Models\Arbeitsblaetter\Sehprofil;
use App\Models\Schueler;
use App\Services\Arbeitsblaetter\AbSatzService;
use App\Services\Arbeitsblaetter\SehprofilService;
use App\Support\AbProfil;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Sehprofile je Klasse/Kind und Profil-Vorlagen. Anzeigen: alle mit `create adapted worksheets`,
 * pflegen: `manage visual profiles` (SehprofilPolicy). Live-Beispielblatt mit dem Profil.
 */
class AbProfilController extends Controller
{
    public function __construct(private SehprofilService $profile)
    {
    }

    public function index(Request $request)
    {
        $this->authorize('viewAny', Sehprofil::class);
        $user = $request->user();
        $klassen = $this->profile->klassenFuer($user);
        $klasseId = (int) $request->query('klasse', 0);
        if ($klasseId && ! $klassen->contains('id', $klasseId)) {
            abort(403);
        }

        $schueler = $klasseId
            ? Schueler::where('klasse_id', $klasseId)->orderBy('nachname')->orderBy('vorname')->get()
            : $this->profile->kinderMitProfil($klassen->pluck('id')->all());
        $profile = Sehprofil::with('vorlage')->whereIn('schueler_id', $schueler->pluck('id'))->get()->keyBy('schueler_id');

        return view('arbeitsblaetter.profile', [
            'klassen' => $klassen,
            'klasseId' => $klasseId,
            'schueler' => $schueler,
            'profile' => $profile->map(fn (Sehprofil $p) => [
                'vorlage_id' => $p->vorlage_id,
                'aktiv' => (bool) $p->aktiv,
                'werte' => $p->wirksameWerte(),
                'kurz' => AbProfil::kurz($p->wirksameWerte()),
                'geaendert' => $p->updated_at?->format('d.m.Y'),
            ]),
            'vorlagen' => $this->profile->vorlagen(),
            'darfPflegen' => $user->can('manage', Sehprofil::class),
            'felder' => AbProfil::FELDER,
            'standard' => AbProfil::standard(),
            'schriften' => AbProfil::SCHRIFTEN,
            'farbschemata' => collect(AbProfil::FARBSCHEMATA)->map(fn ($f) => $f['label'])->all(),
        ]);
    }

    public function speichern(Request $request, Schueler $schueler): JsonResponse
    {
        $this->authorize('manage', Sehprofil::class);
        $this->klasseErlaubt($request, $schueler);
        $daten = $request->validate([
            'vorlage_id' => 'nullable|integer|exists:ab_profil_vorlagen,id',
            'aktiv' => 'required|boolean',
            'werte' => 'required|array',
        ]);

        $profil = $this->profile->speichern($schueler, $daten['vorlage_id'] ?? null, $this->nurFelder($daten['werte']), (bool) $daten['aktiv'], $request->user());

        return response()->json([
            'profil' => [
                'vorlage_id' => $profil->vorlage_id,
                'aktiv' => (bool) $profil->aktiv,
                'werte' => $profil->wirksameWerte(),
                'kurz' => AbProfil::kurz($profil->wirksameWerte()),
                'geaendert' => $profil->updated_at?->format('d.m.Y'),
            ],
            'meldung' => 'Profil von '.$schueler->vorname.' gespeichert.',
        ]);
    }

    public function entfernen(Request $request, Schueler $schueler): JsonResponse
    {
        $this->authorize('manage', Sehprofil::class);
        $this->klasseErlaubt($request, $schueler);
        $this->profile->entfernen($schueler);

        return response()->json(['meldung' => 'Profil entfernt.']);
    }

    /** Live-Beispielblatt mit den (noch nicht gespeicherten) Werten. */
    public function vorschau(Request $request)
    {
        $this->authorize('viewAny', Sehprofil::class);
        $daten = $request->validate(['werte' => 'required|array']);

        $beispiel = new AngepasstesBlatt(['titel' => 'Beispiel: Brüche']);
        $struktur = [
            'seiten' => 1,
            'bausteine' => [
                ['typ' => 'anweisung', 'text' => 'Ergänze die fehlenden Zahlen.'],
                ['typ' => 'aufgabe', 'nummer' => '1', 'inhalt' => [['typ' => 'text', 'text' => '3/4 = '], ['typ' => 'luecke', 'laenge' => 3], ['typ' => 'text', 'text' => ' /8']]],
                ['typ' => 'ankreuzen', 'frage' => 'Ist 1/2 größer als 1/3?', 'optionen' => ['ja', 'nein']],
                ['typ' => 'tabelle', 'kopfzeile' => true, 'zeilen' => [['Bruch', 'Dezimalzahl'], ['1/2', ''], ['1/4', '']]],
                ['typ' => 'schreiblinien', 'anzahl' => 2],
            ],
        ];

        $html = app(AbSatzService::class)->html($beispiel, AbProfil::normalisieren($this->nurFelder($daten['werte'])), 'html', $struktur);

        return response($html, 200, ['Content-Type' => 'text/html; charset=utf-8', 'X-Frame-Options' => 'SAMEORIGIN']);
    }

    // ─── Vorlagen ─────────────────────────────────────────────────────────────

    public function vorlageSpeichern(Request $request, ?AbProfilVorlage $vorlage = null): JsonResponse
    {
        $this->authorize('manage', Sehprofil::class);
        $daten = $request->validate([
            'name' => ['required', 'string', 'max:100', Rule::unique('ab_profil_vorlagen', 'name')->ignore($vorlage?->id)],
            'werte' => 'required|array',
        ]);
        $vorlage = $this->profile->vorlageSpeichern($vorlage, $daten['name'], $this->nurFelder($daten['werte']));

        return response()->json([
            'vorlage' => ['id' => $vorlage->id, 'name' => $vorlage->name, 'werte' => $vorlage->wirksameWerte(), 'kurz' => AbProfil::kurz($vorlage->wirksameWerte())],
            'meldung' => 'Vorlage „'.$vorlage->name.'“ gespeichert.',
        ]);
    }

    public function vorlageLoeschen(AbProfilVorlage $vorlage): JsonResponse
    {
        $this->authorize('manage', Sehprofil::class);
        $fehler = $this->profile->vorlageLoeschen($vorlage);
        if ($fehler) {
            return response()->json(['message' => $fehler], 422);
        }

        return response()->json(['meldung' => 'Vorlage gelöscht.']);
    }

    private function nurFelder(array $werte): array
    {
        return array_intersect_key($werte, AbProfil::FELDER);
    }

    /** Ohne Sonderrecht „alle Klassen“ nur Kinder der eigenen Klassen. */
    private function klasseErlaubt(Request $request, Schueler $schueler): void
    {
        $klassen = $this->profile->klassenFuer($request->user())->pluck('id')->all();
        abort_unless($schueler->klasse_id && in_array((int) $schueler->klasse_id, array_map('intval', $klassen), true), 403);
    }
}
