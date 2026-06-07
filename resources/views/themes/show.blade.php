@extends('layouts.app')

@push('css')
    @vite('resources/css/themes.css')
@endpush

@php
    $canClose = ($theme->creator_id == auth()->id() or auth()->user()->can('complete theme') or (!$theme->group->proteced and auth()->user()->groups()->contains($theme->group))) and !$theme->completed;
    $canManage = ($theme->creator_id == auth()->id() or auth()->user()->can('create themes')) and !$theme->completed;
    $canDelete = $theme->creator_id == auth()->user()->id and $theme->protocols->count() == 0 and $theme->priority == null and $theme->date->startOfDay()->greaterThan(\Carbon\Carbon::now()->startOfDay());
@endphp

@section('content')
<div class="theme-wrapper" id="top" x-data="{ showShare: false, showTask: false }" x-cloak>

    {{-- Floating Timer --}}
    <div class="thm-timer" id="timer"><span id="duration">00:00:00</span></div>

    {{-- Kopfleiste: Zurück + Aktionen --}}
    <div class="flex flex-wrap items-center justify-between gap-3 mb-4">
        <div class="flex flex-wrap gap-2">
            @if(url()->previous() != url()->current())
                <a href="{{ url()->previous() }}" class="thm-btn thm-btn-secondary thm-btn-sm"><i class="fas fa-arrow-left"></i> Zurück</a>
            @else
                @if($theme->group->use_meetings)
                    <a href="{{ url(request()->segment(1).'/meetings') }}" class="thm-btn thm-btn-secondary thm-btn-sm"><i class="far fa-calendar-alt"></i> Meetings</a>
                @endif
                <a href="{{ url(request()->segment(1).'/themes') }}" class="thm-btn thm-btn-secondary thm-btn-sm"><i class="fas fa-list"></i> Themenübersicht</a>
            @endif
        </div>

        <div class="flex flex-wrap items-center gap-2">
            @if($canClose)
                <a href="{{ url(request()->segment(1).'/themes/'.$theme->id.'/close') }}" class="thm-btn thm-btn-danger thm-btn-sm">
                    <i class="fas fa-lock"></i> Abschließen
                </a>
            @endif

            {{-- Aktionen-Dropdown --}}
            <div class="relative" x-data="{ open: false, sub: null }" @click.outside="open = false">
                <button type="button" class="thm-btn thm-btn-primary thm-btn-sm" @click="open = !open">
                    <i class="fas fa-bars"></i> Aktionen <i class="fas fa-chevron-down text-xs" :class="open && 'rotate-180'"></i>
                </button>
                <div x-show="open" x-transition x-cloak style="display:none"
                     class="absolute right-0 mt-2 w-72 bg-white rounded-xl shadow-xl border border-gray-100 py-1 z-40 max-h-[75vh] overflow-y-auto text-gray-700">

                    @can('share theme')
                        @if($theme->share == null)
                            <button type="button" class="w-full text-left px-4 py-2 text-sm hover:bg-gray-50 flex items-center gap-2"
                                    @click="showShare = true; open = false">
                                <i class="fas fa-share-alt w-4 text-gray-400"></i> Freigeben
                            </button>
                        @else
                            <form method="post" action="{{ url('share/'.$theme->id) }}">
                                @csrf @method('delete')
                                <input type="hidden" name="theme" value="{{ base64_encode($theme->id) }}">
                                <button type="submit" class="w-full text-left px-4 py-2 text-sm hover:bg-gray-50 flex items-center gap-2 text-amber-700">
                                    <i class="fas fa-share-alt w-4"></i> Freigabe entfernen
                                </button>
                            </form>
                        @endif
                    @endcan

                    <a href="{{ url(request()->segment(1).'/memory/'.$theme->id) }}" class="px-4 py-2 text-sm hover:bg-gray-50 flex items-center gap-2">
                        <i class="fas fa-box-archive w-4 text-gray-400"></i> In Speicher
                    </a>
                    <a href="{{ url(request()->segment(1).'/themes/'.$theme->id.'/edit') }}" class="px-4 py-2 text-sm hover:bg-gray-50 flex items-center gap-2">
                        <i class="fas fa-pen w-4 text-gray-400"></i> Bearbeiten
                    </a>
                    @if($subscription == null)
                        <a href="{{ url('subscription/theme/'.$theme->id) }}" class="px-4 py-2 text-sm hover:bg-gray-50 flex items-center gap-2">
                            <i class="far fa-bell w-4 text-gray-400"></i> Abonnieren
                        </a>
                    @else
                        <a href="{{ url('subscription/theme/'.$theme->id.'/remove') }}" class="px-4 py-2 text-sm hover:bg-gray-50 flex items-center gap-2">
                            <i class="fas fa-bell w-4 text-blue-500"></i> Abo beenden
                        </a>
                    @endif

                    @if(!$theme->completed)
                        <div class="my-1 border-t border-gray-100"></div>
                        <a href="{{ url(request()->segment(1).'/protocols/'.$theme->id) }}" class="px-4 py-2 text-sm hover:bg-gray-50 flex items-center gap-2">
                            <i class="far fa-sticky-note w-4 text-gray-400"></i> Ausführliches Protokoll
                        </a>
                        <button type="button" class="w-full text-left px-4 py-2 text-sm hover:bg-gray-50 flex items-center gap-2"
                                @click="showTask = true; open = false">
                            <i class="far fa-check-square w-4 text-gray-400"></i> Aufgabe erstellen
                        </button>
                        <a href="{{ url($theme->group->name.'/themes/'.$theme->id.'/survey/create') }}" class="px-4 py-2 text-sm hover:bg-gray-50 flex items-center gap-2">
                            <i class="fas fa-poll w-4 text-gray-400"></i> Umfrage erstellen
                        </a>
                    @endif

                    @if($canManage)
                        {{-- Verschieben zum (Datum) --}}
                        <div class="border-t border-gray-100 my-1"></div>
                        <button type="button" class="w-full text-left px-4 py-2 text-sm hover:bg-gray-50 flex items-center justify-between"
                                @click="sub = (sub === 'date' ? null : 'date')">
                            <span class="flex items-center gap-2"><i class="fas fa-calendar-day w-4 text-gray-400"></i> Verschieben zum</span>
                            <i class="fas fa-chevron-down text-xs" :class="sub === 'date' && 'rotate-180'"></i>
                        </button>
                        <div x-show="sub === 'date'" x-collapse class="bg-gray-50">
                            @for($x = 0; $x < 8; $x++)
                                <a href="{{ url(request()->segment(1).'/move/theme/'.$theme->id.'/'.\Carbon\Carbon::now()->next($group->weekday_name())->addWeeks($x)->format('Y-m-d').'/true') }}"
                                   class="px-8 py-2 text-sm hover:bg-gray-100 flex">{{ \Carbon\Carbon::now()->next($group->weekday_name())->addWeeks($x)->format('d.m.Y') }}</a>
                            @endfor
                        </div>

                        @if($theme->group->hasAllocations and auth()->user()->groups_rel->contains($theme->group))
                            <button type="button" class="w-full text-left px-4 py-2 text-sm hover:bg-gray-50 flex items-center justify-between"
                                    @click="sub = (sub === 'assign' ? null : 'assign')">
                                <span class="flex items-center gap-2"><i class="fas fa-user-tag w-4 text-gray-400"></i>
                                    @if($theme->zugewiesen_an != null) Zugewiesen: {{ $theme->zugewiesen_an->name }} @else Zuweisen an @endif
                                </span>
                                <i class="fas fa-chevron-down text-xs" :class="sub === 'assign' && 'rotate-180'"></i>
                            </button>
                            <div x-show="sub === 'assign'" x-collapse class="bg-gray-50">
                                @foreach($theme->group->users as $user)
                                    @if($theme->zugewiesen_an == null or $theme->zugewiesen_an->id != $user->id)
                                        <a href="{{ url('theme/'.$theme->id.'/assign/'.$user->id) }}" class="px-8 py-2 text-sm hover:bg-gray-100 flex">{{ $user->name }}</a>
                                    @endif
                                @endforeach
                            </div>
                        @endif

                        @can('move themes')
                            <button type="button" class="w-full text-left px-4 py-2 text-sm hover:bg-gray-50 flex items-center justify-between"
                                    @click="sub = (sub === 'group' ? null : 'group')">
                                <span class="flex items-center gap-2"><i class="fas fa-people-arrows w-4 text-gray-400"></i> In andere Gruppe</span>
                                <i class="fas fa-chevron-down text-xs" :class="sub === 'group' && 'rotate-180'"></i>
                            </button>
                            <div x-show="sub === 'group'" x-collapse class="bg-gray-50">
                                @foreach(auth()->user()->groups() as $g)
                                    @if($theme->group_id != $g->id)
                                        <a href="{{ url('theme/'.$theme->id.'/change/group/'.$g->id) }}" class="px-8 py-2 text-sm hover:bg-gray-100 flex">{{ $g->name }}</a>
                                    @endif
                                @endforeach
                            </div>
                        @endcan

                        @if($canDelete)
                            <div class="border-t border-gray-100 my-1"></div>
                            <form action="{{ url(request()->segment(1).'/themes/'.$theme->id) }}" method="post"
                                  onsubmit="return confirm('Thema wirklich löschen?');">
                                @csrf @method('DELETE')
                                <button type="submit" class="w-full text-left px-4 py-2 text-sm hover:bg-red-50 text-red-600 flex items-center gap-2">
                                    <i class="fas fa-trash w-4"></i> Löschen
                                </button>
                            </form>
                        @endif
                    @endif
                </div>
            </div>
        </div>
    </div>

    {{-- Titel --}}
    <div class="thm-card mb-4">
        <div class="thm-band {{ $theme->completed ? 'thm-band-slate' : 'thm-band-blue' }}">
            <div class="flex flex-wrap items-center gap-2">
                @if($theme->zugewiesen_an != null)
                    <span class="thm-badge bg-white/20 text-white">{{ $theme->zugewiesen_an->name }}</span>
                @endif
                @if($theme->completed)<span class="thm-badge bg-white/20 text-white">abgeschlossen</span>@endif
                <h4 class="text-xl font-bold">{{ $theme->theme }}</h4>
            </div>
        </div>

        {{-- Freigabe-Formular (collapse) --}}
        @can('share theme')
            @if($theme->share == null)
                <div x-show="showShare" x-collapse x-cloak class="border-b border-gray-100">
                    <form method="post" action="{{ url('share/'.$theme->id) }}" class="p-5">
                        @csrf
                        <input type="hidden" name="theme" value="{{ base64_encode($theme->id) }}">
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-4 items-end">
                            <div>
                                <label for="activ_until" class="thm-label">Gültig bis</label>
                                <input type="date" name="active_until" class="thm-input" id="activ_until" required value="{{ \Carbon\Carbon::now()->addWeek()->format('Y-m-d') }}">
                            </div>
                            <div>
                                <label for="readonly" class="thm-label">Protokolle erlaubt?</label>
                                <select class="thm-select" name="readonly" id="readonly">
                                    <option value="1">nur lesbar</option>
                                    <option value="0">auch bearbeitbar</option>
                                </select>
                            </div>
                            <button type="submit" class="thm-btn thm-btn-warning">Freigeben</button>
                        </div>
                    </form>
                </div>
            @endif
        @endcan

        {{-- Detailraster --}}
        <div class="p-5 grid grid-cols-1 lg:grid-cols-3 gap-6">
            {{-- Spalte 1+2: Eckdaten --}}
            <div class="lg:col-span-2 space-y-3">
                <div class="flex flex-col sm:flex-row sm:items-center gap-1 sm:gap-3">
                    <span class="thm-section-title sm:w-32 shrink-0">Priorität</span>
                    <div class="flex-1 flex items-center gap-3" id="priority_{{ $theme->id }}">
                        @if ($theme->completed or $theme->priorities->where('creator_id', auth()->id())->first())
                            <div class="thm-progress max-w-xs"><span style="width: {{ 100-$theme->priority }}%"></span></div>
                            <a href="{{ route('priorities.delete', [$theme->id]) }}" class="text-gray-400 hover:text-blue-600" title="Priorität ändern"><i class="fas fa-edit"></i></a>
                        @else
                            <input type="range" id="theme_{{ $theme->id }}" min="1" max="100" value="0" data-theme="{{ $theme->id }}" class="max-w-xs">
                        @endif
                    </div>
                </div>
                <div class="flex flex-col sm:flex-row sm:items-center gap-1 sm:gap-3">
                    <span class="thm-section-title sm:w-32 shrink-0">Von</span>
                    <span class="flex items-center gap-2">
                        <span class="thm-avatar"><img src="{{ $theme->ersteller->photo() }}" alt=""></span>
                        {{ $theme->ersteller->name }}
                    </span>
                </div>
                <div class="flex flex-col sm:flex-row gap-1 sm:gap-3">
                    <span class="thm-section-title sm:w-32 shrink-0">Typ</span>
                    <span><span class="thm-badge thm-badge-blue">{{ $theme->type->type }}</span></span>
                </div>
                <div class="flex flex-col sm:flex-row gap-1 sm:gap-3">
                    <span class="thm-section-title sm:w-32 shrink-0">Ziel</span>
                    <span class="text-gray-800">{{ $theme->goal }}</span>
                </div>
                <div class="flex flex-col sm:flex-row gap-1 sm:gap-3">
                    <span class="thm-section-title sm:w-32 shrink-0">Erstellt</span>
                    <span class="text-gray-600">{{ $theme->created_at->format('d.m.Y H:i') }} Uhr</span>
                </div>
                <div class="flex flex-col sm:flex-row gap-1 sm:gap-3">
                    <span class="thm-section-title sm:w-32 shrink-0">Dauer</span>
                    <span class="text-gray-600">{{ $theme->duration }} Minuten</span>
                </div>
                <div class="flex flex-col sm:flex-row gap-1 sm:gap-3">
                    <span class="thm-section-title sm:w-32 shrink-0">Informationen</span>
                    <div class="thm-prose flex-1 text-gray-800">{!! $theme->information !!}</div>
                </div>
                @if($theme->share)
                    <div class="flex flex-col sm:flex-row gap-1 sm:gap-3">
                        <span class="thm-section-title sm:w-32 shrink-0">Freigabe</span>
                        <a href="{{ url('share/'.$theme->share->uuid) }}" class="text-blue-600 hover:underline break-all">{{ url('share/'.$theme->share->uuid) }}</a>
                    </div>
                @endif

                {{-- Dateien --}}
                <div class="flex flex-col sm:flex-row gap-1 sm:gap-3 pt-2">
                    <div class="thm-section-title sm:w-32 shrink-0">
                        Dateien
                        @can('unarchive theme')
                            <a href="{{ route('themes.archivedFiles') }}" class="block text-xs text-blue-600 hover:underline mt-1 normal-case font-normal">
                                <i class="fas fa-archive"></i> Archivierte Dateien
                            </a>
                        @endcan
                    </div>
                    <div class="flex-1">
                        <ul class="space-y-2">
                            @foreach($theme->getMedia()->reject(fn($m) => $m->getCustomProperty('archiviert'))->sortBy('name') as $media)
                                <li class="flex items-center justify-between gap-2 p-2 rounded-lg border border-gray-100 bg-gray-50/60">
                                    <a href="{{ url('/image/'.$media->id) }}" target="_blank" class="text-blue-600 hover:underline text-sm truncate">
                                        <i class="fas fa-file-download"></i> {{ $media->name }}
                                        <span class="text-gray-400">({{ $media->created_at->format('d.m.Y H:i') }})</span>
                                    </a>
                                    <form action="{{ url(request()->segment(1).'/themes/'.$theme->id.'/files/'.$media->id) }}" method="post"
                                          onsubmit="return confirm('Datei wirklich entfernen? Die Datei wird archiviert und bleibt über bestehende Protokoll-Verweise abrufbar.');">
                                        @csrf @method('DELETE')
                                        <button type="submit" class="thm-btn-icon w-8 h-8 text-red-500 hover:bg-red-50" title="Datei entfernen"><i class="fas fa-trash"></i></button>
                                    </form>
                                </li>
                            @endforeach
                            @if($theme->getMedia()->reject(fn($m) => $m->getCustomProperty('archiviert'))->count() == 0)
                                <li class="text-sm text-gray-400 italic">Keine Dateien</li>
                            @endif
                        </ul>
                    </div>
                </div>
            </div>

            {{-- Spalte 3: Meetings / Aufgaben / Prioritäten --}}
            <div class="space-y-5 lg:border-l lg:border-gray-100 lg:pl-6">
                @if($theme->group->use_meetings)
                    <div>
                        <h3 class="thm-section-title mb-2">Besprochen in Meetings</h3>
                        <ul class="space-y-1">
                            @forelse($theme->meetings as $meeting)
                                <li class="text-sm text-gray-700"><i class="far fa-calendar-alt text-gray-400 mr-1"></i> {{ $meeting->date->format('d.m.Y') }}</li>
                            @empty
                                <li class="text-sm text-gray-400 italic">–</li>
                            @endforelse
                        </ul>
                    </div>
                @endif

                <div>
                    <h3 class="thm-section-title mb-2">Aufgaben</h3>
                    <ul class="space-y-2">
                        @forelse($theme->tasks->sortByDate('date', 'desc') as $task)
                            @if(!is_null($task->taskable))
                                <li class="p-3 rounded-lg border border-gray-100 bg-gray-50/60">
                                    <div class="flex items-center gap-2 text-sm font-medium text-gray-800">
                                        @if($task->completed or (get_class($task->taskable) == 'App\Models\Group' and $task->taskUsers->count() == "0"))
                                            <i class="far fa-check-square text-emerald-500"></i>
                                        @endif
                                        {{ $task->date->format('d.m.Y') }} – {{ optional($task->taskable)->name }}
                                    </div>
                                    <p class="text-sm text-gray-600 mt-1">
                                        {{ $task->task }}
                                        @if($task->taskUsers->count() > 0)
                                            <span class="text-xs text-gray-400">(noch offen: {{ $task->taskUsers->count() }})</span>
                                        @endif
                                    </p>
                                </li>
                            @endif
                        @empty
                            <li class="text-sm text-gray-400 italic">Keine Aufgaben</li>
                        @endforelse
                    </ul>
                </div>

                @can('view priorities')
                    <div>
                        <h3 class="thm-section-title mb-2">Prioritäten</h3>
                        <ul class="space-y-2">
                            @foreach($theme->priorities as $priority)
                                <li>
                                    <div class="text-xs text-gray-600 mb-1">{{ $priority->creator->name }}</div>
                                    <div class="thm-progress"><span style="width: {{ 100-$priority->priority }}%"></span></div>
                                </li>
                            @endforeach
                        </ul>
                    </div>
                @endcan
            </div>
        </div>
    </div>

    {{-- Schnelles Protokoll --}}
    @if (!$theme->completed)
        <div class="thm-card mb-4">
            <div class="p-5">
                <h2 class="thm-section-title mb-3"><i class="far fa-sticky-note mr-1"></i> Schnelles Protokoll</h2>
                <form action="{{ url(request()->segment(1).'/protocols/'.$theme->id) }}" method="post" enctype="multipart/form-data">
                    @csrf
                    <textarea name="protocol" id="quickProtocol" class="thm-textarea">{{ old('protocol') }}</textarea>
                    <button type="submit" class="thm-btn thm-btn-success w-full mt-3"><i class="fas fa-save"></i> Speichern</button>
                </form>
            </div>
        </div>
    @endif

    {{-- Umfragen --}}
    @foreach($theme->surveys as $survey)
        @include('themes.element.survey')
    @endforeach

    {{-- Protokolle --}}
    <div class="thm-card">
        <div class="p-5">
            <h2 class="thm-section-title mb-3"><i class="fas fa-clipboard-list mr-1"></i> Protokoll</h2>
            @if ($theme->protocols->count() == 0)
                <p class="text-sm text-gray-400 italic">Kein Protokoll vorhanden</p>
            @else
                <ul class="space-y-3">
                    @foreach($theme->protocols->sortDesc() as $protocol)
                        <li class="p-4 rounded-xl border border-gray-100 bg-gray-50/40">
                            <div class="flex items-center justify-between gap-2 mb-2">
                                <div class="flex items-center gap-2 text-sm text-gray-600">
                                    @if($protocol->ersteller->getMedia('profile')->count() != 0)
                                        <span class="thm-avatar w-6 h-6 text-[10px]"><img src="{{ $protocol->ersteller->photo() }}" alt=""></span>
                                    @endif
                                    <span>{{ $protocol->created_at->format('d.m.Y H:i') }} · {{ $protocol->ersteller->name }}</span>
                                </div>
                                @if(($protocol->creator_id == auth()->user()->id and $protocol->created_at->greaterThan(\Carbon\Carbon::now()->subMinutes(config('config.protocols.editableTime')))) or $theme->change_protokoll == true)
                                    <a href="{{ url(request()->segment(1).'/protocols/'.$protocol->id.'/edit') }}" class="thm-btn thm-btn-secondary thm-btn-sm"><i class="fas fa-pen"></i> bearbeiten</a>
                                @endif
                            </div>
                            <div class="thm-prose text-gray-800">{!! $protocol->protocol !!}</div>
                            @if($protocol->getMedia()->count())
                                <ul class="mt-3 space-y-1">
                                    @foreach($protocol->getMedia()->sortBy('name') as $media)
                                        <li>
                                            <a href="{{ url('/image/'.$media->id) }}" target="_blank" class="text-blue-600 hover:underline text-sm">
                                                <i class="fas fa-file-download"></i> {{ $media->name }}
                                                <span class="text-gray-400">({{ $media->created_at->format('d.m.Y H:i') }})</span>
                                            </a>
                                        </li>
                                    @endforeach
                                </ul>
                            @endif
                        </li>
                    @endforeach
                </ul>
            @endif
        </div>
    </div>

    {{-- Modal: Aufgabe erstellen --}}
    <div class="thm-modal-backdrop" x-show="showTask" x-transition.opacity @keydown.escape.window="showTask = false" style="display:none;">
        <div class="thm-modal thm-modal-lg" @click.outside="showTask = false">
            <div class="thm-modal-header">
                <h3 class="thm-modal-title">Aufgabe hinzufügen</h3>
                <button type="button" class="thm-modal-close" @click="showTask = false" aria-label="Schließen">&times;</button>
            </div>
            <form action="{{ url(request()->segment(1).'/'.$theme->id.'/tasks') }}" method="post" id="taskForm">
                @csrf
                <div class="thm-modal-body space-y-4">
                    <div>
                        <label for="taskdate" class="thm-label">Zu erledigen bis …</label>
                        <input type="date" name="date" id="taskdate" min="{{ \Carbon\Carbon::now()->addDay()->format('Y-m-d') }}" value="{{ old('date') }}" class="thm-input" required>
                    </div>
                    <div>
                        <label for="task" class="thm-label">Aufgabe</label>
                        <input type="text" name="task" id="task" value="{{ old('task') }}" class="thm-input" required>
                    </div>
                    <div>
                        <label for="taskable" class="thm-label">Aufgabe für …</label>
                        <select class="thm-select" name="taskable" id="taskable">
                            <option value="{{ request()->segment(1) }}">Gruppe {{ request()->segment(1) }}</option>
                            @foreach($theme->group->users as $user)
                                <option value="{{ $user->id }}">{{ $user->name }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>
                <div class="thm-modal-footer">
                    <button type="button" class="thm-btn thm-btn-secondary" @click="showTask = false">Abbrechen</button>
                    <button type="submit" class="thm-btn thm-btn-primary">Speichern</button>
                </div>
            </form>
        </div>
    </div>
</div>
@stop

@push('js')
    @vite('resources/js/themes.js')
    <script>
        // Floating Timer (Restzeit des Themas)
        function makeTimer() {
            const endTime = Math.floor(Date.parse("{{ \Carbon\Carbon::now()->addMinutes($theme->duration)->format('Y-m-d H:i:s') }}") / 1000);
            let now = Math.floor(Date.now() / 1000);
            let timeLeft = endTime - now;
            let out = '';
            if (timeLeft <= 0) {
                timeLeft = now - endTime;
                out = '-';
                document.getElementById('timer')?.classList.add('is-over');
            }
            let hours = Math.floor(timeLeft / 3600);
            let minutes = Math.floor((timeLeft - hours * 3600) / 60);
            let seconds = Math.floor(timeLeft - hours * 3600 - minutes * 60);
            if (hours < 10) hours = '0' + hours;
            if (minutes < 10) minutes = '0' + minutes;
            if (seconds < 10) seconds = '0' + seconds;
            const el = document.getElementById('duration');
            if (el) el.innerHTML = out + hours + ':' + minutes + ':' + seconds;
        }
        setInterval(makeTimer, 1000);
        makeTimer();
    </script>

    <script src="{{ asset('js/plugins/tinymce/jquery.tinymce.min.js') }}"></script>
    <script src="{{ asset('js/plugins/tinymce/tinymce.min.js') }}"></script>
    <script src="{{ asset('js/plugins/tinymce/langs/de.js') }}"></script>
    <script>
        tinymce.init({
            selector: '#quickProtocol',
            lang: 'de',
            height: 400,
            menubar: true,
            autosave_ask_before_unload: true,
            autosave_interval: '40s',
            plugins: [
                'advlist autolink lists link charmap',
                'searchreplace visualblocks code',
                'insertdatetime table paste code wordcount autosave',
            ],
            toolbar: 'undo redo | bold italic backcolor forecolor | alignleft aligncenter alignright alignjustify | bullist numlist outdent indent | removeformat | link | restoredraft',
            table_default_attributes: { border: '1' }
        });
    </script>


@endpush
