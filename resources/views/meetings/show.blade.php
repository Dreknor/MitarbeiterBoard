@extends('layouts.app')

@push('css')
    @vite('resources/css/meetings.css')
@endpush

@php
    $isCancelled = (bool) $meeting->cancelled;
    $isPast      = $meeting->date->lt(now()->startOfDay());
    $heroClass   = $isCancelled ? 'mtg-hero-cancelled' : ($isPast ? 'mtg-hero-past' : ($meeting->isFree() ? 'mtg-hero-free' : 'mtg-hero-group'));
    $isOver      = $themesDuration > $meetingDuration;
    $timeShare   = $meetingDuration > 0 ? min(100, round($themesDuration / $meetingDuration * 100)) : 0;
    $initials    = fn ($name) => \Illuminate\Support\Str::of($name)->explode(' ')->map(fn ($p) => \Illuminate\Support\Str::substr($p, 0, 1))->take(2)->implode('');
    $isGroupMember = $meeting->group_id && auth()->user()->groups()->contains('id', $meeting->group_id);
    $firstOpen   = $agenda->first(fn ($t) => ! $t->completed && ! $protokolliertIds->contains($t->id));
    $organizerIds = collect($selection['organizers'])->push($meeting->creator_id)->filter()->unique();
    $editOpen    = $errors->any() && old('title') !== null;
@endphp

@section('content')
<div class="meeting-wrapper"
     x-data="{ showEdit: {{ $editOpen ? 'true' : 'false' }}, showAddTheme: {{ $errors->any() && old('theme') !== null ? 'true' : 'false' }}, themeTab: 'new', showInvite: false, showAllPeople: false }"
     x-cloak>

    <nav class="mtg-breadcrumb" aria-label="Brotkrumen">
        <a href="{{ route('meetings.overview') }}"><i class="fas fa-users"></i> Meetings</a>
        @if($meeting->group && $isGroupMember)
            <span class="sep">/</span>
            <a href="{{ route('meetings.index', ['group' => $meeting->group->name]) }}">{{ $meeting->group->name }}</a>
        @endif
        <span class="sep">/</span>
        <span class="truncate">{{ $meeting->title }}</span>
    </nav>

    {{-- Hero --}}
    <header class="mtg-hero {{ $heroClass }} mb-6">
        <div class="flex flex-wrap items-start justify-between gap-4">
            <div class="min-w-0">
                <div class="flex flex-wrap items-center gap-2 mb-2">
                    <span class="mtg-badge {{ $meeting->isFree() ? 'mtg-badge-free' : 'mtg-badge-group' }}">
                        <i class="fas {{ $meeting->isFree() ? 'fa-globe' : 'fa-users' }}"></i> {{ $meeting->contextLabel() }}
                    </span>
                    @if($isLive)
                        <span class="mtg-badge mtg-badge-live"><span class="mtg-live-dot"></span> läuft gerade</span>
                    @elseif($isCancelled)
                        <span class="mtg-badge mtg-badge-red">Abgesagt</span>
                    @elseif($meeting->date->isToday())
                        <span class="mtg-badge mtg-badge-blue">Heute</span>
                    @elseif($isPast)
                        <span class="mtg-badge mtg-badge-gray">Vergangen</span>
                    @endif
                </div>
                <h1 class="text-2xl font-bold leading-tight break-words text-gray-900">{{ $meeting->title }}</h1>
                <div class="mtg-meta mt-3">
                    <span><i class="far fa-calendar-alt"></i>{{ $meeting->date->locale('de')->isoFormat('dddd, D. MMMM YYYY') }}</span>
                    <span><i class="far fa-clock"></i>{{ $meeting->start_time }} – {{ $meeting->end_time }} Uhr</span>
                    @if($meeting->roomBooking?->room)
                        <span><i class="fas fa-door-open"></i>{{ $meeting->roomBooking->room->name }}@if($meeting->roomBooking->room->room_number) (Nr. {{ $meeting->roomBooking->room->room_number }})@endif</span>
                    @endif
                    @if($meeting->location)
                        <span><i class="fas fa-map-marker-alt"></i>{{ $meeting->location }}</span>
                    @endif
                    @if($meeting->creator)
                        <span><i class="fas fa-crown"></i>{{ $meeting->creator->name }}</span>
                    @endif
                </div>
            </div>

            <div class="flex flex-wrap items-center gap-2 shrink-0">
                @if($meeting->effectiveMeetingUrl())
                    <a href="{{ $meeting->effectiveMeetingUrl() }}" target="_blank" rel="noopener" class="mtg-btn mtg-btn-primary">
                        <i class="fas fa-video"></i> Beitreten
                    </a>
                @endif
                @if($canManage)
                    <button type="button" class="mtg-btn mtg-btn-secondary" @click="showEdit = true"><i class="fas fa-pen"></i> Bearbeiten</button>
                    <div class="relative" x-data="{ more: false }" @click.outside="more = false">
                        <button type="button" class="mtg-btn-icon mtg-btn-secondary" @click="more = !more" aria-label="Weitere Aktionen" :aria-expanded="more.toString()">
                            <i class="fas fa-ellipsis-v"></i>
                        </button>
                        <div x-show="more" x-transition.opacity style="display:none;"
                             class="absolute right-0 mt-2 w-56 rounded-xl bg-white shadow-lg border border-gray-100 py-1 z-20 text-gray-700">
                            @if(! $isCancelled)
                                <form action="{{ route('meetings.details.cancel', $meeting) }}" method="POST"
                                      onsubmit="return confirm('Meeting wirklich absagen? Eine Raumbuchung wird freigegeben.');">
                                    @csrf
                                    <button type="submit" class="mtg-picker-option"><i class="fas fa-ban w-4 text-amber-500"></i> Absagen</button>
                                </form>
                            @else
                                <form action="{{ route('meetings.details.reactivate', $meeting) }}" method="POST">
                                    @csrf
                                    <button type="submit" class="mtg-picker-option"><i class="fas fa-undo w-4 text-emerald-600"></i> Wieder aktivieren</button>
                                </form>
                            @endif
                            <form action="{{ route('meetings.details.destroy', $meeting) }}" method="POST"
                                  onsubmit="return confirm('Meeting löschen? Die Themen und Protokolle bleiben erhalten.');">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="mtg-picker-option is-danger"><i class="fas fa-trash w-4"></i> Löschen</button>
                            </form>
                        </div>
                    </div>
                @endif
            </div>
        </div>
    </header>

    @if($isLive && $firstOpen)
        <div class="mtg-alert mtg-alert-success flex flex-wrap items-center justify-between gap-3">
            <span><i class="fas fa-play-circle mr-1"></i> Das Meeting läuft. Nächstes offenes Thema: <strong>{{ $firstOpen->theme }}</strong></span>
            <a href="{{ route('meetings.themes.show', [$meeting, $firstOpen]) }}" class="mtg-btn mtg-btn-success mtg-btn-sm">Zum Thema <i class="fas fa-arrow-right"></i></a>
        </div>
    @endif

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

        {{-- Hauptspalte --}}
        <div class="lg:col-span-2 space-y-6">

            @if($meeting->description)
                <div class="mtg-card p-5">
                    <h2 class="mtg-section-title mb-2">Anlass</h2>
                    <p class="text-sm text-gray-700 whitespace-pre-line">{{ $meeting->description }}</p>
                </div>
            @endif

            {{-- Agenda --}}
            <section class="mtg-card" aria-labelledby="agenda-title">
                <div class="mtg-card-head">
                    <div>
                        <h2 id="agenda-title" class="mtg-card-title"><i class="fas fa-list-ol"></i> Agenda</h2>
                        <p class="text-xs text-gray-500 mt-0.5">
                            {{ $agenda->count() }} {{ $agenda->count() === 1 ? 'Thema' : 'Themen' }} ·
                            {{ $themesDuration }} von {{ $meetingDuration }} Minuten verplant
                        </p>
                    </div>
                    <button type="button" class="mtg-btn mtg-btn-primary mtg-btn-sm" @click="showAddTheme = true">
                        <i class="fas fa-plus"></i> Thema
                    </button>
                </div>
                <div class="px-5 pt-3">
                    <div class="mtg-timebar {{ $isOver ? 'is-over' : '' }}" title="{{ $timeShare }} % der Zeit verplant">
                        <span style="width: {{ $timeShare }}%"></span>
                    </div>
                    @if($isOver)
                        <p class="text-xs text-amber-700 mt-1.5"><i class="fas fa-exclamation-triangle"></i> Die Themen benötigen {{ $themesDuration - $meetingDuration }} Minuten mehr als geplant.</p>
                    @endif
                </div>

                @if($agenda->isEmpty())
                    <div class="px-5 py-10 text-center">
                        <i class="far fa-clipboard text-3xl text-gray-300 mb-3 block"></i>
                        <p class="text-sm text-gray-500">Noch keine Themen. Lege ein neues Thema an oder übernimm ein offenes aus deinen Gruppen.</p>
                    </div>
                @else
                    <ol class="mt-3">
                        @foreach($agenda as $i => $theme)
                            @php
                                $state = $theme->completed ? 'is-closed' : ($protokolliertIds->contains($theme->id) ? 'is-done' : '');
                                $foreignContext = (int) $theme->group_id !== (int) $meeting->group_id;
                            @endphp
                            <li class="mtg-agenda-item {{ $state }}">
                                <span class="mtg-agenda-num">
                                    @if($state === 'is-done')<i class="fas fa-check text-xs"></i>@else{{ $i + 1 }}@endif
                                </span>
                                <div class="flex-1 min-w-0">
                                    <a href="{{ route('meetings.themes.show', [$meeting, $theme]) }}" class="mtg-agenda-title break-words">{{ $theme->theme }}</a>
                                    @if($theme->goal)
                                        <p class="text-sm text-gray-500 mt-0.5 line-clamp-2">{{ $theme->goal }}</p>
                                    @endif
                                    <div class="flex flex-wrap items-center gap-1.5 mt-2">
                                        <span class="mtg-badge mtg-badge-gray"><i class="far fa-clock"></i> {{ $theme->duration }} min</span>
                                        @if($theme->type)
                                            <span class="mtg-badge mtg-badge-gray">{{ $theme->type->type }}</span>
                                        @endif
                                        @if($foreignContext)
                                            @if($theme->group)
                                                <span class="mtg-badge mtg-badge-group" title="Thema aus einer Gruppe"><i class="fas fa-users"></i> {{ $theme->group->name }}</span>
                                            @else
                                                <span class="mtg-badge mtg-badge-free"><i class="fas fa-globe"></i> Frei</span>
                                            @endif
                                        @endif
                                        @if($theme->completed)
                                            <span class="mtg-badge mtg-badge-gray"><i class="fas fa-lock"></i> abgeschlossen</span>
                                        @elseif($state === 'is-done')
                                            <span class="mtg-badge mtg-badge-green"><i class="fas fa-check"></i> protokolliert</span>
                                        @endif
                                        @if($theme->tasks_count)
                                            <span class="mtg-badge mtg-badge-amber" title="offene Aufgaben"><i class="far fa-check-square"></i> {{ $theme->tasks_count }}</span>
                                        @endif
                                        @if($theme->protocols_count)
                                            <span class="mtg-badge mtg-badge-blue"><i class="fas fa-clipboard-list"></i> {{ $theme->protocols_count }}</span>
                                        @endif
                                        <span class="text-xs text-gray-400 ml-1">von {{ $theme->ersteller?->name }}</span>
                                    </div>
                                </div>
                                <div class="flex items-center gap-1 shrink-0">
                                    <a href="{{ route('meetings.themes.show', [$meeting, $theme]) }}" class="mtg-btn-icon text-gray-500 hover:bg-gray-100" title="Öffnen" aria-label="Thema öffnen">
                                        <i class="fas fa-arrow-right"></i>
                                    </a>
                                    <form action="{{ route('meetings.agenda.remove', [$meeting, $theme]) }}" method="POST"
                                          onsubmit="return confirm('Thema von der Agenda entfernen? Das Thema selbst bleibt erhalten.');">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="mtg-btn-icon bg-transparent text-gray-400 hover:text-red-600 hover:bg-red-50" title="Von der Agenda entfernen" aria-label="Von der Agenda entfernen">
                                            <i class="fas fa-unlink"></i>
                                        </button>
                                    </form>
                                </div>
                            </li>
                        @endforeach
                    </ol>
                @endif
            </section>
        </div>

        {{-- Seitenspalte --}}
        <aside class="space-y-6">

            {{-- Teilnehmende --}}
            <section class="mtg-card">
                <div class="mtg-card-head">
                    <h2 class="mtg-card-title"><i class="fas fa-user-friends"></i> Teilnehmende <span class="text-gray-400 font-normal text-sm">({{ $participants->count() }})</span></h2>
                    @if($canManage)
                        <button type="button" class="mtg-btn mtg-btn-ghost mtg-btn-sm" @click="showEdit = true"><i class="fas fa-user-plus"></i></button>
                    @endif
                </div>
                <div class="p-5 space-y-4">
                    @if($meeting->group || $meeting->participantGroups->isNotEmpty() || $meeting->participantRoles->isNotEmpty())
                        <div class="flex flex-wrap gap-1.5">
                            @if($meeting->group)
                                <span class="mtg-chip mtg-chip-group pr-2.5"><i class="fas fa-users"></i> {{ $meeting->group->name }} <span class="opacity-60">(Gruppe)</span></span>
                            @endif
                            @foreach($meeting->participantGroups as $g)
                                <span class="mtg-chip mtg-chip-group pr-2.5"><i class="fas fa-users"></i> {{ $g->name }}</span>
                            @endforeach
                            @foreach($meeting->participantRoles as $r)
                                <span class="mtg-chip mtg-chip-role pr-2.5"><i class="fas fa-id-badge"></i> {{ $r->name }}</span>
                            @endforeach
                        </div>
                    @endif

                    <ul>
                        @foreach($participants as $index => $person)
                            <li class="mtg-person" @if($index >= 8) x-show="showAllPeople" style="display:none;" @endif>
                                <span class="mtg-avatar mtg-avatar-sm">{{ $initials($person->name) }}</span>
                                <span class="text-sm text-gray-800 flex-1 truncate">{{ $person->name }}</span>
                                @if($organizerIds->contains($person->id))
                                    <i class="fas fa-crown text-amber-500 text-xs" title="Organisation"></i>
                                @endif
                            </li>
                        @endforeach
                    </ul>
                    @if($participants->count() > 8)
                        <button type="button" class="text-xs font-semibold text-blue-600 hover:text-blue-800 bg-transparent border-0 cursor-pointer p-0"
                                @click="showAllPeople = !showAllPeople"
                                x-text="showAllPeople ? 'Weniger anzeigen' : 'Alle {{ $participants->count() }} anzeigen'"></button>
                    @endif
                </div>
                @if($canManage)
                    <div class="px-5 pb-5">
                        <button type="button" class="mtg-btn mtg-btn-secondary w-full" @click="showInvite = true">
                            <i class="far fa-paper-plane"></i> Einladung versenden
                        </button>
                        @if($meeting->invitation_sent_at)
                            <p class="mtg-hint text-center mt-2">
                                Zuletzt am {{ $meeting->invitation_sent_at->format('d.m.Y H:i') }}@if($meeting->invitationSender) von {{ $meeting->invitationSender->name }}@endif
                            </p>
                        @endif
                    </div>
                @endif
            </section>

            {{-- Rollen im Meeting --}}
            <section class="mtg-card">
                <div class="mtg-card-head">
                    <h2 class="mtg-card-title"><i class="fas fa-user-tag"></i> Rollen</h2>
                </div>
                <div class="p-5">
                    @if($meeting->meetingTasks->isNotEmpty())
                        <ul class="space-y-2 mb-4">
                            @foreach($meeting->meetingTasks as $task)
                                <li class="flex items-center gap-2 text-sm">
                                    <span class="mtg-badge mtg-badge-blue">{{ $task->role }}</span>
                                    <span class="flex-1 truncate text-gray-800">{{ $task->user?->name }}@if($task->notes) <span class="text-gray-400">· {{ $task->notes }}</span>@endif</span>
                                    <form action="{{ route('meetings.details.tasks.destroy', [$meeting, $task]) }}" method="POST">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="mtg-btn-icon bg-transparent text-gray-400 hover:text-red-600 hover:bg-red-50" aria-label="Rolle entfernen"><i class="fas fa-times text-xs"></i></button>
                                    </form>
                                </li>
                            @endforeach
                        </ul>
                    @else
                        <p class="text-sm text-gray-400 italic mb-4">Noch keine Rollen (z. B. Moderation, Protokoll) vergeben.</p>
                    @endif
                    <form action="{{ route('meetings.details.tasks.store', $meeting) }}" method="POST" class="space-y-2">
                        @csrf
                        <div class="grid grid-cols-2 gap-2">
                            <input type="text" name="role" class="mtg-input" placeholder="Rolle" list="mtg-role-suggestions" required maxlength="255">
                            <select name="user_id" class="mtg-select" required>
                                <option value="">Person …</option>
                                @foreach($participants as $person)
                                    <option value="{{ $person->id }}">{{ $person->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <datalist id="mtg-role-suggestions">
                            <option value="Moderation"><option value="Protokoll"><option value="Zeitwächter:in">
                        </datalist>
                        <button type="submit" class="mtg-btn mtg-btn-secondary mtg-btn-sm w-full"><i class="fas fa-plus"></i> Rolle vergeben</button>
                    </form>
                </div>
            </section>

            @if($meeting->group && $isGroupMember && $meeting->date->isToday())
                <a href="{{ url($meeting->group->name . '/presence/' . $meeting->date->format('Ymd')) }}" class="mtg-btn mtg-btn-secondary w-full">
                    <i class="far fa-edit"></i> Anwesenheit erfassen
                </a>
            @endif
        </aside>
    </div>

    {{-- ============ Modals ============ --}}

    {{-- Thema hinzufügen --}}
    <div class="mtg-modal-backdrop" x-show="showAddTheme" x-transition.opacity @keydown.escape.window="showAddTheme = false" style="display:none;">
        <div class="mtg-modal mtg-modal-lg" @click.outside="showAddTheme = false">
            <div class="mtg-modal-header">
                <h3 class="mtg-modal-title">Thema auf die Agenda setzen</h3>
                <button type="button" class="mtg-modal-close" @click="showAddTheme = false" aria-label="Schließen">&times;</button>
            </div>
            <form action="{{ route('meetings.agenda.store', $meeting) }}" method="POST" class="flex flex-col min-h-0">
                @csrf
                <div class="mtg-modal-body">
                    <div class="mtg-tabs" role="tablist">
                        <button type="button" class="mtg-tab" :class="{ 'is-active': themeTab === 'new' }" @click="themeTab = 'new'"><i class="fas fa-plus"></i> Neues Thema</button>
                        <button type="button" class="mtg-tab" :class="{ 'is-active': themeTab === 'existing' }" @click="themeTab = 'existing'"><i class="fas fa-link"></i> Offenes Thema übernehmen</button>
                    </div>

                    <div x-show="themeTab === 'new'" class="space-y-3">
                        <div>
                            <label class="mtg-label" for="new_theme">Titel <span class="mtg-required">*</span></label>
                            <input type="text" id="new_theme" name="theme" class="mtg-input" maxlength="255" value="{{ old('theme') }}" :required="themeTab === 'new'" :disabled="themeTab !== 'new'">
                        </div>
                        <div>
                            <label class="mtg-label" for="new_goal">Ziel <span class="mtg-required">*</span></label>
                            <input type="text" id="new_goal" name="goal" class="mtg-input" maxlength="1000" placeholder="Was soll am Ende feststehen?" value="{{ old('goal') }}" :required="themeTab === 'new'" :disabled="themeTab !== 'new'">
                        </div>
                        <div class="grid grid-cols-2 gap-3">
                            <div>
                                <label class="mtg-label" for="new_duration">Dauer (Min.) <span class="mtg-required">*</span></label>
                                <input type="number" id="new_duration" name="duration" class="mtg-input" min="5" max="240" step="5" value="{{ old('duration', 15) }}" :required="themeTab === 'new'" :disabled="themeTab !== 'new'">
                            </div>
                            <div>
                                <label class="mtg-label" for="new_type">Typ <span class="mtg-required">*</span></label>
                                <select id="new_type" name="type" class="mtg-select" :required="themeTab === 'new'" :disabled="themeTab !== 'new'">
                                    @foreach($types as $type)
                                        <option value="{{ $type->id }}" @selected((string) old('type') === (string) $type->id)>{{ $type->type }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                        <div>
                            <label class="mtg-label" for="new_information">Informationen</label>
                            <textarea id="new_information" name="information" class="mtg-textarea" rows="3" :disabled="themeTab !== 'new'">{{ old('information') }}</textarea>
                        </div>
                        <p class="mtg-hint">
                            @if($meeting->isFree())
                                <i class="fas fa-globe"></i> Das Thema wird als <strong>freies Thema</strong> angelegt und ist für alle Teilnehmenden sichtbar.
                            @else
                                <i class="fas fa-users"></i> Das Thema wird in der Gruppe <strong>{{ $meeting->contextLabel() }}</strong> angelegt.
                            @endif
                        </p>
                    </div>

                    <div x-show="themeTab === 'existing'" style="display:none;">
                        @if($assignableThemes->isEmpty())
                            <p class="text-sm text-gray-500">Es gibt keine offenen Themen, die du übernehmen kannst.</p>
                        @else
                            <label class="mtg-label" for="existing_theme">Offenes Thema</label>
                            <select id="existing_theme" name="existing_theme_id" class="mtg-select" :disabled="themeTab !== 'existing'" :required="themeTab === 'existing'">
                                <option value="">– Thema wählen –</option>
                                @foreach($assignableThemes as $label => $themes)
                                    <optgroup label="{{ $label }}">
                                        @foreach($themes as $open)
                                            <option value="{{ $open->id }}">{{ $open->theme }} ({{ $open->duration }} min{{ $open->memory ? ', Themenspeicher' : '' }})</option>
                                        @endforeach
                                    </optgroup>
                                @endforeach
                            </select>
                            <p class="mtg-hint">Themen bleiben in ihrer Gruppe – sie werden für dieses Meeting nur verknüpft und sind für alle Teilnehmenden hier sichtbar.</p>
                        @endif
                    </div>
                </div>
                <div class="mtg-modal-footer">
                    <button type="button" class="mtg-btn mtg-btn-secondary" @click="showAddTheme = false">Abbrechen</button>
                    <button type="submit" class="mtg-btn mtg-btn-primary"><i class="fas fa-check"></i> Auf die Agenda</button>
                </div>
            </form>
        </div>
    </div>

    @if($canManage)
        {{-- Bearbeiten --}}
        <div class="mtg-modal-backdrop" x-show="showEdit" x-transition.opacity @keydown.escape.window="showEdit = false" style="display:none;">
            <div class="mtg-modal mtg-modal-lg" @click.outside="showEdit = false">
                <div class="mtg-modal-header">
                    <h3 class="mtg-modal-title">Meeting bearbeiten</h3>
                    <button type="button" class="mtg-modal-close" @click="showEdit = false" aria-label="Schließen">&times;</button>
                </div>
                <form action="{{ route('meetings.details.update', $meeting) }}" method="POST" class="flex flex-col min-h-0">
                    @csrf
                    @method('PUT')
                    <div class="mtg-modal-body">
                        @include('meetings.partials.meeting_form', ['meeting' => $meeting])
                    </div>
                    <div class="mtg-modal-footer">
                        <button type="button" class="mtg-btn mtg-btn-secondary" @click="showEdit = false">Abbrechen</button>
                        <button type="submit" class="mtg-btn mtg-btn-primary"><i class="fas fa-save"></i> Speichern</button>
                    </div>
                </form>
            </div>
        </div>

        {{-- Einladung --}}
        <div class="mtg-modal-backdrop" x-show="showInvite" x-transition.opacity @keydown.escape.window="showInvite = false" style="display:none;">
            <div class="mtg-modal" @click.outside="showInvite = false">
                <div class="mtg-modal-header">
                    <h3 class="mtg-modal-title">Einladung versenden</h3>
                    <button type="button" class="mtg-modal-close" @click="showInvite = false" aria-label="Schließen">&times;</button>
                </div>
                <form action="{{ route('meetings.details.invite', $meeting) }}" method="POST">
                    @csrf
                    <div class="mtg-modal-body">
                        <p class="text-sm text-gray-600 mb-3">
                            Die Einladung (mit Kalendereintrag) geht an <strong>{{ $participants->count() }} Teilnehmende</strong>.
                        </p>
                        <label class="mtg-label" for="invite_message">Persönliche Nachricht (optional)</label>
                        <textarea id="invite_message" name="message" class="mtg-textarea" rows="3" maxlength="2000" placeholder="z. B. Bitte die Unterlagen vorab lesen."></textarea>
                    </div>
                    <div class="mtg-modal-footer">
                        <button type="button" class="mtg-btn mtg-btn-secondary" @click="showInvite = false">Abbrechen</button>
                        <button type="submit" class="mtg-btn mtg-btn-primary"><i class="far fa-paper-plane"></i> Versenden</button>
                    </div>
                </form>
            </div>
        </div>
    @endif
</div>
@endsection
