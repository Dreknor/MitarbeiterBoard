<?php

namespace App\Jobs\Arbeitsblaetter;

use App\Models\Arbeitsblaetter\AngepasstesBlatt;
use App\Services\Arbeitsblaetter\AngepasstesBlattService;
use App\Services\Arbeitsblaetter\Ki\AbErkenner;
use App\Services\Arbeitsblaetter\Ki\AbErkennungFehler;
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

    public function __construct(public int $blattId, public ?int $userId = null)
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

        if (AngepasstesBlatt::whereKey($blatt->id)->value('status') !== AngepasstesBlatt::ERKENNUNG) {
            return; // während der Erkennung abgebrochen – Ergebnis verwerfen
        }
        $service->erkennungUebernehmen($blatt, $erkennung, $this->userId);
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
