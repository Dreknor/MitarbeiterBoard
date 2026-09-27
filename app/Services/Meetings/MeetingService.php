<?php

namespace App\Services\Meetings;

use App\Mail\MeetingInvitationMail;
use App\Models\Group;
use App\Models\Meeting;
use App\Models\MeetingParticipant;
use App\Models\RoomBooking;
use App\Models\Theme;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Spatie\Permission\Models\Role;

/**
 * Gemeinsame Logik für gruppengebundene und freie Meetings
 * (Teilnehmer, Agenda-Themen, Einladungen, Absage).
 */
class MeetingService
{
    /**
     * Teilnehmer (Personen, Gruppen/Bereiche, Rollen) vollständig ersetzen.
     *
     * @param  array{users?: array<int>, organizers?: array<int>, groups?: array<int>, roles?: array<int>}  $data
     */
    public function syncParticipants(Meeting $meeting, array $data): void
    {
        $userIds      = collect($data['users'] ?? [])->map(fn ($id) => (int) $id)->filter()->unique();
        $organizerIds = collect($data['organizers'] ?? [])->map(fn ($id) => (int) $id)->filter()->unique();
        $groupIds     = collect($data['groups'] ?? [])->map(fn ($id) => (int) $id)->filter()->unique();
        $roleIds      = collect($data['roles'] ?? [])->map(fn ($id) => (int) $id)->filter()->unique();

        // Organisatoren sind immer auch Teilnehmer
        $userIds = $userIds->merge($organizerIds)->unique();

        DB::transaction(function () use ($meeting, $userIds, $organizerIds, $groupIds, $roleIds) {
            $meeting->participants()->delete();

            $rows = collect();
            foreach ($userIds as $id) {
                $rows->push(['type' => User::class, 'id' => $id, 'organizer' => $organizerIds->contains($id)]);
            }
            foreach ($groupIds as $id) {
                $rows->push(['type' => Group::class, 'id' => $id, 'organizer' => false]);
            }
            foreach ($roleIds as $id) {
                $rows->push(['type' => Role::class, 'id' => $id, 'organizer' => false]);
            }

            foreach ($rows as $row) {
                MeetingParticipant::create([
                    'meeting_id'       => $meeting->id,
                    'participant_type' => $row['type'],
                    'participant_id'   => $row['id'],
                    'is_organizer'     => $row['organizer'],
                ]);
            }
        });
    }

    /**
     * Auswahllisten für den Teilnehmer-Picker (Personen, Gruppen/Bereiche, Rollen).
     *
     * @return array{users: array, groups: array, roles: array}
     */
    public function participantOptions(): array
    {
        return [
            'users'  => User::query()->orderBy('name')->get(['id', 'name'])
                ->map(fn (User $u) => ['id' => $u->id, 'name' => $u->name])->values()->all(),
            'groups' => Group::query()->orderBy('name')->get(['id', 'name'])
                ->map(fn (Group $g) => ['id' => $g->id, 'name' => $g->name])->values()->all(),
            'roles'  => Role::query()->where('guard_name', 'web')->orderBy('name')->get(['id', 'name'])
                ->map(fn (Role $r) => ['id' => $r->id, 'name' => $r->name])->values()->all(),
        ];
    }

    /**
     * Aktuelle Teilnehmer eines Meetings im Format des Pickers.
     *
     * @return array{users: array<int>, organizers: array<int>, groups: array<int>, roles: array<int>}
     */
    public function participantSelection(?Meeting $meeting): array
    {
        if (! $meeting) {
            return ['users' => [], 'organizers' => [], 'groups' => [], 'roles' => []];
        }

        $rows = $meeting->participants()->get();

        return [
            'users'      => $rows->where('participant_type', User::class)->pluck('participant_id')->values()->all(),
            'organizers' => $rows->where('participant_type', User::class)->where('is_organizer', true)->pluck('participant_id')->values()->all(),
            'groups'     => $rows->where('participant_type', Group::class)->pluck('participant_id')->values()->all(),
            'roles'      => $rows->where('participant_type', Role::class)->pluck('participant_id')->values()->all(),
        ];
    }

    /**
     * Offene Themen, die der Nutzer diesem Meeting zuweisen darf – gruppiert nach Kontext.
     *
     * Enthält: offene Themen der Meeting-Gruppe, der eigenen Gruppen sowie
     * freie Themen aus Meetings, an denen der Nutzer teilnimmt oder die er erstellt hat.
     *
     * @return Collection<string, Collection<int, Theme>>
     */
    public function assignableThemes(Meeting $meeting, User $user): Collection
    {
        $groupIds = $user->groups_rel()->pluck('groups.id');
        if ($meeting->group_id) {
            $groupIds->push($meeting->group_id);
        }

        $visibleMeetingIds = Meeting::query()->visibleTo($user)->pluck('id');
        $assignedIds       = $meeting->themes()->pluck('themes.id');

        $themes = Theme::query()
            ->where('completed', false)
            ->whereNotIn('id', $assignedIds)
            ->where(function ($q) use ($groupIds, $visibleMeetingIds, $user) {
                $q->whereIn('group_id', $groupIds->unique())
                    ->orWhere(function ($q) use ($visibleMeetingIds, $user) {
                        $q->whereNull('group_id')
                            ->where(function ($q) use ($visibleMeetingIds, $user) {
                                $q->where('creator_id', $user->id)
                                    ->orWhereHas('meetings', fn ($m) => $m->whereIn('meetings.id', $visibleMeetingIds));
                            });
                    });
            })
            ->with('group')
            ->orderBy('date')
            ->get();

        $ownGroupName = $meeting->group?->name;

        return $themes
            ->groupBy(fn (Theme $t) => $t->group?->name ?? 'Freie Themen')
            ->sortBy(fn ($items, $label) => match (true) {
                $label === $ownGroupName  => '0',
                $label === 'Freie Themen' => '1',
                default                   => '2' . $label,
            });
    }

    /**
     * Darf der Nutzer dieses Thema einem Meeting zuweisen?
     */
    public function canAssignTheme(Meeting $meeting, Theme $theme, User $user): bool
    {
        return $this->assignableThemes($meeting, $user)
            ->flatten(1)
            ->contains(fn (Theme $t) => $t->id === $theme->id);
    }

    public function attachTheme(Meeting $meeting, Theme $theme): void
    {
        if (! $meeting->themes()->where('theme_id', $theme->id)->exists()) {
            $meeting->themes()->attach($theme->id);
            $theme->update(['date' => $meeting->date]);
        }
    }

    /**
     * Legt ein neues Thema an. Bei Gruppen-Meetings gehört es zur Gruppe,
     * bei freien Meetings ist es ein freies Thema.
     */
    public function createTheme(Meeting $meeting, array $data, User $user): Theme
    {
        $theme = new Theme([
            'theme'       => $data['theme'],
            'goal'        => $data['goal'] ?? null,
            'information' => $data['information'] ?? null,
            'duration'    => $data['duration'],
        ]);
        $theme->group_id   = $meeting->group_id;
        $theme->type_id    = $data['type'];
        $theme->creator_id = $user->id;
        $theme->date       = $meeting->date;
        $theme->save();

        $meeting->themes()->attach($theme->id);

        return $theme;
    }

    /**
     * Versendet Einladungen an alle Teilnehmer.
     *
     * @return array{gesendet: int, fehlerhaft: array<int, string>}
     */
    public function sendInvitations(Meeting $meeting, ?string $message, User $sender): array
    {
        $meeting->loadMissing(['themes', 'roomBooking.room', 'group', 'creator', 'participantUsers', 'participantGroups.users', 'participantRoles']);

        $gesendet   = 0;
        $fehlerhaft = [];

        foreach ($meeting->resolvedParticipants() as $user) {
            if (empty($user->email)) {
                Log::warning('Meeting-Einladung: Keine E-Mail-Adresse für Benutzer', [
                    'user_id'    => $user->id,
                    'meeting_id' => $meeting->id,
                ]);
                $fehlerhaft[] = $user->name . ' (keine E-Mail-Adresse)';
                continue;
            }

            try {
                Mail::to($user->email)->queue(
                    new MeetingInvitationMail($meeting, $meeting->group, $user, $message, $sender->name, $sender->email)
                );
                $gesendet++;
            } catch (\Throwable $e) {
                Log::error('Meeting-Einladung: Fehler beim Einreihen der Mail', [
                    'user_id'    => $user->id,
                    'meeting_id' => $meeting->id,
                    'error'      => $e->getMessage(),
                ]);
                $fehlerhaft[] = $user->name . ' (' . $user->email . ')';
            }
        }

        if ($gesendet > 0) {
            $meeting->update([
                'invitation_sent_at' => now(),
                'invitation_sent_by' => $sender->id,
            ]);
            Log::info('Meeting-Einladungen eingereiht', [
                'meeting_id'   => $meeting->id,
                'gesendet'     => $gesendet,
                'fehlerhaft'   => count($fehlerhaft),
                'versender_id' => $sender->id,
            ]);
        }

        return ['gesendet' => $gesendet, 'fehlerhaft' => $fehlerhaft];
    }

    /**
     * Flash-Meldung für das Ergebnis eines Einladungsversands.
     *
     * @return array{type: string, Meldung: string}
     */
    public function invitationFlash(array $result): array
    {
        if (! empty($result['fehlerhaft'])) {
            return [
                'type'    => $result['gesendet'] > 0 ? 'warning' : 'danger',
                'Meldung' => "Einladungen wurden an {$result['gesendet']} Teilnehmende eingereiht. "
                    . 'Folgende Empfänger konnten nicht berücksichtigt werden: '
                    . implode(', ', $result['fehlerhaft']),
            ];
        }

        return [
            'type'    => 'success',
            'Meldung' => "Einladungen wurden an {$result['gesendet']} Teilnehmende eingereiht.",
        ];
    }

    public function cancel(Meeting $meeting, User $user): void
    {
        $meeting->update([
            'cancelled'    => true,
            'cancelled_by' => $user->id,
            'cancelled_at' => now(),
        ]);

        $this->releaseRoomBooking($meeting);

        Log::info('Meeting abgesagt und Raumbuchung freigegeben', [
            'meeting_id' => $meeting->id,
            'group_id'   => $meeting->group_id,
            'user_id'    => $user->id,
        ]);
    }

    public function reactivate(Meeting $meeting): void
    {
        $meeting->update([
            'cancelled'    => false,
            'cancelled_by' => null,
            'cancelled_at' => null,
        ]);
    }

    public function delete(Meeting $meeting): void
    {
        // Verknüpfung zu Themen lösen (die Themen selbst bleiben erhalten)
        $meeting->themes()->detach();
        $this->releaseRoomBooking($meeting);
        $meeting->delete();
    }

    private function releaseRoomBooking(Meeting $meeting): void
    {
        RoomBooking::query()
            ->where('meeting_id', $meeting->id)
            ->where('cancelled', false)
            ->update(['cancelled' => true]);
    }
}
