<?php

namespace App\Jobs\Wochenplan;

use App\Models\Wochenplan\WpDatei;
use App\Services\Wochenplan\WpAblageService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/** Lädt ein Arbeitsblatt aus dem Zwischenspeicher in die Nextcloud (3 Versuche). */
class WpDateiHochladen implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $timeout = 120;

    public function __construct(public int $dateiId)
    {
    }

    public function backoff(): array
    {
        return [30, 120];
    }

    public function handle(WpAblageService $ablage): void
    {
        $datei = WpDatei::find($this->dateiId);
        if (! $datei || ! in_array($datei->status, [WpDatei::AUSSTEHEND, WpDatei::FEHLER], true)) {
            return;
        }

        if (! $ablage->dateiHochladen($datei) && $this->attempts() < $this->tries) {
            $datei->update(['status' => WpDatei::AUSSTEHEND]);
            $this->release($this->backoff()[$this->attempts() - 1] ?? 120);
        }
    }

    public function failed(\Throwable $e): void
    {
        WpDatei::whereKey($this->dateiId)->update(['status' => WpDatei::FEHLER, 'fehler' => mb_substr($e->getMessage(), 0, 500)]);
    }
}
