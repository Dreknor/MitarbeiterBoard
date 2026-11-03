<?php

namespace App\Services\Personal\Zeit;

use App\Models\personal\EmployeHolidayClaim;
use App\Models\personal\Holiday;
use App\Models\personal\HolidayAccountEntry;
use App\Models\personal\Timesheet;
use App\Models\User;
use Carbon\Carbon;
use Carbon\CarbonInterface;

/**
 * Urlaubskonto – einzige Quelle für Anspruch, Übertrag, Verfall, genommene und Resttage.
 *
 *   Rest = Anspruch + Übertrag + Buchungen − genommen − verfallen
 *
 * - Anspruch: individuell hinterlegter Anspruch (unverändert) oder Standard-Setting
 *   "holiday_claim" – anteilig nach Beschäftigungsmonaten und Arbeitstagen/Woche.
 * - Übertrag: Rest des Vorjahres. Bis zum Setting "urlaubskonto_startjahr" wird er aus den
 *   bisherigen Arbeitszeitnachweisen übernommen (Altbestand), danach berechnet.
 * - Verfall: Übertrag, der bis zum Stichtag (Setting "urlaub_verfall_datum", MM-TT) nicht
 *   genommen wurde (genommene Tage werden zuerst auf den Übertrag angerechnet).
 * - genommen: genehmigte Urlaubsanträge (Tage laut Antrag, berechnet über das Arbeitszeitmodell).
 */
class UrlaubskontoService
{
    /** @var array<string, float> */
    private array $cache = [];

    /** @var array<string, array|null> */
    private array $basisCache = [];

    public function __construct(private readonly ArbeitszeitService $arbeitszeit)
    {
    }

    public function vergessen(?User $user = null): void
    {
        if ($user === null) {
            $this->cache = [];
            $this->basisCache = [];
            return;
        }
        foreach (array_keys($this->basisCache) as $key) {
            if (str_starts_with($key, $user->id.':')) {
                unset($this->basisCache[$key]);
            }
        }
        foreach (array_keys($this->cache) as $key) {
            if (str_starts_with($key, $user->id.':')) {
                unset($this->cache[$key]);
            }
        }
    }

    /**
     * Stichtag des neuen Modells (Setting "zeitwirtschaft_stichtag"), siehe TimesheetService.
     */
    public function stichtag(): Carbon
    {
        $wert = trim((string) settings('zeitwirtschaft_stichtag'));

        try {
            return $wert !== '' ? Carbon::parse($wert)->startOfMonth() : Carbon::create(2000, 1, 1);
        } catch (\Throwable) {
            return Carbon::create(2000, 1, 1);
        }
    }

    /**
     * Bestandsschutz im Jahr der Umstellung: Der im Arbeitszeitnachweis des Monats vor dem Stichtag
     * gespeicherte Resturlaub ist die Basis – ab dem Stichtag werden nur noch genehmigte Anträge
     * abgezogen. So springt der Resturlaub durch die Umstellung nicht.
     *
     * @return array{rest: float, bis: Carbon}|null
     */
    public function basis(User $user, int $jahr): ?array
    {
        $stichtag = $this->stichtag();
        if ($stichtag->year !== $jahr || $stichtag->month === 1) {
            return null;
        }

        $key = $user->id.':basis:'.$jahr;
        if (!array_key_exists($key, $this->basisCache)) {
            $vormonat = $stichtag->copy()->subMonth();
            $ts = Timesheet::where('employe_id', $user->id)->where('year', $vormonat->year)->where('month', $vormonat->month)->first();
            $this->basisCache[$key] = ($ts !== null && $ts->getRawOriginal('holidays_rest') !== null)
                ? ['rest' => (float) $ts->holidays_rest, 'bis' => $vormonat->copy()->endOfMonth()->startOfDay()]
                : null;
        }

        return $this->basisCache[$key];
    }

    public function startjahr(): int
    {
        $jahr = (int) settings('urlaubskonto_startjahr');

        return $jahr > 2000 ? $jahr : (int) now()->year;
    }

    public function anspruch(User $user, int $jahr): float
    {
        return $this->merke($user, "anspruch:$jahr", function () use ($user, $jahr) {
            $jahresbeginn = Carbon::create($jahr, 1, 1);
            $jahresende = Carbon::create($jahr, 12, 31);

            $individuell = EmployeHolidayClaim::query()
                ->where('employe_id', $user->id)
                ->whereDate('date_start', '<=', $jahresende)
                ->orderByDesc('date_start')
                ->first();

            if ($individuell !== null) {
                return (float) $individuell->holiday_claim;
            }

            $standard = (float) (settings('holiday_claim', 'config') ?? 0);

            if ((string) settings('urlaub_anteilig') === '0' || !$this->arbeitszeit->hatVertraege($user)) {
                return $standard;
            }

            // Anteilig: je Beschäftigungsmonat 1/12, gewichtet mit Arbeitstagen pro Woche / 5
            $summe = 0.0;
            for ($monat = $jahresbeginn->copy(); $monat->lte($jahresende); $monat->addMonth()) {
                $stichtag = $monat->copy()->startOfMonth()->addDays(14);
                if ($this->arbeitszeit->vertraegeAm($user, $stichtag)->isEmpty()) {
                    continue;
                }
                $summe += $standard / 12 * ($this->arbeitszeit->arbeitstageProWoche($user, $stichtag) / 5);
            }

            return self::aufHalbeTage($summe);
        });
    }

    public function buchungen(User $user, int $jahr): float
    {
        return $this->merke($user, "buchungen:$jahr", fn () => (float) HolidayAccountEntry::query()
            ->where('employe_id', $user->id)
            ->where('year', $jahr)
            ->sum('days'));
    }

    /**
     * Genehmigte Urlaubstage im Jahr (optional nur bis einschließlich $bis).
     */
    public function genommen(User $user, int $jahr, ?CarbonInterface $bis = null): float
    {
        $key = "genommen:$jahr:".($bis?->toDateString() ?? 'jahr');

        return $this->merke($user, $key, function () use ($user, $jahr, $bis) {
            $basis = $this->basis($user, $jahr);

            if ($basis !== null && ($bis === null || Carbon::parse($bis)->gte($basis['bis']))) {
                // bis zum Stichtag: aus dem Nachweis übernommen, danach: genehmigte Anträge
                $vorher = $this->anspruch($user, $jahr) + $this->uebertrag($user, $jahr) - $basis['rest'];

                return $vorher + $this->summeTage($user, Holiday::query()->genehmigt(), $jahr, $bis, $basis['bis']->copy()->addDay());
            }

            return $this->summeTage($user, Holiday::query()->genehmigt(), $jahr, $bis);
        });
    }

    /**
     * Offene (noch nicht entschiedene) Anträge im Jahr.
     */
    public function beantragt(User $user, int $jahr): float
    {
        return $this->merke($user, "beantragt:$jahr", fn () => $this->summeTage($user, Holiday::query()->offen(), $jahr));
    }

    public function uebertrag(User $user, int $jahr): float
    {
        return $this->merke($user, "uebertrag:$jahr", function () use ($user, $jahr) {
            if ($jahr <= $this->startjahr()) {
                return (float) $user->getPreviousYearHolidayRest($jahr);
            }

            return $this->rest($user, $jahr - 1, Carbon::create($jahr - 1, 12, 31));
        });
    }

    public function verfallsdatum(int $jahr): ?Carbon
    {
        $wert = trim((string) settings('urlaub_verfall_datum'));
        if (!preg_match('/^(\d{2})-(\d{2})$/', $wert, $m)) {
            return null;
        }

        return Carbon::create($jahr, (int) $m[1], (int) $m[2])->endOfDay();
    }

    /**
     * Verfallener Übertrag – erst nach dem Verfallsdatum relevant.
     */
    public function verfallen(User $user, int $jahr, ?CarbonInterface $stichtag = null): float
    {
        $stichtag ??= Carbon::now();
        $verfall = $this->verfallsdatum($jahr);
        $uebertrag = $this->uebertrag($user, $jahr);

        if ($verfall === null || $uebertrag <= 0 || $stichtag->lte($verfall)) {
            return 0.0;
        }

        return max(0.0, $uebertrag - $this->genommen($user, $jahr, $verfall));
    }

    /**
     * Resturlaub zum Stichtag (Standard: heute bzw. Jahresende bei vergangenen Jahren).
     */
    public function rest(User $user, int $jahr, ?CarbonInterface $stichtag = null): float
    {
        $stichtag ??= $jahr < now()->year ? Carbon::create($jahr, 12, 31) : Carbon::now();

        return round(
            $this->anspruch($user, $jahr)
            + $this->uebertrag($user, $jahr)
            + $this->buchungen($user, $jahr)
            - $this->genommen($user, $jahr)
            - $this->verfallen($user, $jahr, $stichtag),
            1
        );
    }

    /**
     * Alle Kennzahlen für die Anzeige.
     *
     * @return array{jahr:int, anspruch:float, uebertrag:float, buchungen:float, genommen:float, beantragt:float, verfallen:float, verfall_droht:float, verfallsdatum:?Carbon, rest:float, rest_nach_antraegen:float}
     */
    public function uebersicht(User $user, int $jahr): array
    {
        $verfallsdatum = $this->verfallsdatum($jahr);
        $uebertrag = $this->uebertrag($user, $jahr);
        $verfallen = $this->verfallen($user, $jahr);
        $verfallDroht = 0.0;

        if ($verfallsdatum !== null && now()->lte($verfallsdatum) && $uebertrag > 0) {
            $verfallDroht = max(0.0, $uebertrag - $this->genommen($user, $jahr, $verfallsdatum));
        }

        $rest = $this->rest($user, $jahr);
        $beantragt = $this->beantragt($user, $jahr);

        return [
            'jahr' => $jahr,
            'anspruch' => $this->anspruch($user, $jahr),
            'uebertrag' => $uebertrag,
            'buchungen' => $this->buchungen($user, $jahr),
            'genommen' => $this->genommen($user, $jahr),
            'beantragt' => $beantragt,
            'verfallen' => $verfallen,
            'verfall_droht' => $verfallDroht,
            'verfallsdatum' => $verfallsdatum,
            'rest' => $rest,
            'rest_nach_antraegen' => round($rest - $beantragt, 1),
        ];
    }

    /**
     * Urlaubstage für einen Zeitraum nach Arbeitszeitmodell (halber Tag = 0,5).
     */
    public function tageFuerZeitraum(User $user, CarbonInterface $start, CarbonInterface $ende, bool $halberTag = false): float
    {
        $tage = (float) $this->arbeitszeit->arbeitstageZwischen($user, $start, $ende);

        if ($halberTag && $tage > 0) {
            return 0.5;
        }

        return $tage;
    }

    public static function aufHalbeTage(float $tage): float
    {
        return round($tage * 2) / 2;
    }

    public static function format(float $tage): string
    {
        return rtrim(rtrim(number_format($tage, 1, ',', '.'), '0'), ',');
    }

    private function summeTage(User $user, $query, int $jahr, ?CarbonInterface $bis = null, ?CarbonInterface $von = null): float
    {
        $jahresbeginn = $von !== null ? Carbon::parse($von)->startOfDay() : Carbon::create($jahr, 1, 1);
        $ende = $bis !== null ? Carbon::parse($bis) : Carbon::create($jahr, 12, 31);

        $antraege = $query->where('employe_id', $user->id)
            ->ueberschneidet($jahresbeginn, $ende)
            ->get();

        $summe = 0.0;
        foreach ($antraege as $antrag) {
            $vollstaendig = $antrag->start_date->gte($jahresbeginn) && $antrag->end_date->lte($ende);

            if ($vollstaendig && $antrag->days !== null) {
                $summe += (float) $antrag->days;
                continue;
            }

            $von = $antrag->start_date->max($jahresbeginn);
            $bisDatum = $antrag->end_date->min($ende);
            $summe += $this->tageFuerZeitraum($user, $von, $bisDatum, (bool) $antrag->half_day);
        }

        return $summe;
    }

    private function merke(User $user, string $key, callable $callback): float
    {
        $key = $user->id.':'.$key;

        return $this->cache[$key] ??= (float) $callback();
    }
}
