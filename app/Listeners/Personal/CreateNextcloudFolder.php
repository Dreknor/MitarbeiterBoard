<?php

namespace App\Listeners\Personal;

use App\Events\Personal\EmploymentCreated;
use App\Jobs\Personal\MoveEmployeeFolder;
use App\Models\personal\DocumentType;
use App\Services\Personal\Contracts\NextcloudFileServiceInterface;
use App\Services\Personal\PersonalDocumentService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Log;

/**
 * Legt die Nextcloud-Ordnerstruktur der Personalakte an
 * (/Personal/{Bereich}/Angestellt/{Name}/{Dokumentart}) bzw. holt einen archivierten Ordner zurück.
 */
class CreateNextcloudFolder implements ShouldQueue
{
    public int $tries = 3;

    public function __construct(
        private readonly NextcloudFileServiceInterface $nc,
        private readonly PersonalDocumentService $documents
    ) {}

    public function handle(EmploymentCreated $event): void
    {
        $employe = $event->employment->employe;
        $target  = $this->documents->getEmployeePath($employe, angestellt: true);
        $archive = $this->documents->getEmployeePath($employe, angestellt: false);

        // Wiedereinstellung: Ordner liegt bei "Ausgeschieden" → zurückholen
        if ($archive !== $target && $this->nc->exists($archive) && !$this->nc->exists($target)) {
            $this->ensureParents($target);
            MoveEmployeeFolder::dispatch($employe, $archive, $target);
            return;
        }

        if (!$this->ensureParents($target) || !$this->nc->ensureDirectoryExists($target)) {
            Log::warning('Personal: Nextcloud-Ordner konnte nicht angelegt werden', ['path' => $target]);
            throw new \RuntimeException("Nextcloud-Ordner nicht angelegt: {$target}");
        }

        foreach (DocumentType::pluck('nextcloud_subfolder')->filter()->unique() as $sub) {
            $this->nc->ensureDirectoryExists($target . '/' . $sub);
        }
    }

    /** WebDAV-MKCOL legt keine Zwischenordner an – daher Ebene für Ebene. */
    private function ensureParents(string $path): bool
    {
        $current = '';
        foreach (array_filter(explode('/', dirname($path))) as $segment) {
            $current .= '/' . $segment;
            if (!$this->nc->ensureDirectoryExists($current)) {
                return false;
            }
        }

        return true;
    }
}
