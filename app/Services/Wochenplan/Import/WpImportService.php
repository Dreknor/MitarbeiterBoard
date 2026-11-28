<?php

namespace App\Services\Wochenplan\Import;

use App\Models\Klasse;
use App\Models\Schueler;
use App\Models\User;
use App\Models\Wochenplan\WpAufgabe;
use App\Models\Wochenplan\WpFach;
use App\Models\Wochenplan\WpPlan;
use App\Models\Wochenplan\WpPlanFach;
use App\Services\Wochenplan\WochenplanService;
use App\Services\Wochenplan\WpAblageService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Throwable;

/**
 * Import von Word-Wochenplänen (Papiervorlage der Schule) in den Wochenplan.
 *
 * Ablauf: `vorschau()` liest die Dateien (WpWordParser) und ordnet jedem gefundenen Plan Klassen
 * (aus „Klasse 2 Her/Wi“ → Herbst 2 + Winter 2) bzw. ein Kind („Name: Lotta“ → Kinderplan) zu.
 * `importieren()` legt die ausgewählten Pläne an: Lerngruppen-Pläne als Klassenplan je gewählter
 * Klasse, Pläne mit Namen als Kinderplan (Elternplan = Klassenplan derselben Datei/Woche in der
 * Klasse des Kindes, falls vorhanden). Vorhandene Pläne (gleicher Name + Beginn) werden übersprungen.
 */
class WpImportService
{
    /** Kürzel der Lerngruppen in den Überschriften → Jahreszeit im Klassennamen. */
    private const JAHRESZEITEN = [
        'Frühling' => '/\b(Fr|Frü|Frühling)\b/iu',
        'Sommer' => '/\b(So|Som|Sommer)\b/iu',
        'Herbst' => '/\b(He|Her|Herbst)\b/iu',
        'Winter' => '/\b(Wi|Win|Winter)\b/iu',
    ];

    /** Fach-Bezeichnungen der Vorlage → Name im Fach-Katalog. */
    private const FACH_ALIASE = [
        'deutsch' => 'Deutsch', 'sprache' => 'Deutsch',
        'mathe' => 'Mathe', 'mathematik' => 'Mathe', 'ma' => 'Mathe',
        'sachunterricht' => 'Sachunterricht', 'su' => 'Sachunterricht',
        'englisch' => 'Englisch', 'kunst' => 'Kunst', 'musik' => 'Musik', 'sport' => 'Sport',
        'religion' => 'Religion', 'reli' => 'Religion', 'werken' => 'Werken',
    ];

    public function __construct(
        private WpWordParser $parser,
        private WochenplanService $wochenplan,
        private WpAblageService $ablage,
    ) {
    }

    // ─── Vorschau ───────────────────────────────────────────────────────────

    /**
     * @param  array<int, array{name:string, pfad:string}>  $dateien
     * @param  array<int,int>  $klassenIds  feste Zielklassen (leer = aus der Überschrift erkennen)
     * @return array<int, array> je Datei: name, fehler, plaene[]
     */
    public function vorschau(array $dateien, User $user, array $klassenIds = []): array
    {
        $erlaubt = $this->wochenplan->klassenFuer($user)->keyBy('id');
        $ergebnis = [];

        foreach ($dateien as $d => $datei) {
            $eintrag = ['name' => $datei['name'], 'fehler' => null, 'hinweise' => [], 'plaene' => []];
            if (! str_ends_with(mb_strtolower($datei['name']), '.docx')) {
                $eintrag['fehler'] = 'Nur .docx wird gelesen – bitte die Datei in Word als „Word-Dokument (*.docx)“ speichern.';
                $ergebnis[] = $eintrag;

                continue;
            }
            try {
                $gelesen = $this->parser->parse($datei['pfad']);
            } catch (Throwable $e) {
                $eintrag['fehler'] = $e->getMessage();
                $ergebnis[] = $eintrag;

                continue;
            }
            $eintrag['hinweise'] = $gelesen['hinweise'];

            $jahre = collect($gelesen['plaene'])->pluck('bis')->filter()->map(fn ($d) => substr($d, 0, 4))->countBy();
            $hauptjahr = (string) $jahre->sortDesc()->keys()->first();

            foreach ($gelesen['plaene'] as $p => $plan) {
                $klassen = $klassenIds
                    ? Klasse::whereIn('id', $klassenIds)->orderBy('name')->get()
                    : $this->klassenAus($plan['stufe'], $plan['gruppe']);
                $klassen = $klassen->filter(fn ($k) => $erlaubt->has($k->id))->values();

                $hinweise = [];
                if (! $plan['von'] || ! $plan['bis']) {
                    $hinweise[] = 'Kein Zeitraum erkannt – bitte eintragen.';
                } elseif ($hauptjahr && substr($plan['bis'], 0, 4) !== $hauptjahr) {
                    $hinweise[] = 'Das Jahr weicht von den übrigen Plänen der Datei ab – bitte prüfen.';
                }

                $schueler = null;
                $kandidaten = collect();
                if ($plan['kind']) {
                    $kandidaten = $this->kinderSuchen($plan['kind'], $plan['stufe'], $klassen, $erlaubt);
                    if ($kandidaten->count() === 1) {
                        $schueler = $kandidaten->first();
                    } else {
                        $hinweise[] = $kandidaten->isEmpty()
                            ? 'Kein Kind „'.$plan['kind'].'“ gefunden – bitte auswählen.'
                            : 'Mehrere Kinder „'.$plan['kind'].'“ – bitte auswählen.';
                    }
                } elseif ($klassen->isEmpty()) {
                    $hinweise[] = 'Keine Klasse erkannt – bitte auswählen.';
                }

                $name = ($plan['nummer'] ? $plan['nummer'].'. ' : '').$plan['art'];
                $eintrag['plaene'][] = [
                    'schluessel' => $d.'-'.$p,
                    'name' => $name,
                    'kopf' => $plan['kopf'],
                    'von' => $plan['von'],
                    'bis' => $plan['bis'],
                    'gruppe' => $plan['gruppe'],
                    'stufe' => $plan['stufe'],
                    'kind' => $plan['kind'],
                    'schueler_id' => $schueler?->id,
                    'kandidaten' => $kandidaten->map(fn ($s) => ['id' => $s->id, 'name' => $s->vorname.' '.$s->nachname.' ('.($s->klasse?->name ?? '–').')'])->values()->all(),
                    'klassen' => $klassen->pluck('id')->all(),
                    'anzahl' => [
                        'uebungen' => count($plan['uebungen']),
                        'faecher' => count($plan['faecher']),
                        'aufgaben' => array_sum(array_map(fn ($f) => count($f['aufgaben']), $plan['faecher'])),
                        'freiarbeit' => count($plan['freiarbeit']),
                    ],
                    'vorhanden' => ($plan['kind'] && ! $schueler) ? false : $this->vorhanden($name, $plan['von'], $schueler?->id, $klassen->pluck('id')->all()),
                    'hinweise' => $hinweise,
                    'inhalt' => $plan,
                ];
            }
            $ergebnis[] = $eintrag;
        }

        return $ergebnis;
    }

    /** Klassen zu „Klasse 2 Her/Wi“: Stufe + Lerngruppen-Kürzel; ohne Kürzel alle Klassen der Stufe. */
    public function klassenAus(?int $stufe, ?string $gruppe): Collection
    {
        if (! $stufe) {
            return collect();
        }
        $zeiten = [];
        foreach (self::JAHRESZEITEN as $zeit => $muster) {
            if ($gruppe && preg_match($muster, str_replace('/', ' / ', $gruppe))) {
                $zeiten[] = $zeit;
            }
        }
        $zeiten = $zeiten ?: array_keys(self::JAHRESZEITEN);

        return Klasse::whereIn('name', array_map(fn ($z) => $z.' '.$stufe, $zeiten))->orderBy('name')->get();
    }

    private function kinderSuchen(string $name, ?int $stufe, Collection $klassen, Collection $erlaubt): Collection
    {
        $teile = preg_split('/\s+/u', trim($name));
        $vorname = $teile[0];
        $initiale = isset($teile[1]) ? rtrim($teile[1], '.') : null;

        $suche = fn ($klassenIds) => Schueler::with('klasse')
            ->whereIn('klasse_id', $klassenIds)
            ->whereRaw('LOWER(vorname) = ?', [mb_strtolower($vorname)])
            ->when($initiale, fn ($q) => $q->where('nachname', 'like', $initiale.'%'))
            ->get();

        $treffer = $klassen->isNotEmpty() ? $suche($klassen->pluck('id')) : collect();
        if ($treffer->isEmpty() && $stufe) {
            // Lerngruppe nicht eindeutig → alle erlaubten Klassen derselben Stufe
            $treffer = $suche($erlaubt->filter(fn ($k) => str_ends_with($k->name, ' '.$stufe))->keys());
        }

        return $treffer->values();
    }

    private function vorhanden(string $name, ?string $von, ?int $schuelerId, array $klassenIds): bool
    {
        if (! $von) {
            return false;
        }
        $q = WpPlan::whereDate('gueltig_von', $von)->where('is_vorlage', false);
        if ($schuelerId) {
            return $q->where('schueler_id', $schuelerId)->where('name', 'like', $name.'%')->exists();
        }

        return $klassenIds && $q->whereIn('klasse_id', $klassenIds)->where('name', $name)->exists();
    }

    // ─── Import ─────────────────────────────────────────────────────────────

    /**
     * @param  array<string, array{importieren?:bool, klassen?:array, schueler_id?:?int, von?:?string, bis?:?string}>  $auswahl  je Plan-Schlüssel
     * @return array{angelegt:int, uebersprungen:int, plaene:array<int,WpPlan>, meldungen:array<int,string>}
     */
    public function importieren(array $vorschau, array $auswahl, User $user): array
    {
        $erlaubt = $this->wochenplan->klassenFuer($user)->pluck('id')->all();
        $ergebnis = ['angelegt' => 0, 'uebersprungen' => 0, 'plaene' => [], 'meldungen' => []];

        foreach ($vorschau as $datei) {
            $klassenplaene = [];   // "nummer|von" → [klasse_id → WpPlan] für die Kinderpläne derselben Datei
            foreach ($datei['plaene'] ?? [] as $eintrag) {
                $wahl = $auswahl[$eintrag['schluessel']] ?? [];
                if (empty($wahl['importieren'])) {
                    $ergebnis['uebersprungen']++;

                    continue;
                }
                $von = $wahl['von'] ?? $eintrag['von'];
                $bis = $wahl['bis'] ?? $eintrag['bis'];
                if (! $von || ! $bis) {
                    $ergebnis['meldungen'][] = $datei['name'].': „'.$eintrag['name'].'“ ohne Zeitraum übersprungen.';
                    $ergebnis['uebersprungen']++;

                    continue;
                }
                $gruppenSchluessel = $eintrag['name'].'|'.$von;

                $schuelerId = $eintrag['kind'] ? ($wahl['schueler_id'] ?? $eintrag['schueler_id']) : null;
                if ($eintrag['kind']) {
                    $schueler = $schuelerId ? Schueler::find($schuelerId) : null;
                    if (! $schueler || ! in_array((int) $schueler->klasse_id, $erlaubt, true)) {
                        $ergebnis['meldungen'][] = $datei['name'].': Plan für „'.$eintrag['kind'].'“ übersprungen (kein Kind zugeordnet).';
                        $ergebnis['uebersprungen']++;

                        continue;
                    }
                    $name = $eintrag['name'].' – '.$schueler->vorname.' '.$schueler->nachname;
                    if (WpPlan::where('schueler_id', $schueler->id)->whereDate('gueltig_von', $von)->where('name', $name)->exists()) {
                        $ergebnis['uebersprungen']++;

                        continue;
                    }
                    $eltern = $klassenplaene[$gruppenSchluessel][$schueler->klasse_id] ?? null;
                    $plan = $this->planAnlegen($eintrag['inhalt'], [
                        'name' => $name, 'gueltig_von' => $von, 'gueltig_bis' => $bis,
                        'schueler_id' => $schueler->id, 'parent_plan_id' => $eltern?->id,
                        'formatvorlage_id' => $eltern?->formatvorlage_id,
                    ], $user);
                    $ergebnis['plaene'][] = $plan;
                    $ergebnis['angelegt']++;

                    continue;
                }

                $klassen = array_values(array_intersect(array_map('intval', $wahl['klassen'] ?? $eintrag['klassen']), $erlaubt));
                if (! $klassen) {
                    $ergebnis['meldungen'][] = $datei['name'].': „'.$eintrag['name'].'“ ohne Klasse übersprungen.';
                    $ergebnis['uebersprungen']++;

                    continue;
                }
                foreach ($klassen as $klasseId) {
                    if (WpPlan::where('klasse_id', $klasseId)->whereDate('gueltig_von', $von)->where('name', $eintrag['name'])->where('is_vorlage', false)->exists()) {
                        $ergebnis['uebersprungen']++;

                        continue;
                    }
                    $plan = $this->planAnlegen($eintrag['inhalt'], [
                        'name' => $eintrag['name'], 'gueltig_von' => $von, 'gueltig_bis' => $bis, 'klasse_id' => $klasseId,
                    ], $user);
                    $klassenplaene[$gruppenSchluessel][$klasseId] ??= $plan;
                    $ergebnis['plaene'][] = $plan;
                    $ergebnis['angelegt']++;
                }
            }
        }

        return $ergebnis;
    }

    /** Legt einen Plan mit Übungen, Fächern, Aufgaben und Freiarbeit an (eine Ablage-Anforderung am Ende). */
    public function planAnlegen(array $inhalt, array $eckdaten, User $user): WpPlan
    {
        $plan = DB::transaction(function () use ($inhalt, $eckdaten, $user) {
            $plan = WpPlan::create(array_merge([
                'selbsteinschaetzung' => $inhalt['selbsteinschaetzung'] ? 1 : 0,
                'taegliche_uebungen_aktiv' => ! empty($inhalt['uebungen']),
                'is_vorlage' => false,
                'created_by' => $user->id,
                'version' => 1,
            ], $eckdaten));

            foreach (array_values($inhalt['uebungen']) as $i => $u) {
                $plan->taeglicheUebungen()->create([
                    'aufgabe' => Str::limit($u['text'], 1000, ''),
                    'kuerzel' => $u['kuerzel'] ? Str::limit($u['kuerzel'], 12, '') : null,
                    'sort_order' => $i,
                ]);
            }

            $faecher = [];
            foreach ($inhalt['faecher'] as $f) {
                $pf = $this->planFach($plan, $f['label'], $faecher);
                foreach ($f['aufgaben'] as $a) {
                    $this->aufgabe($pf, $a['text'], (bool) $a['freiwillig'], false);
                }
            }
            foreach ($inhalt['freiarbeit'] as $fa) {
                $pf = $this->planFach($plan, (string) ($fa['fach'] ?? 'Freiarbeit'), $faecher);
                $this->aufgabe($pf, $fa['text'], false, true);
            }

            return $plan;
        });

        $this->ablage->anfordern($plan);

        return $plan;
    }

    private function aufgabe(WpPlanFach $pf, string $text, bool $freiwillig, bool $freiarbeit): void
    {
        WpAufgabe::create([
            'wp_plan_fach_id' => $pf->id,
            'aufgabe' => Str::limit(trim($text), 2000, ''),
            'freiwillig' => $freiwillig,
            'freiarbeit' => $freiarbeit,
            'sort_order' => (int) WpAufgabe::where('wp_plan_fach_id', $pf->id)->max('sort_order') + 1,
        ]);
    }

    /**
     * Plan-Fach zur Beschriftung der Vorlage: Katalog-Fach (über Aliase, Rest = Symbol wie „ABC“/„5+5“),
     * nur Zeichen/Emojis → freies Feld mit Symbol, sonst freies Feld mit Namen.
     *
     * @param  array<string, WpPlanFach>  $faecher  bereits angelegte Fächer dieses Plans
     */
    public function planFach(WpPlan $plan, string $label, array &$faecher): WpPlanFach
    {
        ['fach' => $fach, 'name' => $name, 'symbol' => $symbol] = $this->fachAus($label);
        $schluessel = $fach ? 'fach:'.$fach->id : 'frei:'.mb_strtolower((string) ($name ?? $symbol));
        if (isset($faecher[$schluessel])) {
            return $faecher[$schluessel];
        }

        return $faecher[$schluessel] = $plan->planFaecher()->create([
            'wp_fach_id' => $fach?->id,
            'custom_name' => $name !== null ? Str::limit($name, 100, '') : null,
            'symbol' => $symbol !== null ? Str::limit($symbol, 40, '') : null,
            'sort_order' => count($faecher),
        ]);
    }

    /** @return array{fach:?WpFach, name:?string, symbol:?string} */
    public function fachAus(string $label): array
    {
        $zeilen = array_values(array_filter(array_map(
            fn ($z) => trim(str_replace('✍', '', $z), " \t:"),
            preg_split('/\n|\s\/\s/u', $label)
        ), fn ($z) => $z !== ''));
        if (! $zeilen) {
            return ['fach' => null, 'name' => 'Weitere', 'symbol' => null];
        }
        // „Mathe-matik“ (Silbentrennung in der Vorlage)
        $erste = preg_replace('/^(\p{L}+)-(\p{Ll}+)$/u', '$1$2', $zeilen[0]);

        if (! preg_match('/\p{L}/u', $erste)) {
            return ['fach' => null, 'name' => null, 'symbol' => implode(' ', $zeilen)];
        }

        $katalogName = self::FACH_ALIASE[mb_strtolower($erste)] ?? null;
        $fach = $katalogName ? WpFach::whereRaw('LOWER(name) = ?', [mb_strtolower($katalogName)])->first() : null;
        $fach ??= WpFach::whereRaw('LOWER(name) = ?', [mb_strtolower($erste)])->first();

        if ($fach) {
            $rest = array_slice($zeilen, 1);

            return [
                'fach' => $fach,
                'name' => mb_strtolower($erste) === mb_strtolower($fach->name) || mb_strlen($erste) <= 2 ? null : $erste,
                'symbol' => $rest ? implode(' ', $rest) : null,
            ];
        }

        return ['fach' => null, 'name' => implode(' ', $zeilen), 'symbol' => null];
    }
}
