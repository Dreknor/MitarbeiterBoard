<?php

namespace App\Services\Wochenplan;

use App\Exceptions\VersionKonfliktException;
use App\Jobs\Wochenplan\WpPlanAblageEntfernen;
use App\Models\Klasse;
use App\Models\PaedDiaryTask;
use App\Models\Schueler;
use App\Models\User;
use App\Models\Wochenplan\WpAufgabe;
use App\Models\Wochenplan\WpDatei;
use App\Models\Wochenplan\WpFach;
use App\Models\Wochenplan\WpPlan;
use App\Models\Wochenplan\WpPlanFach;
use App\Models\Wochenplan\WpTaeglicheUebung;
use Carbon\Carbon;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Geschäftslogik des Wochenplans (Web, JSON-Editor und API).
 *
 * Jede inhaltliche Änderung läuft über {@see geaendert()}: erhöht `version`
 * (optimistische Sperre, 409 bei veraltetem Stand) und fordert die Nextcloud-Ablage an.
 */
class WochenplanService
{
    public function __construct(private WpAblageService $ablage)
    {
    }

    // ─── Version & Änderungen ────────────────────────────────────────────────

    /** @throws VersionKonfliktException */
    public function versionPruefen(WpPlan $plan, mixed $version): void
    {
        if ($version === null || $version === '') {
            return;
        }

        $aktuell = (int) WpPlan::withTrashed()->whereKey($plan->id)->value('version');
        if ((int) $version !== $aktuell) {
            throw new VersionKonfliktException('Der Plan wurde zwischenzeitlich geändert.');
        }
    }

    /** Nach jeder inhaltlichen Änderung: Version erhöhen und Ablage anfordern. */
    public function geaendert(WpPlan $plan): void
    {
        WpPlan::withTrashed()->whereKey($plan->id)->increment('version', 1, ['updated_at' => now()]);
        $plan->version = (int) WpPlan::withTrashed()->whereKey($plan->id)->value('version');

        $this->ablage->anfordern($plan);
    }

    // ─── Übersicht ────────────────────────────────────────────────────────────

    /**
     * Pläne für die Übersicht (ohne Vorlagen), gefiltert und nach Sichtbarkeit beschränkt.
     *
     * Filter: klasse_id, typ (klassenplan|schuelerplan), zeitraum (aktuell|kommend|vergangen|alle),
     * q (Suche im Namen, Kind), schuljahr (Startjahr), woche (Datum), meine (nur eigene Klassen).
     */
    public function liste(User $user, array $filter, int $proSeite = 12): LengthAwarePaginator
    {
        $query = WpPlan::query()
            ->with(['klasse', 'schueler.klasse', 'kinderPlaene', 'dateien', 'planFaecher'])
            ->withCount('aufgaben')
            ->where('is_vorlage', false)
            ->sichtbarFuer($user);

        if (! empty($filter['klasse_id'])) {
            $klasseId = (int) $filter['klasse_id'];
            $query->where(function ($q) use ($klasseId) {
                $q->where('klasse_id', $klasseId)
                    ->orWhereIn('schueler_id', Schueler::query()->select('id')->where('klasse_id', $klasseId));
            });
        }

        if (($filter['typ'] ?? null) === 'klassenplan') {
            $query->klassenplaene();
        } elseif (($filter['typ'] ?? null) === 'schuelerplan') {
            $query->schuelerplaene();
        } elseif (empty($filter['typ']) || $filter['typ'] === 'alle') {
            // Kinderpläne erscheinen in der Übersicht unter ihrem Klassenplan
            $query->where(fn ($q) => $q->whereNull('parent_plan_id')->orWhereNull('schueler_id'));
        }

        $heute = now()->toDateString();
        match ($filter['zeitraum'] ?? 'alle') {
            'aktuell' => $query->whereDate('gueltig_von', '<=', $heute)->whereDate('gueltig_bis', '>=', $heute),
            'kommend' => $query->whereDate('gueltig_von', '>', $heute),
            'vergangen' => $query->whereDate('gueltig_bis', '<', $heute),
            default => null,
        };

        if (! empty($filter['woche'])) {
            try {
                $tag = Carbon::parse($filter['woche']);
                $query->whereDate('gueltig_von', '<=', $tag->copy()->endOfWeek()->toDateString())
                    ->whereDate('gueltig_bis', '>=', $tag->copy()->startOfWeek()->toDateString());
            } catch (\Throwable) {
                // ungültiges Datum ignorieren
            }
        }

        if (! empty($filter['schuljahr']) && ctype_digit((string) $filter['schuljahr'])) {
            $start = Carbon::create((int) $filter['schuljahr'], 8, 1)->toDateString();
            $ende = Carbon::create((int) $filter['schuljahr'] + 1, 7, 31)->toDateString();
            $query->whereDate('gueltig_von', '>=', $start)->whereDate('gueltig_von', '<=', $ende);
        }

        if (! empty($filter['q'])) {
            $suche = '%'.str_replace(['%', '_'], ['\%', '\_'], trim($filter['q'])).'%';
            $query->where(function ($q) use ($suche) {
                $q->where('name', 'like', $suche)
                    ->orWhereHas('schueler', fn ($s) => $s->where('vorname', 'like', $suche)->orWhere('nachname', 'like', $suche))
                    ->orWhereHas('klasse', fn ($k) => $k->where('name', 'like', $suche));
            });
        }

        if (! empty($filter['meine'])) {
            $klassenIds = $user->paedKlassenIds()->all();
            $query->where(function ($q) use ($klassenIds) {
                $q->whereIn('klasse_id', $klassenIds)
                    ->orWhereIn('schueler_id', Schueler::query()->select('id')->whereIn('klasse_id', $klassenIds));
            });
        }

        return $query->orderByDesc('gueltig_von')->orderBy('name')->paginate($proSeite)->withQueryString();
    }

    /** Klassen, mit denen die Person arbeiten darf. */
    public function klassenFuer(User $user): Collection
    {
        if (WpPlan::darfAlles($user)) {
            return Klasse::orderBy('name')->get();
        }

        return Klasse::whereIn('id', $user->paedKlassenIds())->orderBy('name')->get();
    }

    /** Schuljahre (Startjahre), in denen es Pläne gibt – für den Filter. */
    public function schuljahre(): Collection
    {
        return WpPlan::where('is_vorlage', false)->pluck('gueltig_von')
            ->filter()
            ->map(fn ($d) => Carbon::parse($d)->month >= 8 ? Carbon::parse($d)->year : Carbon::parse($d)->year - 1)
            ->unique()->sortDesc()->values();
    }

    // ─── Pläne ────────────────────────────────────────────────────────────────

    /** Legt einen Plan an (leer mit Standard-Fächern oder aus einer Vorlage). */
    public function erstellen(array $daten, User $user): WpPlan
    {
        return DB::transaction(function () use ($daten, $user) {
            $vorlageId = $daten['vorlage_id'] ?? null;
            unset($daten['vorlage_id']);
            $daten['created_by'] = $user->id;
            $daten['is_vorlage'] = ! empty($daten['is_vorlage']);

            if ($vorlageId) {
                $vorlage = WpPlan::vorlagen()->findOrFail($vorlageId);
                $plan = $this->duplizieren($vorlage, array_merge($daten, [
                    'is_vorlage' => false,
                    'vorlage_name' => null,
                    'vorlage_id' => $vorlage->id,
                    'schueler_id' => null,
                    'parent_plan_id' => null,
                ]), $user);
            } else {
                $plan = WpPlan::create($daten);
                foreach (WpFach::default()->ordered()->get()->values() as $index => $fach) {
                    $plan->planFaecher()->create(['wp_fach_id' => $fach->id, 'sort_order' => $index]);
                }
            }

            $this->ablage->anfordern($plan);

            return $plan;
        });
    }

    /** Ändert die Eckdaten eines Plans. */
    public function aktualisieren(WpPlan $plan, array $daten, mixed $version = null): WpPlan
    {
        $this->versionPruefen($plan, $version);

        $erlaubt = ['name', 'gueltig_von', 'gueltig_bis', 'selbsteinschaetzung', 'formatvorlage_id', 'taegliche_uebungen_aktiv', 'vorlage_name'];
        if ($plan->isKlassenplan()) {
            $erlaubt[] = 'klasse_id';
        }
        if ($plan->isSchuelerplan()) {
            $erlaubt[] = 'niveau';
        }

        $plan->fill(array_intersect_key($daten, array_flip($erlaubt)))->save();

        // Zeitraum eines Klassenplans gilt auch für seine Kinderpläne
        if ($plan->isKlassenplan() && $plan->wasChanged(['gueltig_von', 'gueltig_bis'])) {
            $plan->kinderPlaene()->get()->each(function (WpPlan $kind) use ($plan) {
                $kind->update(['gueltig_von' => $plan->gueltig_von, 'gueltig_bis' => $plan->gueltig_bis]);
                $this->geaendert($kind);
            });
        }
        if ($plan->isKlassenplan() && $plan->wasChanged(['name', 'klasse_id'])) {
            // Ordner des Klassenplans ändert sich → Kinderpläne werden mit ihm verschoben
            $plan->kinderPlaene()->get()->each(fn (WpPlan $kind) => $this->ablage->anfordern($kind));
        }

        $this->geaendert($plan);

        return $plan->fresh();
    }

    /** Löscht einen Plan (Klassenplan inkl. Kinderplänen); die Ablage wandert nach `_gelöscht/`. */
    public function loeschen(WpPlan $plan): void
    {
        DB::transaction(function () use ($plan) {
            if ($plan->isKlassenplan()) {
                $plan->kinderPlaene()->get()->each(fn (WpPlan $kind) => $kind->delete());
            }
            $plan->delete();
        });

        if (! $plan->isVorlage()) {
            WpPlanAblageEntfernen::dispatch($plan->id);
        }
    }

    /**
     * Kopiert einen Plan mit Fächern, Aufgaben und täglichen Übungen.
     * Arbeitsblätter werden nicht kopiert.
     *
     * @param  array<int,int>|null  $aufgabenZuordnung  Rückgabe: alte Aufgaben-ID → neue Aufgaben-ID
     */
    public function duplizieren(WpPlan $quelle, array $overrides = [], ?User $user = null, ?array &$aufgabenZuordnung = null, ?callable $aufgabenFilter = null): WpPlan
    {
        $aufgabenZuordnung = [];

        $neu = $quelle->replicate([
            'created_at', 'updated_at', 'deleted_at', 'version',
            'ablage_ordner', 'ablage_datei', 'ablage_status', 'ablage_fehler', 'ablage_angefordert_at', 'abgelegt_at',
        ]);
        $neu->fill($overrides);
        $neu->created_by = $user?->id ?? auth()->id() ?? $quelle->created_by;
        $neu->version = 1;
        $neu->save();

        $quelle->load(['planFaecher.aufgaben', 'taeglicheUebungen']);

        foreach ($quelle->planFaecher as $planFach) {
            $neuesFach = $planFach->replicate(['created_at', 'updated_at']);
            $neuesFach->wp_plan_id = $neu->id;
            $neuesFach->save();

            foreach ($planFach->aufgaben as $aufgabe) {
                if ($aufgabenFilter && ! $aufgabenFilter($aufgabe)) {
                    continue;
                }
                $kopie = $aufgabe->replicate(['deleted_at', 'created_at', 'updated_at']);
                $kopie->wp_plan_fach_id = $neuesFach->id;
                $kopie->synced_from_id = null;
                $kopie->angepasst = false;
                $kopie->paed_diary_task_id = null;
                $kopie->save();
                $aufgabenZuordnung[$aufgabe->id] = $kopie->id;
            }
        }

        foreach ($quelle->taeglicheUebungen as $uebung) {
            $kopie = $uebung->replicate(['deleted_at', 'created_at', 'updated_at']);
            $kopie->wp_plan_id = $neu->id;
            $kopie->save();
        }

        return $neu;
    }

    /** Speichert einen Plan als Vorlage (ohne Klasse und Kind). */
    public function alsVorlageSpeichern(WpPlan $plan, string $name, User $user): WpPlan
    {
        return $this->duplizieren($plan, [
            'is_vorlage' => true,
            'vorlage_name' => $name,
            'name' => $name,
            'klasse_id' => null,
            'schueler_id' => null,
            'parent_plan_id' => null,
            'niveau' => null,
        ], $user);
    }

    /**
     * Erstellt einen Kinderplan. Aufgaben behalten ihre Herkunft (`synced_from_id`),
     * damit der spätere Abgleich sie an Ort und Stelle aktualisieren kann.
     * Mit Niveau werden nur Aufgaben ohne Niveau bzw. mit passendem Niveau übernommen.
     */
    public function kinderplanErstellen(WpPlan $klassenplan, Schueler $schueler, ?int $formatvorlageId = null, ?int $niveau = null, ?User $user = null): WpPlan
    {
        return DB::transaction(function () use ($klassenplan, $schueler, $formatvorlageId, $niveau, $user) {
            $zuordnung = [];
            $kind = $this->duplizieren($klassenplan, [
                'klasse_id' => null,
                'schueler_id' => $schueler->id,
                'parent_plan_id' => $klassenplan->id,
                'is_vorlage' => false,
                'vorlage_name' => null,
                'formatvorlage_id' => $formatvorlageId ?: $klassenplan->formatvorlage_id,
                'niveau' => $niveau,
                'name' => $klassenplan->name.' – '.$schueler->vorname.' '.$schueler->nachname,
            ], $user, $zuordnung, fn (WpAufgabe $a) => $this->passtZuNiveau($a, $niveau));

            foreach ($zuordnung as $alt => $neu) {
                WpAufgabe::whereKey($neu)->update(['synced_from_id' => $alt]);
            }

            $this->ablage->anfordern($kind);

            return $kind;
        });
    }

    public function passtZuNiveau(WpAufgabe $aufgabe, ?int $niveau): bool
    {
        return $niveau === null || $aufgabe->niveau === null || (int) $aufgabe->niveau === $niveau;
    }

    /**
     * „Nächste Woche fortschreiben“: Kopie mit um eine Woche verschobenem Zeitraum,
     * optional mit allen Kinderplänen (inkl. ihrer individuellen Aufgaben).
     */
    public function fortschreiben(WpPlan $plan, bool $mitKinderplaenen, User $user): WpPlan
    {
        return DB::transaction(function () use ($plan, $mitKinderplaenen, $user) {
            $zuordnung = [];
            $neu = $this->duplizieren($plan, [
                'gueltig_von' => $plan->gueltig_von->copy()->addWeek(),
                'gueltig_bis' => $plan->gueltig_bis->copy()->addWeek(),
            ], $user, $zuordnung);

            if ($plan->isKlassenplan() && $mitKinderplaenen) {
                foreach ($plan->kinderPlaene()->with('schueler')->get() as $kind) {
                    if (! $kind->schueler) {
                        continue;
                    }
                    $kindZuordnung = [];
                    $neuesKind = $this->duplizieren($kind, [
                        'parent_plan_id' => $neu->id,
                        'gueltig_von' => $neu->gueltig_von,
                        'gueltig_bis' => $neu->gueltig_bis,
                        'name' => $neu->name.' – '.$kind->schueler->vorname.' '.$kind->schueler->nachname,
                    ], $user, $kindZuordnung);

                    // Herkunft auf die Aufgaben des neuen Klassenplans umbiegen
                    $kind->load('planFaecher.aufgaben');
                    foreach ($kind->planFaecher as $pf) {
                        foreach ($pf->aufgaben as $a) {
                            if ($a->synced_from_id && isset($zuordnung[$a->synced_from_id], $kindZuordnung[$a->id])) {
                                WpAufgabe::whereKey($kindZuordnung[$a->id])->update([
                                    'synced_from_id' => $zuordnung[$a->synced_from_id],
                                    'angepasst' => $a->angepasst,
                                ]);
                            }
                        }
                    }
                    $this->ablage->anfordern($neuesKind);
                }
            }

            $this->ablage->anfordern($neu);

            return $neu;
        });
    }

    // ─── Fächer ───────────────────────────────────────────────────────────────

    public function fachHinzufuegen(WpPlan $plan, int $fachId): WpPlanFach
    {
        $vorhanden = $plan->planFaecher()->where('wp_fach_id', $fachId)->first();
        if ($vorhanden) {
            return $vorhanden;
        }

        $planFach = $plan->planFaecher()->create([
            'wp_fach_id' => $fachId,
            'sort_order' => ($plan->planFaecher()->max('sort_order') ?? -1) + 1,
        ]);
        $this->geaendert($plan);

        return $planFach;
    }

    public function fachEntfernen(WpPlanFach $planFach): void
    {
        $plan = $planFach->plan;
        DB::transaction(function () use ($planFach) {
            $planFach->aufgaben()->delete();
            $planFach->delete();
        });
        $this->geaendert($plan);
    }

    /** @param int[] $ids Plan-Fach-IDs in neuer Reihenfolge (nur Fächer dieses Plans) */
    public function faecherSortieren(WpPlan $plan, array $ids): void
    {
        $eigene = $plan->planFaecher()->pluck('id')->map(fn ($id) => (int) $id)->all();
        foreach (array_values($ids) as $index => $id) {
            if (in_array((int) $id, $eigene, true)) {
                WpPlanFach::whereKey($id)->update(['sort_order' => $index]);
            }
        }
        $this->geaendert($plan);
    }

    // ─── Aufgaben ─────────────────────────────────────────────────────────────

    public function aufgabeAnlegen(WpPlanFach $planFach, array $daten): WpAufgabe
    {
        $aufgabe = $planFach->aufgaben()->create([
            'aufgabe' => trim($daten['aufgabe']),
            'dauer' => $daten['dauer'] ?? null,
            'niveau' => $daten['niveau'] ?? null,
            'link' => $daten['link'] ?? null,
            'paed_diary_task_id' => $daten['paed_diary_task_id'] ?? null,
            'sort_order' => ($planFach->aufgaben()->max('sort_order') ?? -1) + 1,
        ]);
        $this->geaendert($planFach->plan);

        return $aufgabe;
    }

    public function aufgabeAendern(WpAufgabe $aufgabe, array $daten): WpAufgabe
    {
        $felder = array_intersect_key($daten, array_flip(['aufgabe', 'dauer', 'niveau', 'link']));
        if (isset($felder['aufgabe'])) {
            $felder['aufgabe'] = trim($felder['aufgabe']);
        }
        $aufgabe->fill($felder);

        if ($aufgabe->isDirty() && $aufgabe->synced_from_id) {
            // Kinderplan-Aufgabe weicht jetzt vom Klassenplan ab
            $aufgabe->angepasst = true;
        }
        $aufgabe->save();
        $this->geaendert($aufgabe->planFach->plan);

        return $aufgabe;
    }

    public function aufgabeLoeschen(WpAufgabe $aufgabe): void
    {
        $plan = $aufgabe->planFach->plan;
        $aufgabe->delete();
        $this->geaendert($plan);
    }

    /**
     * Neue Reihenfolge; Aufgaben dürfen dabei in ein anderes Fach desselben Plans wandern.
     *
     * @param  array<int, array{id:int, wp_plan_fach_id?:int}>  $eintraege
     */
    public function aufgabenSortieren(WpPlan $plan, array $eintraege): void
    {
        $faecher = $plan->planFaecher()->pluck('id')->map(fn ($id) => (int) $id)->all();
        $aufgaben = WpAufgabe::whereIn('wp_plan_fach_id', $faecher)->pluck('wp_plan_fach_id', 'id');

        $zaehler = [];
        foreach ($eintraege as $eintrag) {
            $id = (int) ($eintrag['id'] ?? 0);
            if (! $aufgaben->has($id)) {
                continue;
            }
            $fachId = (int) ($eintrag['wp_plan_fach_id'] ?? $aufgaben[$id]);
            if (! in_array($fachId, $faecher, true)) {
                continue;
            }
            $zaehler[$fachId] = ($zaehler[$fachId] ?? -1) + 1;
            WpAufgabe::whereKey($id)->update(['wp_plan_fach_id' => $fachId, 'sort_order' => $zaehler[$fachId]]);
        }

        $this->geaendert($plan);
    }

    /** Übernimmt eine Bibliotheks-Aufgabe bzw. Tagebuch-Aufgabe ist ein normales Anlegen mit Verknüpfung. */
    public function tagebuchAufgabeUebernehmen(WpPlanFach $planFach, PaedDiaryTask $task, array $daten): WpAufgabe
    {
        $plan = $planFach->plan;
        if (! $plan->isSchuelerplan() || (int) $plan->schueler_id !== (int) $task->schueler_id) {
            throw ValidationException::withMessages(['diary_task_id' => 'Die Tagebuch-Aufgabe gehört nicht zum Kind dieses Plans.']);
        }

        return $this->aufgabeAnlegen($planFach, [
            'aufgabe' => $daten['aufgabe'] ?? $task->title,
            'dauer' => $daten['dauer'] ?? null,
            'paed_diary_task_id' => $task->id,
        ]);
    }

    // ─── Tägliche Übungen ────────────────────────────────────────────────────

    public function uebungenSchalten(WpPlan $plan, bool $aktiv): void
    {
        $plan->update(['taegliche_uebungen_aktiv' => $aktiv]);
        $this->geaendert($plan);
    }

    public function uebungAnlegen(WpPlan $plan, string $text): WpTaeglicheUebung
    {
        $uebung = $plan->taeglicheUebungen()->create([
            'aufgabe' => trim($text),
            'sort_order' => ($plan->taeglicheUebungen()->max('sort_order') ?? -1) + 1,
        ]);
        $this->geaendert($plan);

        return $uebung;
    }

    public function uebungAendern(WpTaeglicheUebung $uebung, string $text): void
    {
        $uebung->update(['aufgabe' => trim($text)]);
        $this->geaendert($uebung->plan);
    }

    public function uebungLoeschen(WpTaeglicheUebung $uebung): void
    {
        $plan = $uebung->plan;
        $uebung->delete();
        $this->geaendert($plan);
    }

    /** @param int[] $ids */
    public function uebungenSortieren(WpPlan $plan, array $ids): void
    {
        $eigene = $plan->taeglicheUebungen()->pluck('id')->map(fn ($id) => (int) $id)->all();
        foreach (array_values($ids) as $index => $id) {
            if (in_array((int) $id, $eigene, true)) {
                WpTaeglicheUebung::whereKey($id)->update(['sort_order' => $index]);
            }
        }
        $this->geaendert($plan);
    }

    // ─── Abgleich Kinderplan ↔ Klassenplan ───────────────────────────────────

    /**
     * Was ein Abgleich ändern würde.
     *
     * @return array{faecher: array<int, array>, summe: array<string,int>}
     */
    public function abgleichVorschau(WpPlan $kind, ?int $fachId = null): array
    {
        $eltern = $this->elternFuerAbgleich($kind);
        $kind->load(['planFaecher.aufgaben', 'planFaecher.fach']);
        $eltern->load(['planFaecher.aufgaben', 'planFaecher.fach']);

        $summe = ['neu' => 0, 'aktualisiert' => 0, 'angepasst' => 0, 'entfallen' => 0, 'eigene' => 0, 'unveraendert' => 0];
        $faecher = [];

        $fachIds = $eltern->planFaecher->pluck('wp_fach_id')
            ->merge($kind->planFaecher->pluck('wp_fach_id'))
            ->filter()->unique();
        if ($fachId !== null) {
            $fachIds = $fachIds->filter(fn ($id) => (int) $id === $fachId);
        }

        foreach ($fachIds as $fid) {
            $elternFach = $eltern->planFaecher->firstWhere('wp_fach_id', $fid);
            $kindFach = $kind->planFaecher->firstWhere('wp_fach_id', $fid);
            $elternAufgaben = $elternFach ? $elternFach->aufgaben->filter(fn ($a) => $this->passtZuNiveau($a, $kind->niveau)) : collect();
            $kindAufgaben = $kindFach ? $kindFach->aufgaben : collect();
            $elternIds = $elternFach ? $elternFach->aufgaben->pluck('id')->all() : [];

            $eintrag = [
                'wp_fach_id' => (int) $fid,
                'name' => ($elternFach ?? $kindFach)?->display_name,
                'neu' => [], 'aktualisiert' => [], 'angepasst' => [], 'entfallen' => [], 'eigene' => [], 'unveraendert' => 0,
            ];

            foreach ($elternAufgaben as $ea) {
                $ka = $kindAufgaben->firstWhere('synced_from_id', $ea->id);
                if (! $ka) {
                    $eintrag['neu'][] = $ea->aufgabe;
                } elseif ($ka->angepasst) {
                    $eintrag['angepasst'][] = ['kind' => $ka->aufgabe, 'klasse' => $ea->aufgabe];
                } elseif ($this->unterscheidet($ka, $ea)) {
                    $eintrag['aktualisiert'][] = ['alt' => $ka->aufgabe, 'neu' => $ea->aufgabe];
                } else {
                    $eintrag['unveraendert']++;
                }
            }
            foreach ($kindAufgaben as $ka) {
                if ($ka->synced_from_id === null) {
                    $eintrag['eigene'][] = $ka->aufgabe;
                } elseif (! in_array($ka->synced_from_id, $elternIds, true)
                    || ! $elternAufgaben->contains('id', $ka->synced_from_id)) {
                    $eintrag['entfallen'][] = $ka->aufgabe;
                }
            }

            foreach (['neu', 'aktualisiert', 'angepasst', 'entfallen', 'eigene'] as $art) {
                $summe[$art] += count($eintrag[$art]);
            }
            $summe['unveraendert'] += $eintrag['unveraendert'];
            $faecher[] = $eintrag;
        }

        return ['faecher' => $faecher, 'summe' => $summe];
    }

    /**
     * Gleicht einen Kinderplan mit seinem Klassenplan ab. Unveränderte Aufgaben werden an Ort und
     * Stelle aktualisiert (Status je Kind bleibt erhalten), neue ergänzt, entfallene entfernt.
     * Angepasste und eigene Aufgaben bleiben standardmäßig erhalten.
     */
    public function abgleichen(WpPlan $kind, ?int $fachId = null, bool $angepassteBehalten = true, bool $eigeneBehalten = true): array
    {
        $eltern = $this->elternFuerAbgleich($kind);
        $vorschau = $this->abgleichVorschau($kind, $fachId);

        DB::transaction(function () use ($kind, $eltern, $fachId, $angepassteBehalten, $eigeneBehalten) {
            $kind->load('planFaecher.aufgaben');
            $eltern->load('planFaecher.aufgaben');

            foreach ($eltern->planFaecher as $elternFach) {
                if (! $elternFach->wp_fach_id || ($fachId !== null && (int) $elternFach->wp_fach_id !== $fachId)) {
                    continue;
                }

                $kindFach = $kind->planFaecher->firstWhere('wp_fach_id', $elternFach->wp_fach_id)
                    ?? $kind->planFaecher()->create([
                        'wp_fach_id' => $elternFach->wp_fach_id,
                        'custom_name' => $elternFach->custom_name,
                        'sort_order' => $elternFach->sort_order,
                    ])->setRelation('aufgaben', collect());

                $kindAufgaben = $kindFach->aufgaben;
                $elternAufgaben = $elternFach->aufgaben->filter(fn ($a) => $this->passtZuNiveau($a, $kind->niveau));
                $gueltig = $elternAufgaben->pluck('id')->all();

                foreach ($kindAufgaben as $ka) {
                    if ($ka->synced_from_id === null) {
                        if (! $eigeneBehalten) {
                            $ka->delete();
                        }
                    } elseif (! in_array($ka->synced_from_id, $gueltig, true)) {
                        $ka->delete();
                    }
                }

                foreach ($elternAufgaben as $ea) {
                    $ka = $kindAufgaben->firstWhere('synced_from_id', $ea->id);
                    $werte = [
                        'aufgabe' => $ea->aufgabe,
                        'dauer' => $ea->dauer,
                        'niveau' => $ea->niveau,
                        'link' => $ea->link,
                        'sort_order' => $ea->sort_order,
                    ];
                    if (! $ka) {
                        $kindFach->aufgaben()->create(array_merge($werte, ['synced_from_id' => $ea->id, 'angepasst' => false]));
                    } elseif (! $ka->angepasst || ! $angepassteBehalten) {
                        $ka->update(array_merge($werte, ['angepasst' => false]));
                    }
                }
            }

            // Fächer, die es im Klassenplan nicht mehr gibt: nur synchronisierte Aufgaben entfernen
            if ($fachId === null) {
                $elternFachIds = $eltern->planFaecher->pluck('wp_fach_id')->filter()->all();
                foreach ($kind->planFaecher as $kf) {
                    if ($kf->wp_fach_id && ! in_array($kf->wp_fach_id, $elternFachIds)) {
                        $kf->aufgaben()->whereNotNull('synced_from_id')->delete();
                    }
                }
            }
        });

        $this->geaendert($kind);

        return $vorschau;
    }

    private function elternFuerAbgleich(WpPlan $kind): WpPlan
    {
        if (! $kind->isSchuelerplan() || ! $kind->parent_plan_id || ! $kind->parentPlan) {
            throw ValidationException::withMessages(['plan' => 'Abgleich ist nur für Kinderpläne mit Klassenplan möglich.']);
        }

        return $kind->parentPlan;
    }

    private function unterscheidet(WpAufgabe $a, WpAufgabe $b): bool
    {
        return $a->aufgabe !== $b->aufgabe
            || (string) $a->dauer !== (string) $b->dauer
            || (int) $a->niveau !== (int) $b->niveau
            || (string) $a->link !== (string) $b->link;
    }

    // ─── Arbeitsblätter ───────────────────────────────────────────────────────

    public function dateiEntfernen(WpDatei $datei): void
    {
        $plan = $datei->plan;
        $this->ablage->dateiEntfernen($datei);
        $this->geaendert($plan);
    }

    // ─── Hilfen ───────────────────────────────────────────────────────────────

    /** Klassenstufe aus dem Klassennamen („3a“ → 3). */
    public static function klassenstufe(?string $klassenname): ?int
    {
        if ($klassenname && preg_match('/^\s*(\d{1,2})/', $klassenname, $m)) {
            return (int) $m[1];
        }

        return null;
    }

    /** JSON-Darstellung für den Editor. */
    public function toArray(WpPlan $plan, User $user): array
    {
        $plan->load([
            'planFaecher.aufgaben.paedDiaryTask', 'planFaecher.fach', 'taeglicheUebungen',
            'klasse', 'schueler.klasse', 'parentPlan', 'kinderPlaene.schueler', 'kinderPlaene.formatvorlage', 'dateien',
            'formatvorlage',
        ]);

        $darfBearbeiten = $user->can('update', $plan);

        return [
            'id' => $plan->id,
            'name' => $plan->name,
            'typ' => $plan->typ,
            'ist_vorlage' => $plan->isVorlage(),
            'ist_kinderplan' => $plan->isSchuelerplan(),
            'ist_klassenplan' => $plan->isKlassenplan(),
            'version' => (int) $plan->version,
            'gueltig_von' => $plan->gueltig_von?->toDateString(),
            'gueltig_bis' => $plan->gueltig_bis?->toDateString(),
            'zeitraum' => $plan->zeitraum,
            'klasse_id' => $plan->klasse_id,
            'klasse' => $plan->klasse?->name ?? $plan->schueler?->klasse?->name,
            'schueler' => $plan->schueler ? [
                'id' => $plan->schueler->id,
                'name' => $plan->schueler->vorname.' '.$plan->schueler->nachname,
            ] : null,
            'niveau' => $plan->niveau,
            'eltern' => $plan->parentPlan ? ['id' => $plan->parentPlan->id, 'name' => $plan->parentPlan->name] : null,
            'selbsteinschaetzung' => (int) $plan->selbsteinschaetzung,
            'formatvorlage_id' => $plan->formatvorlage_id,
            'vorlage_name' => $plan->vorlage_name,
            'taegliche_uebungen_aktiv' => (bool) $plan->taegliche_uebungen_aktiv,
            'darf_bearbeiten' => $darfBearbeiten,
            'faecher' => $plan->planFaecher->map(fn (WpPlanFach $pf) => [
                'id' => $pf->id,
                'wp_fach_id' => $pf->wp_fach_id,
                'name' => $pf->display_name,
                'symbol' => $pf->fach?->symbol_html ?? '',
                'aufgaben' => $pf->aufgaben->map(fn (WpAufgabe $a) => $this->aufgabeArray($a))->values(),
            ])->values(),
            'uebungen' => $plan->taeglicheUebungen->map(fn ($u) => ['id' => $u->id, 'aufgabe' => $u->aufgabe])->values(),
            'dateien' => $plan->alleDateien()->map(fn (WpDatei $d) => $this->dateiArray($d))->values(),
            'kinderplaene' => $plan->kinderPlaene->sortBy(fn ($k) => $k->schueler?->nachname)->map(fn (WpPlan $k) => [
                'id' => $k->id,
                'name' => trim(($k->schueler?->vorname ?? '').' '.($k->schueler?->nachname ?? '')),
                'niveau' => $k->niveau,
                'formatvorlage' => $k->formatvorlage?->name,
                'url' => route('wp.edit', $k),
                'pdf' => route('wp.export.pdf', $k),
            ])->values(),
            'ablage' => [
                'aktiv' => $this->ablage->aktiv(),
                'status' => $plan->ablage_status,
                'fehler' => $plan->ablage_fehler,
                'abgelegt_at' => $plan->abgelegt_at?->format('d.m.Y H:i'),
                'link' => $this->ablage->webLink($plan),
            ],
        ];
    }

    public function aufgabeArray(WpAufgabe $a): array
    {
        return [
            'id' => $a->id,
            'wp_plan_fach_id' => $a->wp_plan_fach_id,
            'aufgabe' => $a->aufgabe,
            'dauer' => $a->dauer,
            'niveau' => $a->niveau,
            'link' => $a->link,
            'synchron' => $a->isSynced(),
            'angepasst' => (bool) $a->angepasst,
            'eigene' => $a->synced_from_id === null,
            'tagebuch' => $a->paed_diary_task_id ? [
                'id' => $a->paed_diary_task_id,
                'status' => $a->paedDiaryTask?->status,
            ] : null,
        ];
    }

    public function dateiArray(WpDatei $d): array
    {
        return [
            'id' => $d->id,
            'name' => $d->name,
            'mime_type' => $d->mime_type,
            'groesse' => $d->groesse_text,
            'status' => $d->status,
            'fehler' => $d->fehler,
            'vom_klassenplan' => (bool) $d->getAttribute('vom_klassenplan'),
            'url' => route('wp.dateien.show', $d),
            'nextcloud' => $this->ablage->dateiWebLink($d),
        ];
    }
}
