<?php

namespace App\Http\Controllers\Personal;

use App\Enums\EmploymentStatus;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\Personal\PersonalScopeService;
use Carbon\Carbon;
use App\Models\personal\PersonalReminder;
use App\Models\personal\ProcedureLink;
use App\Services\Personal\PersonalakteAuditService;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\View\View;

class PersonalakteController extends Controller
{
    /** Vorlauf (Tage), ab dem Vertragsende bzw. Probezeitende als Hinweis erscheinen. */
    private const HINWEIS_VERTRAGSENDE_TAGE = 90;
    private const HINWEIS_PROBEZEIT_TAGE = 30;

    public function __construct(
        private readonly PersonalScopeService $scopeService
    ) {}

    /**
     * GET /personal/mitarbeiter/{employe}
     * Personalakte-Übersicht (Hub) für einen Mitarbeiter.
     * IDOR-Schutz: Immer visibleEmployees() nutzen.
     */
    public function show(int $employe): View
    {
        /** @var User $employe */
        $employe = $this->scopeService->visibleEmployees()->findOrFail($employe);

        $employe->load('employe_data');

        // Alle nicht beendeten Anstellungen (aktiv + ruhend), inkl. zukünftiger Verträge
        $employments = $employe->employments()
            ->where('status', '!=', EmploymentStatus::Beendet->value)
            ->with(['department', 'currentTeacherDetail'])
            ->orderBy('start')
            ->get();

        $laufend = $employments->filter(
            fn ($e) => $e->status === EmploymentStatus::Aktiv
                && $e->start->lessThanOrEqualTo(today())
                && ($e->end === null || $e->end->greaterThanOrEqualTo(today()))
        );

        return view('personal.personalakte.show', [
            'employe'     => $employe,
            'employments' => $employments,
            'laufend'     => $laufend,
            'percent'     => round($laufend->sum('percent'), 1),
            'hours'       => round($laufend->sum('hours'), 2),
            'hinweise'    => $this->hinweise($employments, $laufend),
            'firstStart'  => $employe->employments()->min('start'),
            'links'       => ProcedureLink::where('employe_id', $employe->id)->with('procedure:id,name')->latest()->get(),
            'reminders'   => PersonalReminder::where('employe_id', $employe->id)->open()->orderBy('due_date')->get(),
        ]);
    }

    /**
     * GET /personal/mitarbeiter/{employe}/verlauf
     * Änderungsverlauf (Audit) der Personalakte.
     */
    public function verlauf(Request $request, int $employe, PersonalakteAuditService $audit): View
    {
        /** @var User $employe */
        $employe = $this->scopeService->visibleEmployees()->findOrFail($employe);

        $categories = array_values(array_intersect(
            (array) $request->query('kategorie', []),
            array_keys(PersonalakteAuditService::CATEGORIES)
        ));
        $withAccess = $request->boolean('zugriffe');

        $entries = $audit->timeline($employe, $categories, $withAccess, $request->user()->can('view salary'));

        return view('personal.personalakte.verlauf', [
            'employe'    => $employe,
            'entries'    => $entries,
            'categories' => PersonalakteAuditService::CATEGORIES,
            'selected'   => $categories,
            'withAccess' => $withAccess,
        ]);
    }

    /**
     * Handlungsbedarf zur Personalakte: auslaufende Verträge, Probezeiten, ruhende Verträge …
     *
     * @return Collection<int, array{type: string, text: string}>
     */
    private function hinweise(Collection $employments, Collection $laufend): Collection
    {
        $hinweise = collect();
        $heute = Carbon::today();

        if ($laufend->isEmpty()) {
            $kuenftig = $employments->filter(fn ($e) => $e->start->greaterThan($heute))->sortBy('start')->first();
            $hinweise->push($kuenftig
                ? ['type' => 'info', 'text' => "Die Anstellung beginnt am {$kuenftig->start->format('d.m.Y')}."]
                : ['type' => 'warning', 'text' => 'Es besteht derzeit keine laufende Anstellung.']);
        }

        foreach ($employments as $e) {
            $bereich = $e->department?->name ?? 'Anstellung';

            if ($e->end && $e->end->lessThan($heute)) {
                $hinweise->push(['type' => 'danger', 'text' => "{$bereich}: Enddatum {$e->end->format('d.m.Y')} überschritten – Vertrag noch nicht beendet (wird nachts automatisch abgeschlossen)."]);
            } elseif ($e->end && $e->status === EmploymentStatus::Aktiv
                && $e->end->lessThanOrEqualTo($heute->copy()->addDays(self::HINWEIS_VERTRAGSENDE_TAGE))) {
                $tage = $heute->diffInDays($e->end);
                $hinweise->push(['type' => 'warning', 'text' => "{$bereich}: Vertrag endet am {$e->end->format('d.m.Y')} (in {$tage} Tagen) – Verlängerung oder Beendigung klären."]);
            }

            if ($e->probation_end && $e->probation_end->between($heute, $heute->copy()->addDays(self::HINWEIS_PROBEZEIT_TAGE))) {
                $hinweise->push(['type' => 'info', 'text' => "{$bereich}: Probezeit endet am {$e->probation_end->format('d.m.Y')}."]);
            }

            if ($e->status === EmploymentStatus::Ruhend) {
                $grund = $e->status_reason?->label() ?? 'ohne Angabe';
                $hinweise->push(['type' => 'info', 'text' => "{$bereich}: Anstellung ruht ({$grund})."]);
            }
        }

        return $hinweise;
    }
}
