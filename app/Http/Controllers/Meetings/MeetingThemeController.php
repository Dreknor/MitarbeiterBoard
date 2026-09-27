<?php

namespace App\Http\Controllers\Meetings;

use App\Http\Controllers\Controller;
use App\Http\Requests\ProtocolRequest;
use App\Http\Requests\StoreThemeTaskRequest;
use App\Mail\newProtocolForTask;
use App\Models\Meeting;
use App\Models\Protocol;
use App\Models\Theme;
use App\Notifications\Push;
use App\Services\Meetings\MeetingService;
use App\Services\Tasks\ThemeTaskService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;

/**
 * Agenda-Themen im Meeting-Kontext. Zugriff über die Meeting-Teilnahme –
 * unabhängig davon, ob das Thema zu einer Gruppe gehört oder frei ist.
 */
class MeetingThemeController extends Controller
{
    public function __construct(
        private readonly MeetingService $meetings,
        private readonly ThemeTaskService $tasks,
    ) {
    }

    /**
     * Neues Thema anlegen oder vorhandenes offenes Thema zuweisen.
     */
    public function store(Request $request, Meeting $meeting): RedirectResponse
    {
        $this->authorize('contribute', $meeting);
        $user = auth()->user();

        if ($request->filled('existing_theme_id')) {
            $request->validate(['existing_theme_id' => ['required', 'integer', 'exists:themes,id']]);
            $theme = Theme::findOrFail($request->integer('existing_theme_id'));

            if (! $this->meetings->canAssignTheme($meeting, $theme, $user)) {
                return redirect()->back()->with([
                    'type'    => 'warning',
                    'Meldung' => 'Dieses Thema kann dem Meeting nicht zugewiesen werden.',
                ]);
            }

            $this->meetings->attachTheme($meeting, $theme);
        } else {
            $data = $request->validate([
                'theme'       => ['required', 'string', 'max:255'],
                'goal'        => ['required', 'string', 'max:1000'],
                'information' => ['nullable', 'string', 'max:20000'],
                'duration'    => ['required', 'integer', 'min:5', 'max:240'],
                'type'        => ['required', 'exists:types,id'],
            ]);

            $theme = $this->meetings->createTheme($meeting, $data, $user);
        }

        return redirect()->route('meetings.show', $meeting)->with([
            'type'    => 'success',
            'Meldung' => 'Thema „' . $theme->theme . '“ steht auf der Agenda.',
        ]);
    }

    public function remove(Meeting $meeting, Theme $theme): RedirectResponse
    {
        $this->authorize('contribute', $meeting);

        $meeting->themes()->detach($theme->id);

        return redirect()->route('meetings.show', $meeting)->with([
            'type'    => 'success',
            'Meldung' => 'Thema wurde von der Agenda entfernt. Das Thema selbst bleibt erhalten.',
        ]);
    }

    public function show(Meeting $meeting, Theme $theme)
    {
        $this->authorize('viewTheme', [$meeting, $theme]);

        $meeting->load(['group', 'themes' => fn ($q) => $q->with('priorities')]);
        $theme->load(['type', 'group', 'ersteller', 'zugewiesen_an', 'protocols.ersteller', 'protocols.media', 'media']);

        $agenda   = $meeting->themes->sortByDesc('priority')->values();
        $position = $agenda->search(fn (Theme $t) => $t->id === $theme->id);

        $user = auth()->user();

        return view('meetings.theme', [
            'meeting'      => $meeting,
            'theme'        => $theme,
            'protocols'    => $theme->protocols->sortByDesc('created_at')->values(),
            'previous'     => $position > 0 ? $agenda[$position - 1] : null,
            'next'         => $agenda[$position + 1] ?? null,
            'position'     => $position + 1,
            'agendaCount'  => $agenda->count(),
            'groupLink'    => $theme->group && $user->groups()->contains('id', $theme->group_id)
                ? url($theme->group->name . '/themes/' . $theme->id)
                : null,
            'editableTime' => (int) config('config.protocols.editableTime', 15),
            'tasks'        => $this->tasks->tasksForTheme($theme),
            'participants' => $meeting->resolvedParticipants(),
        ]);
    }

    public function storeProtocol(ProtocolRequest $request, Meeting $meeting, Theme $theme): RedirectResponse
    {
        $this->authorize('viewTheme', [$meeting, $theme]);

        if ($theme->completed) {
            return redirect()->back()->with([
                'type'    => 'warning',
                'Meldung' => 'Thema ist bereits geschlossen.',
            ]);
        }

        $user     = auth()->user();
        $protocol = Protocol::create([
            'creator_id' => $user->id,
            'theme_id'   => $theme->id,
            'protocol'   => $request->input('protocol'),
        ]);

        if ($request->hasFile('files')) {
            foreach ((array) $request->file('files') as $file) {
                $protocol->addMedia($file)->toMediaCollection();
            }
        }

        if ($theme->type?->type === 'Aufgabe' && (int) $theme->creator_id !== (int) $user->id && $theme->ersteller) {
            Notification::send($theme->ersteller, new Push('neues Protokoll', 'Thema: ' . $theme->theme));
            if ($theme->group) {
                Mail::to($theme->ersteller)->queue(new newProtocolForTask($user->name, $theme, $theme->group->name, $protocol));
            }
        }

        if ((int) $request->input('completed') === 1) {
            $theme->update(['completed' => 1]);
            Protocol::create([
                'creator_id' => $user->id,
                'theme_id'   => $theme->id,
                'protocol'   => 'Thema geschlossen',
            ]);
        }

        return redirect()->route('meetings.themes.show', [$meeting, $theme])->with([
            'type'    => 'success',
            'Meldung' => (int) $request->input('completed') === 1
                ? 'Protokoll gespeichert und Thema geschlossen.'
                : 'Protokoll gespeichert.',
        ]);
    }

    /**
     * Aufgabe zum Thema anlegen – für alle Teilnehmenden oder ausgewählte Personen.
     */
    public function storeTask(StoreThemeTaskRequest $request, Meeting $meeting, Theme $theme): RedirectResponse
    {
        $this->authorize('viewTheme', [$meeting, $theme]);

        $participants = $meeting->resolvedParticipants();

        if ($request->wholeContext()) {
            $assignees = $participants;
        } else {
            $assignees = $participants->whereIn('id', $request->userIds())->values();
            if ($assignees->count() !== count($request->userIds())) {
                return redirect()->back()->withInput()->with([
                    'type'    => 'warning',
                    'Meldung' => 'Aufgaben können nur an Teilnehmende des Meetings vergeben werden.',
                ]);
            }
        }

        $result = $this->tasks->create($theme, $meeting, $assignees, $request->wholeContext(), $request->input('task'), $request->input('date'), auth()->user());

        if ($result['added']->isNotEmpty()) {
            $this->tasks->notify($result['task'], $result['added'], auth()->user());
        }

        return redirect()->route('meetings.themes.show', [$meeting, $theme])->with($this->tasks->resultFlash($result));
    }

    public function updateProtocol(ProtocolRequest $request, Meeting $meeting, Theme $theme, Protocol $protocol): RedirectResponse
    {
        $this->authorize('viewTheme', [$meeting, $theme]);
        abort_unless((int) $protocol->theme_id === (int) $theme->id, 404);

        $editable = (int) $protocol->creator_id === (int) auth()->id()
            && $protocol->created_at->greaterThan(now()->subMinutes((int) config('config.protocols.editableTime', 15)));

        if (! $editable && ! $theme->change_protokoll) {
            return redirect()->back()->with([
                'type'    => 'warning',
                'Meldung' => 'Das Protokoll kann nicht mehr bearbeitet werden.',
            ]);
        }

        $protocol->update(['protocol' => $request->input('protocol')]);

        return redirect()->route('meetings.themes.show', [$meeting, $theme])->with([
            'type'    => 'success',
            'Meldung' => 'Protokoll aktualisiert.',
        ]);
    }
}
