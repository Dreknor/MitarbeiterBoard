<?php

namespace App\Services\UserDeletion;

use App\Models\User;
use App\Models\personal\Consent;
use App\Models\personal\Employment;
use App\Models\personal\EmployeeQualification;
use App\Models\personal\PersonalDocument;
use App\Models\personal\Timesheet;
use App\Models\personal\TimesheetDays;
use App\Models\personal\TrainingParticipant;
use Illuminate\Database\Query\Builder;

/**
 * Zentrale Liste aller Spalten, die auf users verweisen, mit ihrer Löschregel.
 *
 * Jede neue Spalte mit Bezug auf users muss hier eingetragen werden.
 * Tests\Feature\UserDeletion\UserForeignKeyCoverageTest schlägt sonst fehl.
 *
 * Stufen: 1 = Deaktivieren, 2 = Anonymisieren, 3 = endgültig Löschen (retain-Regeln).
 */
class RuleRegistry
{
    /** @var Rule[]|null */
    private ?array $rules = null;

    /**
     * Spalten ohne Fremdschlüssel bzw. mit polymorphem Verweis, die zusätzlich abgedeckt sind.
     */
    public const EXTRA_COLUMNS = [
        'procedure_step_history.performed_by',
        'survey_answers.user_id',
        'paed_diary_categories.user_id',
        'listen.user_id',
        'tasks.taskable_id',
        'notifications.notifiable_id',
        'push_subscriptions.subscribable_id',
        'model_has_roles.model_id',
        'model_has_permissions.model_id',
        'audits.user_id',
        'pers_access_logs.resource_id',
    ];

    /**
     * @return Rule[]
     */
    public function all(): array
    {
        return $this->rules ??= $this->build();
    }

    /**
     * @return Rule[]
     */
    public function forStage(int $stage): array
    {
        return array_values(array_filter($this->all(), fn (Rule $rule) => $rule->stage === $stage));
    }

    /**
     * @return Rule[]
     */
    public function retained(): array
    {
        return array_values(array_filter($this->all(), fn (Rule $rule) => $rule->action === Rule::RETAIN));
    }

    /**
     * Alle abgedeckten Spalten im Format "tabelle.spalte".
     */
    public function coveredColumns(): array
    {
        return array_values(array_unique(array_map(fn (Rule $rule) => $rule->table.'.'.$rule->column, $this->all())));
    }

    /**
     * @return Rule[]
     */
    private function build(): array
    {
        $userType = (new User())->getMorphClass();
        $morph = fn (string $typeColumn) => fn (Builder $q) => $q->where($typeColumn, $userType);
        $today = fn () => now()->toDateString();

        return [
            // ── Konto und Einstellungen ───────────────────────────────────────
            Rule::delete('Konto', 'Persönliche Aufgaben', 'tasks', 'taskable_id', 1, $morph('taskable_type')),
            Rule::delete('Konto', 'Push-Abonnements', 'push_subscriptions', 'subscribable_id', 1, $morph('subscribable_type')),
            Rule::delete('Konto', 'Benachrichtigungen', 'notifications', 'notifiable_id', 2, $morph('notifiable_type')),
            Rule::delete('Konto', 'Rollen', 'model_has_roles', 'model_id', 2, $morph('model_type')),
            Rule::delete('Konto', 'Einzelrechte', 'model_has_permissions', 'model_id', 2, $morph('model_type')),
            Rule::delete('Konto', 'Benachrichtigungs-Abos', 'subscriptions', 'users_id'),
            Rule::delete('Konto', 'Gruppenaufgaben', 'group_task_users', 'users_id'),
            Rule::delete('Konto', 'Kalender-Feeds', 'user_ical_feeds', 'user_id'),
            Rule::delete('Konto', 'Kalenderfarben', 'user_calendar_colors', 'user_id'),
            Rule::delete('Konto', 'Quicklinks', 'user_quicklinks', 'user_id'),
            Rule::delete('Konto', 'Dashboard-Karten', 'dashboard_card_user', 'user_id'),
            Rule::delete('Konto', 'Angepinnte Tickets', 'tickets_pinned', 'user_id'),
            Rule::delete('Konto', 'Ausgeblendete Notizkategorien', 'paed_diary_user_hidden_categories', 'user_id'),
            Rule::nullify('Konto', 'Vorgesetzte(r) anderer Personen', 'users', 'superior_id', 1),
            Rule::nullify('Konto', 'Stellvertretung anderer Personen', 'users', 'deputy_id', 1),

            // ── Gruppen, Organisation, Klassen ────────────────────────────────
            Rule::delete('Organisation', 'Gruppenmitgliedschaften', 'group_user', 'user_id'),
            Rule::delete('Organisation', 'Positionen', 'position_user', 'user_id'),
            Rule::delete('Organisation', 'Organigramm-Positionen', 'pers_org_position_user', 'user_id'),
            Rule::delete('Organisation', 'Klassenzuordnungen', 'klasse_user', 'user_id'),
            Rule::delete('Organisation', 'Eigene Klassengruppierungen', 'paed_diary_class_groups', 'user_id'),
            Rule::anonymize('Organisation', 'Ersteller von Gruppen', 'groups', 'creator_id'),
            Rule::anonymize('Organisation', 'Verantwortung Inventarstandorte', 'inv_locations', 'verantwortlicher_id'),

            // ── Kommunikation, Sitzungen, Inhalte ─────────────────────────────
            Rule::anonymize('Inhalte', 'Beiträge', 'posts', 'author_id'),
            Rule::anonymize('Inhalte', 'Themen (Ersteller)', 'themes', 'creator_id'),
            Rule::anonymize('Inhalte', 'Themen (zugewiesen)', 'themes', 'assigned_to'),
            Rule::delete('Inhalte', 'Persönliche Priorisierungen', 'priorities', 'creator_id'),
            Rule::anonymize('Inhalte', 'Protokolle', 'protocols', 'creator_id'),
            Rule::anonymize('Inhalte', 'Wiederkehrende Themen', 'recurring_themes', 'creator_id'),
            Rule::anonymize('Inhalte', 'Sitzungen (Einladung versendet)', 'meetings', 'invitation_sent_by'),
            Rule::anonymize('Inhalte', 'Sitzungen (abgesagt)', 'meetings', 'cancelled_by'),
            Rule::anonymize('Inhalte', 'Sitzungsaufgaben', 'meeting_tasks', 'user_id'),
            Rule::anonymize('Inhalte', 'Anwesenheiten in Gremien', 'presences', 'user_id'),
            Rule::anonymize('Inhalte', 'Anwesenheiten (erfasst von)', 'presences', 'created_by'),
            Rule::delete('Inhalte', 'Freigabelinks', 'shares', 'creators_id'),
            Rule::anonymize('Inhalte', 'Wiki-Seiten', 'wiki_sites', 'author_id'),
            Rule::anonymize('Inhalte', 'Umfragen', 'surveys', 'created_by'),
            Rule::anonymize('Inhalte', 'Umfrage-Teilnahmen', 'survey_user_answers', 'user_id'),
            Rule::anonymize('Inhalte', 'Umfrage-Antworten', 'survey_answers', 'user_id'),
            Rule::anonymize('Inhalte', 'Terminlisten', 'listen', 'user_id'),
            Rule::nullify('Inhalte', 'Künftige Terminreservierungen', 'listen_termine', 'reserviert_fuer', 1,
                fn (Builder $q) => $q->where('termin', '>=', now())),
            Rule::anonymize('Inhalte', 'Vergangene Terminreservierungen', 'listen_termine', 'reserviert_fuer'),

            // ── Tickets und Prozesse ──────────────────────────────────────────
            Rule::anonymize('Tickets und Prozesse', 'Tickets (Ersteller)', 'tickets', 'user_id'),
            Rule::anonymize('Tickets und Prozesse', 'Tickets (zugewiesen)', 'tickets', 'assigned_to'),
            Rule::anonymize('Tickets und Prozesse', 'Ticket-Kommentare', 'ticket_comments', 'user_id'),
            Rule::anonymize('Tickets und Prozesse', 'Prozesse', 'procedures', 'author_id'),
            Rule::anonymize('Tickets und Prozesse', 'Prozessvorlagen', 'procedure_templates', 'author_id'),
            Rule::delete('Tickets und Prozesse', 'Zuständigkeiten für Prozessschritte', 'steps_users', 'users_id'),
            Rule::anonymize('Tickets und Prozesse', 'Erledigte Prozessschritte', 'procedure_steps', 'completed_by'),
            Rule::anonymize('Tickets und Prozesse', 'Prozess-Historie', 'procedure_step_history', 'performed_by'),
            Rule::anonymize('Tickets und Prozesse', 'Kommentare zu Prozessschritten', 'procedure_step_comments', 'user_id'),

            // ── Vertretungsplan, Kalender, Räume ──────────────────────────────
            Rule::anonymize('Vertretungsplan und Kalender', 'Vertretungen', 'vertretungen', 'users_id'),
            Rule::delete('Vertretungsplan und Kalender', 'Abwesenheiten im Vertretungsplan', 'vertretungsplan_absences', 'user_id'),
            Rule::delete('Vertretungsplan und Kalender', 'Künftige Raumbuchungen', 'room_bookings', 'users_id', 1,
                fn (Builder $q) => $q->where(fn (Builder $w) => $w->whereNull('date')->orWhere('date', '>=', $today()))),
            Rule::anonymize('Vertretungsplan und Kalender', 'Vergangene Raumbuchungen', 'room_bookings', 'users_id'),
            Rule::anonymize('Vertretungsplan und Kalender', 'Kalendertermine (Ersteller)', 'ox_termine', 'erstellt_von'),
            Rule::delete('Vertretungsplan und Kalender', 'Kalender-Synchronisationsprotokoll', 'ox_sync_log', 'user_id'),

            // ── Pädagogik und Schülerdaten (Fristen der Schülerakte) ──────────
            Rule::anonymize('Pädagogik', 'Einträge im Pädagogischen Tagebuch', 'paed_diary_entries', 'user_id'),
            Rule::anonymize('Pädagogik', 'Aufgaben im Pädagogischen Tagebuch', 'paed_diary_tasks', 'created_by'),
            Rule::anonymize('Pädagogik', 'Ziele (Ersteller)', 'paed_diary_goals', 'user_id'),
            Rule::anonymize('Pädagogik', 'Ziele (erreicht markiert)', 'paed_diary_goals', 'achieved_by'),
            Rule::anonymize('Pädagogik', 'Fehlzeiten von Schülern (erfasst von)', 'paed_diary_schueler_absences', 'marked_by'),
            Rule::delete('Pädagogik', 'Künftige Termine im Tagebuch', 'paed_diary_appointments', 'user_id', 1,
                fn (Builder $q) => $q->where('start_date', '>=', $today())),
            Rule::anonymize('Pädagogik', 'Vergangene Termine im Tagebuch', 'paed_diary_appointments', 'user_id'),
            Rule::anonymize('Pädagogik', 'Pausierte Klassentage', 'paed_diary_class_day_pauses', 'paused_by'),
            Rule::anonymize('Pädagogik', 'Notizkategorien', 'paed_diary_categories', 'user_id'),
            Rule::anonymize('Pädagogik', 'Diagnostik-Sitzungen', 'diagnostic_sessions', 'user_id'),
            Rule::anonymize('Pädagogik', 'Kommentare zu Diagnostik-Zielen', 'diagnostic_goal_comments', 'user_id'),
            Rule::anonymize('Pädagogik', 'Bewertungsdokumentationen', 'grading_documentation_sessions', 'user_id'),
            Rule::anonymize('Pädagogik', 'Coaching-Notizen', 'grading_coaching_notes', 'user_id'),
            Rule::anonymize('Pädagogik', 'Bewertungshistorie', 'schueler_grading_histories', 'changed_by'),
            Rule::anonymize('Pädagogik', 'Wochenpläne', 'wp_plaene', 'created_by'),
            Rule::anonymize('Pädagogik', 'Wochenplan-Formatvorlagen', 'wp_formatvorlagen', 'created_by'),

            // ── Personal: Bearbeiter-Verweise (die Person hat für andere gehandelt) ──
            Rule::anonymize('Personal (Bearbeiter)', 'Vertragsänderungen (geändert von)', 'contract_audits', 'changed_by'),
            Rule::anonymize('Personal (Bearbeiter)', 'Urlaubsansprüche (geändert von)', 'employe_holiday_claims', 'changedBy'),
            Rule::anonymize('Personal (Bearbeiter)', 'Urlaubsanträge (genehmigt von)', 'holidays', 'approved_by'),
            Rule::anonymize('Personal (Bearbeiter)', 'Arbeitszeitnachweise (gesperrt von)', 'timesheets', 'locked_by'),
            Rule::anonymize('Personal (Bearbeiter)', 'Arbeitszeitnachweise (geprüft von)', 'timesheets', 'reviewed_by'),
            Rule::anonymize('Personal (Bearbeiter)', 'Prüfhinweise (erledigt von)', 'timesheet_anomalies', 'resolved_by'),
            Rule::anonymize('Personal (Bearbeiter)', 'Dokumente (hochgeladen von)', 'pers_documents', 'uploaded_by'),
            Rule::anonymize('Personal (Bearbeiter)', 'Qualifikationen (geprüft von)', 'pers_employee_qualifications', 'verified_by'),
            Rule::anonymize('Personal (Bearbeiter)', 'Fortbildungen (genehmigt von)', 'pers_training_participants', 'approved_by'),
            Rule::anonymize('Personal (Bearbeiter)', 'Fortbildungen (angelegt von)', 'pers_trainings', 'created_by'),
            Rule::anonymize('Personal (Bearbeiter)', 'Abwesenheiten (eingetragen von)', 'absences', 'creator_id'),
            Rule::anonymize('Personal (Bearbeiter)', 'Hortplanungen', 'hort_planungen', 'created_by'),
            Rule::anonymize('Personal (Bearbeiter)', 'Hortplanungs-Snapshots', 'hort_planung_snapshots', 'created_by'),
            Rule::anonymize('Personal (Bearbeiter)', 'Hort-Faktorwerte', 'hort_faktor_werte', 'created_by'),
            Rule::anonymize('Protokolle', 'Änderungsprotokoll (Akteur)', 'audits', 'user_id', $morph('user_type')),
            Rule::delete('Personal', 'Prüfhinweise zur Zeiterfassung', 'timesheet_anomalies', 'employe_id'),

            // ── Personalakte: aufbewahren bis Fristende (Stufe 3) ─────────────
            Rule::retain('Personalakte', 'Personaldaten (Name, Geburtsdatum, SV-Nummer)', 'employes_data', 'user_id', 'payroll'),
            Rule::retain('Personalakte', 'Beschäftigungsverhältnisse', 'employments', 'employe_id', 'payroll', Employment::class),
            Rule::retain('Personalakte', 'Vertragsänderungen', 'contract_audits', 'employe_id', 'payroll'),
            Rule::retain('Personalakte', 'Arbeitszeiten', 'working_times', 'employe_id', 'payroll'),
            Rule::retainVia('Personalakte', 'Arbeitszeitnachweise (Tage)', 'timesheet_days', 'timesheet_id', 'timesheets', 'employe_id', 'payroll', TimesheetDays::class),
            Rule::retain('Personalakte', 'Arbeitszeitnachweise', 'timesheets', 'employe_id', 'payroll', Timesheet::class),
            Rule::retain('Personalakte', 'Urlaub', 'holidays', 'employe_id', 'payroll'),
            Rule::retain('Personalakte', 'Urlaubsansprüche', 'employe_holiday_claims', 'employe_id', 'payroll'),
            Rule::retain('Personalakte', 'Dienstplan-Einträge', 'roster_events', 'employe_id', 'payroll'),
            Rule::retain('Personalakte', 'Hortplanung (Person)', 'hort_planung_personen', 'user_id', 'payroll'),
            Rule::retain('Personalakte', 'Abwesenheiten (inkl. Krankheit)', 'absences', 'users_id', 'payroll'),
            Rule::retain('Personalakte', 'Dokumente der Personalakte', 'pers_documents', 'employe_id', 'documents', PersonalDocument::class),
            Rule::retain('Personalakte', 'Qualifikationen', 'pers_employee_qualifications', 'employe_id', 'claims', EmployeeQualification::class),
            Rule::retain('Personalakte', 'Fortbildungsteilnahmen', 'pers_training_participants', 'employe_id', 'claims', TrainingParticipant::class),
            Rule::retain('Personalakte', 'Einwilligungen', 'pers_consents', 'employe_id', 'claims', Consent::class),
            Rule::retain('Protokolle', 'Zugriffe auf Personaldaten (durch die Person)', 'pers_access_logs', 'user_id', 'access_log'),
            Rule::retain('Protokolle', 'Zugriffe auf Personaldaten (über die Person)', 'pers_access_logs', 'resource_id', 'payroll', null,
                $morph('resource_type')),
        ];
    }
}
