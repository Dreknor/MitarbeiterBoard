<?php

namespace App\Jobs\Arbeitsblaetter;

use App\Models\Arbeitsblaetter\AngepasstesBlatt;
use App\Services\Arbeitsblaetter\AngepasstesBlattService;
use App\Services\Arbeitsblaetter\Ki\AbErkenner;
use App\Services\Arbeitsblaetter\Ki\AbErkennung;
use App\Services\Arbeitsblaetter\Ki\AbErkennungFehler;
use App\Services\Arbeitsblaetter\Ki\ErkennerVorgaben;
use App\Support\AbStruktur;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * KI-Erkennung eines Blatts (3 Versuche mit Backoff bei vorübergehenden Fehlern).
 * Endgültiger Fehler → Status `fehler` + Benachrichtigung; manuell geht es im Editor weiter.
 */
class AbBlattErkennen implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $timeout = 300;

    /**
     * @param  int|null  $basisVersion  Version des Blatts beim Start: Ist sie bei der Übernahme
     *                                  unverändert, ersetzt das Ergebnis die vorhandenen Bausteine;
     *                                  wurde inzwischen gespeichert, wird angehängt.
     */
    public function __construct(public int $blattId, public ?int $userId = null, public ?int $basisVersion = null)
    {
    }

    public function backoff(): array
    {
        return [60, 300];
    }

    public function handle(AngepasstesBlattService $service, AbErkenner $erkenner): void
    {
        $blatt = AngepasstesBlatt::find($this->blattId);
        if (! $blatt || $blatt->status !== AngepasstesBlatt::ERKENNUNG) {
            return;
        }

        if ($this->synchron()) {
            // läuft ohne Worker nach der Antwort im Web-Prozess – nicht am PHP-Zeitlimit scheitern
            @set_time_limit(0);
            @ignore_user_abort(true);
        }

        \App\Services\Arbeitsblaetter\Ki\KiProtokoll::fuer($blatt->id);
        try {
            $this->erkennen($blatt, $service, $erkenner);
        } finally {
            \App\Services\Arbeitsblaetter\Ki\KiProtokoll::fuer(null);
        }
    }

    private function erkennen(AngepasstesBlatt $blatt, AngepasstesBlattService $service, AbErkenner $erkenner): void
    {
        $pausen = (array) config('arbeitsblaetter.ki.wiederholung_sekunden', [5, 15]);
        $versuch = 0;
        while (true) {
            try {
                $erkennung = $erkenner->erkennen($service->seitenFuerErkennung($blatt), $blatt->textebene);
                break;
            } catch (AbErkennungFehler $e) {
                if (! $e->wiederholbar) {
                    $service->erkennungFehlgeschlagen($blatt, $e->getMessage());

                    return;
                }

                if ($this->synchron()) {
                    // Ohne Queue-Worker wird release() verworfen – deshalb hier direkt erneut versuchen
                    if ($versuch >= count($pausen)) {
                        $service->erkennungFehlgeschlagen($blatt, $e->getMessage().' (nach '.($versuch + 1).' Versuchen)');

                        return;
                    }
                    $service->erkennungHinweis($blatt, $e->getMessage().' Neuer Versuch …');
                    sleep(max(0, (int) $pausen[$versuch]));
                    $versuch++;
                    if (AngepasstesBlatt::whereKey($blatt->id)->value('status') !== AngepasstesBlatt::ERKENNUNG) {
                        return; // inzwischen abgebrochen
                    }

                    continue;
                }

                if ($this->attempts() < $this->tries) {
                    $warten = $this->backoff()[$this->attempts() - 1] ?? 300;
                    $service->erkennungHinweis($blatt, $e->getMessage().' Neuer Versuch in '.max(1, (int) round($warten / 60)).' Minute(n).');
                    $this->release($warten);

                    return;
                }
                $service->erkennungFehlgeschlagen($blatt, $e->getMessage().' (nach '.$this->tries.' Versuchen)');

                return;
            }
        }

        $erkennung = $this->vollstaendigkeitPruefen($blatt, $service, $erkenner, $erkennung);

        if (AngepasstesBlatt::whereKey($blatt->id)->value('status') !== AngepasstesBlatt::ERKENNUNG) {
            return; // während der Erkennung abgebrochen – Ergebnis verwerfen
        }
        $service->erkennungUebernehmen($blatt, $erkennung, $this->userId, $this->basisVersion);
    }

    /**
     * Wirkt das Ergebnis unvollständig (zu wenige Bausteine, viel weniger Text als die Textebene),
     * wird einmal neu angefragt und das umfangreichere Ergebnis genommen. Bleibt es unvollständig,
     * bekommt die Lehrkraft einen Hinweis im Editor.
     */
    private function vollstaendigkeitPruefen(AngepasstesBlatt $blatt, AngepasstesBlattService $service, AbErkenner $erkenner, AbErkennung $erkennung): AbErkennung
    {
        $seiten = max(1, (int) ($blatt->ki_seiten ?: $erkennung->seiten));
        $luecken = ErkennerVorgaben::unvollstaendig($erkennung->struktur, $seiten, $blatt->textebene);
        if ($luecken && config('arbeitsblaetter.ki.nachfragen', true)) {
            try {
                $service->erkennungHinweis($blatt, 'Das Ergebnis wirkt unvollständig – es wird ein zweites Mal erkannt …');
                $nachfrage = method_exists($erkenner, 'nachfrageErkenner') ? ($erkenner->nachfrageErkenner() ?? $erkenner) : $erkenner;
                $zweite = $nachfrage->erkennen($service->seitenFuerErkennung($blatt), $blatt->textebene);
                $umfang = fn (AbErkennung $e) => mb_strlen(AbStruktur::klartext($e->struktur)) + 20 * AbStruktur::anzahl($e->struktur['bausteine']);
                $besser = $umfang($zweite) > $umfang($erkennung) ? $zweite : $erkennung;
                $erkennung = new AbErkennung(
                    $besser->struktur, $besser->seiten, $erkennung->kostenCent + $zweite->kostenCent,
                    $besser->anbieter, $besser->modell, array_values(array_unique(array_merge($erkennung->hinweise, $zweite->hinweise))),
                );
                $luecken = ErkennerVorgaben::unvollstaendig($erkennung->struktur, $seiten, $blatt->textebene);
            } catch (AbErkennungFehler) {
                // erstes Ergebnis behalten
            }
        }

        if (! $luecken) {
            return $erkennung;
        }

        return new AbErkennung($erkennung->struktur, $erkennung->seiten, $erkennung->kostenCent, $erkennung->anbieter, $erkennung->modell,
            array_merge($erkennung->hinweise, $luecken, ['Bitte fehlende Teile ergänzen oder „Erkennen“ erneut starten.']));
    }

    /** Läuft der Auftrag ohne Queue-Worker (QUEUE_CONNECTION=sync bzw. dispatchSync)? */
    private function synchron(): bool
    {
        return $this->job === null || $this->job instanceof \Illuminate\Queue\Jobs\SyncJob;
    }

    public function failed(\Throwable $e): void
    {
        $blatt = AngepasstesBlatt::find($this->blattId);
        if ($blatt && $blatt->status === AngepasstesBlatt::ERKENNUNG) {
            app(AngepasstesBlattService::class)->erkennungFehlgeschlagen($blatt, 'Die Erkennung ist fehlgeschlagen.');
        }
    }
}
