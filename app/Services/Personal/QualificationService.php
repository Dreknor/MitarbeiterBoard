<?php

namespace App\Services\Personal;

use App\Enums\QualificationStatus;
use App\Models\personal\EmployeeQualification;
use App\Models\personal\QualificationType;
use App\Models\personal\Training;
use App\Models\personal\TrainingParticipant;
use App\Models\User;
use App\Notifications\Personal\QualificationExpiringNotification;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Notification;

class QualificationService
{
    /**
     * Fehlende Pflichtqualifikationen für einen MA ermitteln.
     * WICHTIG: Prüft alle aktiven Employment-Typen (Multi-Anstellung!).
     */
    public function getMissingRequired(User $employe): Collection
    {
        $activeTypes = $employe->employments()
            ->where('status', 'aktiv')
            ->pluck('employment_type')
            ->filter()
            ->map(fn ($t) => is_string($t) ? $t : $t->value)
            ->unique()
            ->toArray();

        $required = QualificationType::where('category', 'pflicht')
            ->where('is_active', true)
            ->get()
            ->filter(function ($qt) use ($activeTypes) {
                if ($qt->applies_to === null) return true;
                return ! empty(array_intersect($qt->applies_to, $activeTypes));
            });

        return $required->filter(function ($qt) use ($employe) {
            return ! $employe->qualifications()
                ->where('qualification_type_id', $qt->id)
                ->where('status', '!=', QualificationStatus::Fehlend->value)
                ->exists();
        })->values();
    }

    /**
     * Status aller Qualifikationen für einen MA berechnen und speichern.
     */
    public function getQualificationStatus(User $employe): Collection
    {
        return $employe->qualifications()
            ->with('qualificationType')
            ->get()
            ->map(function ($qual) {
                $status = $this->calculateStatus($qual);
                if ($qual->status !== $status) {
                    $qual->status = $status;
                    $qual->saveQuietly();
                }
                return $qual;
            });
    }

    /**
     * Status einer einzelnen Qualifikation berechnen.
     */
    public function calculateStatus(EmployeeQualification $qual): QualificationStatus
    {
        if (! $qual->expiry_date) {
            return $qual->acquired_date ? QualificationStatus::Gueltig : QualificationStatus::Fehlend;
        }

        $daysLeft = now()->diffInDays($qual->expiry_date, false);

        if ($daysLeft < 0) return QualificationStatus::Abgelaufen;

        $reminderDays = $qual->qualificationType?->reminder_days ?? 90;
        if ($daysLeft <= $reminderDays) return QualificationStatus::Ablaufend;

        return QualificationStatus::Gueltig;
    }

    /**
     * Ablaufende Qualifikationen prüfen und Erinnerungen senden (Scheduler).
     */
    public function checkExpiringQualifications(): void
    {
        // Status aller ablaufenden Qualifikationen aktualisieren
        EmployeeQualification::whereNotNull('expiry_date')
            ->whereDate('expiry_date', '>', now())
            ->with('qualificationType')
            ->get()
            ->each(function ($qual) {
                $newStatus = $this->calculateStatus($qual);
                if ($qual->status !== $newStatus) {
                    $qual->status = $newStatus;
                    $qual->saveQuietly();
                }
            });

        // Erinnerungen senden für ablaufende Qualifikationen
        $expiring = EmployeeQualification::where('status', QualificationStatus::Ablaufend->value)
            ->with(['employe', 'qualificationType'])
            ->get();

        foreach ($expiring as $qual) {
            $recipients = collect([$qual->employe])
                ->merge(User::permission('manage qualifications')->get());

            Notification::send($recipients->unique('id'), new QualificationExpiringNotification($qual));
        }
    }

    /**
     * Qualifikationsmatrix: Alle MA × alle Pflichtqualifikationen.
     * Caching für 5 Minuten.
     */
    public function getQualificationMatrix(User $viewer): array
    {
        return Cache::remember('qualification_matrix_' . $viewer->id, 300, function () use ($viewer) {
            $employees = app(PersonalScopeService::class)->visibleEmployees($viewer)
                ->with(['qualifications.qualificationType'])
                ->get();

            $types = QualificationType::where('category', 'pflicht')
                ->where('is_active', true)
                ->orderBy('name')
                ->get();

            return compact('employees', 'types');
        });
    }

    /**
     * Qualifikations-Cache invalidieren (nach Änderungen aufrufen).
     */
    public function invalidateMatrixCache(User $viewer): void
    {
        Cache::forget('qualification_matrix_' . $viewer->id);
    }

    /**
     * Qualifikation nach Fortbildungs-Teilnahme automatisch erneuern.
     */
    public function renewFromTraining(TrainingParticipant $participant): void
    {
        $training = $participant->training;
        if (! $training->qualification_type_id) return;

        $expiryDate = null;
        if ($type = $training->qualificationType) {
            $expiryDate = $type->validity_months
                ? $training->end_date->addMonths($type->validity_months)
                : null;
        }

        EmployeeQualification::updateOrCreate(
            [
                'employe_id'            => $participant->employe_id,
                'qualification_type_id' => $training->qualification_type_id,
            ],
            [
                'acquired_date' => $training->end_date,
                'expiry_date'   => $expiryDate,
                'status'        => QualificationStatus::Gueltig,
                'notes'         => "Erworben durch Teilnahme an: {$training->title}",
            ]
        );
    }
}

