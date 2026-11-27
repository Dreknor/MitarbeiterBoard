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

        try {
            $erkennung = $erkenner->erkennen($service->seitenFuerErkennung($blatt), $blatt->textebene);
        } catch (AbErkennungFehler $e) {
            if ($e->wiederholbar && $this->attempts() < $this->tries) {
                $this->release($this->backoff()[$this->attempts() - 1] ?? 300);

                return;
            }
            $service->erkennungFehlgeschlagen($blatt, $e->getMessage());

            return;
        }

        $service->erkennungUebernehmen($blatt, $erkennung, $this->userId);
    }

    public function failed(\Throwable $e): void
    {
        $blatt = AngepasstesBlatt::find($this->blattId);
        if ($blatt && $blatt->status === AngepasstesBlatt::ERKENNUNG) {
            app(AngepasstesBlattService::class)->erkennungFehlgeschlagen($blatt, 'Die Erkennung ist fehlgeschlagen.');
        }
    }
}
