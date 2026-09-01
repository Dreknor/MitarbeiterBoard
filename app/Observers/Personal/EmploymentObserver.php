<?php

namespace App\Observers\Personal;

use App\Models\personal\ContractAudit;
use App\Models\personal\Employment;
use App\Models\personal\Timesheet;
use App\Services\Personal\PersonalScopeService;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class EmploymentObserver
{
    /**
     * Felder, deren Änderung Soll-Zeiten/Vertragskonditionen betreffen und daher
     * die Prüfengine (CONTRACT_CHANGE_IN_PERIOD / RETROACTIVE_CONTRACT_CHANGE) auslösen.
     */
    private const RELEVANT_FIELDS = ['start', 'end', 'hours', 'employment_type', 'contract_type', 'status'];

    public function created(Employment $employment): void
    {
        // Scope-Cache invalidieren nach neuer Anstellung
        app(PersonalScopeService::class)->invalidateCache($employment->employe);

        $audit = $this->writeAudit($employment, 'created', null);
        $this->handleRetroactivity($employment, $audit, $employment->start, $employment->end);
    }

    public function updated(Employment $employment): void
    {
        // Scope-Cache invalidieren bei Änderungen (z.B. department_id)
        app(PersonalScopeService::class)->invalidateCache($employment->employe);

        $changedFields = $this->buildChangedFields($employment);

        // Kein Audit-Eintrag falls nur irrelevante Felder (z.B. comment) geändert wurden
        if (empty($changedFields)) {
            return;
        }

        $audit = $this->writeAudit($employment, 'updated', $changedFields);

        $oldStart = $employment->getOriginal('start');
        $oldEnd   = $employment->getOriginal('end');
        $oldStart = $oldStart ? Carbon::parse($oldStart) : $employment->start;
        $oldEnd   = $oldEnd ? Carbon::parse($oldEnd) : $employment->end;

        $rangeStart = $oldStart->lessThan($employment->start) ? $oldStart : $employment->start;
        $rangeEnd   = ($employment->end === null || ($oldEnd !== null && $oldEnd->greaterThan($employment->end)))
            ? ($oldEnd ?? Carbon::now())
            : $employment->end;

        $this->handleRetroactivity($employment, $audit, $rangeStart, $rangeEnd);
    }

    public function deleted(Employment $employment): void
    {
        app(PersonalScopeService::class)->invalidateCache($employment->employe);
    }

    /**
     * Schreibt einen Snapshot der Vertragskonditionen in `contract_audits`.
     */
    private function writeAudit(Employment $employment, string $action, ?array $changedFields): ContractAudit
    {
        return ContractAudit::create([
            'employment_id'    => $employment->id,
            'employe_id'       => $employment->employe_id,
            'action'           => $action,
            'valid_from'       => $employment->start,
            'valid_to'         => $employment->end,
            'hours'            => $employment->hours,
            'employment_type'  => $employment->employment_type?->value,
            'contract_type'    => $employment->contract_type?->value,
            'status'           => $employment->status?->value,
            'changed_fields'   => $changedFields,
            'changed_by'       => auth()->id(),
        ]);
    }

    /**
     * Baut eine ['feld' => ['old' => ..., 'new' => ...]]-Struktur für alle geänderten,
     * für die Prüfengine relevanten Felder.
     */
    private function buildChangedFields(Employment $employment): array
    {
        $changed = [];
        foreach (self::RELEVANT_FIELDS as $field) {
            if ($employment->isDirty($field)) {
                $changed[$field] = [
                    'old' => $employment->getOriginal($field),
                    'new' => $employment->getAttribute($field) instanceof \BackedEnum
                        ? $employment->getAttribute($field)->value
                        : $employment->getAttribute($field),
                ];
            }
        }

        return $changed;
    }

    /**
     * Arbeitspaket 3.2: Prüft, ob die Änderung einen bereits abgezeichneten
     * (gesperrten) Monatsabschluss betrifft. Falls ja, wird der Audit-Eintrag als
     * rückwirkend markiert und die betroffenen Timesheets zur erneuten Prüfung markiert.
     */
    private function handleRetroactivity(Employment $employment, ContractAudit $audit, Carbon $rangeStart, ?Carbon $rangeEnd): void
    {
        $rangeEnd ??= Carbon::now();

        $affectedTimesheets = Timesheet::where('employe_id', $employment->employe_id)
            ->whereNotNull('locked_at')
            ->get()
            ->filter(function (Timesheet $timesheet) use ($rangeStart, $rangeEnd) {
                $monthStart = Carbon::create($timesheet->year, $timesheet->month, 1)->startOfMonth();
                $monthEnd   = $monthStart->copy()->endOfMonth();
                return $monthStart->lessThanOrEqualTo($rangeEnd) && $monthEnd->greaterThanOrEqualTo($rangeStart);
            });

        if ($affectedTimesheets->isEmpty()) {
            return;
        }

        $periodStart = $affectedTimesheets->min(fn (Timesheet $t) => Carbon::create($t->year, $t->month, 1)->startOfMonth());
        $periodEnd   = $affectedTimesheets->max(fn (Timesheet $t) => Carbon::create($t->year, $t->month, 1)->endOfMonth());

        $audit->update([
            'is_retroactive'         => true,
            'affected_period_start'  => $periodStart,
            'affected_period_end'    => $periodEnd,
        ]);

        foreach ($affectedTimesheets as $timesheet) {
            $timesheet->markRequiresReview(sprintf(
                'Rückwirkende Vertragsänderung zum %s (Audit #%d).',
                $employment->start->format('d.m.Y'),
                $audit->id
            ));
        }

        Log::warning('Personal: Rückwirkende Vertragsänderung erkannt', [
            'employment_id' => $employment->id,
            'employe_id'    => $employment->employe_id,
            'audit_id'      => $audit->id,
            'affected_timesheets' => $affectedTimesheets->pluck('id'),
        ]);
    }
}


