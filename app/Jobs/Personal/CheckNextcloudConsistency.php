<?php

namespace App\Jobs\Personal;

use App\Enums\SyncStatus;
use App\Models\personal\PersonalDocument;
use App\Services\Personal\Contracts\NextcloudFileServiceInterface;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class CheckNextcloudConsistency implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function handle(NextcloudFileServiceInterface $nc): void
    {
        PersonalDocument::where('sync_status', SyncStatus::Synced->value)
            ->chunk(50, function ($documents) use ($nc) {
                foreach ($documents as $doc) {
                    if (! $nc->exists($doc->nextcloud_path)) {
                        $doc->update(['sync_status' => SyncStatus::SyncFehler]);
                        Log::warning("NC-Konsistenz: Datei fehlt: {$doc->nextcloud_path}");
                    }
                }
            });
    }
}

