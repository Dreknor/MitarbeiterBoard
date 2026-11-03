<?php

namespace App\Services\Personal;

use App\Enums\ContractType;
use App\Enums\EmploymentStatus;
use App\Enums\EmploymentType;
use App\Enums\TerminationReason;
use App\Events\Personal\EmploymentCreated;
use App\Models\personal\Employment;
use App\Models\personal\HourType;
use App\Models\personal\SchoolType;
use App\Models\personal\TeacherDetail;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Zentrale Stelle für alle Änderungen an Anstellungen (Arbeitsverträgen).
 *
 * Neuanlage, Bearbeitung, Ablösung ("ersetzt Anstellung") und das automatische
 * Beenden abgelaufener befristeter Verträge laufen ausschließlich hierüber –
 * so greifen Audit (EmploymentObserver), Events und Plausibilitätsprüfungen
 * unabhängig davon, aus welcher Oberfläche die Änderung kommt.
 */
class ContractService
{
    /** Felder, die nur mit Berechtigung "edit salary" geschrieben werden dürfen. */
    private const SALARY_FIELDS = ['salary_group', 'salary_level', 'salary_table_id'];

    /** Felder, die nicht direkt an Employment gehen, sondern an TeacherDetail. */
    private const TEACHER_FIELDS = [
        'school_type_id', 'deputat_hours', 'reduction_hours', 'reduction_reason', 'anrechnungsstunden',
    ];

    public function __construct(
        private readonly ContractValidationService $validation
    ) {}

    /**
     * Legt eine Anstellung an (inkl. Lehrer-Details) und feuert EmploymentCreated.
     *
     * @return array{employment: Employment, warnings: string[]}
     */
    public function create(User $employe, array $data, ?User $actor = null): array
    {
        $actor ??= auth()->user();
        $replacedId = $data['replaced_employment_id'] ?? null;
        $data = $this->applyTeacherHours($this->stripForbiddenFields($data, $actor));

        $employment = DB::transaction(function () use ($employe, $data, $replacedId) {
            $employment = Employment::create(array_merge(
                $this->employmentAttributes($data),
                [
                    'employe_id'             => $employe->id,
                    'status'                 => EmploymentStatus::Aktiv->value,
                    'replaced_employment_id' => $replacedId,
                ]
            ));

            $this->syncTeacherDetail($employment, $data);

            if ($replacedId) {
                $this->closeReplaced($employe, (int) $replacedId, $employment);
            }

            return $employment;
        });

        event(new EmploymentCreated($employment));

        return [
            'employment' => $employment,
            'warnings'   => $this->warningsFor($employment),
        ];
    }

    /**
     * Aktualisiert eine Anstellung inkl. Lehrer-Details.
     *
     * @return array{employment: Employment, warnings: string[]}
     */
    public function update(Employment $employment, array $data, ?User $actor = null): array
    {
        $actor ??= auth()->user();
        $data = $this->applyTeacherHours($this->stripForbiddenFields($data, $actor), $employment);

        DB::transaction(function () use ($employment, $data) {
            $employment->update($this->employmentAttributes($data, $employment));
            $this->syncTeacherDetail($employment, $data);
        });

        return [
            'employment' => $employment->fresh(),
            'warnings'   => $this->warningsFor($employment),
        ];
    }

    /**
     * Beendet alle Anstellungen, deren Enddatum überschritten ist, aber noch "aktiv"/"ruhend" sind.
     *
     * Wird der Vertrag nahtlos fortgesetzt (Nachfolge-Vertrag oder weitere laufende Anstellung),
     * wird er still beendet – ohne Offboarding, Ordner-Verschiebung und Löschfristen.
     *
     * @return int Anzahl beendeter Anstellungen
     */
    public function endExpired(?Carbon $today = null): int
    {
        $today ??= Carbon::today();
        $count = 0;

        Employment::query()
            ->whereIn('status', [EmploymentStatus::Aktiv->value, EmploymentStatus::Ruhend->value])
            ->whereNotNull('end')
            ->where('end', '<', $today->copy()->startOfDay())
            ->orderBy('end')
            ->each(function (Employment $employment) use (&$count) {
                $silent = $this->isContinued($employment);
                $employment->setBeendet($employment->termination_reason ?? TerminationReason::Befristungsablauf, null, dispatchEvent: !$silent);
                $count++;
            });

        return $count;
    }

    /**
     * Wird die Anstellung durch einen anderen Vertrag derselben Person fortgeführt?
     */
    public function isContinued(Employment $employment): bool
    {
        $dayAfterEnd = $employment->end->copy()->addDay()->startOfDay();

        return Employment::where('employe_id', $employment->employe_id)
            ->where('id', '!=', $employment->id)
            ->where('status', '!=', EmploymentStatus::Beendet->value)
            ->where(function ($q) use ($employment, $dayAfterEnd) {
                $q->where('replaced_employment_id', $employment->id)
                  ->orWhere(function ($q) use ($dayAfterEnd) {
                      $q->where('start', '<=', $dayAfterEnd)
                        ->where(fn ($q) => $q->whereNull('end')->orWhere('end', '>=', $dayAfterEnd));
                  });
            })
            ->exists();
    }

    /**
     * Plausibilitätswarnungen (blockieren das Speichern nicht).
     *
     * @return string[]
     */
    public function warningsFor(Employment $employment): array
    {
        $warnings = [];

        if ($employment->contract_type?->isBefristet()) {
            $kette = $this->validation->checkBefristungsketten($employment->employe_id, $employment->id, $employment);
            if ($kette['warnung']) {
                $warnings[] = $kette['nachricht'];
            }
        }

        // Überschneidung im selben Bereich (außer bei ersetztem Vertrag)
        $overlap = Employment::where('employe_id', $employment->employe_id)
            ->where('id', '!=', $employment->id)
            ->where('department_id', $employment->department_id)
            ->where('status', '!=', EmploymentStatus::Beendet->value)
            ->where('id', '!=', $employment->replaced_employment_id ?? 0)
            ->where('start', '<=', $employment->end ?? '9999-12-31')
            ->where(fn ($q) => $q->whereNull('end')->orWhere('end', '>=', $employment->start))
            ->exists();
        if ($overlap) {
            $warnings[] = 'Es besteht bereits eine weitere Anstellung im selben Bereich, die sich zeitlich überschneidet.';
        }

        // Summe der Stellenanteile im Zeitraum > 100 %
        $parallel = Employment::where('employe_id', $employment->employe_id)
            ->where('status', EmploymentStatus::Aktiv->value)
            ->where('start', '<=', $employment->end ?? '9999-12-31')
            ->where(fn ($q) => $q->whereNull('end')->orWhere('end', '>=', $employment->start))
            ->get();
        $percent = $parallel->sum(fn (Employment $e) => $e->percent);
        if ($percent > 100.01) {
            $warnings[] = sprintf('Die parallelen Anstellungen ergeben zusammen %.1f %% – mehr als eine Vollzeitstelle.', $percent);
        }

        if ($employment->probation_end && $employment->start && $employment->probation_end->lessThan($employment->start)) {
            $warnings[] = 'Das Ende der Probezeit liegt vor dem Vertragsbeginn.';
        }

        return $warnings;
    }

    // ------------------------------------------------------------------

    /**
     * Wochenstunden einer Lehrkraft aus dem Deputat:
     * Deputat ÷ Regeldeputat der Schulart × Vollzeit-Wochenstunden der Stundenart.
     */
    public function wochenstundenAusDeputat(int $schoolTypeId, float $deputat, int $hourTypeId): ?float
    {
        $regel = (float) SchoolType::find($schoolTypeId)?->default_deputat;
        $vollzeit = (float) HourType::find($hourTypeId)?->fulltimehours;
        if ($regel <= 0 || $vollzeit <= 0) {
            return null;
        }

        return round($deputat / $regel * $vollzeit, 2);
    }

    /**
     * Bei Lehrkräften ergeben sich die Wochenstunden aus dem Deputat – außer sie werden bewusst manuell gesetzt.
     */
    private function applyTeacherHours(array $data, ?Employment $existing = null): array
    {
        $isTeacher = ($data['employment_type'] ?? $existing?->employment_type?->value) === EmploymentType::Lehrer->value;
        $manual = !empty($data['hours_manual']);
        unset($data['hours_manual']);

        if (!$isTeacher || $manual || empty($data['school_type_id']) || !isset($data['deputat_hours'])) {
            return $data;
        }

        $hourTypeId = $data['hour_type_id'] ?? $existing?->hour_type_id;
        $hours = $hourTypeId
            ? $this->wochenstundenAusDeputat((int) $data['school_type_id'], (float) $data['deputat_hours'], (int) $hourTypeId)
            : null;

        if ($hours !== null) {
            $data['hours'] = $hours;
        }

        return $data;
    }

    private function employmentAttributes(array $data, ?Employment $existing = null): array
    {
        $attrs = array_diff_key($data, array_flip(self::TEACHER_FIELDS));
        unset($attrs['replaced_employment_id'], $attrs['hours_manual']);

        // Beschreibung der Änderung gibt es nur bei Änderungsvertrag bzw. internem Wechsel
        if (array_key_exists('is_amendment', $attrs) || array_key_exists('is_internal_transfer', $attrs)) {
            $aenderung = (bool) ($attrs['is_amendment'] ?? $existing?->is_amendment);
            $wechsel   = (bool) ($attrs['is_internal_transfer'] ?? $existing?->is_internal_transfer);
            if (!$aenderung && !$wechsel) {
                $attrs['amendment_description'] = null;
            }
        }

        $beendet   = $existing?->status === EmploymentStatus::Beendet;
        $vorgemerkt = $existing && !$beendet && $existing->termination_reason !== null;

        if (array_key_exists('end', $attrs) && $attrs['end'] === null) {
            if ($beendet) {
                // Ein beendeter Vertrag behält sein Austrittsdatum – sonst gälte er wieder unbegrenzt.
                unset($attrs['end']);
            } elseif ($vorgemerkt) {
                // Austrittsdatum geleert → vorgemerkte Beendigung wird zurückgenommen.
                $attrs['termination_reason'] = null;
            }
        } elseif (($attrs['contract_type'] ?? null) === ContractType::Unbefristet->value
            && !array_key_exists('end', $attrs) && !$beendet && !$vorgemerkt) {
            // Wechsel auf unbefristet: ein vorhandenes Befristungsende entfällt –
            // nicht aber ein Austrittsdatum (vorgemerkte Beendigung bzw. bereits beendeter Vertrag).
            $attrs['end'] = null;
        }

        return $attrs;
    }

    private function stripForbiddenFields(array $data, ?User $actor): array
    {
        if (!$actor || !$actor->can('edit salary')) {
            $data = array_diff_key($data, array_flip(self::SALARY_FIELDS));
        }

        return $data;
    }

    /**
     * Ein Nachfolge-Vertrag beendet den ersetzten Vertrag am Vortag – ohne Offboarding.
     */
    private function closeReplaced(User $employe, int $replacedId, Employment $successor): void
    {
        $replaced = Employment::where('employe_id', $employe->id)->find($replacedId);
        if (!$replaced || $replaced->status === EmploymentStatus::Beendet) {
            return;
        }

        $replaced->setBeendet(
            TerminationReason::Sonstig,
            $successor->start->copy()->subDay(),
            dispatchEvent: false
        );
    }

    private function syncTeacherDetail(Employment $employment, array $data): void
    {
        $isTeacher = ($data['employment_type'] ?? $employment->employment_type?->value) === EmploymentType::Lehrer->value;
        if (!$isTeacher || empty($data['school_type_id'])) {
            return;
        }

        $values = [
            'school_type_id'     => $data['school_type_id'],
            'deputat_hours'      => $data['deputat_hours'] ?? 0,
            'reduction_hours'    => $data['reduction_hours'] ?? 0,
            'reduction_reason'   => $data['reduction_reason'] ?? null,
            'anrechnungsstunden' => $data['anrechnungsstunden'] ?? 0,
        ];

        $current = $employment->teacherDetails()->whereNull('valid_until')->latest('valid_from')->first();

        if ($current === null) {
            TeacherDetail::create($values + [
                'employment_id' => $employment->id,
                'valid_from'    => $employment->start,
                'valid_until'   => null,
            ]);
            return;
        }

        $current->fill($values);
        if (!$current->isDirty()) {
            return;
        }

        // Änderung ab heute versionieren, damit die Historie erhalten bleibt
        $from = Carbon::today()->max($employment->start);
        if ($current->valid_from->greaterThanOrEqualTo($from)) {
            $current->save();
            return;
        }

        $current->fresh()->update(['valid_until' => $from->copy()->subDay()]);
        TeacherDetail::create($values + [
            'employment_id' => $employment->id,
            'valid_from'    => $from,
            'valid_until'   => null,
        ]);
    }
}
