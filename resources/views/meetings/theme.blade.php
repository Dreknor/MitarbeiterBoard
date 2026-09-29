@extends('layouts.app')

@push('css')
    @vite('resources/css/meetings.css')
@endpush

@php
    $systemTexts = ['Thema geschlossen', 'Thema aktiviert', 'Thema in Themenspeicher verschoben'];
    $themeFiles  = $theme->getMedia()->reject(fn ($m) => $m->getCustomProperty('archiviert'))->sortBy('name');
@endphp

@section('content')
<div class="meeting-wrapper" x-data="{ editing: null, showTask: {{ old('assign') !== null ? 'true' : 'false' }} }" x-cloak>

    <nav class="mtg-breadcrumb" aria-label="Brotkrumen">
        <a href="{{ route('meetings.overview') }}"><i class="fas fa-users"></i> Meetings</a>
        <span class="sep">/</span>
        <a href="{{ route('meetings.show', $meeting) }}" class="truncate">{{ $meeting->title }}</a>
        <span class="sep">/</span>
        <span>Thema {{ $position }}</span>
    </nav>

    {{-- Agenda-Navigation --}}
    <div class="flex flex-wrap items-center justify-between gap-3 mb-4">
        <a href="{{ route('meetings.show', $meeting) }}" class="mtg-btn mtg-btn-secondary mtg-btn-sm">
            <i class="fas fa-list-ol"></i> Agenda
        </a>
        <div class="flex items-center gap-2">
            <span class="text-xs text-gray-500 mr-1">Thema {{ $position }} von {{ $agendaCount }}</span>
            @if($previous)
                <a href="{{ route('meetings.themes.show', [$meeting, $previous]) }}" class="mtg-btn mtg-btn-secondary mtg-btn-sm" title="{{ $previous->theme }}">
                    <i class="fas fa-chevron-left"></i> <span class="hidden sm:inline">Vorheriges</span>
                </a>
            @endif
            @if($next)
                <a href="{{ route('meetings.themes.show', [$meeting, $next]) }}" class="mtg-btn mtg-btn-primary mtg-btn-sm" title="{{ $next->theme }}">
                    <span class="hidden sm:inline">Nächstes</span> <i class="fas fa-chevron-right"></i>
                </a>
            @endif
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <div class="lg:col-span-2 space-y-6">

            {{-- Thema --}}
            <article class="mtg-card">
                <div class="p-5 sm:p-6">
                    <div class="flex flex-wrap items-center gap-1.5 mb-3">
                        @if($theme->group)
                            <span class="mtg-badge mtg-badge-group"><i class="fas fa-users"></i> {{ $theme->group->name }}</span>
                        @else
                            <span class="mtg-badge mtg-badge-free"><i class="fas fa-globe"></i> Freies Thema</span>
                        @endif
                        @if($theme->type)<span class="mtg-badge mtg-badge-gray">{{ $theme->type->type }}</span>@endif
                        <span class="mtg-badge mtg-badge-gray"><i class="far fa-clock"></i> {{ $theme->duration }} min</span>
                        @if($theme->completed)
                            <span class="mtg-badge mtg-badge-gray"><i class="fas fa-lock"></i> abgeschlossen</span>
                        @endif
                    </div>
                    <h1 class="text-2xl font-bold text-gray-900 break-words">{{ $theme->theme }}</h1>
                    @if($theme->goal)
                        <div class="mt-3 flex items-start gap-2 text-sm text-gray-700 bg-blue-50/60 border border-blue-100 rounded-xl px-3 py-2">
                            <i class="fas fa-bullseye text-blue-500 mt-0.5"></i>
                            <span><strong>Ziel:</strong> {{ $theme->goal }}</span>
                        </div>
                    @endif
                    @if($theme->information)
                        <div class="mtg-prose mt-4">{!! $theme->information !!}</div>
                    @endif
                    @if($themeFiles->isNotEmpty())
                        <ul class="mt-4 flex flex-wrap gap-2">
                            @foreach($themeFiles as $media)
                                <li>
                                    <a href="{{ url('/image/' . $media->id) }}" target="_blank" class="mtg-btn mtg-btn-secondary mtg-btn-sm">
                                        <i class="fas fa-paperclip"></i> {{ $media->name }}
                                    </a>
                                </li>
                            @endforeach
                        </ul>
                    @endif
                </div>
            </article>

            {{-- Neuer Protokolleintrag --}}
            @if(! $theme->completed)
                <section class="mtg-card">
                    <div class="mtg-card-head">
                        <h2 class="mtg-card-title"><i class="fas fa-pen-nib"></i> Protokollieren</h2>
                    </div>
                    <form action="{{ route('meetings.themes.protocols.store', [$meeting, $theme]) }}" method="POST" enctype="multipart/form-data" class="p-5 space-y-3">
                        @csrf
                        <textarea name="protocol" id="protocol_new" class="mtg-textarea mtg-editor" rows="6">{{ old('protocol') }}</textarea>
                        @error('protocol')<p class="text-sm text-red-600">{{ $message }}</p>@enderror
                        <div class="flex flex-wrap items-center justify-between gap-3">
                            <div class="flex flex-wrap items-center gap-4">
                                <label class="inline-flex items-center gap-2 text-sm text-gray-600 cursor-pointer">
                                    <i class="fas fa-paperclip text-gray-400"></i>
                                    <input type="file" name="files[]" multiple class="text-xs">
                                </label>
                                <label class="inline-flex items-center gap-2 text-sm text-gray-700 cursor-pointer">
                                    <input type="checkbox" name="completed" value="1" class="accent-blue-600">
                                    Thema damit abschließen
                                </label>
                            </div>
                            <button type="submit" class="mtg-btn mtg-btn-primary"><i class="fas fa-save"></i> Speichern</button>
                        </div>
                    </form>
                </section>
            @endif

            {{-- Protokollverlauf --}}
            <section class="mtg-card">
                <div class="mtg-card-head">
                    <h2 class="mtg-card-title"><i class="fas fa-clipboard-list"></i> Protokoll <span class="text-gray-400 font-normal text-sm">({{ $protocols->count() }})</span></h2>
                </div>
                <div class="p-5">
                    @if($protocols->isEmpty())
                        <p class="text-sm text-gray-400 italic">Noch keine Einträge.</p>
                    @else
                        <ol class="mtg-timeline">
                            @foreach($protocols as $protocol)
                                @php
                                    $isSystem = in_array(strip_tags($protocol->protocol), $systemTexts, true) || $protocol->isChanged();
                                    $canEdit  = ! $theme->completed && ! $isSystem && (
                                        $theme->change_protokoll
                                        || ((int) $protocol->creator_id === (int) auth()->id() && $protocol->created_at->greaterThan(now()->subMinutes($editableTime)))
                                    );
                                @endphp
                                <li class="mtg-timeline-item {{ $isSystem ? 'is-system' : '' }}">
                                    <span class="mtg-timeline-dot"></span>
                                    <div class="flex flex-wrap items-center justify-between gap-2 mb-1">
                                        <div class="text-xs text-gray-500">
                                            <strong class="text-gray-700">{{ $protocol->ersteller->name }}</strong>
                                            · {{ $protocol->created_at->format('d.m.Y H:i') }}
                                            @if($protocol->created_at->isSameDay($meeting->date))
                                                <span class="mtg-badge mtg-badge-blue ml-1">dieses Meeting</span>
                                            @endif
                                        </div>
                                        @if($canEdit)
                                            <button type="button" class="mtg-btn mtg-btn-ghost mtg-btn-sm" @click="editing = editing === {{ $protocol->id }} ? null : {{ $protocol->id }}">
                                                <i class="fas fa-pen"></i> bearbeiten
                                            </button>
                                        @endif
                                    </div>

                                    @if($isSystem)
                                        <p class="text-sm text-gray-500 italic">{{ strip_tags($protocol->protocol) }}</p>
                                    @else
                                        <div class="mtg-prose" x-show="editing !== {{ $protocol->id }}">{!! $protocol->protocol !!}</div>
                                    @endif

                                    @if($canEdit)
                                        <form x-show="editing === {{ $protocol->id }}" style="display:none;" method="POST" class="space-y-2 mt-2"
                                              action="{{ route('meetings.themes.protocols.update', [$meeting, $theme, $protocol]) }}">
                                            @csrf
                                            @method('PUT')
                                            <textarea name="protocol" class="mtg-textarea mtg-editor" rows="5">{{ $protocol->protocol }}</textarea>
                                            <div class="flex justify-end gap-2">
                                                <button type="button" class="mtg-btn mtg-btn-secondary mtg-btn-sm" @click="editing = null">Abbrechen</button>
                                                <button type="submit" class="mtg-btn mtg-btn-primary mtg-btn-sm"><i class="fas fa-save"></i> Speichern</button>
                                            </div>
                                        </form>
                                    @endif

                                    @if($protocol->media->isNotEmpty())
                                        <ul class="mt-2 flex flex-wrap gap-2">
                                            @foreach($protocol->media->sortBy('name') as $media)
                                                <li>
                                                    <a href="{{ url('/image/' . $media->id) }}" target="_blank" class="mtg-link text-xs">
                                                        <i class="fas fa-file-download"></i> {{ $media->name }}
                                                    </a>
                                                </li>
                                            @endforeach
                                        </ul>
                                    @endif
                                </li>
                            @endforeach
                        </ol>
                    @endif
                </div>
            </section>
        </div>

        {{-- Seitenspalte --}}
        <aside class="space-y-6">
            <section class="mtg-card p-5 space-y-3 text-sm">
                <h2 class="mtg-section-title">Details</h2>
                <dl class="space-y-2">
                    <div class="flex justify-between gap-3"><dt class="text-gray-500">Eingebracht von</dt><dd class="text-gray-900 text-right">{{ $theme->ersteller?->name }}</dd></div>
                    @if($theme->zugewiesen_an)
                        <div class="flex justify-between gap-3"><dt class="text-gray-500">Zuständig</dt><dd class="text-gray-900 text-right">{{ $theme->zugewiesen_an->name }}</dd></div>
                    @endif
                    <div class="flex justify-between gap-3"><dt class="text-gray-500">Angelegt</dt><dd class="text-gray-900 text-right">{{ $theme->created_at?->format('d.m.Y') }}</dd></div>
                    <div class="flex justify-between gap-3"><dt class="text-gray-500">Meeting</dt><dd class="text-right"><a href="{{ route('meetings.show', $meeting) }}" class="mtg-link">{{ $meeting->date->format('d.m.Y') }}</a></dd></div>
                </dl>
                @if($groupLink)
                    <a href="{{ $groupLink }}" class="mtg-btn mtg-btn-secondary mtg-btn-sm w-full">
                        <i class="fas fa-external-link-alt"></i> In Gruppe „{{ $theme->group->name }}“ öffnen
                    </a>
                    <p class="mtg-hint">Dort stehen Umfragen, Aufgaben, Teilen und Dateiverwaltung zur Verfügung.</p>
                @endif

                @if($moveTargets->isNotEmpty() || $canMakeFree)
                    <form action="{{ route('meetings.themes.move', [$meeting, $theme]) }}" method="POST" class="space-y-2 pt-3 border-t border-gray-100">
                        @csrf
                        @method('PUT')
                        <label class="mtg-label" for="move_group">Gruppe ändern</label>
                        <select id="move_group" name="group_id" class="mtg-select">
                            @foreach($moveTargets as $targetGroup)
                                <option value="{{ $targetGroup->id }}">Gruppe {{ $targetGroup->name }}</option>
                            @endforeach
                            @if($canMakeFree)
                                <option value="">Freies Thema (nur in diesem Meeting)</option>
                            @endif
                        </select>
                        <button type="submit" class="mtg-btn mtg-btn-secondary mtg-btn-sm w-full"><i class="fas fa-exchange-alt"></i> Zuordnung ändern</button>
                        <p class="mtg-hint">Einer Gruppe zugeordnete Themen bleiben in deren Themenliste erhalten, wenn sie hier nicht abgeschlossen werden.</p>
                    </form>
                @endif
            </section>

            {{-- Aufgaben --}}
            <section class="mtg-card" aria-labelledby="tasks-title">
                <div class="mtg-card-head">
                    <h2 id="tasks-title" class="mtg-card-title"><i class="far fa-check-square"></i> Aufgaben
                        <span class="text-gray-400 font-normal text-sm">({{ $tasks->where('completed', false)->count() }} offen)</span>
                    </h2>
                    @if(! $theme->completed)
                        <button type="button" class="mtg-btn mtg-btn-secondary mtg-btn-sm" @click="showTask = true"><i class="fas fa-plus"></i> Aufgabe</button>
                    @endif
                </div>
                <div class="p-4">
                    @include('tasks.partials.theme_tasks', ['tasks' => $tasks, 'ui' => 'mtg'])
                </div>
            </section>

            @if($next)
                <a href="{{ route('meetings.themes.show', [$meeting, $next]) }}" class="mtg-card p-4 flex items-center gap-3 hover:border-blue-200">
                    <span class="mtg-agenda-num">{{ $position + 1 }}</span>
                    <span class="flex-1 min-w-0">
                        <span class="block text-xs text-gray-500">Als Nächstes</span>
                        <span class="block text-sm font-semibold text-gray-900 truncate">{{ $next->theme }}</span>
                    </span>
                    <i class="fas fa-chevron-right text-gray-300"></i>
                </a>
            @endif
        </aside>
    </div>
    {{-- Modal: Aufgabe vergeben --}}
    <div class="mtg-modal-backdrop" x-show="showTask" x-transition.opacity @keydown.escape.window="showTask = false" style="display:none;">
        <div class="mtg-modal" @click.outside="showTask = false">
            <div class="mtg-modal-header">
                <h3 class="mtg-modal-title">Aufgabe vergeben</h3>
                <button type="button" class="mtg-modal-close" @click="showTask = false" aria-label="Schließen">&times;</button>
            </div>
            <div class="mtg-modal-body">
                @if($errors->has('task') || $errors->has('date') || $errors->has('users'))
                    <div class="mtg-alert mtg-alert-warning">{{ $errors->first() }}</div>
                @endif
                @include('tasks.partials.task_form', [
                    'action'   => route('meetings.themes.tasks.store', [$meeting, $theme]),
                    'users'    => $participants,
                    'allLabel' => 'Alle Teilnehmenden',
                    'ui'       => 'mtg',
                    'cancel'   => 'showTask = false',
                ])
            </div>
        </div>
    </div>
</div>
@endsection

@push('js')
    <script src="{{ asset('js/plugins/tinymce/tinymce.min.js') }}"></script>
    <script src="{{ asset('js/plugins/tinymce/langs/de.js') }}"></script>
    <script>
        tinymce.init({
            selector: 'textarea.mtg-editor',
            lang: 'de',
            height: 280,
            menubar: false,
            autosave_ask_before_unload: true,
            autosave_interval: '40s',
            plugins: [
                'advlist autolink lists link charmap',
                'searchreplace visualblocks code',
                'insertdatetime table paste code wordcount autosave',
            ],
            toolbar: 'undo redo | bold italic backcolor forecolor | bullist numlist outdent indent | table link | removeformat | restoredraft',
            table_default_attributes: { border: '1' },
            setup: function (editor) {
                editor.on('change', function () { editor.save(); });
            }
        });
    </script>
@endpush
