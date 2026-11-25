<?php

namespace App\Services\Wochenplan;

use App\Models\Klasse;
use App\Models\PaedDiaryTask;
use App\Models\Schueler;
use App\Models\User;
use App\Models\Wochenplan\WpAufgabe;
use App\Models\Wochenplan\WpAufgabeStatus;
use App\Models\Wochenplan\WpPlan;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

/**
 * Bearbeitungsstand der Wochenplan-Aufgaben je Kind (offen/teilweise/erledigt + Notiz).
 *
 * - Welche Aufgaben ein Kind in einer Woche hat: die seines Kinderplans, sonst die des
 *   Klassenplans seiner Klasse ({@see planeFuerSchueler()}); tägliche Übungen zählen nicht.
 * - Mit Tagebuch-Anbindung (Setting `wochenplan_tagebuch_anbindung`) wird „erledigt“ mit
 *   verknüpften Tagebuch-Aufgaben in beide Richtungen abgeglichen ({@see tagebuchAufgabeGeaendert()}).
 * - Kein Verlauf: gespeichert wird nur der aktuelle Stand mit Person und Zeitpunkt.
 */
class WpStatusService
{
    /** Verhindert Endlosschleifen beim beidseitigen Abgleich mit dem Tagebuch. */
    private static bool $imAbgleich = false;

    public static function tagebuchAktiv(): bool
    {
        return (bool) settings('wochenplan_tagebuch_anbindung');
    }

    // ─── Welche Aufgaben hat ein Kind? ────────────────────────────────────────

    /** Pläne, deren Aufgaben für das Kind in der Woche gelten. */
    public function planeFuerSchueler(Schueler $schueler, Carbon $tag): Collection
    {
        $von = $tag->copy()->startOfWeek()->toDateString();
        $bis = $tag->copy()->endOfWeek()->toDateString();

        $kinderplaene = WpPlan::query()
            ->where('is_vorlage', false)
            ->where('schueler_id', $schueler->id)
            ->whereDate('gueltig_von', '<=', $bis)->whereDate('gueltig_bis', '>=', $von)
            ->orderBy('gueltig_von')
            ->get();

        if ($kinderplaene->isNotEmpty() || ! $schueler->klasse_id) {
            return $kinderplaene;
        }

        return WpPlan::query()
            ->where('is_vorlage', false)
            ->whereNull('schueler_id')
            ->where('klasse_id', $schueler->klasse_id)
            ->whereDate('gueltig_von', '<=', $bis)->whereDate('gueltig_bis', '>=', $von)
            ->orderBy('gueltig_von')
            ->get();
    }

    /** Darf für dieses Kind ein Status an dieser Aufgabe gesetzt werden? */
    public function aufgabeGiltFuer(WpAufgabe $aufgabe, Schueler $schueler): bool
    {
        $plan = $aufgabe->planFach?->plan;
        if (! $plan || $plan->isVorlage()) {
            return false;
        }

        if ($plan->isSchuelerplan()) {
            return (int) $plan->schueler_id === (int) $schueler->id;
        }

        if ((int) $plan->klasse_id !== (int) $schueler->klasse_id) {
            return false;
        }

        // Hat das Kind zu diesem Klassenplan einen eigenen Kinderplan, gelten dessen Aufgaben
        return ! WpPlan::where('parent_plan_id', $plan->id)->where('schueler_id', $schueler->id)->exists();
    }

    /**
     * Aufgaben und Status eines Kindes in einer Woche (für die Tagebuch-Seitenleiste und die API).
     */
    public function wocheFuerSchueler(Schueler $schueler, Carbon $tag): array
    {
        $plaene = $this->planeFuerSchueler($schueler, $tag)->load(['planFaecher.aufgaben', 'planFaecher.fach']);
        $aufgabenIds = $plaene->flatMap(fn ($p) => $p->planFaecher->flatMap->aufgaben->pluck('id'))->all();
        $status = WpAufgabeStatus::with('user')->where('schueler_id', $schueler->id)
            ->whereIn('wp_aufgabe_id', $aufgabenIds)->get()->keyBy('wp_aufgabe_id');

        $ergebnis = [];
        foreach ($plaene as $plan) {
            $ergebnis[] = [
                'id' => $plan->id,
                'name' => $plan->name,
                'typ' => $plan->typ,
                'zeitraum' => $plan->zeitraum,
                'url' => route('wp.edit', $plan),
                'faecher' => $plan->planFaecher->filter(fn ($pf) => $pf->aufgaben->isNotEmpty())->map(fn ($pf) => [
                    'id' => $pf->id,
                    'name' => $pf->display_name,
                    'aufgaben' => $pf->aufgaben->map(fn (WpAufgabe $a) => $this->aufgabeMitStatus($a, $status->get($a->id)))->values(),
                ])->values(),
            ];
        }

        return [
            'schueler' => ['id' => $schueler->id, 'name' => $schueler->vorname.' '.$schueler->nachname],
            'woche' => ['von' => $tag->copy()->startOfWeek()->toDateString(), 'bis' => $tag->copy()->endOfWeek()->toDateString()],
            'plaene' => $ergebnis,
            'zusammenfassung' => $this->zaehlen(collect($aufgabenIds), $status),
        ];
    }

    /**
     * Wochenpläne eines Kindes für die Schülerakte (neueste zuerst): eigene Kinderpläne und
     * Klassenpläne seiner Klasse, für die es keinen Kinderplan hat – jeweils mit Stand.
     *
     * @return array<int, array{id:int, name:string, typ:string, zeitraum:string, url:string, pdf:string, zusammenfassung:array}>
     */
    public function planeFuerSchuelerakte(Schueler $schueler, int $anzahl = 15): array
    {
        $kinderplaene = WpPlan::where('is_vorlage', false)->where('schueler_id', $schueler->id)
            ->with('planFaecher.aufgaben')->orderByDesc('gueltig_von')->limit($anzahl)->get();

        $klassenplaene = $schueler->klasse_id
            ? WpPlan::where('is_vorlage', false)->whereNull('schueler_id')->where('klasse_id', $schueler->klasse_id)
                ->whereNotIn('id', $kinderplaene->pluck('parent_plan_id')->filter())
                ->with('planFaecher.aufgaben')->orderByDesc('gueltig_von')->limit($anzahl)->get()
            : collect();

        $plaene = $kinderplaene->concat($klassenplaene)->sortByDesc('gueltig_von')->take($anzahl)->values();
        $ids = $plaene->flatMap(fn ($p) => $p->planFaecher->flatMap->aufgaben->pluck('id'));
        $status = WpAufgabeStatus::where('schueler_id', $schueler->id)->whereIn('wp_aufgabe_id', $ids)->get()->keyBy('wp_aufgabe_id');

        return $plaene->map(fn (WpPlan $p) => [
            'id' => $p->id,
            'name' => $p->name,
            'typ' => $p->typ,
            'zeitraum' => $p->zeitraum,
            'url' => route('wp.edit', $p),
            'pdf' => route('wp.export.pdf', $p),
            'zusammenfassung' => $this->zaehlen($p->planFaecher->flatMap->aufgaben->pluck('id'), $status),
        ])->all();
    }

    /**
     * Zusammenfassung je Kind einer Klasse für eine Woche (Tagebuch-Wochenansicht).
     *
     * @return array<int, array{erledigt:int, teilweise:int, gesamt:int}>
     */
    public function zusammenfassungKlasse(Klasse $klasse, Carbon $tag, ?Collection $schuelerIds = null): array
    {
        $von = $tag->copy()->startOfWeek()->toDateString();
        $bis = $tag->copy()->endOfWeek()->toDateString();
        $schuelerIds ??= Schueler::where('klasse_id', $klasse->id)->pluck('id');
        if ($schuelerIds->isEmpty()) {
            return [];
        }

        $klassenplaene = WpPlan::where('is_vorlage', false)->whereNull('schueler_id')->where('klasse_id', $klasse->id)
            ->whereDate('gueltig_von', '<=', $bis)->whereDate('gueltig_bis', '>=', $von)
            ->with('planFaecher.aufgaben')->get();
        $kinderplaene = WpPlan::where('is_vorlage', false)->whereIn('schueler_id', $schuelerIds)
            ->whereDate('gueltig_von', '<=', $bis)->whereDate('gueltig_bis', '>=', $von)
            ->with('planFaecher.aufgaben')->get()->groupBy('schueler_id');

        $klassenAufgaben = $klassenplaene->flatMap(fn ($p) => $p->planFaecher->flatMap->aufgaben->pluck('id'));

        $zuordnung = [];
        foreach ($schuelerIds as $id) {
            $zuordnung[$id] = isset($kinderplaene[$id])
                ? $kinderplaene[$id]->flatMap(fn ($p) => $p->planFaecher->flatMap->aufgaben->pluck('id'))
                : $klassenAufgaben;
        }

        $alle = collect($zuordnung)->flatten()->unique()->values();
        $status = WpAufgabeStatus::whereIn('schueler_id', $schuelerIds)->whereIn('wp_aufgabe_id', $alle)->get()
            ->groupBy('schueler_id');

        $ergebnis = [];
        foreach ($zuordnung as $id => $aufgabenIds) {
            if ($aufgabenIds->isEmpty()) {
                continue;
            }
            $ergebnis[$id] = $this->zaehlen($aufgabenIds, ($status[$id] ?? collect())->keyBy('wp_aufgabe_id'));
        }

        return $ergebnis;
    }

    /**
     * Matrix Kinder × Aufgaben eines Plans.
     * Beim Klassenplan: Spalten = Aufgaben des Klassenplans; Kinder mit Kinderplan nutzen dessen
     * passende Aufgabe (`synced_from_id`), zusätzliche eigene Aufgaben laufen unter „Weitere“.
     */
    public function matrix(WpPlan $plan): array
    {
        $plan->load(['planFaecher.aufgaben', 'planFaecher.fach', 'klasse', 'schueler']);

        if ($plan->isSchuelerplan()) {
            $kinder = collect([$plan->schueler])->filter();
            $kinderplaene = collect([$plan->schueler_id => $plan]);
        } else {
            $kinder = $plan->klasse
                ? $plan->klasse->schueler()->orderBy('nachname')->orderBy('vorname')->get()
                : collect();
            $kinderplaene = $plan->kinderPlaene()->with('planFaecher.aufgaben')->get()->keyBy('schueler_id');
        }

        $spalten = [];
        foreach ($plan->planFaecher as $pf) {
            foreach ($pf->aufgaben as $a) {
                $spalten[] = ['id' => $a->id, 'fach' => $pf->display_name, 'aufgabe' => $a->aufgabe, 'niveau' => $a->niveau];
            }
        }

        $alleAufgabenIds = collect($spalten)->pluck('id');
        foreach ($kinderplaene as $kp) {
            $alleAufgabenIds = $alleAufgabenIds->merge($kp->planFaecher->flatMap->aufgaben->pluck('id'));
        }
        $status = WpAufgabeStatus::with('user')->whereIn('schueler_id', $kinder->pluck('id'))
            ->whereIn('wp_aufgabe_id', $alleAufgabenIds->unique())->get()
            ->groupBy('schueler_id')->map->keyBy('wp_aufgabe_id');

        $zeilen = [];
        foreach ($kinder as $kind) {
            $eigeneStati = $status[$kind->id] ?? collect();
            $kp = $plan->isKlassenplan() ? ($kinderplaene[$kind->id] ?? null) : null;
            $kpAufgaben = $kp ? $kp->planFaecher->flatMap->aufgaben : collect();

            $zellen = [];
            $verwendet = [];
            foreach ($spalten as $spalte) {
                $aufgabeId = $spalte['id'];
                if ($kp) {
                    $passend = $kpAufgaben->firstWhere('synced_from_id', $spalte['id']);
                    $aufgabeId = $passend?->id;
                }
                if ($aufgabeId === null) {
                    $zellen[] = null;

                    continue;
                }
                $verwendet[] = $aufgabeId;
                $s = $eigeneStati->get($aufgabeId);
                $zellen[] = [
                    'aufgabe_id' => $aufgabeId,
                    'status' => $s?->status ?? WpAufgabeStatus::OFFEN,
                    'notiz' => $s?->notiz,
                    'von' => $s?->user?->name,
                    'am' => $s?->updated_at?->format('d.m.Y H:i'),
                ];
            }

            $weitere = $kpAufgaben->reject(fn ($a) => in_array($a->id, $verwendet, true))->values();

            $zeilen[] = [
                'schueler' => ['id' => $kind->id, 'name' => $kind->nachname.', '.$kind->vorname],
                'kinderplan' => $kp ? ['id' => $kp->id, 'url' => route('wp.edit', $kp)] : null,
                'zellen' => $zellen,
                'weitere' => $weitere->map(fn ($a) => $this->aufgabeMitStatus($a, $eigeneStati->get($a->id)))->all(),
                'zusammenfassung' => $this->zaehlen(collect($verwendet)->merge($weitere->pluck('id')), $eigeneStati),
            ];
        }

        return ['spalten' => $spalten, 'zeilen' => $zeilen];
    }

    // ─── Status setzen ────────────────────────────────────────────────────────

    public function setzen(WpAufgabe $aufgabe, Schueler $schueler, string $status, ?string $notiz, ?User $user): ?WpAufgabeStatus
    {
        if (! in_array($status, WpAufgabeStatus::STATUS, true)) {
            throw ValidationException::withMessages(['status' => 'Unbekannter Status.']);
        }
        if (! $this->aufgabeGiltFuer($aufgabe, $schueler)) {
            throw ValidationException::withMessages(['wp_aufgabe_id' => 'Diese Aufgabe gehört nicht zum Wochenplan des Kindes.']);
        }

        $notiz = $notiz !== null ? trim(mb_substr($notiz, 0, 255)) : null;
        $notiz = $notiz === '' ? null : $notiz;

        $eintrag = WpAufgabeStatus::where('wp_aufgabe_id', $aufgabe->id)->where('schueler_id', $schueler->id)->first();
        $vorher = $eintrag?->status ?? WpAufgabeStatus::OFFEN;

        if ($status === WpAufgabeStatus::OFFEN && $notiz === null) {
            $eintrag?->delete();
            $eintrag = null;
        } else {
            $eintrag ??= new WpAufgabeStatus(['wp_aufgabe_id' => $aufgabe->id, 'schueler_id' => $schueler->id]);
            $eintrag->fill(['status' => $status, 'notiz' => $notiz, 'user_id' => $user?->id])->save();
        }

        if ($vorher !== $status) {
            $this->tagebuchNachziehen($aufgabe, $schueler, $status);
        }

        return $eintrag;
    }

    /**
     * Mehrere Zellen auf einmal (Matrix: „alle erledigt“, Mehrfachauswahl).
     *
     * @param  array<int, array{wp_aufgabe_id:int, schueler_id:int}>  $zellen
     */
    public function mehrere(array $zellen, string $status, ?User $user, ?callable $darfSchueler = null): int
    {
        $anzahl = 0;
        foreach ($zellen as $zelle) {
            $aufgabe = WpAufgabe::find($zelle['wp_aufgabe_id'] ?? 0);
            $schueler = Schueler::find($zelle['schueler_id'] ?? 0);
            if (! $aufgabe || ! $schueler || ! $this->aufgabeGiltFuer($aufgabe, $schueler)) {
                continue;
            }
            if ($darfSchueler && ! $darfSchueler($schueler, $aufgabe)) {
                continue;
            }
            $vorhanden = WpAufgabeStatus::where('wp_aufgabe_id', $aufgabe->id)->where('schueler_id', $schueler->id)->value('notiz');
            $this->setzen($aufgabe, $schueler, $status, $vorhanden, $user);
            $anzahl++;
        }

        return $anzahl;
    }

    // ─── Abgleich mit dem Tagebuch ───────────────────────────────────────────

    /** Wochenplan → Tagebuch: nur „erledigt“ schließt; Zurücknehmen öffnet wieder. */
    private function tagebuchNachziehen(WpAufgabe $aufgabe, Schueler $schueler, string $status): void
    {
        if (self::$imAbgleich || ! self::tagebuchAktiv() || ! $aufgabe->paed_diary_task_id) {
            return;
        }

        $task = PaedDiaryTask::find($aufgabe->paed_diary_task_id);
        if (! $task || (int) $task->schueler_id !== (int) $schueler->id) {
            return;
        }

        self::$imAbgleich = true;
        try {
            if ($status === WpAufgabeStatus::ERLEDIGT && $task->status !== 'closed') {
                $task->update(['status' => 'closed', 'highlighted' => false, 'closed_at' => now()]);
            } elseif ($status !== WpAufgabeStatus::ERLEDIGT && $task->status === 'closed') {
                $task->update(['status' => 'open', 'closed_at' => null]);
            }
        } finally {
            self::$imAbgleich = false;
        }
    }

    /** Tagebuch → Wochenplan (vom Observer aufgerufen, wenn sich der Status einer Tagebuch-Aufgabe ändert). */
    public function tagebuchAufgabeGeaendert(PaedDiaryTask $task): void
    {
        if (self::$imAbgleich || ! self::tagebuchAktiv() || ! $task->schueler_id) {
            return;
        }

        $schueler = Schueler::find($task->schueler_id);
        if (! $schueler) {
            return;
        }

        self::$imAbgleich = true;
        try {
            WpAufgabe::where('paed_diary_task_id', $task->id)->get()->each(function (WpAufgabe $aufgabe) use ($task, $schueler) {
                if (! $this->aufgabeGiltFuer($aufgabe, $schueler)) {
                    return;
                }
                $eintrag = WpAufgabeStatus::where('wp_aufgabe_id', $aufgabe->id)->where('schueler_id', $schueler->id)->first();
                $aktuell = $eintrag?->status ?? WpAufgabeStatus::OFFEN;

                if ($task->status === 'closed' && $aktuell !== WpAufgabeStatus::ERLEDIGT) {
                    $this->setzen($aufgabe, $schueler, WpAufgabeStatus::ERLEDIGT, $eintrag?->notiz, null);
                } elseif ($task->status !== 'closed' && $aktuell === WpAufgabeStatus::ERLEDIGT) {
                    $this->setzen($aufgabe, $schueler, WpAufgabeStatus::OFFEN, $eintrag?->notiz, null);
                }
            });
        } finally {
            self::$imAbgleich = false;
        }
    }

    // ─── Hilfen ───────────────────────────────────────────────────────────────

    public function aufgabeMitStatus(WpAufgabe $a, ?WpAufgabeStatus $s): array
    {
        return [
            'id' => $a->id,
            'aufgabe' => $a->aufgabe,
            'dauer' => $a->dauer,
            'niveau' => $a->niveau,
            'link' => $a->link,
            'tagebuch' => $a->paed_diary_task_id !== null,
            'status' => $s?->status ?? WpAufgabeStatus::OFFEN,
            'notiz' => $s?->notiz,
            'von' => $s?->user?->name,
            'am' => $s?->updated_at?->format('d.m.Y H:i'),
        ];
    }

    /** @return array{erledigt:int, teilweise:int, gesamt:int} */
    private function zaehlen(Collection $aufgabenIds, Collection $statusNachAufgabe): array
    {
        $ids = $aufgabenIds->unique();
        $erledigt = 0;
        $teilweise = 0;
        foreach ($ids as $id) {
            $s = $statusNachAufgabe->get($id)?->status;
            if ($s === WpAufgabeStatus::ERLEDIGT) {
                $erledigt++;
            } elseif ($s === WpAufgabeStatus::TEILWEISE) {
                $teilweise++;
            }
        }

        return ['erledigt' => $erledigt, 'teilweise' => $teilweise, 'gesamt' => $ids->count()];
    }
}
