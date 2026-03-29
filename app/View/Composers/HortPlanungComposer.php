<?php

namespace App\View\Composers;

use App\Models\personal\HortPlanung;
use App\Services\HortPlanungService;
use Carbon\Carbon;
use Illuminate\View\View;

/**
 * View Composer für die Hortstunden-Planungs-Dashboard-Card.
 *
 * Liefert:
 *   $hortPlanung          – aktive Planung der Abteilung des Users (oder null)
 *   $hortAktuellerMonat   – Berechnungsergebnis für den aktuellen Monat
 *   $hortAbwesenheiten    – Langzeitabwesenheiten (Warnungen)
 *   $hortBudgetRest       – Budget-Rest des aktuellen Monats (float|null)
 */
class HortPlanungComposer
{
    public function __construct(protected HortPlanungService $service) {}

    public function compose(View $view): void
    {
        $user = auth()->user();

        if (!$user || !$user->can('view hort planung')) {
            $view->with([
                'hortPlanung'        => null,
                'hortAktuellerMonat' => null,
                'hortAbwesenheiten'  => collect(),
                'hortBudgetRest'     => null,
            ]);
            return;
        }

        // Aktive Planung der Abteilungen des Users
        $abteilungIds = $user->groups()->pluck('id');

        $planung = HortPlanung::whereIn('department_id', $abteilungIds)
            ->where('aktiv', true)
            ->with([
                'faktoren.werte',
                'zusatzstundenTypen',
                'monate' => fn($q) => $q->where(
                    'monat',
                    '<=',
                    now()->format('Y-m-01')
                )->orderByDesc('monat')->limit(1),
                'monate.personen',
                'monate.monatZusatzstunden.typ',
                'department',
            ])
            ->first();

        if (!$planung) {
            $view->with([
                'hortPlanung'        => null,
                'hortAktuellerMonat' => null,
                'hortAbwesenheiten'  => collect(),
                'hortBudgetRest'     => null,
            ]);
            return;
        }

        $aktuellerMonat = $planung->monate->first();
        $berechnungen   = null;
        $budgetRest     = null;

        if ($aktuellerMonat) {
            try {
                $berechnungen = $this->service->berechneMonat($aktuellerMonat);
                $budgetRest   = $berechnungen['budget_rest_sp1'] ?? null;
            } catch (\Throwable) {
                // Stille Fehlerbehandlung – Dashboard-Card darf nicht abstürzen
            }
        }

        // Abwesenheits-Warnungen
        try {
            $abwesenheiten = $this->service->abwesenheitenImZeitraum($planung);
        } catch (\Throwable) {
            $abwesenheiten = collect();
        }

        $view->with([
            'hortPlanung'        => $planung,
            'hortAktuellerMonat' => $berechnungen,
            'hortAbwesenheiten'  => $abwesenheiten,
            'hortBudgetRest'     => $budgetRest,
        ]);
    }
}

