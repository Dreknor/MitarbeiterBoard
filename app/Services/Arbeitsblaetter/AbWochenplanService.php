<?php

namespace App\Services\Arbeitsblaetter;

use App\Jobs\Arbeitsblaetter\AbKindNachziehen;
use App\Jobs\Arbeitsblaetter\AbWochenplanDateiUebernehmen;
use App\Jobs\Arbeitsblaetter\AbWochenplanVerteilen;
use App\Models\Arbeitsblaetter\AbFassung;
use App\Models\Arbeitsblaetter\AngepasstesBlatt;
use App\Models\Schueler;
use App\Models\User;
use App\Models\Wochenplan\WpDatei;
use App\Models\Wochenplan\WpPlan;
use App\Services\Wochenplan\WochenplanService;
use App\Services\Wochenplan\WpAblageService;
use Illuminate\Support\Facades\Log;

/**
 * Anbindung der Angepassten Arbeitsblätter an den Wochenplan.
 *
 * 1. Neues Arbeitsblatt an einem Klassenplan ({@see dateiAngelegt()}, Event `WpDateiAngelegt`):
 *    hat die Klasse Kinder mit aktivem Sehprofil und ist `ab_wochenplan_automatisch` an,
 *    entsteht ein Blatt (`quelle = wochenplan`) und die Erkennung startet.
 * 2. Freigabe → {@see fassungenVerteilen()}: für jedes Profilkind der Klasse den Kinderplan
 *    der Woche suchen bzw. anlegen, Fassung setzen und dort ablegen (`ab_fassung_id`,
 *    `ersetzt_wp_datei_id`). Im Kinderplan blendet `WpPlan::alleDateien()` das Original aus.
 * 3. Später angelegte Kinderpläne ({@see kinderplanAngelegt()}) und neue/geänderte Profile
 *    ({@see kindNachziehen()}) ziehen Fassungen nach – ohne KI, nur neu setzen.
 * 4. Original gelöscht: der Wochenplan entfernt die Fassungsdateien selbst
 *    ({@see WochenplanService::dateiEntfernen()}); das Blatt bleibt in der Bibliothek.
 */
class AbWochenplanService
{
    /** Während des Verteilens selbst angelegte Kinderpläne lösen kein Nachziehen aus. */
    private static bool $unterdrueckt = false;

    public function __construct(
        private SehprofilService $profile,
        private AbAusgabeService $ausgabe,
        private WpAblageService $ablage,
    ) {
    }

    public static function automatikAn(): bool
    {
        $wert = settings('ab_wochenplan_automatisch');

        return $wert === null ? true : (bool) $wert;
    }

    public static function unterstuetzt(WpDatei $datei): bool
    {
        $endung = strtolower(pathinfo((string) ($datei->dateiname ?: $datei->name), PATHINFO_EXTENSION));

        return in_array($endung, explode(',', AbQuellenService::ERLAUBTE_ENDUNGEN), true);
    }

    // ─── 1. Automatischer Start ───────────────────────────────────────────────

    public function dateiAngelegt(WpDatei $datei): void
    {
        $plan = $datei->plan;
        if (! $plan || ! $plan->isKlassenplan() || ! $plan->klasse_id || $plan->trashed()) {
            return;
        }
        if ($datei->ersetzt_wp_datei_id || $datei->ab_fassung_id || ! self::automatikAn() || ! self::unterstuetzt($datei)) {
            return;
        }
        if (! $this->profile->klasseHatProfilkinder((int) $plan->klasse_id)) {
            return;
        }

        AbWochenplanDateiUebernehmen::dispatch($datei->id, $datei->hochgeladen_von);
    }

    /** Knopf „Angepasste Fassung erzeugen“ im Wochenplan-Editor (auch ohne Automatik). */
    public function manuellStarten(WpDatei $datei, User $user): ?AngepasstesBlatt
    {
        return $this->dateiUebernehmen($datei, $user->id, true);
    }

    /** Legt das Blatt zur Datei an (einmal je Datei) und startet die Erkennung. */
    public function dateiUebernehmen(WpDatei $datei, ?int $userId, bool $erzwingen = false): ?AngepasstesBlatt
    {
        $vorhanden = $this->blattZuDatei($datei->id);
        if ($vorhanden) {
            return $vorhanden;
        }
        $plan = $datei->plan;
        if (! $plan || ! $plan->isKlassenplan()) {
            return null;
        }
        if (! $erzwingen && ! $this->profile->klasseHatProfilkinder($plan->klasse_id)) {
            return null;
        }

        $inhalt = $this->ablage->dateiInhalt($datei);
        if ($inhalt === null) {
            Log::info('Angepasste Arbeitsblätter: Inhalt des Arbeitsblatts nicht verfügbar', ['wp_datei' => $datei->id]);

            return null;
        }

        try {
            return app(AngepasstesBlattService::class)->ausInhalt([
                'titel' => pathinfo((string) $datei->name, PATHINFO_FILENAME),
                'quelle' => 'wochenplan',
                'quelle_wp_datei_id' => $datei->id,
                'klassenstufe' => WochenplanService::klassenstufe($plan->klasse?->name),
            ], $inhalt, (string) ($datei->dateiname ?: $datei->name), $userId ? User::find($userId) : null);
        } catch (AbQuellenFehler $e) {
            Log::info('Angepasste Arbeitsblätter: Datei nicht übernommen', ['wp_datei' => $datei->id, 'grund' => $e->getMessage()]);

            return null;
        }
    }

    public function blattZuDatei(int $wpDateiId): ?AngepasstesBlatt
    {
        return AngepasstesBlatt::where('quelle_wp_datei_id', $wpDateiId)->latest('id')->first();
    }

    // ─── 2. Freigabe → Fassungen verteilen ───────────────────────────────────

    public function fassungenVerteilenAnfordern(AngepasstesBlatt $blatt, ?User $user): void
    {
        AbWochenplanVerteilen::dispatch($blatt->id, $user?->id);
    }

    /** Liefert die Anzahl der abgelegten (neuen oder ersetzten) Fassungen. */
    public function fassungenVerteilen(AngepasstesBlatt $blatt, ?int $userId = null): int
    {
        $original = $blatt->quelle_wp_datei_id ? WpDatei::find($blatt->quelle_wp_datei_id) : null;
        $klassenplan = $original?->plan;
        if (! $original || ! $klassenplan || ! $klassenplan->isKlassenplan() || ! $klassenplan->klasse_id || $klassenplan->trashed()) {
            return 0;
        }

        $user = $userId ? User::find($userId) : null;
        $anzahl = 0;
        foreach ($this->profile->kinderMitProfil([(int) $klassenplan->klasse_id]) as $kind) {
            $anzahl += $this->fassungFuerKind($blatt, $klassenplan, $original, $kind, $user) ? 1 : 0;
        }

        return $anzahl;
    }

    /**
     * Fassung eines Kindes an dessen Kinderplan der Woche hängen (Kinderplan wird bei Bedarf
     * angelegt). Liefert true, wenn eine Datei neu abgelegt bzw. ersetzt wurde.
     */
    public function fassungFuerKind(AngepasstesBlatt $blatt, WpPlan $klassenplan, WpDatei $original, Schueler $kind, ?User $user): bool
    {
        if (! $blatt->istFreigegeben() || $this->profile->wirksameWerte($kind) === null) {
            return false;
        }

        $kinderplan = WpPlan::where('parent_plan_id', $klassenplan->id)->where('schueler_id', $kind->id)->first();
        if (! $kinderplan) {
            self::$unterdrueckt = true;
            try {
                $kinderplan = app(WochenplanService::class)->kinderplanErstellen($klassenplan, $kind, null, null, $user);
            } finally {
                self::$unterdrueckt = false;
            }
        }

        $fassung = $this->ausgabe->fassung($blatt, $kind, null, $user);
        if (! $fassung) {
            return false;
        }

        $vorhandene = WpDatei::where('wp_plan_id', $kinderplan->id)->where('ersetzt_wp_datei_id', $original->id)->get();
        $aktuell = $vorhandene->firstWhere('id', $fassung->wp_datei_id);
        if ($aktuell && ! $fassung->neuGesetzt && (int) $aktuell->ab_fassung_id === (int) $fassung->id) {
            return false;
        }

        foreach ($vorhandene as $alt) {
            $this->ablage->dateiEntfernen($alt);
        }

        $pdf = $this->ausgabe->pdfInhalt($fassung);
        if ($pdf === null) {
            return false;
        }
        $name = pathinfo((string) $original->name, PATHINFO_FILENAME).' (angepasst).pdf';
        $datei = $this->ablage->dateiAusInhalt($kinderplan, $name, $pdf, 'application/pdf', $user?->id ?? $original->hochgeladen_von);
        $datei->update(['ab_fassung_id' => $fassung->id, 'ersetzt_wp_datei_id' => $original->id]);
        $fassung->update(['wp_datei_id' => $datei->id]);

        app(WochenplanService::class)->geaendert($kinderplan);

        return true;
    }

    // ─── 3. Nachziehen ────────────────────────────────────────────────────────

    public function kinderplanAngelegt(WpPlan $kinderplan): void
    {
        if (self::$unterdrueckt || ! $kinderplan->parent_plan_id || ! $kinderplan->schueler_id) {
            return;
        }
        $klassenplan = $kinderplan->parentPlan;
        $kind = $kinderplan->schueler;
        if (! $klassenplan || ! $kind || $this->profile->wirksameWerte($kind) === null) {
            return;
        }

        foreach ($this->freigegebeneBlaetter($klassenplan) as [$original, $blatt]) {
            $this->fassungFuerKind($blatt, $klassenplan, $original, $kind, null);
        }
    }

    public function kindNachziehenAnfordern(int $schuelerId): void
    {
        AbKindNachziehen::dispatch($schuelerId);
    }

    /** Neues bzw. geändertes Profil: laufende und künftige Klassenpläne der Klasse durchgehen. */
    public function kindNachziehen(int $schuelerId): int
    {
        $kind = Schueler::find($schuelerId);
        if (! $kind || ! $kind->klasse_id || $this->profile->wirksameWerte($kind) === null) {
            return 0;
        }

        $anzahl = 0;
        WpPlan::klassenplaene()
            ->where('klasse_id', $kind->klasse_id)
            ->whereDate('gueltig_bis', '>=', now()->toDateString())
            ->orderBy('gueltig_von')
            ->get()
            ->each(function (WpPlan $klassenplan) use ($kind, &$anzahl) {
                foreach ($this->freigegebeneBlaetter($klassenplan) as [$original, $blatt]) {
                    $anzahl += $this->fassungFuerKind($blatt, $klassenplan, $original, $kind, null) ? 1 : 0;
                }
            });

        return $anzahl;
    }

    /** @return array<int, array{0: WpDatei, 1: AngepasstesBlatt}> */
    private function freigegebeneBlaetter(WpPlan $klassenplan): array
    {
        $dateien = WpDatei::where('wp_plan_id', $klassenplan->id)->get()->keyBy('id');
        if ($dateien->isEmpty()) {
            return [];
        }

        return AngepasstesBlatt::whereIn('quelle_wp_datei_id', $dateien->keys())
            ->where('status', AngepasstesBlatt::FREIGEGEBEN)
            ->orderByDesc('id')
            ->get()
            ->unique('quelle_wp_datei_id')
            ->map(fn (AngepasstesBlatt $b) => [$dateien[$b->quelle_wp_datei_id], $b])
            ->values()
            ->all();
    }

    // ─── Anzeige im Wochenplan-Editor ─────────────────────────────────────────

    /**
     * Abzeichen je Datei eines Klassenplans: Status des Blatts und Zahl der angepassten Fassungen.
     *
     * @return array<int, array{blatt_id: int, status: string, status_text: string, fassungen: int, url: string}>
     */
    public function dateiInfos(WpPlan $plan): array
    {
        if (! $plan->isKlassenplan()) {
            return [];
        }
        $ids = WpDatei::where('wp_plan_id', $plan->id)->pluck('id');
        if ($ids->isEmpty()) {
            return [];
        }

        $fassungen = WpDatei::whereIn('ersetzt_wp_datei_id', $ids)->selectRaw('ersetzt_wp_datei_id, COUNT(*) as anzahl')
            ->groupBy('ersetzt_wp_datei_id')->pluck('anzahl', 'ersetzt_wp_datei_id');

        $infos = [];
        AngepasstesBlatt::whereIn('quelle_wp_datei_id', $ids)->orderBy('id')->get()
            ->each(function (AngepasstesBlatt $b) use (&$infos, $fassungen) {
                $infos[(int) $b->quelle_wp_datei_id] = [
                    'blatt_id' => $b->id,
                    'status' => $b->status,
                    'status_text' => $b->status_text,
                    'fassungen' => (int) ($fassungen[$b->quelle_wp_datei_id] ?? 0),
                    'url' => route('arbeitsblaetter.edit', $b),
                ];
            });

        return $infos;
    }

    /** Fassungen eines Blatts, die an Wochenplänen hängen (für die Ausgabe-Seite). */
    public function wochenplanFassungen(AngepasstesBlatt $blatt)
    {
        return AbFassung::with(['schueler', 'wpDatei.plan'])->where('blatt_id', $blatt->id)->whereNotNull('wp_datei_id')->get()
            ->filter(fn (AbFassung $f) => $f->wpDatei !== null)->values();
    }
}
