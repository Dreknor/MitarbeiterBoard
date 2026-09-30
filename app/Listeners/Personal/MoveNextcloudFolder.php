<?php

namespace App\Listeners\Personal;

use App\Events\Personal\EmploymentTerminated;
use App\Jobs\Personal\MoveEmployeeFolder;
use App\Models\personal\PersonalDocument;
use App\Services\Personal\Contracts\NextcloudFileServiceInterface;
use App\Services\Personal\PersonalDocumentService;
use App\Services\Personal\PersonalProcessService;

/**
 * Verschiebt die Personalakte in Nextcloud von "Angestellt" nach "Ausgeschieden",
 * sobald die Person keine weitere offene Anstellung mehr hat.
 */
class MoveNextcloudFolder
{
    public function __construct(
        private readonly NextcloudFileServiceInterface $nc,
        private readonly PersonalDocumentService $documents,
        private readonly PersonalProcessService $processes
    ) {}

    public function handle(EmploymentTerminated $event): void
    {
        $employment = $event->employment;
        if ($this->processes->hasOtherOpenEmployment($employment)) {
            return;
        }

        $employe = $employment->employe;
        $target  = $this->documents->getEmployeePath($employe, angestellt: false);
        $source  = $this->currentRoot($employe->id) ?? $this->documents->getEmployeePath($employe, angestellt: true);

        if ($source === $target) {
            return;
        }

        $this->nc->ensureDirectoryExists(dirname($target));
        MoveEmployeeFolder::dispatch($employe, $source, $target);
    }

    /** Tatsächlicher Ordner laut vorhandenen Dokumenten (unabhängig von zwischenzeitlichen Gruppenwechseln). */
    private function currentRoot(int $employeId): ?string
    {
        $path = PersonalDocument::where('employe_id', $employeId)->whereNotNull('nextcloud_path')->value('nextcloud_path');
        if (!$path) {
            return null;
        }

        $baseDepth = count(array_filter(explode('/', config('nextcloud.personal.base_path', '/Personal'))));
        $segments  = array_values(array_filter(explode('/', $path)));

        // base / Gruppe / Status / Name → danach folgen Unterordner und Datei
        return '/' . implode('/', array_slice($segments, 0, $baseDepth + 3));
    }
}
