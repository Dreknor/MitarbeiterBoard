<?php

namespace App\Services;

use App\Models\Absence;
use App\Models\personal\Employment;
use App\Models\personal\HortFaktor;
use App\Models\personal\HortFaktorWert;
use App\Models\personal\HortMonatZusatz;
use App\Models\personal\HortPlanung;
use App\Models\personal\HortPlanungMonat;
use App\Models\personal\HortPlanungPerson;
use App\Models\personal\HortPlanungSnapshot;
use App\Models\personal\HortZusatzstundenTyp;
use App\Models\personal\Timesheet;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class HortPlanungService
{
    // ── §4.1 Kernberechnungen pro Monat ──────────────────────────────────────

    /**
     * Berechnet alle VZÄ- und Stundenwerte für einen Planungsmonat.
     * Dynamische Faktor-Berechnung nach position-sortierter Reihenfolge.
     *
     * @param HortPlanungMonat $monat
     * @return array
     */
    public function berechneMonat(HortPlanungMonat $monat, ?HortPlanung $planung = null): array
    {
        $planung  = $planung ?? $monat->planung;

        // Personen laden (Eager Load vermeiden von N+1)
        $personen = $monat->relationLoaded('personen')
            ? $monat->personen
            : $monat->personen()->get();

        // Summenwerte Personen
        $summe_sp1 = (float) $personen->sum('stunden_gesamt');
        $summe_sp2 = (float) $personen->sum('stunden_stadt');

        // Division-by-zero Schutz
        $vz = $monat->vollzeitstunden > 0 ? $monat->vollzeitstunden : 40;

        $summe_vz_sp1 = $summe_sp1 / $vz;
        $summe_vz_sp2 = $summe_sp2 / $vz;

        // ── Dynamische Faktor-Berechnung ──────────────────────────────────────
        $faktoren = $planung->relationLoaded('faktoren')
            ? $planung->faktoren->where('aktiv', true)->sortBy('position')
            : $planung->faktoren()->where('aktiv', true)->orderBy('position')->with('werte')->get();

        $betreuungsschluessel = 0;
        $faktor_ergebnisse    = [];
        $zwischensumme        = 0;

        foreach ($faktoren as $faktor) {
            $wert = $faktor->wertFuerMonat($monat->monat);

            if ($wert === null || $wert == 0) {
                $faktor_ergebnisse[$faktor->kuerzel] = [
                    'bezeichnung' => $faktor->bezeichnung,
                    'wert'        => $wert,
                    'vz'          => 0,
                ];
                // Zwischensumme bleibt unverändert (kein Beitrag)
                continue;
            }

            $vz_ergebnis = match ($faktor->berechnungs_typ) {
                'divisor'        => $monat->kinderanzahl > 0 ? $monat->kinderanzahl / $wert : 0,
                'faktor_auf_bs'  => $betreuungsschluessel * $wert,
                'faktor_auf_summe' => $zwischensumme * $wert,
                default          => 0,
            };

            // Sonderfall: Der erste divisor-Faktor definiert den Betreuungsschlüssel
            if ($faktor->berechnungs_typ === 'divisor' && $betreuungsschluessel === 0) {
                $betreuungsschluessel = $vz_ergebnis;
            }

            $faktor_ergebnisse[$faktor->kuerzel] = [
                'bezeichnung' => $faktor->bezeichnung,
                'wert'        => $wert,
                'vz'          => round($vz_ergebnis, 5),
            ];

            $zwischensumme += $vz_ergebnis;
        }

        $summe_gesetz_vz       = $zwischensumme;
        $summe_stunden_gesetzl = $summe_gesetz_vz * $vz;

        // ── Dynamische Zusatzstunden ──────────────────────────────────────────
        $zusatzstunden_details = $monat->zusatzstunden_details();
        $summe_zusatzstunden   = (float) $zusatzstunden_details->sum('stunden');

        // Budget-Rest (analog Excel Zeile 29):
        // (Gesetzl. Stunden + alle Zusatzstunden) − tatsächlich geplante SP1
        $budget_gesamt   = $summe_stunden_gesetzl + $summe_zusatzstunden;
        $budget_rest_sp1 = $budget_gesamt - $summe_sp1;

        // Stadt-Differenz (SP2 enthält keine Zusatzstunden)
        $differenz_stadt  = $summe_sp2 - $summe_stunden_gesetzl;
        $differenz_vz_sp2 = $summe_vz_sp2 - $summe_gesetz_vz;

        // Ist-Daten (für Rückblick)
        $summe_ist     = (float) $personen->sum('stunden_ist');

        // Vertragsstunden live aus Employment-Daten berechnen (identisch zur
        // Einzelzeilen-Logik in show.blade.php), damit Σ Verträge mit den pro
        // Person angezeigten Werten übereinstimmt – auch nach Vertragsänderungen.
        $monatStart   = $monat->monat;
        $departmentId = $planung->department_id;
        $summe_vertrag = 0;

        foreach ($personen as $person) {
            $user = $person->relationLoaded('user') ? $person->user : $person->user;
            if (!$user) continue;

            $employments = $user->relationLoaded('employments')
                ? $user->employments
                : $user->employments()->get();

            $summe_vertrag += (float) $employments
                ->filter(fn($emp) =>
                    $emp->department_id === $departmentId
                    && $emp->start->startOfDay()->lessThanOrEqualTo($monatStart)
                    && (is_null($emp->end) || $emp->end->greaterThanOrEqualTo($monatStart))
                )
                ->sum('hours');
        }

        return [
            'summe_sp1'               => round($summe_sp1, 2),
            'summe_sp2'               => round($summe_sp2, 2),
            'summe_vz_sp1'            => round($summe_vz_sp1, 4),
            'summe_vz_sp2'            => round($summe_vz_sp2, 4),
            'betreuungsschluessel'    => round($betreuungsschluessel, 4),
            'faktoren'                => $faktor_ergebnisse,
            'summe_gesetz_vz'         => round($summe_gesetz_vz, 4),
            'summe_stunden_gesetzl'   => round($summe_stunden_gesetzl, 2),
            'zusatzstunden'           => $zusatzstunden_details->toArray(),
            'summe_zusatzstunden'     => round($summe_zusatzstunden, 2),
            'budget_gesamt'           => round($budget_gesamt, 2),
            'budget_rest_sp1'         => round($budget_rest_sp1, 2),
            'differenz_stadt'         => round($differenz_stadt, 2),
            'differenz_vz_sp2'        => round($differenz_vz_sp2, 4),
            // Ist-Vergleichsdaten
            'summe_vertrag'           => round($summe_vertrag, 2),
            'summe_ist'               => round($summe_ist, 2),
            'abweichung_soll_vertrag' => round($summe_sp1 - $summe_vertrag, 2),
            'abweichung_soll_ist'     => round($summe_sp1 - $summe_ist, 2),
        ];
    }

    // ── §4.2 Grundarbeitszeit pro Person berechnen ───────────────────────────

    /**
     * Aufschlüsselung der Personenstunden in gesetzliche Komponenten.
     * Erweiterung gegenüber der Excel (pro Person, nicht nur global).
     *
     * @param HortPlanungPerson $person
     * @return array
     */
    public function berechneGrundarbeitszeit(HortPlanungPerson $person): array
    {
        $monat   = $person->monat;
        $planung = $monat->planung;
        $vz      = $monat->vollzeitstunden > 0 ? $monat->vollzeitstunden : 40;

        $vz_anteil = $monat->vollzeitstunden > 0 && ($person->stunden_gesamt ?? 0) > 0
            ? $person->stunden_gesamt / $vz
            : 0;

        // Dynamische Aufschlüsselung: Anteil der Person an jeder Faktor-Kategorie
        $faktoren     = $planung->faktoren()->where('aktiv', true)->orderBy('position')->with('werte')->get();
        $gesamt_faktor = 0;

        foreach ($faktoren as $f) {
            if ($f->berechnungs_typ === 'divisor') {
                continue;
            }
            $wert = $f->wertFuerMonat($monat->monat);
            if ($wert) {
                $gesamt_faktor += $wert;
            }
        }

        // Gesamt-Divisor: 1 (Betreuung) + Summe aller Multiplikator-Faktoren
        $divisor      = 1 + $gesamt_faktor;
        $erzieher_vz  = $divisor > 0 ? $vz_anteil / $divisor : 0;

        $aufschluesselung = [
            'vz_anteil'     => round($vz_anteil, 4),
            'wochenstunden' => round($person->stunden_gesamt ?? 0, 2),
            'erzieher_vz'   => round($erzieher_vz, 4),
        ];

        foreach ($faktoren as $f) {
            if ($f->berechnungs_typ === 'divisor') {
                continue;
            }
            $wert = $f->wertFuerMonat($monat->monat) ?? 0;
            $aufschluesselung[$f->kuerzel . '_vz'] = round($erzieher_vz * $wert, 4);
        }

        // Zusatzstunden-Anteil: proportional auf aktive Personen verteilt
        $personen_count = $monat->personen()
            ->whereNotNull('stunden_gesamt')
            ->where('stunden_gesamt', '>', 0)
            ->count();

        $aufschluesselung['zusatzstunden'] = $personen_count > 0
            ? round($monat->summeZusatzstunden() / $personen_count, 2)
            : 0;

        return $aufschluesselung;
    }

    // ── §4.3 Gesamtplanung berechnen ─────────────────────────────────────────

    /**
     * Berechnet alle Monate einer Planung.
     *
     * @param HortPlanung $planung
     * @return Collection
     */
    public function berechnePlanung(HortPlanung $planung): Collection
    {
        return $planung->monate()
            ->with(['personen.user', 'monatZusatzstunden.typ'])
            ->get()
            ->map(fn($monat) => [
                'monat'        => $monat->monat,
                'parameter'    => [
                    'kinderanzahl'    => $monat->kinderanzahl,
                    'vollzeitstunden' => $monat->vollzeitstunden,
                ],
                'personen'     => $monat->personen,
                'berechnungen' => $this->berechneMonat($monat),
            ]);
    }

    // ── §4.4 Import aus Employments ──────────────────────────────────────────

    /**
     * Befüllt hort_planung_personen aus aktiven Anstellungen der Abteilung.
     *
     * @param HortPlanung $planung
     * @return int Anzahl importierter Einträge
     */
    public function importiereAusEmployments(HortPlanung $planung): int
    {
        $count = 0;

        foreach ($planung->monate as $monat) {
            $employments = Employment::query()
                ->where('department_id', $planung->department_id)
                ->active($monat->monat, $monat->monat->copy()->endOfMonth())
                ->get();

            foreach ($employments as $emp) {
                HortPlanungPerson::updateOrCreate(
                    [
                        'hort_planung_monat_id' => $monat->id,
                        'user_id'               => $emp->employe_id,
                    ],
                    [
                        'stunden_gesamt'  => $emp->hours,
                        'stunden_stadt'   => $emp->hours,
                        'stunden_vertrag' => $emp->hours,
                    ]
                );
                $count++;
            }
        }

        return $count;
    }

    // ── §4.5 Ist-Stunden aus Timesheets synchronisieren ──────────────────────

    /**
     * Liest geleistete Stunden aus Timesheets und schreibt sie in stunden_ist.
     * Nur vergangene Monate werden synchronisiert.
     *
     * @param HortPlanung $planung
     * @return int Anzahl aktualisierter Einträge
     */
    public function syncIstStunden(HortPlanung $planung): int
    {
        $count = 0;
        $heute = now()->startOfMonth();

        foreach ($planung->monate as $monat) {
            // Nur vergangene Monate synchronisieren
            if ($monat->monat->greaterThanOrEqualTo($heute)) {
                continue;
            }

            foreach ($monat->personen as $person) {
                $timesheet = Timesheet::where('employe_id', $person->user_id)
                    ->where('month', $monat->monat->month)
                    ->where('year', $monat->monat->year)
                    ->first();

                if ($timesheet) {
                    // Ist-Sekunden aus TimesheetDays → Wochenstunden umrechnen
                    // Formel: (Gesamtstunden / Arbeitstage_im_Monat) * 5 = Wochenstunden
                    $ist_sekunden  = $timesheet->timesheet_days->sum('duration');
                    $arbeitstage   = $monat->monat->copy()->daysInMonth * 5 / 7; // ≈ Werktage
                    $ist_wochenstunden = ($ist_sekunden / 3600) / ($arbeitstage / 5);

                    $person->update(['stunden_ist' => round($ist_wochenstunden, 2)]);
                    $count++;
                }
            }
        }

        return $count;
    }

    // ── §4.6 Vertragsstunden aktualisieren ───────────────────────────────────

    /**
     * Synchronisiert stunden_vertrag mit aktuellen Employment-Daten.
     * Nützlich nach Änderungsverträgen.
     *
     * @param HortPlanung $planung
     * @return int Anzahl aktualisierter Einträge
     */
    public function syncVertragsstunden(HortPlanung $planung): int
    {
        $count = 0;

        foreach ($planung->monate as $monat) {
            foreach ($monat->personen as $person) {
                // Alle aktiven Anstellungen der Person im Planungs-Department laden.
                // Mehrere parallele Anstellungen im selben Department werden summiert.
                $employments = $person->user->employments_date(
                    $monat->monat,
                    $monat->monat->copy()->endOfMonth()
                );

                $vertragsstunden = $employments
                    ->where('department_id', $planung->department_id)
                    ->sum('hours');

                $person->update(['stunden_vertrag' => $vertragsstunden ?: null]);
                $count++;
            }
        }

        return $count;
    }

    // ── §4.7 Szenarien-Vergleich ─────────────────────────────────────────────

    /**
     * Vergleicht zwei Planungen über ihren gemeinsamen Zeitraum.
     *
     * @param HortPlanung $planungA
     * @param HortPlanung $planungB
     * @return Collection
     */
    public function vergleichePlanungen(HortPlanung $planungA, HortPlanung $planungB): Collection
    {
        $planungA->load(['monate.personen', 'monate.monatZusatzstunden.typ', 'faktoren.werte']);
        $planungB->load(['monate.personen', 'monate.monatZusatzstunden.typ', 'faktoren.werte']);

        $monateA = $planungA->monate->keyBy(fn($m) => $m->monat->format('Y-m'));
        $monateB = $planungB->monate->keyBy(fn($m) => $m->monat->format('Y-m'));

        $gemeinsam = $monateA->keys()->intersect($monateB->keys());

        return $gemeinsam->map(function ($key) use ($monateA, $monateB) {
            $a = $this->berechneMonat($monateA[$key]);
            $b = $this->berechneMonat($monateB[$key]);

            return [
                'monat'      => $key,
                'planung_a'  => $a,
                'planung_b'  => $b,
                'diff_sp1'   => round($a['summe_sp1'] - $b['summe_sp1'], 2),
                'diff_sp2'   => round($a['summe_sp2'] - $b['summe_sp2'], 2),
                'diff_vz'    => round($a['summe_vz_sp1'] - $b['summe_vz_sp1'], 4),
                'diff_gesetzl' => round($a['budget_rest_sp1'] - $b['budget_rest_sp1'], 2),
            ];
        });
    }

    // ── §4.8 Bulk-Update Personen-Stunden ────────────────────────────────────

    /**
     * Ändert die Stunden einer Person ab einem bestimmten Monat für alle Folgemonate.
     *
     * @param HortPlanung $planung
     * @param int         $userId
     * @param Carbon      $abMonat
     * @param float|null  $stundenGesamt
     * @param float|null  $stundenStadt
     * @param string|null $kommentar
     * @return int Anzahl aktualisierter Einträge
     */
    public function bulkUpdatePerson(
        HortPlanung $planung,
        int $userId,
        Carbon $abMonat,
        ?float $stundenGesamt,
        ?float $stundenStadt,
        ?string $kommentar = null
    ): int {
        $count = 0;

        foreach ($planung->monate as $monat) {
            if ($monat->monat->lessThan($abMonat)) {
                continue;
            }

            $data = [];
            if (!is_null($stundenGesamt)) {
                $data['stunden_gesamt'] = $stundenGesamt;
            }
            if (!is_null($stundenStadt)) {
                $data['stunden_stadt'] = $stundenStadt;
            }
            if (!is_null($kommentar)) {
                $data['kommentar'] = $kommentar;
            }

            HortPlanungPerson::updateOrCreate(
                ['hort_planung_monat_id' => $monat->id, 'user_id' => $userId],
                $data
            );
            $count++;
        }

        return $count;
    }

    // ── §4.9 Planung duplizieren ─────────────────────────────────────────────

    /**
     * Erstellt eine vollständige Kopie einer Planung als neues Szenario.
     * Kopiert Faktoren + Werte, Zusatzstunden-Typen, Monate, Personen und
     * Monat-Zusatzstunden.
     *
     * @param HortPlanung $original
     * @param string      $name
     * @param string|null $beschreibung
     * @return HortPlanung
     */
    public function dupliziere(HortPlanung $original, string $name, ?string $beschreibung = null): HortPlanung
    {
        $original->load([
            'faktoren.werte',
            'zusatzstundenTypen',
            'monate.personen',
            'monate.monatZusatzstunden',
        ]);

        return DB::transaction(function () use ($original, $name, $beschreibung) {
            $kopie = $original->replicate(['aktiv', 'deleted_at']);
            $kopie->name           = $name;
            $kopie->beschreibung   = $beschreibung ?? $original->beschreibung;
            $kopie->aktiv          = false;
            $kopie->kopiert_von_id = $original->id;
            $kopie->created_by     = auth()->id();
            $kopie->save();

            // Faktoren + Werte kopieren (mit ID-Mapping)
            $faktorMapping = [];
            foreach ($original->faktoren as $faktor) {
                $neuerFaktor = $faktor->replicate();
                $neuerFaktor->hort_planung_id = $kopie->id;
                $neuerFaktor->save();
                $faktorMapping[$faktor->id] = $neuerFaktor->id;

                foreach ($faktor->werte as $wert) {
                    $neuerWert = $wert->replicate();
                    $neuerWert->hort_faktor_id = $neuerFaktor->id;
                    $neuerWert->save();
                }
            }

            // Zusatzstunden-Typen kopieren (mit ID-Mapping)
            $zusatzMapping = [];
            foreach ($original->zusatzstundenTypen as $typ) {
                $neuerTyp = $typ->replicate();
                $neuerTyp->hort_planung_id = $kopie->id;
                $neuerTyp->save();
                $zusatzMapping[$typ->id] = $neuerTyp->id;
            }

            // Monate + Personen + Monat-Zusatzstunden kopieren
            foreach ($original->monate as $monat) {
                $neuerMonat = $monat->replicate();
                $neuerMonat->hort_planung_id = $kopie->id;
                $neuerMonat->save();

                foreach ($monat->personen as $person) {
                    $neuePerson = $person->replicate();
                    $neuePerson->hort_planung_monat_id = $neuerMonat->id;
                    $neuePerson->save();
                }

                foreach ($monat->monatZusatzstunden as $zusatz) {
                    $neuerZusatz = $zusatz->replicate();
                    $neuerZusatz->hort_planung_monat_id     = $neuerMonat->id;
                    $neuerZusatz->hort_zusatzstunden_typ_id = $zusatzMapping[$zusatz->hort_zusatzstunden_typ_id]
                        ?? throw new \RuntimeException(
                            'Zusatz-Typ-Mapping fehlt für ID ' . $zusatz->hort_zusatzstunden_typ_id
                        );
                    $neuerZusatz->save();
                }
            }

            return $kopie;
        });
    }

    // ── §4.10 Snapshot erstellen ─────────────────────────────────────────────

    /**
     * Friert den aktuellen Stand der Planung als JSON-Snapshot ein.
     *
     * @param HortPlanung $planung
     * @param string      $name
     * @return HortPlanungSnapshot
     */
    public function erstelleSnapshot(HortPlanung $planung, string $name): HortPlanungSnapshot
    {
        $daten = $this->berechnePlanung($planung)->map(function ($item) {
            return [
                'monat'        => $item['monat']->format('Y-m-d'),
                'parameter'    => $item['parameter'],
                'personen'     => $item['personen']->map(fn($p) => $p->only([
                    'user_id', 'stunden_gesamt', 'stunden_stadt',
                    'stunden_vertrag', 'stunden_ist', 'kommentar',
                ]))->values(),
                'berechnungen' => $item['berechnungen'],
            ];
        });

        return HortPlanungSnapshot::create([
            'hort_planung_id' => $planung->id,
            'name'            => $name,
            'daten'           => $daten->toArray(),
            'created_by'      => auth()->id(),
        ]);
    }

    // ── §4.10.2 Snapshot wiederherstellen ────────────────────────────────────

    /**
     * Stellt den Planungsstand eines Snapshots wieder her.
     * Überschreibt Monatsparameter und Personenstunden; Zusatzstunden werden nicht berührt.
     *
     * @param HortPlanungSnapshot $snapshot
     * @return int  Anzahl wiederhergestellter Monate
     */
    public function restoreSnapshot(HortPlanungSnapshot $snapshot): int
    {
        $planung = $snapshot->planung;
        $daten   = $snapshot->daten ?? [];
        $count   = 0;

        // Monate der Planung einmal laden und nach Datums-String indexieren
        $monate = $planung->monate()
            ->with('personen')
            ->get()
            ->keyBy(fn($m) => $m->monat->format('Y-m-d'));

        foreach ($daten as $eintrag) {
            $monat = $monate->get($eintrag['monat'] ?? '');
            if (!$monat) {
                continue;
            }

            // Monatsparameter wiederherstellen
            $monat->update([
                'kinderanzahl'    => $eintrag['parameter']['kinderanzahl']    ?? $monat->kinderanzahl,
                'vollzeitstunden' => $eintrag['parameter']['vollzeitstunden'] ?? $monat->vollzeitstunden,
            ]);

            // Personenstunden wiederherstellen
            foreach ($eintrag['personen'] ?? [] as $pData) {
                HortPlanungPerson::updateOrCreate(
                    [
                        'hort_planung_monat_id' => $monat->id,
                        'user_id'               => $pData['user_id'],
                    ],
                    [
                        'stunden_gesamt'  => $pData['stunden_gesamt'],
                        'stunden_stadt'   => $pData['stunden_stadt'],
                        'stunden_vertrag' => $pData['stunden_vertrag'] ?? null,
                        'stunden_ist'     => $pData['stunden_ist']     ?? null,
                        'kommentar'       => $pData['kommentar']        ?? null,
                    ]
                );
            }

            $count++;
        }

        return $count;
    }

    // ── §4.11 Trend-Daten für Chart ──────────────────────────────────────────

    /**
     * Liefert Verlaufsdaten für die Darstellung als Linien-Chart (VZÄ-Entwicklung).
     *
     * @param HortPlanung $planung
     * @return array
     */
    public function trendDaten(HortPlanung $planung): array
    {
        $berechnungen = $this->berechnePlanung($planung);

        return [
            'labels'   => $berechnungen->pluck('monat')->map(fn($m) => $m->format('M Y'))->toArray(),
            'datasets' => [
                'vz_sp1'           => $berechnungen->pluck('berechnungen.summe_vz_sp1')->toArray(),
                'vz_sp2'           => $berechnungen->pluck('berechnungen.summe_vz_sp2')->toArray(),
                'vz_gesetzlich'    => $berechnungen->pluck('berechnungen.summe_gesetz_vz')->toArray(),
                'budget_rest_sp1'  => $berechnungen->pluck('berechnungen.budget_rest_sp1')->toArray(),
                'differenz_stadt'  => $berechnungen->pluck('berechnungen.differenz_stadt')->toArray(),
                'differenz_vz_sp2' => $berechnungen->pluck('berechnungen.differenz_vz_sp2')->toArray(),
                'kinderanzahl'     => $berechnungen->pluck('parameter.kinderanzahl')->toArray(),
            ],
        ];
    }

    // ── §4.12 Langzeitabwesenheiten ermitteln ────────────────────────────────

    /**
     * Prüft, ob Personen in einem Planungszeitraum langzeitig abwesend sind
     * (Elternzeit, Langzeitkrank, Mutterschutz oder > 30 Tage).
     *
     * @param HortPlanung $planung
     * @return Collection  Gruppiert nach user_id
     */
    public function abwesenheitenImZeitraum(HortPlanung $planung): Collection
    {
        $userIds = $planung->monate->flatMap(fn($m) => $m->personen->pluck('user_id'))->unique();

        if ($userIds->isEmpty()) {
            return collect();
        }

        return Absence::whereIn('users_id', $userIds)
            ->where('start', '<=', $planung->end_monat->endOfMonth())
            ->where('end', '>=', $planung->start_monat)
            ->with('user:id,name')
            ->get()
            ->filter(function ($abs) {
                $reason = (string) ($abs->reason ?? '');
                $istLangzeit = str_contains($reason, 'Elternzeit')
                    || str_contains($reason, 'Langzeitkrank')
                    || str_contains($reason, 'Mutterschutz');

                // Langzeitabwesenheit: über 30 Tage
                $istLang = false;
                if ($abs->start && $abs->end) {
                    try {
                        $istLang = \Carbon\Carbon::parse($abs->start)
                            ->diffInDays(\Carbon\Carbon::parse($abs->end)) > 30;
                    } catch (\Throwable) {
                        // Bei Fehler konservativ: nicht als Langzeit zählen
                    }
                }

                return $istLangzeit || $istLang;
            })
            ->groupBy('users_id');
    }

    // ── Hilfsmethoden (Service-intern) ───────────────────────────────────────

    /**
     * Erstellt alle Planungsmonate für den Zeitraum start_monat bis end_monat.
     * Wird beim Anlegen einer neuen Planung aufgerufen.
     *
     * @param HortPlanung $planung
     * @param int         $standardKinderanzahl
     * @return void
     */
    public function erstelleMonate(HortPlanung $planung, int $standardKinderanzahl = 100): void
    {
        $periode = CarbonPeriod::create(
            $planung->start_monat->startOfMonth(),
            '1 month',
            $planung->end_monat->startOfMonth()
        );

        foreach ($periode as $datum) {
            HortPlanungMonat::firstOrCreate(
                [
                    'hort_planung_id' => $planung->id,
                    'monat'           => $datum->format('Y-m-01'),
                ],
                [
                    'kinderanzahl'    => $standardKinderanzahl,
                    'vollzeitstunden' => 40.00,
                ]
            );
        }
    }

    /**
     * Legt die 5 Standard-Faktoren (entsprechend §2.1 und §6.6) für eine neue
     * Planung an, inklusive ihrer initialen Werte.
     *
     * Standard-Faktoren:
     *  1. kinderschluessel  – divisor,         22.2222  (§12 Abs. 2 SächsKitaG)
     *  2. leitung           – faktor_auf_bs,    0.1000
     *  3. vorbereitung      – faktor_auf_bs,    0.0540
     *  4. anpassung         – faktor_auf_summe, 0.0400
     *  5. mentor            – faktor_auf_bs,    0.0000  (deaktiviert)
     *
     * @param HortPlanung $planung
     * @return void
     */
    public function erstelleStandardFaktoren(HortPlanung $planung): void
    {
        $standardFaktoren = [
            [
                'kuerzel'              => 'kinderschluessel',
                'bezeichnung'          => 'Kinderschlüssel',
                'berechnungs_typ'      => 'divisor',
                'position'             => 1,
                'aktiv'                => true,
                'gesetzliche_grundlage' => '§12 Abs. 2 SächsKitaG',
                'wert'                 => 22.222222,
            ],
            [
                'kuerzel'              => 'leitung',
                'bezeichnung'          => 'Leitung',
                'berechnungs_typ'      => 'faktor_auf_bs',
                'position'             => 2,
                'aktiv'                => true,
                'gesetzliche_grundlage' => '§12 Abs. 2 SächsKitaG',
                'wert'                 => 0.1,
            ],
            [
                'kuerzel'              => 'vorbereitung',
                'bezeichnung'          => 'Vorbereitung',
                'berechnungs_typ'      => 'faktor_auf_bs',
                'position'             => 3,
                'aktiv'                => true,
                'gesetzliche_grundlage' => null,
                'wert'                 => 0.054,
            ],
            [
                'kuerzel'              => 'anpassung',
                'bezeichnung'          => 'Anpassung BS',
                'berechnungs_typ'      => 'faktor_auf_summe',
                'position'             => 4,
                'aktiv'                => true,
                'gesetzliche_grundlage' => null,
                'wert'                 => 0.04,
            ],
            [
                'kuerzel'              => 'mentor',
                'bezeichnung'          => 'Mentor',
                'berechnungs_typ'      => 'faktor_auf_bs',
                'position'             => 5,
                'aktiv'                => false,
                'gesetzliche_grundlage' => null,
                'wert'                 => 0.0,
            ],
        ];

        $gueltigAb = $planung->start_monat->format('Y-m-01');

        foreach ($standardFaktoren as $def) {
            $faktor = HortFaktor::create([
                'hort_planung_id'      => $planung->id,
                'kuerzel'              => $def['kuerzel'],
                'bezeichnung'          => $def['bezeichnung'],
                'berechnungs_typ'      => $def['berechnungs_typ'],
                'position'             => $def['position'],
                'aktiv'                => $def['aktiv'],
                'gesetzliche_grundlage' => $def['gesetzliche_grundlage'],
            ]);

            HortFaktorWert::create([
                'hort_faktor_id' => $faktor->id,
                'wert'           => $def['wert'],
                'gueltig_ab'     => $gueltigAb,
                'notiz'          => 'Initialer Wert',
                'created_by'     => auth()->id(),
            ]);
        }
    }

    /**
     * Legt die 2 Standard-Zusatzstunden-Typen für eine neue Planung an.
     *
     * Standard-Typen:
     *  1. zusatz_weg – Zusatzstunden (Weg/Beratung)
     *  2. sonstige   – Sonstige Stunden
     *
     * @param HortPlanung $planung
     * @return void
     */
    public function erstelleStandardZusatztypen(HortPlanung $planung): void
    {
        $standardTypen = [
            [
                'kuerzel'     => 'zusatz_weg',
                'bezeichnung' => 'Zusatzstunden (Weg/Beratung)',
                'position'    => 1,
                'aktiv'       => true,
            ],
            [
                'kuerzel'     => 'sonstige',
                'bezeichnung' => 'Sonstige Stunden',
                'position'    => 2,
                'aktiv'       => true,
            ],
        ];

        foreach ($standardTypen as $def) {
            HortZusatzstundenTyp::create([
                'hort_planung_id' => $planung->id,
                'kuerzel'         => $def['kuerzel'],
                'bezeichnung'     => $def['bezeichnung'],
                'position'        => $def['position'],
                'aktiv'           => $def['aktiv'],
            ]);
        }
    }

    // ── §4.13 Vertragsänderungen berechnen ───────────────────────────────────

    /**
     * Ermittelt alle Vertragsänderungs-Zeitpunkte pro Person.
     *
     * Erkennt Übergänge: Wann ändert sich SP1 oder SP2 einer Person von einem
     * Monat zum nächsten? SP1 und SP2 können sich unabhängig voneinander ändern,
     * auch wenn die Gesamtsumme gleich bleibt → beide Werte werden einzeln
     * verglichen.
     *
     * Pro Eintrag wird angegeben:
     *   - SP1 (stunden_gesamt) und SP2 (stunden_stadt)
     *   - vorheriger SP1-/SP2-Wert (aus dem Vormonat)
     *   - Anteil Zusatzstunden (gleichmäßig auf aktive Personen verteilt)
     *   - Gesamtwert SP1 (SP1 + Zusatzstunden-Anteil)
     *   - aktueller Vertrag (stunden_vertrag)
     *
     * @param HortPlanung $planung
     * @return Collection  Gruppiert nach user_id → Collection von Änderungs-Records
     */
    public function berechneVertragsaenderungen(HortPlanung $planung): Collection
    {
        $monate = $planung->monate->sortBy('monat')->values();

        // Alle einzigartigen Personen ermitteln
        $allePersonen = $monate
            ->flatMap(fn($m) => $m->personen)
            ->unique('user_id')
            ->sortBy(fn($p) => $p->user?->name ?? 'zzz')
            ->values();

        // Personen-Daten indexiert nach Monat-Key + User-ID für O(1)-Zugriff
        $personenIndex = [];
        foreach ($monate as $monat) {
            $mk = $monat->monat->format('Y-m');
            foreach ($monat->personen as $person) {
                $personenIndex[$mk . '_' . $person->user_id] = $person;
            }
        }

        // Zusatzstunden pro Person pro Monat vorberechnen
        $zusatzProPersonMonat = [];
        foreach ($monate as $monat) {
            $mk = $monat->monat->format('Y-m');
            $aktive = $monat->personen->filter(fn($p) => ($p->stunden_gesamt ?? 0) > 0)->count();
            $zusatzGesamt = $monat->summeZusatzstunden();
            $zusatzProPersonMonat[$mk] = $aktive > 0 ? round($zusatzGesamt / $aktive, 2) : 0;
        }

        $aenderungen = collect();

        foreach ($allePersonen as $person) {
            $userId   = $person->user_id;
            $userName = $person->user?->name ?? '–';

            $vorherigerSP1 = null;
            $vorherigerSP2 = null;

            foreach ($monate as $monat) {
                $mk = $monat->monat->format('Y-m');
                $mp = $personenIndex[$mk . '_' . $userId] ?? null;

                if (!$mp) {
                    // Person in diesem Monat nicht vorhanden → Vorwerte zurücksetzen
                    $vorherigerSP1 = null;
                    $vorherigerSP2 = null;
                    continue;
                }

                $sp1     = $mp->stunden_gesamt;
                $sp2     = $mp->stunden_stadt;
                $vertrag = $mp->stunden_vertrag;

                if ($sp1 === null && $sp2 === null) {
                    $vorherigerSP1 = null;
                    $vorherigerSP2 = null;
                    continue;
                }

                // Übergang erkennen: SP1 oder SP2 hat sich gegenüber Vormonat geändert
                $sp1Geaendert = ($vorherigerSP1 === null && $sp1 !== null)
                    || ($vorherigerSP1 !== null && $sp1 !== null && abs($sp1 - $vorherigerSP1) >= 0.01);
                $sp2Geaendert = ($vorherigerSP2 === null && $sp2 !== null)
                    || ($vorherigerSP2 !== null && $sp2 !== null && abs($sp2 - $vorherigerSP2) >= 0.01);

                if ($sp1Geaendert || $sp2Geaendert) {
                    $zusatz = ($sp1 ?? 0) > 0 ? ($zusatzProPersonMonat[$mk] ?? 0) : 0;

                    $aenderungen->push([
                        'user_id'          => $userId,
                        'user_name'        => $userName,
                        'monat'            => $monat->monat,
                        'monat_label'      => $monat->monat->locale('de')->translatedFormat('F Y'),
                        'sp1'              => $sp1 !== null ? round($sp1, 2) : null,
                        'sp2'              => $sp2 !== null ? round($sp2, 2) : null,
                        'sp1_vorher'       => $vorherigerSP1 !== null ? round($vorherigerSP1, 2) : null,
                        'sp2_vorher'       => $vorherigerSP2 !== null ? round($vorherigerSP2, 2) : null,
                        'zusatzstunden'    => $zusatz,
                        'gesamtwert_sp1'   => round(($sp1 ?? 0) + $zusatz, 2),
                        'vertrag'          => $vertrag !== null ? round($vertrag, 2) : null,
                        'differenz'        => round(($sp1 ?? 0) - ($vertrag ?? 0), 2),
                        'sp1_geaendert'    => $sp1Geaendert,
                        'sp2_geaendert'    => $sp2Geaendert,
                    ]);
                }

                $vorherigerSP1 = $sp1;
                $vorherigerSP2 = $sp2;
            }
        }

        // Gruppiert nach Person, sortiert nach Name → Monat
        return $aenderungen
            ->sortBy([
                ['user_name', 'asc'],
                ['monat', 'asc'],
            ])
            ->groupBy('user_id');
    }
}

