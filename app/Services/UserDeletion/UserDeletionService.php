<?php

namespace App\Services\UserDeletion;

use App\Models\User;
use App\Notifications\UserDeletionDueNotification;
use App\Services\Personal\Contracts\NextcloudFileServiceInterface;
use App\Services\Personal\PersonalRetentionService;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use RuntimeException;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * Löschkonzept für Benutzerkonten in drei Stufen.
 *
 *  Stufe 1 – deactivate(): Übergabe offener Verantwortungen, Soft-Delete (umkehrbar)
 *  Stufe 2 – anonymize():  nach Sperrfrist, Kontodaten überschreiben, Inhalte auf „Ehemalige Person“
 *  Stufe 3 – purge():      nach Ablauf der Aufbewahrungsfristen, Personalakte löschen, zuletzt den User
 *
 * Welche Spalte wie behandelt wird, steht in RuleRegistry.
 */
class UserDeletionService
{
    public const STAGE_ACTIVE = 0;
    public const STAGE_DEACTIVATED = 1;
    public const STAGE_ANONYMIZED = 2;

    public function __construct(
        private readonly RuleRegistry $rules,
        private readonly PersonalRetentionService $retention,
    ) {
    }

    // ── Platzhalter ───────────────────────────────────────────────────────

    /**
     * Platzhalter-Konto „Ehemalige Person“: nicht anmeldbar (soft-deleted), ohne Rollen.
     */
    public function placeholder(): User
    {
        $placeholder = User::withTrashed()->where('is_placeholder', true)->first();

        if ($placeholder) {
            return $placeholder;
        }

        $placeholder = new User();
        $placeholder->forceFill([
            'name' => config('user_deletion.placeholder_name'),
            'email' => 'ehemalige-person@invalid.local',
            'password' => Hash::make(Str::random(64)),
            'changePassword' => false,
            'is_placeholder' => true,
            'deletion_stage' => self::STAGE_ANONYMIZED,
        ])->save();
        $placeholder->delete();

        return $placeholder;
    }

    // ── Übergabe ──────────────────────────────────────────────────────────

    /**
     * Offene Verantwortungen der Person mit Anzahl.
     *
     * @return array<int, array{handover: Handover, count: int}>
     */
    public function handoverItems(User $user): array
    {
        $items = [];

        foreach (Handover::all() as $handover) {
            $count = $handover->count($user->id);

            if ($count > 0) {
                $items[] = ['handover' => $handover, 'count' => $count];
            }
        }

        return $items;
    }

    /**
     * Mögliche Übernehmende: zuerst die/der Vorgesetzte, dann Mitglieder der gleichen Gruppen.
     */
    public function handoverCandidates(User $user): Collection
    {
        $groupIds = DB::table('group_user')->where('user_id', $user->id)->pluck('group_id');

        $members = User::query()
            ->whereIn('id', DB::table('group_user')->whereIn('group_id', $groupIds)->select('user_id'))
            ->where('id', '!=', $user->id)
            ->orderBy('name')
            ->get();

        $superior = $user->superior_id ? User::find($user->superior_id) : null;

        if ($superior) {
            $members = $members->reject(fn (User $m) => $m->id === $superior->id)->prepend($superior);
        }

        return $members->values();
    }

    public function suggestedHandoverTarget(User $user): ?User
    {
        return $user->superior_id ? User::find($user->superior_id) : null;
    }

    // ── Stufe 1: Deaktivieren ─────────────────────────────────────────────

    /**
     * @param array<string, int|null> $handovers Handover-Key => ID der übernehmenden Person (null = nicht übergeben)
     */
    public function deactivate(User $user, array $handovers = [], ?User $actor = null, ?Carbon $leftAt = null): array
    {
        $this->guardNotPlaceholder($user);

        if ($user->trashed()) {
            throw new RuntimeException('Das Konto ist bereits deaktiviert.');
        }

        $allowed = $this->handoverCandidates($user)->pluck('id')->all();

        $counts = DB::transaction(function () use ($user, $handovers, $allowed, $leftAt) {
            $counts = [];

            foreach (Handover::all() as $handover) {
                if (!array_key_exists($handover->key, $handovers)) {
                    continue;
                }

                $target = $handovers[$handover->key] ? (int) $handovers[$handover->key] : null;

                if ($target !== null && !in_array($target, $allowed, true)) {
                    throw new RuntimeException('Ungültige Person für die Übergabe: '.$handover->label);
                }

                $counts['handover.'.$handover->key] = $handover->apply($user->id, $target);
            }

            $counts += $this->applyRules($this->rules->forStage(1), $user);

            $user->forceFill([
                'left_at' => ($leftAt ?? now())->toDateString(),
                'deletion_stage' => self::STAGE_DEACTIVATED,
                'deletion_notified_stage' => self::STAGE_DEACTIVATED,
            ])->save();

            $user->delete();

            return $counts;
        });

        $this->forgetCaches($user);
        $this->log($user, 'deactivated', $actor, $counts);

        return $counts;
    }

    /**
     * Reaktivierung ist nur in Stufe 1 möglich.
     */
    public function restore(User $user): void
    {
        $this->guardNotPlaceholder($user);

        if ((int) $user->deletion_stage >= self::STAGE_ANONYMIZED) {
            throw new RuntimeException('Anonymisierte Konten können nicht reaktiviert werden.');
        }

        $user->restore();
        $user->forceFill([
            'deletion_stage' => self::STAGE_ACTIVE,
            'deletion_notified_stage' => self::STAGE_ACTIVE,
            'left_at' => null,
            'deletion_reviewed_at' => null,
            'deletion_review_note' => null,
        ])->save();

        $this->log($user, 'restored', auth()->user());
    }

    // ── Stufe 2: Anonymisieren ────────────────────────────────────────────

    public function canAnonymize(User $user, ?Carbon $now = null): bool
    {
        return $user->trashed()
            && !$user->is_placeholder
            && (int) $user->deletion_stage === self::STAGE_DEACTIVATED
            && ($now ?? now())->greaterThanOrEqualTo($this->retention->anonymizeDueAt($user));
    }

    /**
     * Anonymisiert das Konto. Aufbewahrungspflichtige Personaldaten bleiben gesperrt erhalten.
     *
     * @param bool $early true bei Löschantrag nach Art. 17 DSGVO (Sperrfrist wird übersprungen)
     */
    public function anonymize(User $user, User $actor, bool $early = false): array
    {
        $this->guardNotPlaceholder($user);

        if (!$user->trashed() || (int) $user->deletion_stage !== self::STAGE_DEACTIVATED) {
            throw new RuntimeException('Nur deaktivierte, noch nicht anonymisierte Konten können anonymisiert werden.');
        }

        if (!$early && !$this->canAnonymize($user)) {
            throw new RuntimeException('Die Sperrfrist ist noch nicht abgelaufen (bis '.$this->retention->anonymizeDueAt($user)->format('d.m.Y').').');
        }

        $placeholder = $this->placeholder();

        $counts = DB::transaction(function () use ($user, $placeholder) {
            // Eindeutige Kategorienamen pro User erhalten, bevor sie auf den Platzhalter wandern
            foreach (DB::table('paed_diary_categories')->where('user_id', $user->id)->get(['id', 'name']) as $category) {
                DB::table('paed_diary_categories')->where('id', $category->id)
                    ->update(['name' => Str::limit($category->name, 200, '').' (#'.$user->id.')']);
            }

            $counts = $this->applyRules($this->rules->forStage(1), $user, $placeholder);
            $counts += $this->applyRules($this->rules->forStage(2), $user, $placeholder);

            $counts['ox_termin_teilnehmer'] = DB::table('ox_termin_teilnehmer')
                ->where('email', $user->email)
                ->update(['email' => $this->anonymizedEmail($user), 'name' => config('user_deletion.placeholder_name')]);

            $counts['password_resets'] = DB::table('password_resets')->where('email', $user->email)->delete();

            $this->clearEmployeeCalendarFields($user);

            $counts['media'] = 0;
            foreach ($user->media()->get() as $media) {
                $media->delete();
                $counts['media']++;
            }

            $user->forceFill([
                'name' => config('user_deletion.placeholder_name').' #'.$user->id,
                'email' => $this->anonymizedEmail($user),
                'username' => null,
                'kuerzel' => null,
                'password' => Hash::make(Str::random(64)),
                'remember_token' => null,
                'calendar_token' => null,
                'atom_feed_url' => null,
                'superior_id' => null,
                'deputy_id' => null,
                'sso_uid_hash' => $user->username ? self::ssoHash($user->username) : null,
                'deletion_stage' => self::STAGE_ANONYMIZED,
                'deletion_notified_stage' => self::STAGE_ANONYMIZED,
                'anonymized_at' => now(),
                'deletion_reviewed_at' => null,
                'deletion_review_note' => null,
            ])->save();

            return $counts;
        });

        $this->forgetCaches($user);
        $this->log($user, $early ? 'anonymized_early' : 'anonymized', $actor, $counts);

        return $counts;
    }

    // ── Stufe 3: Endgültig löschen ────────────────────────────────────────

    /**
     * Löschbare (abgelaufene) Datensätze je Regel.
     *
     * @return array<int, array{rule: Rule, count: int, expires: Carbon}>
     */
    public function purgeableRows(User $user, ?Carbon $now = null): array
    {
        $rows = [];

        foreach ($this->rules->retained() as $rule) {
            if ($rule->category === 'documents') {
                $count = $this->expiredDocuments($user, $now)->count();
                $expires = $this->documentsExpireAt($user);
            } else {
                $count = $this->retention->isExpired($user, $rule->category, $now) ? $rule->count($user->id) : 0;
                $expires = $this->retention->expiresAt($user, $rule->category);
            }

            if ($count > 0) {
                $rows[] = ['rule' => $rule, 'count' => $count, 'expires' => $expires];
            }
        }

        return $rows;
    }

    public function remainingRetainedCount(User $user): int
    {
        return array_sum(array_map(fn (Rule $rule) => $rule->count($user->id), $this->rules->retained()));
    }

    public function canPurge(User $user, ?Carbon $now = null): bool
    {
        return (int) $user->deletion_stage === self::STAGE_ANONYMIZED
            && !$user->is_placeholder
            && !$user->legal_hold
            && (count($this->purgeableRows($user, $now)) > 0 || $this->remainingRetainedCount($user) === 0);
    }

    /**
     * Löscht alle abgelaufenen Personaldaten. Ist danach nichts mehr aufzubewahren,
     * wird auch der User-Datensatz endgültig entfernt.
     *
     * @return array{counts: array, deleted: bool}
     */
    public function purge(User $user, User $actor, ?Carbon $now = null): array
    {
        $this->guardNotPlaceholder($user);

        if ((int) $user->deletion_stage !== self::STAGE_ANONYMIZED) {
            throw new RuntimeException('Nur anonymisierte Konten können endgültig gelöscht werden.');
        }

        if ($user->legal_hold) {
            throw new RuntimeException('Für das Konto ist eine Aufbewahrungssperre (Legal Hold) gesetzt.');
        }

        $placeholder = $this->placeholder();

        $result = DB::transaction(function () use ($user, $now, $placeholder) {
            $counts = [];

            foreach ($this->rules->retained() as $rule) {
                $key = $rule->table.'.'.$rule->column;

                if ($rule->category === 'documents') {
                    $counts[$key] = $this->purgeDocuments($this->expiredDocuments($user, $now));
                    continue;
                }

                if (!$this->retention->isExpired($user, $rule->category, $now)) {
                    continue;
                }

                if ($rule->table === 'pers_employee_qualifications') {
                    $this->deleteQualificationMedia($rule->query($user->id)->pluck('document_id'));
                }

                $counts[$key] = $rule->apply($user->id, $placeholder->id);
            }

            $deleted = false;

            if ($this->remainingRetainedCount($user) === 0) {
                // Sicherheitsnetz: alle übrigen Verweise (auch nach dem Anonymisieren neu entstandene) umhängen
                $counts += $this->applyRules($this->rules->forStage(1), $user, $placeholder);
                $counts += $this->applyRules($this->rules->forStage(2), $user, $placeholder);

                $user->forceDelete();
                $deleted = true;
            } else {
                $user->forceFill(['deletion_notified_stage' => self::STAGE_ANONYMIZED])->save();
            }

            return ['counts' => array_filter($counts), 'deleted' => $deleted];
        });

        $this->log($user, $result['deleted'] ? 'purged' : 'purged_partial', $actor, $result['counts']);

        return $result;
    }

    // ── Führungszeugnis (§ 72a Abs. 5 SGB VIII) ───────────────────────────

    /**
     * Löscht Daten zum Führungszeugnis spätestens 3 Monate nach Austritt.
     * Läuft automatisch, da es sich um eine gesetzliche Löschpflicht handelt.
     */
    public function purgeFuehrungszeugnisse(?Carbon $now = null): int
    {
        $now ??= now();
        $total = 0;

        $typeIds = DB::table('pers_qualification_types')
            ->where('name', 'like', config('user_deletion.fuehrungszeugnis_pattern'))
            ->pluck('id');

        if ($typeIds->isEmpty()) {
            return 0;
        }

        $users = User::onlyTrashed()
            ->where('is_placeholder', false)
            ->whereIn('id', DB::table('pers_employee_qualifications')->whereIn('qualification_type_id', $typeIds)->select('employe_id'))
            ->get();

        foreach ($users as $user) {
            if ($now->lessThan($this->retention->fuehrungszeugnisDueAt($user))) {
                continue;
            }

            $query = DB::table('pers_employee_qualifications')
                ->where('employe_id', $user->id)
                ->whereIn('qualification_type_id', $typeIds);

            $this->deleteQualificationMedia((clone $query)->pluck('document_id'));

            DB::table('audits')
                ->where('auditable_type', \App\Models\personal\EmployeeQualification::class)
                ->whereIn('auditable_id', (clone $query)->pluck('id'))
                ->delete();

            $count = $query->delete();
            $total += $count;

            $this->log($user, 'fuehrungszeugnis_deleted', null, ['pers_employee_qualifications' => $count]);
        }

        return $total;
    }

    // ── Auswirkungsübersicht ──────────────────────────────────────────────

    /**
     * Vollständige Übersicht, was in der Stufe mit welchen Datensätzen passiert.
     *
     * @return array<string, array<int, array{label: string, table: string, count: int, action: string, until: ?Carbon}>>
     */
    public function impactReport(User $user, int $stage, ?Carbon $now = null): array
    {
        $report = [];
        $add = function (string $area, string $label, string $table, int $count, string $action, ?Carbon $until = null) use (&$report) {
            if ($count > 0) {
                $report[$area][] = compact('label', 'table', 'count', 'action', 'until');
            }
        };

        if ($stage === 1) {
            foreach ($this->handoverItems($user) as $item) {
                $add('Übergabe', $item['handover']->label, $item['handover']->table, $item['count'], 'übergeben oder schließen');
            }
            foreach ($this->rules->forStage(1) as $rule) {
                $add($rule->area, $rule->label, $rule->table, $rule->count($user->id), $rule->actionLabel());
            }
            $add('Konto', 'Anmeldung', 'users', 1, 'gesperrt (Reaktivierung möglich)');
        }

        if ($stage === 2) {
            foreach (array_merge($this->rules->forStage(1), $this->rules->forStage(2)) as $rule) {
                $add($rule->area, $rule->label, $rule->table, $rule->count($user->id), $rule->actionLabel());
            }
            $add('Konto', 'Name, E-Mail, Benutzername, Kürzel, Passwort, Tokens', 'users', 1, 'überschreiben');
            $add('Konto', 'Profilfoto und weitere Dateien', 'media', $user->media()->count(), 'löschen');
            $add('Vertretungsplan und Kalender', 'Kalendertermine (Teilnahme per E-Mail)', 'ox_termin_teilnehmer',
                DB::table('ox_termin_teilnehmer')->where('email', $user->email)->count(), 'anonymisieren');

            foreach ($this->rules->retained() as $rule) {
                $count = $rule->count($user->id);
                $until = $rule->category === 'documents'
                    ? $this->documentsExpireAt($user)
                    : $this->retention->expiresAt($user, $rule->category);
                $add($rule->area, $rule->label, $rule->table, $count, 'gesperrt aufbewahren', $until);
            }
        }

        if ($stage === 3) {
            $purgeable = $this->purgeableRows($user, $now);

            foreach ($purgeable as $row) {
                $add($row['rule']->area, $row['rule']->label, $row['rule']->table, $row['count'], 'endgültig löschen', $row['expires']);
            }

            foreach ($this->rules->retained() as $rule) {
                $remaining = $rule->count($user->id) - collect($purgeable)
                    ->filter(fn ($row) => $row['rule'] === $rule)->sum('count');
                $until = $rule->category === 'documents'
                    ? $this->documentsExpireAt($user)
                    : $this->retention->expiresAt($user, $rule->category);
                $add($rule->area, $rule->label, $rule->table, $remaining, 'weiter aufbewahren', $until);
            }

            $add('Personalakte', 'Dateien in Nextcloud', 'pers_documents',
                $this->expiredDocuments($user, $now)->whereNotNull('nextcloud_path')->count(), 'endgültig löschen');

            if ($this->remainingRetainedCount($user) - array_sum(array_column($purgeable, 'count')) <= 0) {
                $add('Konto', 'Benutzerdatensatz', 'users', 1, 'endgültig löschen');
            }
        }

        return $report;
    }

    /**
     * Schritte außerhalb des Boards, die der Admin selbst erledigen muss.
     */
    public function externalSteps(User $user, int $stage): array
    {
        $steps = [];

        if ($stage <= 2 && ($user->username || $user->sso_uid_hash)) {
            $steps[] = 'Konto im Identitätsanbieter (SAML) sperren bzw. löschen. Das Board lehnt spätere SSO-Anmeldungen dieser Kennung ab.';
        }
        if ($stage === 2) {
            $steps[] = 'Termine der Person im OX-Kalender prüfen und ggf. entfernen.';
        }
        if ($stage === 3) {
            $steps[] = 'Persönliche Ordner in Nextcloud prüfen, die nicht über die Personalakte angelegt wurden.';
        }

        return $steps;
    }

    // ── Fälligkeiten und Benachrichtigung ─────────────────────────────────

    public function deactivatedAccounts(): Collection
    {
        return User::onlyTrashed()
            ->where('is_placeholder', false)
            ->where('deletion_stage', '<', self::STAGE_ANONYMIZED)
            ->orderBy('left_at')
            ->get();
    }

    public function anonymizedAccounts(): Collection
    {
        return User::withTrashed()
            ->where('is_placeholder', false)
            ->where('deletion_stage', self::STAGE_ANONYMIZED)
            ->orderBy('left_at')
            ->get();
    }

    /**
     * Konten, über die Admins noch nicht informiert wurden.
     *
     * @return array{legacy: Collection, anonymize: Collection, purge: Collection}
     */
    public function pendingNotifications(?Carbon $now = null): array
    {
        $now ??= now();
        $deactivated = $this->deactivatedAccounts();

        $anonymize = $deactivated->filter(fn (User $u) => $u->deletion_notified_stage < self::STAGE_ANONYMIZED
            && $this->canAnonymize($u, $now)
            && !$this->isKeptByReview($u, $now));

        $legacy = $deactivated->filter(fn (User $u) => (int) $u->deletion_notified_stage === self::STAGE_ACTIVE
            && !$anonymize->contains($u));

        $purge = $this->anonymizedAccounts()->filter(fn (User $u) => $u->deletion_notified_stage < 3
            && $this->canPurge($u, $now));

        return ['legacy' => $legacy->values(), 'anonymize' => $anonymize->values(), 'purge' => $purge->values()];
    }

    /**
     * Täglicher Lauf: Führungszeugnisse löschen (gesetzliche Pflicht) und Admins über
     * anstehende Entscheidungen informieren. Stufe 2 und 3 werden nie automatisch ausgeführt.
     */
    public function runScheduledChecks(?Carbon $now = null): array
    {
        $fuehrungszeugnisse = $this->purgeFuehrungszeugnisse($now);
        $pending = $this->pendingNotifications($now);

        $hasPending = $pending['legacy']->isNotEmpty() || $pending['anonymize']->isNotEmpty() || $pending['purge']->isNotEmpty();

        if ($hasPending || $fuehrungszeugnisse > 0) {
            $admins = User::permission('manage user deletion')->get();

            Notification::send($admins, new UserDeletionDueNotification(
                $pending['legacy'], $pending['anonymize'], $pending['purge'], $fuehrungszeugnisse
            ));

            $this->markNotified($pending);
        }

        return [
            'fuehrungszeugnisse' => $fuehrungszeugnisse,
            'legacy' => $pending['legacy']->count(),
            'anonymize' => $pending['anonymize']->count(),
            'purge' => $pending['purge']->count(),
        ];
    }

    public function markNotified(array $pending): void
    {
        foreach ($pending['legacy'] as $user) {
            $user->forceFill(['deletion_notified_stage' => self::STAGE_DEACTIVATED])->save();
        }
        foreach ($pending['anonymize'] as $user) {
            $user->forceFill(['deletion_notified_stage' => self::STAGE_ANONYMIZED])->save();
        }
        foreach ($pending['purge'] as $user) {
            $user->forceFill(['deletion_notified_stage' => 3])->save();
        }
    }

    /**
     * Admin-Entscheidung „vorerst behalten“ gilt für die Dauer der Sperrfrist.
     */
    public function isKeptByReview(User $user, ?Carbon $now = null): bool
    {
        return $user->deletion_reviewed_at
            && ($now ?? now())->lessThan(Carbon::parse($user->deletion_reviewed_at)->addMonths((int) config('user_deletion.anonymize_after_months')));
    }

    public function keep(User $user, User $actor, string $note): void
    {
        $user->forceFill([
            'deletion_reviewed_at' => now(),
            'deletion_review_note' => $note,
            'deletion_notified_stage' => self::STAGE_DEACTIVATED,
        ])->save();

        $this->log($user, 'kept', $actor);
    }

    public function setLegalHold(User $user, User $actor, bool $hold, ?string $reason = null): void
    {
        $user->forceFill(['legal_hold' => $hold, 'legal_hold_reason' => $hold ? $reason : null])->save();

        $this->log($user, $hold ? 'legal_hold_set' : 'legal_hold_removed', $actor);
    }

    // ── SSO ───────────────────────────────────────────────────────────────

    public static function ssoHash(string $uid): string
    {
        return hash_hmac('sha256', Str::lower(trim($uid)), (string) config('app.key'));
    }

    /**
     * true, wenn zu dieser SSO-Kennung ein deaktiviertes oder anonymisiertes Konto existiert.
     * Verhindert, dass beim SSO-Login still ein neues Konto angelegt wird.
     */
    public static function isSsoBlocked(?string $uid, ?string $email = null): bool
    {
        if ($uid && User::withTrashed()->where('sso_uid_hash', self::ssoHash($uid))->exists()) {
            return true;
        }

        return User::onlyTrashed()
            ->where(function ($q) use ($uid, $email) {
                if ($uid) {
                    $q->orWhere('username', $uid);
                }
                if ($email) {
                    $q->orWhere('email', $email);
                }
            })
            ->when(!$uid && !$email, fn ($q) => $q->whereRaw('1 = 0'))
            ->exists();
    }

    // ── Hilfsfunktionen ───────────────────────────────────────────────────

    /**
     * @param Rule[] $rules
     */
    private function applyRules(array $rules, User $user, ?User $placeholder = null): array
    {
        $placeholderId = $placeholder?->id ?? $this->placeholder()->id;
        $counts = [];

        foreach ($rules as $rule) {
            $key = $rule->table.'.'.$rule->column;
            $counts[$key] = ($counts[$key] ?? 0) + $rule->apply($user->id, $placeholderId);
        }

        return array_filter($counts);
    }

    private function expiredDocuments(User $user, ?Carbon $now = null): Collection
    {
        return DB::table('pers_documents')
            ->leftJoin('pers_document_types', 'pers_document_types.id', '=', 'pers_documents.document_type_id')
            ->where('pers_documents.employe_id', $user->id)
            ->get(['pers_documents.id', 'pers_documents.nextcloud_path', 'pers_document_types.retention_years'])
            ->filter(fn ($doc) => $this->retention->isExpired($user, 'payroll', $now, $doc->retention_years !== null ? (int) $doc->retention_years : null))
            ->values();
    }

    private function documentsExpireAt(User $user): Carbon
    {
        $years = DB::table('pers_documents')
            ->leftJoin('pers_document_types', 'pers_document_types.id', '=', 'pers_documents.document_type_id')
            ->where('pers_documents.employe_id', $user->id)
            ->pluck('pers_document_types.retention_years')
            ->map(fn ($y) => $y !== null ? (int) $y : $this->retention->retentionYears('payroll'))
            ->max() ?? $this->retention->retentionYears('payroll');

        return $this->retention->expiresAt($user, 'payroll', $years);
    }

    private function purgeDocuments(Collection $documents): int
    {
        if ($documents->isEmpty()) {
            return 0;
        }

        $nextcloud = app()->bound(NextcloudFileServiceInterface::class) ? app(NextcloudFileServiceInterface::class) : null;

        foreach ($documents as $doc) {
            if ($nextcloud && $doc->nextcloud_path) {
                try {
                    $nextcloud->deleteFile($doc->nextcloud_path);
                } catch (\Throwable $e) {
                    Log::warning('Löschkonzept: Nextcloud-Datei konnte nicht gelöscht werden', [
                        'document_id' => $doc->id,
                        'exception' => $e->getMessage(),
                    ]);
                }
            }
        }

        $ids = $documents->pluck('id');

        DB::table('audits')
            ->where('auditable_type', \App\Models\personal\PersonalDocument::class)
            ->whereIn('auditable_id', $ids)
            ->delete();

        return DB::table('pers_documents')->whereIn('id', $ids)->delete();
    }

    private function deleteQualificationMedia(Collection $mediaIds): void
    {
        $mediaIds = $mediaIds->filter();

        if ($mediaIds->isNotEmpty()) {
            Media::whereIn('id', $mediaIds)->get()->each->delete();
        }
    }

    private function clearEmployeeCalendarFields(User $user): void
    {
        $fields = array_filter(
            ['google_calendar_link', 'caldav_uuid', 'caldav_working_time', 'caldav_events'],
            fn ($column) => Schema::hasColumn('employes_data', $column)
        );

        if ($fields) {
            DB::table('employes_data')->where('user_id', $user->id)
                ->update(array_fill_keys($fields, null));
        }
    }

    private function anonymizedEmail(User $user): string
    {
        return 'geloescht-'.$user->id.'@invalid.local';
    }

    private function forgetCaches(User $user): void
    {
        foreach (['user.', 'groups_', 'user_photo_', 'user_employments_', 'user_holidays_'] as $prefix) {
            Cache::forget($prefix.$user->id);
        }
    }

    private function guardNotPlaceholder(User $user): void
    {
        if ($user->is_placeholder) {
            throw new RuntimeException('Das Platzhalter-Konto kann nicht bearbeitet werden.');
        }
    }

    /**
     * Protokoll ohne Personendaten: nur IDs, Aktion und Anzahl der Datensätze.
     */
    private function log(User $user, string $action, ?User $actor, array $counts = []): void
    {
        DB::table('user_deletion_logs')->insert([
            'user_id' => $user->id,
            'action' => $action,
            'confirmed_by' => $actor?->id,
            'counts' => $counts ? json_encode($counts) : null,
            'created_at' => now(),
        ]);

        Log::info('Löschkonzept: '.$action, ['user_id' => $user->id, 'actor_id' => $actor?->id]);
    }
}
