<?php

namespace App\Listeners\Personal;

use App\Enums\QualificationStatus;
use App\Events\Personal\EmploymentCreated;
use App\Models\personal\EmployeeQualification;
use App\Services\Personal\QualificationService;

/**
 * Legt für fehlende Pflicht-Qualifikationen (abhängig von der Anstellungsart) Einträge mit Status "fehlend" an,
 * damit sie in der Qualifikationsmatrix und der Personalakte sichtbar sind.
 */
class InitMissingQualifications
{
    public function __construct(private readonly QualificationService $qualifications) {}

    public function handle(EmploymentCreated $event): void
    {
        $employe = $event->employment->employe;

        foreach ($this->qualifications->getMissingRequired($employe) as $type) {
            EmployeeQualification::firstOrCreate(
                ['employe_id' => $employe->id, 'qualification_type_id' => $type->id],
                ['status' => QualificationStatus::Fehlend]
            );
        }
    }
}
