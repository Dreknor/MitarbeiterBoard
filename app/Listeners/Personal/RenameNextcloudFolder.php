<?php

namespace App\Listeners\Personal;

use App\Events\Personal\EmployeeNameChanged;
use App\Jobs\Personal\MoveEmployeeFolder;
use App\Services\Personal\PersonalDocumentService;

/**
 * Benennt den Nextcloud-Ordner um, wenn sich Vor- oder Nachname ändern.
 * Die Dokument-Pfade zieht der MoveEmployeeFolder-Job nach.
 */
class RenameNextcloudFolder
{
    public function __construct(private readonly PersonalDocumentService $documents) {}

    public function handle(EmployeeNameChanged $event): void
    {
        $user = $event->user->fresh(['employe_data']);
        $old  = $this->documents->getEmployeePath($user, $event->oldFamilienname ?? '', $event->oldVorname ?? '');
        $new  = $this->documents->getEmployeePath($user);

        if ($old !== $new) {
            MoveEmployeeFolder::dispatch($user, $old, $new);
        }
    }
}
