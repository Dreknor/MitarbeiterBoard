<?php

namespace App\Http\Controllers\Personal;

use App\Enums\EmploymentStatus;
use App\Enums\EmploymentStatusReason;
use App\Enums\EmploymentType;
use App\Enums\TerminationReason;
use App\Http\Controllers\Controller;
use App\Http\Requests\personal\StoreContractRequest;
use App\Http\Requests\personal\UpdateContractRequest;
use App\Models\Group;
use App\Models\personal\Employment;
use App\Models\personal\HourType;
use App\Models\personal\SalaryTable;
use App\Models\personal\SchoolType;
use App\Models\User;
use App\Services\Personal\ContractService;
use App\Services\Personal\PersonalScopeService;
use App\Services\Personal\Zeit\ArbeitszeitService;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Vertragsverwaltung (Anstellungen) innerhalb der Personalakte.
 * Sämtliche Schreibvorgänge laufen über den ContractService.
 */
class ContractController extends Controller
{
    public function __construct(
        private readonly PersonalScopeService $scopeService,
        private readonly ContractService $contracts
    ) {}

    /**
     * Vertragsübersicht eines Mitarbeiters.
     */
    public function index(int $employe)
    {
        $employe = $this->scopeService->visibleEmployees()->findOrFail($employe);
        $this->authorize('viewFor', [Employment::class, $employe]);

        $employments = $employe->employments()
            ->with(['department', 'salaryTable', 'currentTeacherDetail.subjects', 'currentTeacherDetail.schoolType', 'hour_type', 'replacedEmployment.department'])
            ->latest('start')
            ->get();

        $activeContracts  = $employments->filter(fn ($e) => $e->status === EmploymentStatus::Aktiv);
        $pastContracts    = $employments->filter(fn ($e) => $e->status === EmploymentStatus::Beendet);
        $ruhendeContracts = $employments->filter(fn ($e) => $e->status === EmploymentStatus::Ruhend);
        $hasTeacher       = $employments->contains(fn ($e) => $e->employment_type?->requiresTeacherDetail());

        return view('personal.contracts.index', compact(
            'employe', 'activeContracts', 'pastContracts', 'ruhendeContracts', 'hasTeacher'
        ));
    }

    /**
     * Formular: Neue Anstellung anlegen.
     */
    public function create(int $employe)
    {
        $employe = $this->scopeService->visibleEmployees()->findOrFail($employe);
        $this->authorize('createFor', [Employment::class, $employe]);

        return view('personal.contracts.create', $this->formData($employe));
    }

    /**
     * Neue Anstellung speichern.
     */
    public function store(StoreContractRequest $request, int $employe): RedirectResponse
    {
        $employe = $this->scopeService->visibleEmployees()->findOrFail($employe);
        $this->authorize('createFor', [Employment::class, $employe]);

        $result = $this->contracts->create($employe, $request->validated(), $request->user());

        return redirect()->route('personal.contracts.index', $employe->id)
            ->with(['Meldung' => 'Anstellung wurde erfolgreich angelegt.', 'type' => 'success'])
            ->with('vertrags_warnungen', $result['warnings']);
    }

    /**
     * Bearbeitungsformular.
     */
    public function edit(Employment $employment)
    {
        $this->authorize('update', $employment);

        return view('personal.contracts.edit', $this->formData($employment->employe, $employment) + ['employment' => $employment]);
    }

    /**
     * Anstellung aktualisieren.
     */
    public function update(UpdateContractRequest $request, Employment $employment): RedirectResponse
    {
        $this->authorize('update', $employment);

        $result = $this->contracts->update($employment, $request->validated(), $request->user());

        return redirect()->route('personal.contracts.index', $employment->employe_id)
            ->with(['Meldung' => 'Anstellung wurde aktualisiert.', 'type' => 'success'])
            ->with('vertrags_warnungen', $result['warnings']);
    }

    /**
     * Status auf 'ruhend' setzen.
     */
    public function setRuhend(Request $request, Employment $employment): RedirectResponse
    {
        $this->authorize('update', $employment);
        $data = $request->validate(['reason' => ['required', Rule::enum(EmploymentStatusReason::class)]]);

        try {
            $employment->setRuhend(EmploymentStatusReason::from($data['reason']));
        } catch (\LogicException $e) {
            return $this->back($employment, $e->getMessage(), 'danger');
        }

        return $this->back($employment, 'Anstellung wurde auf ruhend gesetzt.', 'warning');
    }

    /**
     * Ruhende Anstellung wieder aktivieren.
     */
    public function setAktiv(Employment $employment): RedirectResponse
    {
        $this->authorize('update', $employment);

        try {
            $employment->setAktiv();
        } catch (\LogicException $e) {
            return $this->back($employment, $e->getMessage(), 'danger');
        }

        return $this->back($employment, 'Anstellung wurde reaktiviert.', 'success');
    }

    /**
     * Status auf 'beendet' setzen.
     */
    public function setBeendet(Request $request, Employment $employment): RedirectResponse
    {
        $this->authorize('update', $employment);
        $data = $request->validate([
            'reason'   => ['required', Rule::enum(TerminationReason::class)],
            'end_date' => ['nullable', 'date', 'after_or_equal:' . $employment->start->toDateString()],
        ]);

        try {
            $employment->setBeendet(
                TerminationReason::from($data['reason']),
                isset($data['end_date']) ? Carbon::parse($data['end_date']) : null
            );
        } catch (\LogicException $e) {
            return $this->back($employment, $e->getMessage(), 'danger');
        }

        return $this->back($employment, 'Anstellung wurde beendet.', 'warning');
    }

    private function back(Employment $employment, string $meldung, string $type): RedirectResponse
    {
        return redirect()->route('personal.contracts.index', $employment->employe_id)
            ->with(['Meldung' => $meldung, 'type' => $type]);
    }

    private function formData(User $employe, ?Employment $employment = null): array
    {
        // Auch inaktive Schularten/Stundenarten anbieten, wenn der Vertrag sie noch verwendet
        $detail      = $employment?->currentTeacherDetail;
        $schoolTypes = SchoolType::where('is_active', true)
            ->when($detail?->school_type_id, fn ($q, $id) => $q->orWhere('id', $id))
            ->orderBy('name')->get();
        $hourTypes   = HourType::orderBy('name')->get();
        $vollzeit    = app(ArbeitszeitService::class)->vollzeitStunden();

        // Weichen die gespeicherten Wochenstunden einer Lehrkraft vom Deputat ab, wurden sie bewusst
        // manuell gesetzt – dann darf das Formular sie beim Speichern nicht still neu berechnen.
        $manual = false;
        if ($employment?->employment_type === EmploymentType::Lehrer && $detail && $employment->hour_type_id) {
            $berechnet = $this->contracts->wochenstundenAusDeputat(
                (int) $detail->school_type_id, (float) $detail->deputat_hours, (int) $employment->hour_type_id
            );
            $manual = $berechnet !== null && abs($berechnet - (float) $employment->hours) > 0.01;
        }

        return [
            'vollzeit'     => $vollzeit,
            'formConfig'   => [
                'type'         => old('employment_type', $employment?->employment_type?->value ?? 'regulaer'),
                'contractType' => old('contract_type', $employment?->contract_type?->value ?? 'unbefristet'),
                'schoolTypeId' => (string) old('school_type_id', $detail?->school_type_id ?? ''),
                'deputat'      => (string) old('deputat_hours', $detail?->deputat_hours ?? ''),
                'hourTypeId'   => (string) old('hour_type_id', $employment?->hour_type_id ?? ''),
                'hours'        => (string) old('hours', $employment?->hours ?? ''),
                'manual'       => (bool) old('hours_manual', $manual),
                // Austrittsdatum (vorgemerkte oder vollzogene Beendigung) bleibt auch bei unbefristeten Verträgen sichtbar
                'hasExitDate'  => $employment !== null
                    && ($employment->termination_reason !== null || $employment->status === EmploymentStatus::Beendet),
                'schools'      => $schoolTypes->pluck('default_deputat', 'id'),
                'hourTypes'    => $hourTypes->pluck('fulltimehours', 'id'),
                'vollzeit'     => $vollzeit,
            ],
            'employe'      => $employe,
            'departments'  => Group::orderBy('name')->get(),
            'hourTypes'    => $hourTypes,
            'schoolTypes'  => $schoolTypes,
            // Abgelaufene Tarifwerke nur, wenn der Vertrag sie noch verwendet (sonst ginge die Zuordnung beim Speichern verloren)
            'salaryTables' => SalaryTable::where(fn ($q) => $q->whereNull('valid_until')->orWhere('valid_until', '>=', now()))
                ->when($employment?->salary_table_id, fn ($q, $id) => $q->orWhere('id', $id))
                ->get(),
            // Für "ersetzt Anstellung": laufende Verträge dieser Person
            'replaceable'  => $employe->employments()
                ->where('status', '!=', EmploymentStatus::Beendet->value)
                ->with('department')
                ->latest('start')
                ->get(),
        ];
    }
}
