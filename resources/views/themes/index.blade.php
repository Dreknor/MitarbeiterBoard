@extends('layouts.app')

@push('css')
    @vite('resources/css/themes.css')
@endpush

@section('content')
<div class="theme-wrapper">

    {{-- Kopf --}}
    <div class="thm-card thm-card-visible mb-6">
        <div class="p-5">
            @include('themes.element.header')
        </div>
        @php
            // Sprungziel: heutiger Tag bzw. nächster Termin; sonst der jüngste vergangene Tag / "offen".
            $heute = \Carbon\Carbon::today();
            $sprungZiel = null;
            $sprungDatum = null;
            foreach ($themes->keys() as $tag) {
                if ($tag == 'offen') {
                    continue;
                }
                $datum = \Carbon\Carbon::createFromFormat('d.m.Y', $tag)->startOfDay();
                if ($datum->greaterThanOrEqualTo($heute) and ($sprungDatum === null or $datum->lessThan($sprungDatum))) {
                    $sprungDatum = $datum;
                    $sprungZiel = $datum->format('Ymd');
                }
            }
            if ($sprungZiel === null and count($themes) > 0) {
                foreach ($themes->keys()->reverse() as $tag) {
                    if ($tag != 'offen') {
                        $sprungDatum = \Carbon\Carbon::createFromFormat('d.m.Y', $tag)->startOfDay();
                        $sprungZiel = $sprungDatum->format('Ymd');
                        break;
                    }
                }
                $sprungZiel ??= $themes->has('offen') ? 'offen' : null;
            }
        @endphp
        @if($sprungZiel !== null or auth()->user()->can('create themes'))
            <div class="px-5 pb-5 flex flex-wrap gap-2">
                @if($sprungZiel !== null)
                    <a href="#{{ $sprungZiel }}" class="thm-btn thm-btn-secondary flex-1" x-data
                       @click.prevent="$dispatch('thm-jump', { id: '{{ $sprungZiel }}' })">
                        <i class="fas fa-crosshairs"></i>
                        @if($sprungDatum === null)
                            Zu den offenen Themen
                        @elseif($sprungDatum->isSameDay($heute))
                            Zu heute
                        @elseif($sprungDatum->greaterThan($heute))
                            Zum nächsten Termin ({{ $sprungDatum->format('d.m.Y') }})
                        @else
                            Zum letzten Termin ({{ $sprungDatum->format('d.m.Y') }})
                        @endif
                    </a>
                @endif
                @can('create themes')
                    <a href="{{ url(request()->segment(1).'/themes/create') }}" class="thm-btn thm-btn-primary flex-1">
                        <i class="fas fa-plus"></i> Neues Thema
                    </a>
                @endcan
            </div>
        @endif
    </div>

    @if (count($themes) == 0)
        <div class="thm-card p-8 text-center text-gray-500">
            <i class="far fa-folder-open text-3xl text-gray-300 mb-3 block"></i>
            Es gibt keine offenen Themen.
        </div>
    @else
        <div class="space-y-5">
            @foreach($themes as $day => $dayThemes)
                @php
                    $tagDatum = $day == 'offen' ? null : \Carbon\Carbon::createFromFormat('d.m.Y', $day)->startOfDay();
                    $dayId = $tagDatum?->format('Ymd') ?? 'offen';
                    $akzent = match (true) {
                        $tagDatum === null => 'thm-day-offen',
                        $tagDatum->isToday() => 'thm-day-heute',
                        $tagDatum->isPast() => 'thm-day-vergangen',
                        default => '',
                    };
                @endphp
                {{-- Ein-/aufklappbar; Zustand pro Gruppe und Tag in der Browser-Session (übersteht Reloads). "offen" startet zugeklappt. --}}
                <div class="thm-card thm-day {{ $akzent }}" id="{{ $dayId }}"
                     x-data="{
                        moveOpen: false,
                        open: {{ $day == 'offen' ? 'false' : 'true' }},
                        key: 'thm-day-{{ $group->id }}-{{ $dayId }}',
                        init() {
                            try {
                                const gespeichert = sessionStorage.getItem(this.key);
                                if (gespeichert !== null) this.open = gespeichert === '1';
                            } catch (e) {}
                            const ziel = location.hash ? document.getElementById(location.hash.slice(1)) : null;
                            if (ziel && this.$el.contains(ziel)) this.open = true;
                        },
                        toggle() {
                            this.open = !this.open;
                            try { sessionStorage.setItem(this.key, this.open ? '1' : '0'); } catch (e) {}
                        },
                     }"
                     @thm-jump.window="if ($event.detail.id === '{{ $dayId }}') { open = true; $nextTick(() => $el.scrollIntoView({ behavior: 'smooth', block: 'start' })); }">
                    <div class="thm-day-head" :class="open && 'thm-day-head-open'">
                        <div class="flex flex-wrap items-center justify-between gap-3">
                            <button type="button" class="thm-day-toggle flex items-center gap-3 text-left min-w-0 flex-1"
                                    @click="toggle()" :aria-expanded="open.toString()">
                                <i class="fas fa-chevron-right text-xs text-gray-400 w-3 transition-transform duration-200" :class="open && 'rotate-90'"></i>
                                <span class="min-w-0">
                                    <span class="flex flex-wrap items-center gap-2">
                                        @if($day == 'offen')
                                            <span class="text-base font-semibold text-gray-900">Offene Themen</span>
                                            <span class="thm-badge thm-badge-amber">aus früheren Sitzungen</span>
                                        @else
                                            <span class="text-base font-semibold text-gray-900">
                                                <span class="text-gray-500 font-normal">{{ $tagDatum->locale('de')->isoFormat('dddd') }},</span>
                                                {{ $day }}
                                            </span>
                                            @if($tagDatum->isToday())
                                                <span class="thm-badge thm-badge-blue">Heute</span>
                                            @elseif($tagDatum->isPast())
                                                <span class="thm-badge thm-badge-gray">vergangen</span>
                                            @endif
                                        @endif
                                    </span>
                                    <span class="block text-xs text-gray-500 mt-0.5">
                                        {{ $dayThemes->count() }} {{ $dayThemes->count() == 1 ? 'Thema' : 'Themen' }}
                                        @if($day != 'offen')
                                            <span class="text-gray-300 mx-1">·</span> {{ $dayThemes->sum('duration') }} Minuten
                                        @endif
                                    </span>
                                </span>
                            </button>
                            @can('move themes')
                                @if($day != 'offen')
                                    <button type="button" class="thm-btn thm-btn-ghost thm-btn-sm"
                                            title="Alle Themen verschieben" @click="moveOpen = !moveOpen">
                                        <i class="far fa-calendar-alt"></i> <span class="hidden sm:inline">Verschieben</span>
                                    </button>
                                @endif
                            @endcan
                        </div>
                        @can('move themes')
                            @if($day != 'offen')
                                <div x-show="moveOpen" x-collapse x-cloak class="mt-3">
                                    <form method="post" action="{{ url(request()->segment(1).'/move/themes') }}"
                                          class="flex flex-wrap items-end gap-2 bg-white border border-gray-200 rounded-xl p-3">
                                        @csrf
                                        <div>
                                            <label class="thm-label">Alle Themen verschieben auf</label>
                                            <input type="date" class="thm-input w-auto" name="date"
                                                   value="{{ \Carbon\Carbon::now()->next($group->weekday_name())->format('Y-m-d') }}">
                                        </div>
                                        <input type="hidden" name="oldDate" value="{{ $tagDatum->format('Y-m-d') }}">
                                        <button type="submit" class="thm-btn thm-btn-primary thm-btn-sm">
                                            <i class="fas fa-arrow-right"></i> Verschieben
                                        </button>
                                    </form>
                                </div>
                            @endif
                        @endcan
                    </div>

                    <div class="p-3 sm:p-4" x-show="open" x-collapse>
                        <div class="thm-theme-list divide-y divide-gray-100" data-theme-list>
                            @if($day != 'offen')
                                {{-- System-Eintrag: Anwesenheit --}}
                                <div class="thm-theme-item {{ (isset($anwesenheiten) and $anwesenheiten->where('date', \Carbon\Carbon::now())->count() > 0) ? 'thm-row-protokoll' : '' }}">
                                    <span class="thm-avatar bg-gray-100 text-gray-500"><i class="fas fa-users"></i></span>
                                    <div class="min-w-0 flex-1">
                                        <h3 class="font-semibold text-gray-900">Anwesenheit</h3>
                                        <p class="text-xs text-gray-400 mt-0.5">System</p>
                                    </div>
                                    <div class="flex items-center gap-2 shrink-0 sm:ml-auto">
                                        <a href="{{ url(request()->segment(1).'/presence/'.\Carbon\Carbon::createFromFormat('d.m.Y', $day)->format('Ymd')) }}"
                                           class="thm-btn thm-btn-secondary thm-btn-sm">
                                            @if(\Carbon\Carbon::createFromFormat('d.m.Y', $day)->isSameDay(\Carbon\Carbon::now()) and isset($anwesenheiten) and $anwesenheiten->where('date', \Carbon\Carbon::now())->count() == null)
                                                <i class="far fa-edit"></i> erstellen
                                            @else
                                                <i class="far fa-eye"></i> zeigen
                                            @endif
                                        </a>
                                    </div>
                                </div>
                            @endif

                            @foreach($dayThemes->sortByDesc(fn($t) => $t->priority ?? -INF) as $theme)
                                <div id="{{ $theme->id }}" data-priority="{{ $theme->priority }}"
                                     class="thm-theme-item {{ $theme->protocols->where('created_at', '>', \Carbon\Carbon::now()->startOfDay())->count() > 0 ? 'thm-row-protokoll' : '' }} {{ $theme->zugewiesen_an?->id === auth()->id() ? 'thm-row-assigned' : '' }}">
                                    {{-- Ersteller-Avatar --}}
                                    <span class="thm-avatar" title="{{ $theme->ersteller->name }}">
                                        @if($theme->ersteller->getMedia('profile')->count() != 0)
                                            <img src="{{ $theme->ersteller->photo() }}" alt="{{ $theme->ersteller->name }}">
                                        @else
                                            {{ \Illuminate\Support\Str::of($theme->ersteller->name)->explode(' ')->map(fn($p)=>\Illuminate\Support\Str::substr($p,0,1))->take(2)->implode('') }}
                                        @endif
                                    </span>

                                    {{-- Hauptinhalt: Titel, Ziel, Meta --}}
                                    <div class="min-w-0 flex-1">
                                        <div class="flex flex-wrap items-center gap-2">
                                            <h6 class="text-sm font-semibold text-gray-900">{{ $theme->theme }}</h6>
                                            <span class="thm-badge thm-badge-blue"><i class="fas fa-tag text-[10px]"></i> {{ $theme->type->type }}</span>
                                            @if($group->hasAllocations and $theme->zugewiesen_an != null)
                                                <span class="thm-badge thm-badge-amber"><i class="fas fa-user-check text-[10px]"></i> {{ $theme->zugewiesen_an?->name }}</span>
                                            @endif
                                        </div>
                                        @if($theme->goal)
                                            <p class="text-sm text-gray-500 mt-0.5 thm-clamp-2">{{ $theme->goal }}</p>
                                        @endif
                                        <div class="flex flex-wrap items-center gap-x-3 gap-y-1 mt-1 text-xs text-gray-400">
                                            <span>{{ $theme->ersteller->name }}</span>
                                            <span><i class="far fa-clock"></i> {{ $theme->duration }} Min.</span>
                                        </div>
                                    </div>

                                    {{-- Priorität --}}
                                    <div id="priority_{{ $theme->id }}" class="w-full sm:w-40 shrink-0">
                                        @if ($theme->priorities->where('creator_id', auth()->id())->first())
                                            <div class="thm-progress"><span style="width: {{ $theme->priority }}%"></span></div>
                                        @else
                                            <input type="range" id="theme_{{ $theme->id }}" min="1" max="100" value="0" data-theme="{{ $theme->id }}" title="Priorität festlegen">
                                        @endif
                                    </div>

                                    {{-- Aktionen --}}
                                    <div class="flex items-center gap-2 shrink-0">
                                        <a href="{{ url(request()->segment(1).'/themes/'.$theme->id) }}" class="thm-btn thm-btn-secondary thm-btn-sm">
                                            <i class="far fa-eye"></i> <span class="hidden sm:inline">zeigen</span>
                                        </a>
                                        <a href="{{ url(request()->segment(1).'/protocols/'.$theme->id) }}" class="thm-btn thm-btn-secondary thm-btn-sm">
                                            <i class="far fa-sticky-note"></i> <span class="hidden sm:inline">Protokoll</span>
                                        </a>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
    @endif
</div>
@stop

@push('js')
    @vite('resources/js/themes.js')
@endpush
