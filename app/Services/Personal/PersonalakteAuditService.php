<?php

namespace App\Services\Personal;

use App\Enums\ContractType;
use App\Enums\EmploymentStatus;
use App\Enums\EmploymentStatusReason;
use App\Enums\EmploymentType;
use App\Enums\TerminationReason;
use App\Models\Group;
use App\Models\personal\Address;
use App\Models\personal\ContractAudit;
use App\Models\personal\Consent;
use App\Models\personal\EmployeData;
use App\Models\personal\EmployeeQualification;
use App\Models\personal\EmployeHolidayClaim;
use App\Models\personal\Employment;
use App\Models\personal\HourType;
use App\Models\personal\PersonalAccessLog;
use App\Models\personal\PersonalDocument;
use App\Models\personal\PersonalReminder;
use App\Models\personal\ProcedureLink;
use App\Models\personal\TeacherDetail;
use App\Models\personal\TrainingParticipant;
use App\Models\User;
use Illuminate\Support\Collection;
use OwenIt\Auditing\Models\Audit;

/**
 * Einheitlicher Änderungsverlauf einer Personalakte.
 *
 * Führt die Protokolle aller Teilbereiche (Stammdaten, Verträge, Lehrer-Details, Dokumente,
 * Qualifikationen, Einwilligungen, Fortbildungen, Urlaubsanspruch), die Prozesse, Wiedervorlagen
 * sowie – optional – die Lesezugriffe zu einer Zeitleiste zusammen.
 *
 * Jeder Eintrag: ['at' => Carbon, 'who' => string, 'category' => string, 'event' => string,
 *                 'title' => string, 'changes' => array<int, array{label,old,new}>, 'level' => string]
 */
class PersonalakteAuditService
{
    public const CATEGORIES = [
        'stammdaten' => 'Stammdaten',
        'vertrag'    => 'Verträge',
        'dokument'   => 'Dokumente',
        'qualifikation' => 'Qualifikationen & Fortbildungen',
        'einwilligung'  => 'Einwilligungen',
        'urlaub'     => 'Urlaubsanspruch',
        'prozess'    => 'Prozesse & Wiedervorlagen',
        'zugriff'    => 'Zugriffe (Lesen)',
    ];

    private const EVENTS = ['created' => 'angelegt', 'updated' => 'geändert', 'deleted' => 'gelöscht', 'restored' => 'wiederhergestellt'];

    private const FIELD_LABELS = [
        'familienname' => 'Nachname', 'geburtsname' => 'Geburtsname', 'vorname' => 'Vorname',
        'geburtstag' => 'Geburtsdatum', 'geschlecht' => 'Geschlecht', 'geburtsort' => 'Geburtsort',
        'sozialversicherungsnummer' => 'Sozialversicherungsnummer', 'staatsangehoerigkeit' => 'Staatsangehörigkeit',
        'schwerbehindert' => 'Schwerbehinderung', 'mail_timesheet' => 'Arbeitszeitnachweis per Mail',
        'primary_department_id' => 'Primärer Bereich (Ablage)', 'caldav_working_time' => 'Kalender: Arbeitszeiten',
        'caldav_events' => 'Kalender: Termine', 'google_calendar_link' => 'Google-Kalender-Link',
        'employment_type' => 'Anstellungsart', 'contract_type' => 'Vertragsart', 'status' => 'Status',
        'status_reason' => 'Ruhensgrund', 'termination_reason' => 'Austrittsgrund', 'department_id' => 'Bereich',
        'hour_type_id' => 'Stundenart', 'start' => 'Beginn', 'end' => 'Ende', 'hours' => 'Wochenstunden',
        'workdays' => 'Arbeitstage', 'probation_end' => 'Probezeit bis', 'notice_period' => 'Kündigungsfrist',
        'salary_group' => 'Tarifgruppe', 'salary_level' => 'Vergütungsstufe', 'salary_table_id' => 'Tarifwerk',
        'comment' => 'Bemerkung', 'is_amendment' => 'Änderungsvertrag', 'is_internal_transfer' => 'Interner Wechsel',
        'amendment_description' => 'Beschreibung der Änderung', 'replaced_employment_id' => 'Ersetzt Anstellung',
        'school_type_id' => 'Schulart', 'deputat_hours' => 'Deputat (Std.)', 'reduction_hours' => 'Ermäßigung (Std.)',
        'reduction_reason' => 'Ermäßigungsgrund', 'anrechnungsstunden' => 'Anrechnungsstunden',
        'valid_from' => 'Gültig ab', 'valid_until' => 'Gültig bis',
        'strasse' => 'Straße', 'nr' => 'Hausnummer', 'plz' => 'PLZ', 'ort' => 'Ort', 'land' => 'Land',
        'title' => 'Titel', 'sync_status' => 'Nextcloud-Sync', 'expiry_date' => 'Ablaufdatum',
        'acquired_date' => 'Erworben am', 'notes' => 'Notiz', 'granted_at' => 'Erteilt am', 'revoked_at' => 'Widerrufen am',
    ];

    /** Ja/Nein-Felder (im Protokoll als 0/1 gespeichert). */
    private const BOOLEAN_FIELDS = [
        'is_amendment', 'is_internal_transfer', 'schwerbehindert', 'mail_timesheet', 'caldav_working_time', 'caldav_events',
    ];

    /** Felder, deren Werte nicht offen angezeigt werden. */
    private const MASKED = ['sozialversicherungsnummer', 'salary_group', 'salary_level', 'salary_table_id'];

    /**
     * @param  array<int, string>  $categories  leer = alle (ohne Zugriffe)
     */
    public function timeline(User $employe, array $categories = [], bool $withAccess = false, bool $showSalary = false, int $limit = 400): Collection
    {
        $want = fn (string $cat) => $categories === [] ? $cat !== 'zugriff' : in_array($cat, $categories, true);
        $entries = collect();

        $employmentIds = Employment::withTrashed()->where('employe_id', $employe->id)->pluck('id');

        // ── Änderungsprotokolle (owen-it) ─────────────────────────────────────────
        $sources = [
            'stammdaten'    => [EmployeData::class, EmployeData::where('user_id', $employe->id)->pluck('id'), fn ($a) => 'Stammdaten'],
            'vertrag'       => [Employment::class, $employmentIds, fn ($a) => 'Anstellung ' . $this->employmentName($a->auditable_id)],
            'dokument'      => [PersonalDocument::class, PersonalDocument::withTrashed()->where('employe_id', $employe->id)->pluck('id'), fn ($a) => 'Dokument'],
            'qualifikation' => [EmployeeQualification::class, EmployeeQualification::where('employe_id', $employe->id)->pluck('id'), fn ($a) => 'Qualifikation'],
            'einwilligung'  => [Consent::class, Consent::where('employe_id', $employe->id)->pluck('id'), fn ($a) => 'Einwilligung'],
        ];
        foreach ($sources as $category => [$class, $ids, $titleFn]) {
            if (!$want($category) || $ids->isEmpty()) {
                continue;
            }
            $this->fromAudits($entries, $class, $ids, $category, $titleFn, $showSalary);
        }

        if ($want('stammdaten')) {
            $addressIds = Address::withTrashed()->where('employe_id', $employe->id)->pluck('id');
            if ($addressIds->isNotEmpty()) {
                $this->fromAudits($entries, Address::class, $addressIds, 'stammdaten', fn ($a) => 'Anschrift', $showSalary);
            }
        }

        if ($want('vertrag') && $employmentIds->isNotEmpty()) {
            $details = TeacherDetail::whereIn('employment_id', $employmentIds)->pluck('id');
            if ($details->isNotEmpty()) {
                $this->fromAudits($entries, TeacherDetail::class, $details, 'vertrag', fn ($a) => 'Lehrer-Details', $showSalary);
            }
            $this->retroactive($entries, $employe);
        }

        if ($want('qualifikation')) {
            $ids = TrainingParticipant::where('employe_id', $employe->id)->pluck('id');
            if ($ids->isNotEmpty()) {
                $this->fromAudits($entries, TrainingParticipant::class, $ids, 'qualifikation', fn ($a) => 'Fortbildung', $showSalary);
            }
        }

        if ($want('urlaub')) {
            EmployeHolidayClaim::where('employe_id', $employe->id)->get()->each(function ($claim) use ($entries) {
                $entries->push($this->entry(
                    $claim->created_at, $this->userName($claim->changedBy), 'urlaub', 'geändert',
                    'Urlaubsanspruch festgelegt',
                    [['label' => 'Tage pro Jahr', 'old' => null, 'new' => (string) $claim->holiday_claim],
                     ['label' => 'Gültig ab', 'old' => null, 'new' => $claim->date_start?->format('d.m.Y')]]
                ));
            });
        }

        if ($want('prozess')) {
            $this->processes($entries, $employe);
        }

        if ($withAccess && $want('zugriff')) {
            $this->accessLogs($entries, $employe);
        }

        return $entries->sortByDesc(fn ($e) => $e['at']->timestamp)->take($limit)->values();
    }

    // ── Quellen ───────────────────────────────────────────────────────────────

    private function fromAudits(Collection $entries, string $class, Collection $ids, string $category, callable $titleFn, bool $showSalary): void
    {
        Audit::where('auditable_type', (new $class)->getMorphClass())
            ->whereIn('auditable_id', $ids)
            ->with('user:id,name')
            ->orderByDesc('created_at')
            ->limit(500)
            ->get()
            ->each(function (Audit $audit) use ($entries, $category, $titleFn, $showSalary) {
                $changes = $this->changes($audit, $showSalary);
                // Reine Sync-/Technikänderungen ohne fachlichen Inhalt ausblenden
                if ($audit->event === 'updated' && $changes === []) {
                    return;
                }
                $entries->push($this->entry(
                    $audit->created_at, $audit->user?->name ?? 'System', $category,
                    self::EVENTS[$audit->event] ?? $audit->event, $titleFn($audit), $changes
                ));
            });
    }

    /** Rückwirkende Vertragsänderungen (betreffen bereits abgeschlossene Monate). */
    private function retroactive(Collection $entries, User $employe): void
    {
        ContractAudit::where('employe_id', $employe->id)->where('is_retroactive', true)->with('changedBy:id,name')->get()
            ->each(function (ContractAudit $a) use ($entries) {
                $entries->push($this->entry(
                    $a->created_at, $a->changedBy?->name ?? 'System', 'vertrag', 'Hinweis',
                    'Rückwirkende Vertragsänderung – betrifft bereits abgeschlossene Zeiträume',
                    [['label' => 'Betroffener Zeitraum',
                      'old' => null,
                      'new' => $a->affected_period_start?->format('d.m.Y') . ' – ' . $a->affected_period_end?->format('d.m.Y')]],
                    'warning'
                ));
            });
    }

    private function processes(Collection $entries, User $employe): void
    {
        ProcedureLink::where('employe_id', $employe->id)->with('procedure:id,name')->get()->each(function ($l) use ($entries) {
            $entries->push($this->entry($l->created_at, 'System', 'prozess', 'gestartet',
                ($l->type?->label() ?? 'Prozess') . ': ' . ($l->procedure?->name ?? '#' . $l->procedure_id), []));
            if ($l->completed_at) {
                $entries->push($this->entry($l->completed_at, 'System', 'prozess', 'abgeschlossen',
                    ($l->type?->label() ?? 'Prozess') . ' abgeschlossen', []));
            }
        });

        PersonalReminder::where('employe_id', $employe->id)->get()->each(function ($r) use ($entries) {
            $entries->push($this->entry($r->created_at, 'System', 'prozess', 'angelegt',
                'Wiedervorlage: ' . $r->label() . ' am ' . $r->due_date->format('d.m.Y'), []));
            if ($r->notified_at) {
                $entries->push($this->entry($r->notified_at, 'System', 'prozess', 'gemeldet',
                    'Wiedervorlage gemeldet: ' . $r->label(), []));
            }
            if ($r->done_at) {
                $entries->push($this->entry($r->done_at, 'System', 'prozess', 'erledigt',
                    'Wiedervorlage erledigt: ' . $r->label(), []));
            }
        });
    }

    private function accessLogs(Collection $entries, User $employe): void
    {
        PersonalAccessLog::where('resource_type', User::class)->where('resource_id', $employe->id)
            ->with('user:id,name')->orderByDesc('created_at')->limit(300)->get()
            ->each(function ($log) use ($entries) {
                $entries->push($this->entry($log->created_at, $log->user?->name ?? 'Unbekannt', 'zugriff', 'angesehen',
                    'Seite aufgerufen: ' . ($log->route ?? '–'), [], 'muted'));
            });
    }

    // ── Aufbereitung ──────────────────────────────────────────────────────────

    private function entry($at, string $who, string $category, string $event, string $title, array $changes, string $level = 'normal'): array
    {
        return [
            'at' => $at, 'who' => $who, 'category' => $category, 'category_label' => self::CATEGORIES[$category] ?? $category,
            'event' => $event, 'title' => $title, 'changes' => $changes, 'level' => $level,
        ];
    }

    /** @return array<int, array{label: string, old: ?string, new: ?string}> */
    private function changes(Audit $audit, bool $showSalary): array
    {
        $old = $audit->old_values ?? [];
        $new = $audit->new_values ?? [];
        $keys = array_unique(array_merge(array_keys($old), array_keys($new)));
        $out = [];

        foreach ($keys as $key) {
            if (in_array($key, ['id', 'created_at', 'updated_at', 'deleted_at', 'employe_id', 'user_id', 'employment_id', 'media_id'], true)) {
                continue;
            }
            if (in_array($key, self::MASKED, true) && !$showSalary && $key !== 'sozialversicherungsnummer') {
                $out[] = ['label' => self::FIELD_LABELS[$key] ?? $key, 'old' => '•••', 'new' => '•••'];
                continue;
            }
            $out[] = [
                'label' => self::FIELD_LABELS[$key] ?? $key,
                'old'   => array_key_exists($key, $old) ? $this->format($key, $old[$key]) : null,
                'new'   => array_key_exists($key, $new) ? $this->format($key, $new[$key]) : null,
            ];
        }

        return $out;
    }

    private function format(string $key, mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return '–';
        }
        if (is_array($value) || (is_string($value) && str_starts_with($value, '['))) {
            $arr = is_array($value) ? $value : json_decode($value, true);
            if ($key === 'workdays' && is_array($arr)) {
                return collect($arr)->map(fn ($d) => ['', 'Mo', 'Di', 'Mi', 'Do', 'Fr', 'Sa', 'So'][(int) $d] ?? $d)->implode(', ');
            }
            if (is_array($value)) {
                return json_encode($value, JSON_UNESCAPED_UNICODE);
            }
        }
        if (is_bool($value) || (in_array($value, [0, 1, '0', '1'], true) && in_array($key, self::BOOLEAN_FIELDS, true))) {
            return $value ? 'ja' : 'nein';
        }

        return match ($key) {
            'employment_type'    => EmploymentType::tryFrom((string) $value)?->label() ?? (string) $value,
            'contract_type'      => ContractType::tryFrom((string) $value)?->label() ?? (string) $value,
            'status'             => EmploymentStatus::tryFrom((string) $value)?->label() ?? (string) $value,
            'status_reason'      => EmploymentStatusReason::tryFrom((string) $value)?->label() ?? (string) $value,
            'termination_reason' => TerminationReason::tryFrom((string) $value)?->label() ?? (string) $value,
            'department_id', 'primary_department_id' => Group::find($value)?->name ?? "#{$value}",
            'hour_type_id'       => HourType::find($value)?->name ?? "#{$value}",
            'start', 'end', 'probation_end', 'geburtstag', 'valid_from', 'valid_until', 'expiry_date', 'acquired_date'
                => $this->date($value),
            default              => (string) $value,
        };
    }

    private function date(mixed $value): string
    {
        try {
            return \Carbon\Carbon::parse($value)->format('d.m.Y');
        } catch (\Throwable) {
            return (string) $value;
        }
    }

    private function userName(?int $id): string
    {
        return $id ? (User::find($id)?->name ?? "#{$id}") : 'System';
    }

    private array $employmentNames = [];

    private function employmentName(int $id): string
    {
        return $this->employmentNames[$id] ??= (function () use ($id) {
            $e = Employment::withTrashed()->with('department')->find($id);

            return $e ? trim(($e->department?->name ?? 'ohne Bereich') . ' (ab ' . $e->start?->format('d.m.Y') . ')') : "#{$id}";
        })();
    }
}
