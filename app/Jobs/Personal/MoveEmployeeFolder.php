<?php

namespace App\Jobs\Personal;

use App\Enums\SyncStatus;
use App\Models\personal\PersonalDocument;
use App\Models\User;
use App\Services\Personal\Contracts\NextcloudFileServiceInterface;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class MoveEmployeeFolder implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public function __construct(
        private readonly User   $employe,
        private readonly string $source,
        private readonly string $target
    ) {}

    public function backoff(): array
    {
        return [60, 300, 900];
    }

    public function handle(NextcloudFileServiceInterface $nc): void
    {
        if (! $nc->exists($this->source)) {
            Log::warning("NC-Move: Quellordner nicht gefunden: {$this->source}");
            return;
        }

        $nc->ensureDirectoryExists(dirname($this->target));
        $success = $nc->moveDirectory($this->source, $this->target);

        if ($success) {
            PersonalDocument::where('employe_id', $this->employe->id)
                ->where('nextcloud_path', 'LIKE', $this->source . '%')
                ->each(function ($doc) {
                    $doc->update([
                        'nextcloud_path' => str_replace($this->source, $this->target, $doc->nextcloud_path),
                        'sync_status'    => SyncStatus::Synced,
                    ]);
                });
        }
    }
}

