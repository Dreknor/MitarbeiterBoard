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

class UploadDocumentToNextcloud implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries   = 3;
    public int $timeout = 60;

    public function __construct(
        private readonly PersonalDocument $document,
        private readonly string           $localPath
    ) {}

    public function backoff(): array
    {
        return [30, 60, 120];
    }

    public function handle(NextcloudFileServiceInterface $nc): void
    {
        $this->document->update(['sync_status' => SyncStatus::Uploading]);

        $nc->ensureDirectoryExists(dirname($this->document->nextcloud_path));
        $success = $nc->uploadFile($this->localPath, $this->document->nextcloud_path);

        $this->document->update([
            'sync_status' => $success ? SyncStatus::Synced : SyncStatus::SyncFehler,
        ]);

        if ($success && file_exists($this->localPath)) {
            unlink($this->localPath);
        }
    }

    public function failed(\Throwable $e): void
    {
        $this->document->update(['sync_status' => SyncStatus::SyncFehler]);
        Log::error("NC-Upload fehlgeschlagen für Dokument #{$this->document->id}: {$e->getMessage()}");
    }
}

