<?php

namespace App\Http\Controllers\Personal;

use App\Http\Controllers\Controller;
use App\Http\Requests\personal\AddHortPlanungPersonRequest;
use App\Http\Requests\personal\BulkUpdateHortPlanungPersonRequest;
use App\Http\Requests\personal\CreateHortPlanungRequest;
use App\Http\Requests\personal\DuplicateHortPlanungRequest;
use App\Http\Requests\personal\SnapshotHortPlanungRequest;
use App\Http\Requests\personal\StoreHortFaktorRequest;
use App\Http\Requests\personal\StoreHortFaktorWertRequest;
use App\Http\Requests\personal\StoreHortZusatzTypRequest;
use App\Http\Requests\personal\UpdateHortFaktorRequest;
use App\Http\Requests\personal\UpdateHortMonatZusatzRequest;
use App\Http\Requests\personal\UpdateHortPlanungMonatRequest;
use App\Http\Requests\personal\UpdateHortPlanungPersonRequest;
use App\Http\Requests\personal\UpdateHortPlanungRequest;
use App\Exports\HortPlanungExport;
use App\Http\Requests\personal\UpdateHortZusatzTypRequest;
use App\Imports\HortPlanungImport;
use App\Models\Group;
use App\Models\personal\HortFaktor;
use App\Models\personal\HortFaktorWert;
use App\Models\personal\HortMonatZusatz;
use App\Models\personal\HortPlanung;
use App\Models\personal\HortPlanungMonat;
use App\Models\personal\HortPlanungPerson;
use App\Models\personal\HortPlanungSnapshot;
use App\Models\personal\HortZusatzstundenTyp;
use App\Models\User;
use App\Services\HortPlanungService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Maatwebsite\Excel\Facades\Excel;

class HortPlanungController extends Controller
{
    public function __construct(protected HortPlanungService $service)
    {
    }

    // ── CRUD Planung ─────────────────────────────────────────────────────────

    /**
     * Übersicht aller Planungen.
     */
    public function index(): View
    {
        $planungen = HortPlanung::with([
            'department',
            'kopiertvon',
            'monate' => fn($q) => $q->where('monat', '<=', now()->format('Y-m-01'))
                                    ->orderByDesc('monat')
                                    ->limit(1),
        ])
        ->withCount('monate')
        ->orderByDesc('aktiv')
        ->orderByDesc('updated_at')
        ->get();

        return view('personal.hort_planung.index', compact('planungen'));
    }

    /**
     * Formular: Neue Planung erstellen.
     */
    public function create(): View
    {
        $abteilungen = Group::orderBy('name')->get();

        return view('personal.hort_planung.create', compact('abteilungen'));
    }

    /**
     * Planung speichern (inkl. Monate + Standard-Faktoren + Zusatztypen).
     */
    public function store(CreateHortPlanungRequest $request): RedirectResponse
    {
        $planung = HortPlanung::create([
            'name'          => $request->name,
            'beschreibung'  => $request->beschreibung,
            'department_id' => $request->department_id,
            'start_monat'   => Carbon::parse($request->start_monat)->startOfMonth(),
            'end_monat'     => Carbon::parse($request->end_monat)->startOfMonth(),
            'typ'           => $request->typ ?? 'planung',
            'aktiv'         => false,
            'created_by'    => auth()->id(),
        ]);

        // Monate, Standard-Faktoren und -Zusatztypen anlegen
        $this->service->erstelleMonate($planung, $request->kinderanzahl ?? 100);
        $this->service->erstelleStandardFaktoren($planung);
        $this->service->erstelleStandardZusatztypen($planung);

        // Optional: Personen automatisch aus Anstellungen importieren
        if ($request->boolean('import_employments')) {
            $this->service->importiereAusEmployments($planung);
        }

        return redirect()->to(route('hort-planung.show', $planung))
            ->with(['type' => 'success', 'Meldung' => 'Planung „' . $planung->name . '" wurde erfolgreich angelegt.']);
    }

    /**
     * Matrix-Ansicht (Haupt-View).
     */
    public function show(HortPlanung $planung): View
    {
        $planung->load([
            'faktoren.werte',
            'zusatzstundenTypen',
            'monate.personen.user.employments',
            'monate.monatZusatzstunden.typ',
            'department',
            'kopiertvon',
        ]);

        // Abwesenheiten (Langzeit) laden
        $abwesenheiten = $this->service->abwesenheitenImZeitraum($planung);

        // Vergleichbare Planungen für Dropdown
        $anderePlanungen = HortPlanung::where('id', '!=', $planung->id)
            ->orderBy('name')
            ->get(['id', 'name']);

        // Alle einzigartigen Personen alphabetisch sortiert
        $allePersonen = $planung->monate
            ->flatMap(fn($m) => $m->personen)
            ->unique('user_id')
            ->sortBy(fn($p) => $p->user?->name ?? 'zzz')
            ->values();

        // Vorberechnete Monatswerte für Alpine.js initData
        $berechnungenNachMonat = $planung->monate->mapWithKeys(fn($m) => [
            $m->monat->format('Y-m') => $this->service->berechneMonat($m, $planung),
        ]);

        // Alle Nutzer für Person-Hinzufügen-Modal
        $alleNutzer = User::orderBy('name')->get(['id', 'name']);

        // Snapshots dieser Planung (neueste zuerst)
        $snapshots = $planung->snapshots()
            ->with('ersteller:id,name')
            ->orderByDesc('created_at')
            ->get();

        return view('personal.hort_planung.show', compact(
            'planung',
            'abwesenheiten',
            'anderePlanungen',
            'allePersonen',
            'berechnungenNachMonat',
            'alleNutzer',
            'snapshots'
        ));
    }

    /**
     * Formular: Planung bearbeiten (Parameter + Faktoren + Zusatzstunden).
     */
    public function edit(HortPlanung $planung): View
    {
        $planung->load([
            'faktoren.werte',
            'zusatzstundenTypen',
            'department',
        ]);

        return view('personal.hort_planung.edit', compact('planung'));
    }

    /**
     * Planung-Einstellungen speichern.
     */
    public function update(UpdateHortPlanungRequest $request, HortPlanung $planung): RedirectResponse
    {
        // Wenn aktiv gesetzt wird, andere Planungen deaktivieren
        if ($request->boolean('aktiv') && !$planung->aktiv) {
            HortPlanung::where('department_id', $planung->department_id)
                ->where('id', '!=', $planung->id)
                ->update(['aktiv' => false]);
        }

        $planung->update($request->only(['name', 'beschreibung', 'aktiv']));

        // Zeitraum aktualisieren, wenn angegeben
        $zeitraumGeaendert = false;
        if ($request->filled('start_monat') || $request->filled('end_monat')) {
            $neuerStart = $request->filled('start_monat')
                ? Carbon::parse($request->start_monat)->startOfMonth()
                : $planung->start_monat;
            $neuesEnde = $request->filled('end_monat')
                ? Carbon::parse($request->end_monat)->startOfMonth()
                : $planung->end_monat;

            if ($neuesEnde->greaterThanOrEqualTo($neuerStart)) {
                $planung->update([
                    'start_monat' => $neuerStart,
                    'end_monat'   => $neuesEnde,
                ]);
                // Fehlende Monate anlegen (firstOrCreate verhindert Duplikate)
                $this->service->erstelleMonate($planung);
                $zeitraumGeaendert = true;
            } else {
                return redirect()->to(route('hort-planung.edit', $planung))
                    ->with(['type' => 'danger', 'Meldung' => 'Der Endmonat muss nach dem Startmonat liegen.']);
            }
        }

        $meldung = $zeitraumGeaendert
            ? 'Planung und Zeitraum wurden aktualisiert. Fehlende Monate wurden ergänzt.'
            : 'Planung wurde aktualisiert.';

        return redirect()->to(route('hort-planung.edit', $planung))
            ->with(['type' => 'success', 'Meldung' => $meldung]);
    }

    /**
     * Planung löschen (SoftDelete).
     */
    public function destroy(HortPlanung $planung): RedirectResponse
    {
        $name = $planung->name;
        $planung->delete();

        return redirect()->to(route('hort-planung.index'))
            ->with(['type' => 'success', 'Meldung' => 'Planung „' . $name . '" wurde gelöscht.']);
    }

    // ── AJAX: Monat & Person ─────────────────────────────────────────────────

    /**
     * AJAX: Monatsparameter aktualisieren.
     * Gibt aktualisierte Berechnungswerte als JSON zurück.
     */
    public function updateMonat(
        UpdateHortPlanungMonatRequest $request,
        HortPlanung $planung,
        HortPlanungMonat $monat
    ): JsonResponse {
        abort_if($monat->hort_planung_id !== $planung->id, 403);

        $monat->update($request->only(['kinderanzahl', 'vollzeitstunden', 'notiz']));
        $monat->load(['personen', 'monatZusatzstunden.typ']);

        // Faktoren für Berechnung laden
        $planung->load('faktoren.werte');

        try {
            $berechnungen = $this->service->berechneMonat($monat, $planung);
        } catch (\Throwable $e) {
            report($e);
            return response()->json([
                'success' => false,
                'message' => 'Fehler bei der Neuberechnung: ' . $e->getMessage(),
            ], 500);
        }

        return response()->json([
            'success'      => true,
            'berechnungen' => $berechnungen,
        ]);
    }

    /**
     * AJAX: Personenstunden aktualisieren.
     * Gibt aktualisierte Berechnungswerte des Monats zurück.
     */
    public function updatePerson(
        UpdateHortPlanungPersonRequest $request,
        HortPlanung $planung,
        HortPlanungPerson $person
    ): JsonResponse {
        abort_if($person->monat->hort_planung_id !== $planung->id, 403);

        $person->update($request->only(['stunden_gesamt', 'stunden_stadt', 'kommentar']));

        $monat = $person->monat;
        $monat->load(['personen', 'monatZusatzstunden.typ']);
        $planung->load('faktoren.werte');

        try {
            $berechnungen = $this->service->berechneMonat($monat, $planung);
        } catch (\Throwable $e) {
            report($e);
            return response()->json([
                'success' => false,
                'message' => 'Fehler bei der Neuberechnung: ' . $e->getMessage(),
            ], 500);
        }

        return response()->json([
            'success'      => true,
            'berechnungen' => $berechnungen,
        ]);
    }

    /**
     * AJAX: Stunden einer Person ab einem Monat X für alle Folgemonate setzen.
     * Gibt neben dem Ergebnis auch die aktualisierten PersonIds und Berechnungen zurück,
     * damit das Frontend seinen State ohne Seitenreload aktualisieren kann.
     */
    public function bulkUpdatePerson(
        BulkUpdateHortPlanungPersonRequest $request,
        HortPlanung $planung,
        User $user
    ): JsonResponse {
        $abMonat = Carbon::parse($request->ab_monat)->startOfMonth();

        $this->service->bulkUpdatePerson(
            $planung,
            $user->id,
            $abMonat,
            $request->stunden_gesamt,
            $request->stunden_stadt,
            $request->kommentar
        );

        // Betroffene Monate neu laden und Berechnungen + PersonIds zurückgeben
        $planung->load([
            'faktoren.werte',
            'monate' => fn($q) => $q->where('monat', '>=', $abMonat)
                                    ->with(['personen', 'monatZusatzstunden.typ']),
        ]);

        $personIds    = [];
        $berechnungen = [];
        foreach ($planung->monate as $monat) {
            $mk = $monat->monat->format('Y-m');
            $p  = $monat->personen->firstWhere('user_id', $user->id);
            if ($p) {
                $personIds[$mk] = $p->id;
            }
            $berechnungen[$mk] = $this->service->berechneMonat($monat, $planung);
        }

        return response()->json([
            'success'      => true,
            'personIds'    => $personIds,
            'berechnungen' => $berechnungen,
        ]);
    }

    /**
     * Person zur Planung (alle Monate) hinzufügen.
     */
    public function addPerson(AddHortPlanungPersonRequest $request, HortPlanung $planung): RedirectResponse
    {
        $userId       = $request->user_id;
        $hinzugefuegt = 0;

        foreach ($planung->monate as $monat) {
            $existiert = HortPlanungPerson::where('hort_planung_monat_id', $monat->id)
                ->where('user_id', $userId)
                ->exists();

            if (!$existiert) {
                HortPlanungPerson::create([
                    'hort_planung_monat_id' => $monat->id,
                    'user_id'               => $userId,
                ]);
                $hinzugefuegt++;
            }
        }

        return redirect()->to(route('hort-planung.show', $planung))
            ->with(['type' => 'success', 'Meldung' => 'Person wurde der Planung hinzugefügt (' . $hinzugefuegt . ' Monate).']);
    }

    /**
     * Person aus allen Monaten der Planung entfernen.
     */
    public function removePerson(HortPlanung $planung, User $user): RedirectResponse
    {
        $monatIds = $planung->monate->pluck('id');
        $count    = HortPlanungPerson::whereIn('hort_planung_monat_id', $monatIds)
            ->where('user_id', $user->id)
            ->delete();

        return redirect()->to(route('hort-planung.show', $planung))
            ->with(['type' => 'success', 'Meldung' => 'Person wurde aus der Planung entfernt (' . $count . ' Einträge).']);
    }

    // ── Faktoren-CRUD ────────────────────────────────────────────────────────

    /**
     * Neuen Faktor anlegen (inkl. initialem Wert).
     */
    public function storeFaktor(StoreHortFaktorRequest $request, HortPlanung $planung): RedirectResponse
    {
        $faktor = HortFaktor::create([
            'hort_planung_id'       => $planung->id,
            'kuerzel'               => $request->kuerzel,
            'bezeichnung'           => $request->bezeichnung,
            'berechnungs_typ'       => $request->berechnungs_typ,
            'position'              => $request->position,
            'aktiv'                 => true,
            'gesetzliche_grundlage' => $request->gesetzliche_grundlage,
        ]);

        HortFaktorWert::create([
            'hort_faktor_id' => $faktor->id,
            'wert'           => $request->wert,
            'gueltig_ab'     => Carbon::parse($request->gueltig_ab)->format('Y-m-01'),
            'notiz'          => 'Initialer Wert',
            'created_by'     => auth()->id(),
        ]);

        return redirect()->to(route('hort-planung.edit', $planung))
            ->with(['type' => 'success', 'Meldung' => 'Faktor „' . $faktor->bezeichnung . '" wurde angelegt.']);
    }

    /**
     * AJAX: Faktor-Definition bearbeiten.
     */
    public function updateFaktor(
        UpdateHortFaktorRequest $request,
        HortPlanung $planung,
        HortFaktor $faktor
    ): JsonResponse {
        abort_if($faktor->hort_planung_id !== $planung->id, 403);

        $faktor->update($request->only([
            'bezeichnung', 'berechnungs_typ', 'position', 'aktiv', 'gesetzliche_grundlage',
        ]));

        return response()->json([
            'success' => true,
            'faktor'  => $faktor->fresh()->only([
                'id', 'kuerzel', 'bezeichnung', 'berechnungs_typ', 'position', 'aktiv', 'gesetzliche_grundlage',
            ]),
        ]);
    }

    /**
     * Faktor deaktivieren (Soft-Deactivate – Daten bleiben erhalten).
     */
    public function deleteFaktor(HortPlanung $planung, HortFaktor $faktor): RedirectResponse|JsonResponse
    {
        abort_if($faktor->hort_planung_id !== $planung->id, 403);

        $faktor->update(['aktiv' => false]);

        if (request()->expectsJson()) {
            return response()->json(['success' => true]);
        }

        return redirect()->to(route('hort-planung.edit', $planung))
            ->with(['type' => 'success', 'Meldung' => 'Faktor „' . $faktor->bezeichnung . '" wurde deaktiviert.']);
    }

    /**
     * Neuen zeitlichen Faktor-Wert hinzufügen.
     */
    public function storeFaktorWert(
        StoreHortFaktorWertRequest $request,
        HortPlanung $planung,
        HortFaktor $faktor
    ): RedirectResponse {
        abort_if($faktor->hort_planung_id !== $planung->id, 403);

        HortFaktorWert::create([
            'hort_faktor_id' => $faktor->id,
            'wert'           => $request->wert,
            'gueltig_ab'     => Carbon::parse($request->gueltig_ab)->format('Y-m-01'),
            'notiz'          => $request->notiz,
            'created_by'     => auth()->id(),
        ]);

        return redirect()->to(route('hort-planung.edit', $planung))
            ->with(['type' => 'success', 'Meldung' => 'Neuer Faktor-Wert ab ' . Carbon::parse($request->gueltig_ab)->format('M Y') . ' gespeichert.']);
    }

    /**
     * Faktor-Wert-Änderung entfernen.
     * Hinweis: Nur nicht-initiale Werte können gelöscht werden.
     */
    public function deleteFaktorWert(HortPlanung $planung, HortFaktorWert $wert): RedirectResponse|JsonResponse
    {
        abort_if($wert->faktor->hort_planung_id !== $planung->id, 403);

        $anzahl = HortFaktorWert::where('hort_faktor_id', $wert->hort_faktor_id)->count();

        if ($anzahl <= 1) {
            if (request()->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Der initiale Faktor-Wert kann nicht gelöscht werden.',
                ], 422);
            }
            return redirect()->to(route('hort-planung.edit', $planung))
                ->with(['type' => 'danger', 'Meldung' => 'Der initiale Faktor-Wert kann nicht gelöscht werden.']);
        }

        $wert->delete();

        if (request()->expectsJson()) {
            return response()->json(['success' => true]);
        }

        return redirect()->to(route('hort-planung.edit', $planung))
            ->with(['type' => 'success', 'Meldung' => 'Faktor-Wert wurde entfernt.']);
    }

    // ── Zusatzstunden-Typen-CRUD ─────────────────────────────────────────────

    /**
     * Neuen Zusatzstunden-Typ anlegen.
     */
    public function storeZusatzTyp(StoreHortZusatzTypRequest $request, HortPlanung $planung): RedirectResponse
    {
        $maxPosition = $planung->zusatzstundenTypen()->max('position') ?? 0;

        HortZusatzstundenTyp::create([
            'hort_planung_id' => $planung->id,
            'kuerzel'         => $request->kuerzel,
            'bezeichnung'     => $request->bezeichnung,
            'position'        => $request->position ?? $maxPosition + 1,
            'aktiv'           => true,
        ]);

        return redirect()->to(route('hort-planung.edit', $planung))
            ->with(['type' => 'success', 'Meldung' => 'Zusatzstunden-Typ „' . $request->bezeichnung . '" wurde angelegt.']);
    }

    /**
     * AJAX: Zusatzstunden-Typ umbenennen oder (de-)aktivieren.
     */
    public function updateZusatzTyp(
        UpdateHortZusatzTypRequest $request,
        HortPlanung $planung,
        HortZusatzstundenTyp $typ
    ): JsonResponse {
        abort_if($typ->hort_planung_id !== $planung->id, 403);

        $typ->update($request->only(['bezeichnung', 'position', 'aktiv']));

        return response()->json([
            'success' => true,
            'typ'     => $typ->fresh()->only(['id', 'kuerzel', 'bezeichnung', 'position', 'aktiv']),
        ]);
    }

    /**
     * Zusatzstunden-Typ deaktivieren.
     */
    public function deleteZusatzTyp(
        HortPlanung $planung,
        HortZusatzstundenTyp $typ
    ): RedirectResponse|JsonResponse {
        abort_if($typ->hort_planung_id !== $planung->id, 403);

        $typ->update(['aktiv' => false]);

        if (request()->expectsJson()) {
            return response()->json(['success' => true]);
        }

        return redirect()->to(route('hort-planung.edit', $planung))
            ->with(['type' => 'success', 'Meldung' => 'Zusatzstunden-Typ „' . $typ->bezeichnung . '" wurde deaktiviert.']);
    }

    /**
     * AJAX: Zusatzstunden-Wert pro Monat und Typ setzen.
     */
    public function updateMonatZusatz(
        UpdateHortMonatZusatzRequest $request,
        HortPlanung $planung,
        HortPlanungMonat $monat,
        HortZusatzstundenTyp $typ
    ): JsonResponse {
        abort_if($monat->hort_planung_id !== $planung->id, 403);
        abort_if($typ->hort_planung_id !== $planung->id, 403);

        $zusatz = HortMonatZusatz::updateOrCreate(
            [
                'hort_planung_monat_id'      => $monat->id,
                'hort_zusatzstunden_typ_id'  => $typ->id,
            ],
            [
                'stunden' => $request->stunden,
                'notiz'   => $request->notiz,
            ]
        );

        $monat->load(['personen', 'monatZusatzstunden.typ']);
        $planung->load('faktoren.werte');

        $berechnungen = $this->service->berechneMonat($monat, $planung);

        return response()->json([
            'success'      => true,
            'zusatz'       => $zusatz,
            'berechnungen' => $berechnungen,
        ]);
    }

    // ── Import / Sync / Szenarien ────────────────────────────────────────────

    /**
     * AJAX (Schritt 1): Datei hochladen + parsen → Personen-Vorschläge zurückgeben.
     * Der Dateipfad wird in der Session gespeichert für Schritt 2.
     */
    public function importParse(Request $request): JsonResponse
    {
        $request->validate(['file' => ['required', 'file', 'mimes:xlsx,xls', 'max:10240']]);

        // Temporäre Datei speichern
        $pfad = $request->file('file')->storeAs(
            'temp',
            'hort_import_' . auth()->id() . '_' . now()->timestamp . '.xlsx'
        );

        try {
            $import = new HortPlanungImport();
            Excel::import($import, storage_path('app/' . $pfad));
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'Fehler beim Lesen der Excel-Datei: ' . $e->getMessage(),
            ], 422);
        }

        $monate        = $import->planungSheet->getMonate();
        $personNamen   = $import->planungSheet->getPersonNamen();
        $schluessel    = $import->schluesselSheet->getSchluessel();

        if (empty($monate)) {
            return response()->json([
                'success' => false,
                'message' => 'Keine Monatsspalten gefunden. Bitte prüfen Sie das Format der Datei.',
            ], 422);
        }

        // Fuzzy-Matching: Excel-Name → User-Vorschlag
        $alleNutzer  = User::orderBy('name')->get(['id', 'name']);
        $vorschlaege = [];

        foreach ($personNamen as $excelName) {
            $bestUser  = null;
            $bestScore = 0;

            foreach ($alleNutzer as $nutzer) {
                similar_text(
                    mb_strtolower($excelName),
                    mb_strtolower($nutzer->name),
                    $score
                );
                if ($score > $bestScore) {
                    $bestScore = $score;
                    $bestUser  = $nutzer;
                }
            }

            $vorschlaege[] = [
                'excel_name' => $excelName,
                'user_id'    => ($bestScore >= 50 && $bestUser) ? $bestUser->id   : null,
                'user_name'  => ($bestScore >= 50 && $bestUser) ? $bestUser->name : null,
                'score'      => round($bestScore, 1),
            ];
        }

        // Pfad in Session für Schritt 2 speichern
        session(['hort_import_pfad_' . auth()->id() => $pfad]);

        return response()->json([
            'success'     => true,
            'monate'      => $monate,
            'personen'    => $vorschlaege,
            'schluessel'  => $schluessel,
            'alle_nutzer' => $alleNutzer->map(fn($u) => ['id' => $u->id, 'name' => $u->name]),
        ]);
    }

    /**
     * Excel-Import (Schritt 2): Erstellt eine neue Planung aus der hochgeladenen Datei.
     * Erwartet: name, beschreibung, department_id, mapping[] (excel_name → user_id)
     */
    public function importExcel(Request $request): RedirectResponse
    {
        $request->validate([
            'file'          => ['required', 'file', 'mimes:xlsx,xls', 'max:10240'],
            'name'          => ['required', 'string', 'max:255'],
            'beschreibung'  => ['nullable', 'string'],
            'department_id' => ['required', 'exists:groups,id'],
            'mapping'       => ['nullable', 'array'],
            'mapping.*'     => ['nullable', 'exists:users,id'],
        ]);

        // Datei nochmals parsen (Schritt 2 – die Datei wird erneut hochgeladen)
        try {
            $import = new HortPlanungImport();
            Excel::import($import, $request->file('file'));
        } catch (\Throwable $e) {
            return redirect()->to(route('hort-planung.index'))
                ->with(['type' => 'danger', 'Meldung' => 'Fehler beim Lesen der Excel-Datei: ' . $e->getMessage()]);
        }

        $monate      = $import->planungSheet->getMonate();
        $personenDaten = $import->planungSheet->getPersonen();
        $parameter   = $import->planungSheet->getParameter();

        if (empty($monate)) {
            return redirect()->to(route('hort-planung.index'))
                ->with(['type' => 'danger', 'Meldung' => 'Keine Monatsspalten in der Excel-Datei gefunden.']);
        }

        $startMonat = Carbon::parse(reset($monate))->startOfMonth();
        $endMonat   = Carbon::parse(end($monate))->startOfMonth();
        $mapping    = $request->input('mapping', []);

        // ── Neue Planung anlegen ───────────────────────────────────────
        $planung = HortPlanung::create([
            'name'          => $request->name,
            'beschreibung'  => $request->beschreibung,
            'department_id' => $request->department_id,
            'start_monat'   => $startMonat,
            'end_monat'     => $endMonat,
            'typ'           => 'planung',
            'aktiv'         => false,
            'created_by'    => auth()->id(),
        ]);

        // ── Monate + Standardwerte anlegen ────────────────────────────
        $this->service->erstelleStandardFaktoren($planung);
        $this->service->erstelleStandardZusatztypen($planung);

        // Monate anlegen und Parameter befüllen
        foreach ($monate as $monatStr) {
            $kinderanzahl    = (int) ($parameter['kinderanzahl'][$monatStr]    ?? 100);
            $vollzeitstunden = (float) ($parameter['vollzeitstunden'][$monatStr] ?? 40.0);

            \App\Models\personal\HortPlanungMonat::firstOrCreate(
                ['hort_planung_id' => $planung->id, 'monat' => $monatStr],
                [
                    'kinderanzahl'    => max(1, $kinderanzahl),
                    'vollzeitstunden' => max(1, $vollzeitstunden),
                ]
            );
        }

        $planung->load('monate');

        // ── Personendaten importieren ─────────────────────────────────
        $importiert = 0;
        foreach ($personenDaten as $person) {
            $userId = $mapping[$person['name']] ?? null;
            if (!$userId) {
                continue; // Nicht zugeordnete Personen überspringen
            }

            foreach ($planung->monate as $monat) {
                $monatKey = $monat->monat->format('Y-m-01');
                $sp1      = $person['sp1'][$monatKey] ?? 0;
                $sp2      = $person['sp2'][$monatKey] ?? 0;

                \App\Models\personal\HortPlanungPerson::updateOrCreate(
                    ['hort_planung_monat_id' => $monat->id, 'user_id' => $userId],
                    ['stunden_gesamt' => $sp1, 'stunden_stadt' => $sp2]
                );
                $importiert++;
            }
        }

        // Temp-Datei aufräumen
        $sessionPfad = session('hort_import_pfad_' . auth()->id());
        if ($sessionPfad && \Illuminate\Support\Facades\Storage::exists($sessionPfad)) {
            \Illuminate\Support\Facades\Storage::delete($sessionPfad);
            session()->forget('hort_import_pfad_' . auth()->id());
        }

        return redirect()->to(route('hort-planung.show', $planung))
            ->with(['type' => 'success', 'Meldung' => 'Planung „' . $planung->name . '" wurde importiert (' . $importiert . ' Personen-Einträge).']);
    }

    /**
     * Excel-Export: Lädt die Planung als formatierte .xlsx-Datei herunter.
     */
    public function export(HortPlanung $planung): \Symfony\Component\HttpFoundation\BinaryFileResponse
    {
        $dateiname = 'Hortstunden_' . str_replace([' ', '/'], '_', $planung->name) . '_' . now()->format('Ymd') . '.xlsx';

        return Excel::download(new HortPlanungExport($planung, $this->service), $dateiname);
    }

    /**
     * Aus Anstellungen (Employment) importieren.
     */
    public function importEmployments(HortPlanung $planung): RedirectResponse
    {
        $count = $this->service->importiereAusEmployments($planung);

        return redirect()->to(route('hort-planung.show', $planung))
            ->with(['type' => 'success', 'Meldung' => $count . ' Personen-Einträge aus Anstellungen importiert.']);
    }

    public function syncIstStunden(HortPlanung $planung): RedirectResponse
    {
        $count = $this->service->syncIstStunden($planung);

        return redirect()->to(route('hort-planung.rueckblick', $planung))
            ->with(['type' => 'success', 'Meldung' => $count . ' Ist-Stunden wurden aus der Zeiterfassung synchronisiert.']);
    }

    public function syncVertrag(HortPlanung $planung): RedirectResponse
    {
        $count = $this->service->syncVertragsstunden($planung);

        return redirect()->back()
            ->with(['type' => 'success', 'Meldung' => $count . ' Vertragsstunden wurden aktualisiert.']);
    }

    // ── AJAX: Berechnungen & Trend ───────────────────────────────────────────

    /**
     * AJAX: Aktuelle Berechnungswerte für alle Monate einer Planung.
     */
    public function berechnungen(HortPlanung $planung): JsonResponse
    {
        $planung->load([
            'faktoren.werte',
            'monate.personen',
            'monate.monatZusatzstunden.typ',
        ]);

        $ergebnisse = $this->service->berechnePlanung($planung)->map(fn($item) => [
            'monat'        => $item['monat']->format('Y-m-d'),
            'parameter'    => $item['parameter'],
            'berechnungen' => $item['berechnungen'],
        ]);

        return response()->json([
            'success'    => true,
            'ergebnisse' => $ergebnisse,
        ]);
    }

    /**
     * AJAX: Trend-Daten für Chart.js.
     */
    public function trend(HortPlanung $planung): JsonResponse
    {
        $planung->load([
            'faktoren.werte',
            'monate.personen',
            'monate.monatZusatzstunden.typ',
        ]);

        return response()->json([
            'success' => true,
            'data'    => $this->service->trendDaten($planung),
        ]);
    }

    // ── Szenarien & Snapshots ────────────────────────────────────────────────

    /**
     * Planung als Szenario duplizieren.
     */
    public function duplicate(DuplicateHortPlanungRequest $request, HortPlanung $planung): RedirectResponse
    {
        $kopie = $this->service->dupliziere(
            $planung,
            $request->name,
            $request->beschreibung
        );

        return redirect()->to(route('hort-planung.show', $kopie))
            ->with(['type' => 'success', 'Meldung' => 'Planung wurde als „' . $kopie->name . '" dupliziert.']);
    }

    /**
     * Zwei Planungen vergleichen.
     */
    public function vergleich(HortPlanung $planung, HortPlanung $other): View
    {
        $planung->load(['faktoren.werte', 'monate.personen', 'monate.monatZusatzstunden.typ']);
        $other->load(['faktoren.werte', 'monate.personen', 'monate.monatZusatzstunden.typ']);

        $vergleich = $this->service->vergleichePlanungen($planung, $other);

        return view('personal.hort_planung.vergleich', compact('planung', 'other', 'vergleich'));
    }

    /**
     * Aktuellen Stand als Snapshot einfrieren.
     */
    public function snapshot(SnapshotHortPlanungRequest $request, HortPlanung $planung): RedirectResponse
    {
        $snapshot = $this->service->erstelleSnapshot($planung, $request->name);

        return redirect()->to(route('hort-planung.show', $planung))
            ->with(['type' => 'success', 'Meldung' => 'Snapshot „' . $snapshot->name . '" wurde erstellt.']);
    }

    /**
     * Snapshot als Excel exportieren.
     */
    public function exportSnapshot(HortPlanung $planung, HortPlanungSnapshot $snapshot): \Symfony\Component\HttpFoundation\BinaryFileResponse
    {
        abort_if($snapshot->hort_planung_id !== $planung->id, 403);

        $filename = \Illuminate\Support\Str::slug($planung->name . '-' . $snapshot->name, '-') . '.xlsx';

        return \Maatwebsite\Excel\Facades\Excel::download(
            new \App\Exports\HortPlanungSnapshotExport($snapshot),
            $filename
        );
    }

    /**
     * Planungsstand aus einem Snapshot wiederherstellen.
     */
    public function restoreSnapshot(HortPlanung $planung, HortPlanungSnapshot $snapshot): RedirectResponse
    {
        abort_if($snapshot->hort_planung_id !== $planung->id, 403);

        $anzahl = $this->service->restoreSnapshot($snapshot);

        return redirect()->to(route('hort-planung.show', $planung))
            ->with([
                'type'    => 'success',
                'Meldung' => 'Snapshot „' . $snapshot->name . '" wurde wiederhergestellt ('
                    . $anzahl . ' ' . ($anzahl === 1 ? 'Monat' : 'Monate') . ').',
            ]);
    }

    /**
     * Snapshot löschen.
     */
    public function deleteSnapshot(HortPlanung $planung, HortPlanungSnapshot $snapshot): RedirectResponse
    {
        abort_if($snapshot->hort_planung_id !== $planung->id, 403);

        $name = $snapshot->name;
        $snapshot->delete();

        return redirect()->to(route('hort-planung.show', $planung))
            ->with(['type' => 'success', 'Meldung' => 'Snapshot „' . $name . '" wurde gelöscht.']);
    }

    /**
     * Rückblick: Soll-Ist-Vergleichsansicht.
     */
    public function rueckblick(HortPlanung $planung): View
    {
        $planung->load([
            'faktoren.werte',
            'monate.personen.user',
            'monate.monatZusatzstunden.typ',
            'department',
        ]);

        $heute = now()->startOfMonth();

        // Nur vergangene und aktuelle Monate
        $monate = $planung->monate->filter(
            fn($m) => $m->monat->lessThan($heute)
        );

        // Berechnungen für alle relevanten Monate
        $berechnungenNachMonat = $monate->mapWithKeys(fn($m) => [
            $m->monat->format('Y-m') => $this->service->berechneMonat($m, $planung),
        ]);

        return view('personal.hort_planung.rueckblick', compact(
            'planung',
            'monate',
            'berechnungenNachMonat'
        ));
    }
}

