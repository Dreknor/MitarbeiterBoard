@extends('layouts.app')

@section('title')
    {{ $roster->is_template ? 'Vorlage' : 'Dienstplan' }} {{ $department->name }}
@endsection

@section('site-title')
    Dienstplanung
@endsection

@push('css')
    @vite(['resources/css/zeit.css', 'resources/js/zeit.js'])
@endpush

@php
    $wochenEnde = $roster->weekEnd();
    $editorDaten = [
        'rosterId' => $roster->id,
        'days' => collect($days)->map(fn ($d) => [
            'date' => $d->toDateString(),
            'label' => $d->locale('de')->isoFormat('dddd, DD.MM.'),
            'kurz' => $d->locale('de')->isoFormat('dd'),
            'tag' => $d->format('d.m.'),
            'feiertag' => is_holiday($d)['title'] ?? null,
        ])->values(),
        'employes' => $employes->map(fn ($e) => ['id' => $e->id, 'name' => $e->name, 'vorname' => $e->vorname ?: $e->name])->values(),
        'fenster' => $fenster,
        'raster' => $raster,
        'offeneAenderungen' => $offeneAenderungen,
        'urls' => [
            'data' => route('roster.data', $roster->id),
            'drop' => route('roster.events.drop'),
            'eventStore' => route('roster.events.store', $roster->id),
            'events' => url('tasks'),
            'workingTime' => route('roster.working-time.store'),
            'trashDay' => route('roster.trash-day', $roster->id),
        ],
    ];
    $kopierWochen = collect(range(1, 12))->map(fn ($i) => $roster->start_date->copy()->startOfWeek()->addWeeks($i));
@endphp

@section('content')
<script type="application/json" id="dienstplan-daten">@json($editorDaten)</script>

<div class="zeit-wrapper" x-data="dienstplanEditor(JSON.parse(document.getElementById('dienstplan-daten').textContent))" @keydown.escape.window="schliessen()">

    {{-- Kopf --}}
    <div class="flex flex-wrap items-start justify-between gap-3 mb-4">
        <div class="min-w-0">
            <a href="{{ route('roster.index') }}" class="text-sm text-blue-600 hover:text-blue-800"><i class="fas fa-arrow-left mr-1"></i>Dienstpläne</a>
            <div class="flex flex-wrap items-center gap-2 mt-1">
                @if($vorherige)
                    <a href="{{ route('roster.show', $vorherige->id) }}" class="zw-btn-icon is-sm" title="vorherige Woche"><i class="fas fa-chevron-left"></i></a>
                @endif
                <h1 class="zw-page-title">
                    {{ $department->name }} ·
                    @if($roster->is_template) Vorlage @else KW {{ $roster->start_date->isoWeek() }} @endif
                </h1>
                @if($naechste)
                    <a href="{{ route('roster.show', $naechste->id) }}" class="zw-btn-icon is-sm" title="nächste Woche"><i class="fas fa-chevron-right"></i></a>
                @endif
            </div>
            <div class="flex flex-wrap items-center gap-2 mt-1 text-sm text-gray-500">
                <span>{{ $roster->start_date->format('d.m.') }}–{{ $wochenEnde->format('d.m.Y') }}</span>
                @if($roster->comment)<span>· {{ $roster->comment }}</span>@endif
                @unless($roster->is_template)
                    @if($roster->published)
                        <span class="zw-badge zw-badge-green"><i class="fas fa-check"></i> veröffentlicht{{ $roster->published_at ? ' am '.$roster->published_at->format('d.m.') : '' }}</span>
                    @else
                        <span class="zw-badge zw-badge-gray">Entwurf – für Mitarbeitende noch nicht sichtbar</span>
                    @endif
                @endunless
            </div>
        </div>

        <div class="flex flex-wrap items-center gap-2">
            @unless($roster->is_template)
                @if(!$roster->published)
                    <form action="{{ route('roster.publish', $roster->id) }}" method="post" data-confirm="Plan veröffentlichen? Alle eingeplanten Personen werden benachrichtigt.">
                        @csrf
                        <button type="submit" class="zw-btn zw-btn-success"><i class="fas fa-bullhorn"></i> Veröffentlichen</button>
                    </form>
                @else
                    <form action="{{ route('roster.notify-changes', $roster->id) }}" method="post" x-show="offeneAenderungen > 0" x-cloak>
                        @csrf
                        <button type="submit" class="zw-btn zw-btn-warning"><i class="fas fa-paper-plane"></i> Änderungen mitteilen (<span x-text="offeneAenderungen"></span>)</button>
                    </form>
                @endif
            @endunless

            <div class="relative" x-data="{ menu: false }" @click.outside="menu = false">
                <button type="button" class="zw-btn zw-btn-secondary" @click="menu = !menu" :aria-expanded="menu.toString()"><i class="fas fa-ellipsis-h"></i> Mehr</button>
                <div x-show="menu" x-cloak x-transition class="absolute right-0 z-30 mt-1 w-64 rounded-xl border border-gray-200 bg-white py-1 shadow-lg">
                    @unless($roster->is_template)
                        <a href="{{ route('roster.export.pdf', $roster->id) }}" target="_blank" class="block px-4 py-2 text-sm hover:bg-gray-50"><i class="fas fa-file-pdf w-5 text-gray-400"></i> PDF anzeigen</a>
                        <form action="{{ route('roster.export.mail', $roster->id) }}" method="post" data-confirm="Plan per E-Mail an alle Mitarbeitenden der Abteilung senden?">
                            @csrf
                            <button type="submit" class="w-full text-left px-4 py-2 text-sm hover:bg-gray-50"><i class="fas fa-envelope w-5 text-gray-400"></i> Per E-Mail senden</button>
                        </form>
                        @if(config('nextcloud.enabled'))
                            <form action="{{ route('roster.export.nextcloud', $roster->id) }}" method="post">
                                @csrf
                                <button type="submit" class="w-full text-left px-4 py-2 text-sm hover:bg-gray-50"><i class="fas fa-comment w-5 text-gray-400"></i> An Nextcloud Talk senden</button>
                            </form>
                        @endif
                        <div class="border-t border-gray-100 my-1"></div>
                        <a href="{{ route('roster.importCalendar.preview', $roster->id) }}" class="block px-4 py-2 text-sm hover:bg-gray-50"><i class="far fa-calendar-plus w-5 text-gray-400"></i> Termine aus Kalender</a>
                        <a href="{{ route('roster.autoPlan', $roster->id) }}" class="block px-4 py-2 text-sm hover:bg-gray-50"><i class="fas fa-magic w-5 text-gray-400"></i> Auto-Umplanung</a>
                    @endunless
                    <button type="button" class="w-full text-left px-4 py-2 text-sm hover:bg-gray-50" @click="menu = false; dialog = 'kopieren'"><i class="far fa-copy w-5 text-gray-400"></i> In weitere Wochen kopieren</button>
                    @if($roster->published)
                        <form action="{{ route('roster.unpublish', $roster->id) }}" method="post" data-confirm="Veröffentlichung zurückziehen? Mitarbeitende sehen den Plan dann nicht mehr.">
                            @csrf
                            <button type="submit" class="w-full text-left px-4 py-2 text-sm hover:bg-gray-50"><i class="fas fa-eye-slash w-5 text-gray-400"></i> Veröffentlichung zurückziehen</button>
                        </form>
                    @endif
                    <div class="border-t border-gray-100 my-1"></div>
                    <form action="{{ route('roster.delete', $roster->id) }}" method="post" data-confirm="{{ $roster->is_template ? 'Vorlage' : 'Dienstplan' }} löschen?">
                        @csrf @method('delete')
                        <button type="submit" class="w-full text-left px-4 py-2 text-sm text-red-600 hover:bg-red-50"><i class="fas fa-trash w-5"></i> Löschen</button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    {{-- Ansicht + Tage --}}
    <div class="flex flex-col lg:flex-row lg:items-center gap-3 mb-4">
        <div class="zw-segment lg:w-56 shrink-0">
            <input type="radio" id="ansicht-tag" value="tag" x-model="ansicht">
            <label for="ansicht-tag"><i class="fas fa-stream"></i> Tag</label>
            <input type="radio" id="ansicht-woche" value="woche" x-model="ansicht">
            <label for="ansicht-woche"><i class="fas fa-th"></i> Woche</label>
        </div>
        <div class="zw-daybar flex-1" x-show.important="ansicht === 'tag'">
            <template x-for="(d, i) in days" :key="d.date">
                <button type="button" :class="i === tagIndex && 'is-active'" @click="tagIndex = i" :title="d.label + (d.feiertag ? ' – ' + d.feiertag : '')">
                    <span x-text="d.kurz"></span>
                    <span class="font-normal opacity-80" x-text="d.tag"></span>
                    <span class="zw-dot" x-show="hatKonflikteAm(d.date)"></span>
                </button>
            </template>
        </div>
    </div>

    {{-- ================= TAG ================= --}}
    <section x-show="ansicht === 'tag'">
        <div class="flex flex-wrap items-center justify-between gap-2 mb-3">
            <h2 class="text-lg font-bold text-gray-900">
                <span x-text="tag.label"></span>
                <span class="zw-badge zw-badge-blue ml-1" x-show="tag.feiertag" x-text="tag.feiertag"></span>
            </h2>
            <div class="flex items-center gap-2">
                <button type="button" class="zw-btn zw-btn-sm zw-btn-primary hidden lg:inline-flex" @click="neuerTermin()"><i class="fas fa-plus"></i> Termin</button>
                <button type="button" class="zw-btn zw-btn-sm zw-btn-danger-ghost" @click="tagLeeren()"><i class="fas fa-eraser"></i><span class="hidden sm:inline">Tag leeren</span></button>
            </div>
        </div>

        @if($employes->isEmpty())
            <div class="zw-card"><div class="zw-empty"><i class="fas fa-users"></i> Für diese Woche hat niemand einen Vertrag in {{ $department->name }}.</div></div>
        @endif

        {{-- Desktop: Zeitraster --}}
        <div class="hidden lg:block zw-card overflow-hidden">
            <div class="zw-roster-scroll">
                <div class="zw-roster-grid">
                    {{-- Zeitspalte --}}
                    <div class="zw-roster-col is-time">
                        <div class="zw-roster-head"></div>
                        <div class="zw-roster-wt text-[10px] text-gray-400 cursor-default hover:bg-transparent">Dienst</div>
                        <div class="relative" :style="`height:${hoehe}px`">
                            <template x-for="m in stundenLinien" :key="m">
                                <span class="zw-time-label" :style="linieStil(m)" x-text="String(Math.floor(m / 60)).padStart(2, '0') + ':00'"></span>
                            </template>
                        </div>
                        <div class="zw-roster-foot cursor-default hover:bg-transparent"></div>
                    </div>

                    {{-- Personen --}}
                    <template x-for="emp in employes" :key="emp.id">
                        <div class="zw-roster-col">
                            <div class="zw-roster-head">
                                <div class="flex items-center justify-between gap-1">
                                    <span class="truncate" x-text="emp.vorname" :title="emp.name"></span>
                                    <i class="fas fa-exclamation-circle text-red-500" x-show="konflikteFuer(emp.id, tag.date).length" :title="konflikteFuer(emp.id, tag.date).join('\n')"></i>
                                </div>
                                <div class="text-[11px] font-normal text-gray-500">
                                    <span x-text="stundenVon(emp.id).geplant.toLocaleString('de-DE')"></span> / <span x-text="stundenVon(emp.id).vertrag.toLocaleString('de-DE')"></span> h Woche
                                </div>
                            </div>
                            <div class="zw-roster-wt" :class="!zeitFuer(emp.id, tag.date)?.start && 'is-empty'" @click="zeitBearbeiten(emp.id, tag.date)" role="button" tabindex="0" @keydown.enter="zeitBearbeiten(emp.id, tag.date)">
                                <div class="font-semibold" x-text="zeitLabel(zeitFuer(emp.id, tag.date))"></div>
                                <div class="truncate text-gray-500" x-text="zeitFuer(emp.id, tag.date)?.function || ''"></div>
                            </div>
                            <div class="zw-timeline" data-timeline :data-employe="emp.id" :data-date="tag.date" :style="`height:${hoehe}px`"
                                 @pointerdown="auswahlStart($event, emp.id, tag.date)" @pointermove="auswahlBewegen($event)" @pointerup="auswahlEnde()">
                                <template x-for="m in stundenLinien" :key="m">
                                    <div class="zw-hour" :style="linieStil(m)"></div>
                                </template>
                                <div class="zw-selection" x-show="auswahl && auswahl.empId === emp.id && auswahl.date === tag.date" :style="auswahlStil()"></div>
                                <template x-for="e in eventsFuer(emp.id, tag.date)" :key="e.id">
                                    <div class="zw-ev" tabindex="0"
                                         :class="{ 'is-abwesend': e.abwesend, 'is-pause': e.pause, 'is-dragging': drag && drag.moved && drag.id === e.id }"
                                         :style="stil(e)" :title="`${e.event} ${e.start}–${e.end}`"
                                         @pointerdown="ziehenStart($event, e)" @keydown.enter="bearbeiten(e)">
                                        <div class="font-semibold truncate" x-text="e.event"></div>
                                        <div class="opacity-80" x-text="`${e.start}–${e.end}`"></div>
                                    </div>
                                </template>
                            </div>
                            <div class="zw-roster-foot">
                                <template x-for="k in konflikteFuer(emp.id, tag.date)" :key="k">
                                    <div class="zw-konflikt"><i class="fas fa-exclamation-triangle mt-0.5"></i><span x-text="k"></span></div>
                                </template>
                            </div>
                        </div>
                    </template>

                    {{-- Seitenspalte: Merkliste + Checks --}}
                    <div class="zw-roster-col is-side">
                        <div class="zw-roster-head flex items-center justify-between">
                            <span>Merkliste</span>
                            <button type="button" class="zw-btn-icon is-sm" title="Termin ohne Zuordnung" @click="neuerTermin(null)"><i class="fas fa-plus"></i></button>
                        </div>
                        <div class="p-2 flex flex-col gap-1.5">
                            <template x-for="e in merkliste(tag.date)" :key="e.id">
                                <div class="rounded-lg border border-dashed border-gray-300 bg-white px-2 py-1.5 text-xs cursor-grab hover:border-blue-400"
                                     style="touch-action:none" @pointerdown="ziehenStart($event, e)" tabindex="0" @keydown.enter="bearbeiten(e)" title="In eine Spalte ziehen oder anklicken">
                                    <div class="font-semibold" x-text="e.event"></div>
                                    <div class="text-gray-500" x-text="`${e.start}–${e.end}`"></div>
                                </div>
                            </template>
                            <p class="text-xs text-gray-400" x-show="merkliste(tag.date).length === 0">Leer – nicht zugewiesene Termine landen hier.</p>
                        </div>
                        <div class="px-2 pt-3 pb-2 border-t border-gray-200 mt-2" x-show="checksAm(tag.date).length">
                            <p class="zw-section-title mb-1.5">Checks</p>
                            <template x-for="[name, ok] in checksAm(tag.date)" :key="name">
                                <div class="flex items-start gap-1.5 text-xs mb-1" :class="ok ? 'text-emerald-700' : 'text-red-600'">
                                    <i class="fas mt-0.5" :class="ok ? 'fa-check-circle' : 'fa-times-circle'"></i><span x-text="name"></span>
                                </div>
                            </template>
                        </div>
                    </div>
                </div>
            </div>
            <p class="px-4 py-2 text-xs text-gray-500 border-t border-gray-100">
                <i class="fas fa-mouse-pointer mr-1"></i> Im Raster ziehen = neuer Termin · Termin ziehen = verschieben (auch in andere Spalten) · Klick = bearbeiten · Dienstzeile anklicken = Arbeitszeit setzen
            </p>
        </div>

        {{-- Smartphone/Tablet: Liste je Person --}}
        <div class="lg:hidden flex flex-col gap-3">
            <template x-for="emp in employes" :key="emp.id">
                <div class="zw-card">
                    <div class="flex items-center justify-between gap-2 px-4 py-3 border-b border-gray-100">
                        <div class="min-w-0">
                            <div class="font-semibold text-gray-900 truncate" x-text="emp.name"></div>
                            <div class="text-xs text-gray-500"><span x-text="stundenVon(emp.id).geplant.toLocaleString('de-DE')"></span> / <span x-text="stundenVon(emp.id).vertrag.toLocaleString('de-DE')"></span> h Woche</div>
                        </div>
                        <button type="button" class="zw-btn zw-btn-sm zw-btn-secondary shrink-0" @click="zeitBearbeiten(emp.id, tag.date)">
                            <i class="far fa-clock"></i> <span x-text="zeitLabel(zeitFuer(emp.id, tag.date))"></span>
                        </button>
                    </div>
                    <div class="px-4 py-2 flex flex-col gap-1.5">
                        <p class="text-xs text-gray-500" x-show="zeitFuer(emp.id, tag.date)?.function" x-text="'Aufgabe: ' + (zeitFuer(emp.id, tag.date)?.function || '')"></p>
                        <template x-for="e in eventsFuer(emp.id, tag.date)" :key="e.id">
                            <button type="button" class="flex items-center gap-3 rounded-lg px-3 py-2 text-left text-sm"
                                    :class="e.abwesend ? 'bg-amber-50 text-amber-900 cursor-default' : (e.pause ? 'bg-slate-100 text-slate-700' : 'bg-blue-50 text-blue-900')"
                                    @click="bearbeiten(e)">
                                <span class="w-24 shrink-0 tabular-nums text-xs" x-text="`${e.start}–${e.end}`"></span>
                                <span class="font-medium flex-1" x-text="e.event"></span>
                                <i class="fas fa-chevron-right text-xs opacity-40" x-show="!e.abwesend"></i>
                            </button>
                        </template>
                        <p class="text-xs text-gray-400" x-show="eventsFuer(emp.id, tag.date).length === 0">Keine Termine</p>
                        <template x-for="k in konflikteFuer(emp.id, tag.date)" :key="k">
                            <div class="zw-konflikt text-xs"><i class="fas fa-exclamation-triangle mt-0.5"></i><span x-text="k"></span></div>
                        </template>
                        <button type="button" class="self-start text-sm font-semibold text-blue-600 py-1" @click="neuerTermin(emp.id)"><i class="fas fa-plus mr-1"></i>Termin</button>
                    </div>
                </div>
            </template>

            <div class="zw-card" x-show="merkliste(tag.date).length || checksAm(tag.date).length">
                <div class="px-4 py-3 flex flex-col gap-2">
                    <template x-if="merkliste(tag.date).length">
                        <div>
                            <p class="zw-section-title mb-1.5">Merkliste</p>
                            <template x-for="e in merkliste(tag.date)" :key="e.id">
                                <button type="button" class="w-full flex items-center gap-3 rounded-lg border border-dashed border-gray-300 px-3 py-2 mb-1.5 text-left text-sm" @click="bearbeiten(e)">
                                    <span class="w-24 shrink-0 text-xs tabular-nums" x-text="`${e.start}–${e.end}`"></span>
                                    <span class="flex-1 font-medium" x-text="e.event"></span>
                                    <span class="text-xs text-blue-600">zuweisen</span>
                                </button>
                            </template>
                        </div>
                    </template>
                    <template x-if="checksAm(tag.date).length">
                        <div>
                            <p class="zw-section-title mb-1.5">Checks</p>
                            <template x-for="[name, ok] in checksAm(tag.date)" :key="name">
                                <div class="flex items-start gap-1.5 text-sm mb-1" :class="ok ? 'text-emerald-700' : 'text-red-600'">
                                    <i class="fas mt-0.5" :class="ok ? 'fa-check-circle' : 'fa-times-circle'"></i><span x-text="name"></span>
                                </div>
                            </template>
                        </div>
                    </template>
                </div>
            </div>
        </div>
    </section>

    {{-- ================= WOCHE ================= --}}
    <section x-show="ansicht === 'woche'" x-cloak class="zw-card overflow-hidden">
        <div class="overflow-x-auto">
            <table class="zw-table">
                <thead>
                <tr>
                    <th class="sticky left-0 bg-gray-50 z-10">Person</th>
                    <template x-for="(d, i) in days" :key="d.date">
                        <th class="text-center cursor-pointer hover:text-blue-700" @click="tagIndex = i; ansicht = 'tag'">
                            <span x-text="d.kurz"></span> <span class="font-normal" x-text="d.tag"></span>
                            <div class="font-normal normal-case text-blue-700" x-show="d.feiertag" x-text="d.feiertag"></div>
                        </th>
                    </template>
                    <th class="text-right">Std.</th>
                </tr>
                </thead>
                <tbody>
                <template x-for="emp in employes" :key="emp.id">
                    <tr>
                        <td class="sticky left-0 bg-white z-10 font-medium whitespace-nowrap" x-text="emp.name"></td>
                        <template x-for="(d, i) in days" :key="d.date">
                            <td class="text-center cursor-pointer align-top" @click="tagIndex = i; ansicht = 'tag'"
                                :class="konflikteFuer(emp.id, d.date).length ? 'bg-red-50' : (abwesenheitAm(emp.id, d.date) ? 'bg-amber-50' : '')"
                                :title="konflikteFuer(emp.id, d.date).join('\n')">
                                <template x-if="abwesenheitAm(emp.id, d.date)">
                                    <div class="text-xs font-semibold text-amber-800" x-text="abwesenheitAm(emp.id, d.date).event"></div>
                                </template>
                                <div class="text-xs whitespace-nowrap" :class="zeitFuer(emp.id, d.date)?.start ? 'font-semibold text-gray-900' : 'text-gray-300'" x-text="zeitFuer(emp.id, d.date)?.start ? zeitLabel(zeitFuer(emp.id, d.date)) : '–'"></div>
                                <div class="text-[11px] text-gray-500 truncate max-w-[8rem] mx-auto" x-text="zeitFuer(emp.id, d.date)?.function || ''"></div>
                                <div class="text-[11px] text-blue-700" x-show="anzahlAm(emp.id, d.date)" x-text="anzahlAm(emp.id, d.date) + ' Termin(e)'"></div>
                                <i class="fas fa-exclamation-triangle text-red-500 text-xs" x-show="konflikteFuer(emp.id, d.date).length"></i>
                            </td>
                        </template>
                        <td class="text-right whitespace-nowrap text-sm"
                            :class="stundenVon(emp.id).geplant > stundenVon(emp.id).vertrag + 0.01 ? 'text-red-600 font-semibold' : 'text-gray-700'">
                            <span x-text="stundenVon(emp.id).geplant.toLocaleString('de-DE')"></span>
                            <span class="text-gray-400">/ <span x-text="stundenVon(emp.id).vertrag.toLocaleString('de-DE')"></span></span>
                        </td>
                    </tr>
                </template>
                </tbody>
            </table>
        </div>
        <p class="px-4 py-2 text-xs text-gray-500 border-t border-gray-100">Zelle anklicken, um den Tag zu bearbeiten. Stunden: geplant (ohne Pausen) / laut Vertrag in dieser Abteilung.</p>
    </section>

    {{-- ================= Hinweise & Änderungen ================= --}}
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-4 mt-5">
        <section class="zw-card" x-data="{ neu: false }">
            <div class="zw-card-head">
                <h2 class="zw-card-title"><i class="fas fa-sticky-note"></i> Hinweise zum Plan</h2>
                <button type="button" class="zw-btn zw-btn-sm zw-btn-ghost" @click="neu = !neu"><i class="fas fa-plus"></i> Hinweis</button>
            </div>
            <form x-show.important="neu" x-cloak action="{{ route('roster.news.add', $roster->id) }}" method="post" class="zw-card-body flex flex-col sm:flex-row gap-2 border-b border-gray-100">
                @csrf
                <input type="text" name="news" class="zw-input" required maxlength="255" placeholder="Erscheint auf dem PDF">
                <button type="submit" class="zw-btn zw-btn-primary shrink-0">Speichern</button>
            </form>
            @if($roster->news->isEmpty())
                <div class="zw-empty py-6"><i class="far fa-sticky-note"></i> Keine Hinweise.</div>
            @else
                <ul class="zw-list">
                    @foreach($roster->news as $news)
                        <li class="zw-row">
                            <span class="flex-1 text-sm">{{ $news->news }}</span>
                            <form action="{{ route('roster.news.delete', $news->id) }}" method="post" data-confirm="Hinweis löschen?">
                                @csrf @method('delete')
                                <button type="submit" class="zw-btn-icon is-sm text-red-600" title="Löschen"><i class="fas fa-trash"></i></button>
                            </form>
                        </li>
                    @endforeach
                </ul>
            @endif
        </section>

        @if($roster->published)
            <section class="zw-card">
                <div class="zw-card-head">
                    <h2 class="zw-card-title"><i class="fas fa-history"></i> Änderungen seit Veröffentlichung</h2>
                </div>
                @if($aenderungen->isEmpty())
                    <div class="zw-empty py-6"><i class="fas fa-check"></i> Keine Änderungen.</div>
                @else
                    <ul class="zw-list max-h-80 overflow-y-auto">
                        @foreach($aenderungen as $aenderung)
                            <li class="zw-row text-sm">
                                <span class="w-2 h-2 rounded-full shrink-0 {{ $aenderung->notified_at ? 'bg-gray-300' : 'bg-amber-500' }}" title="{{ $aenderung->notified_at ? 'mitgeteilt' : 'noch nicht mitgeteilt' }}"></span>
                                <div class="flex-1 min-w-0">
                                    <div class="text-gray-900">{{ $aenderung->employe?->name ?? '–' }}: {{ $aenderung->description }}</div>
                                    <div class="text-xs text-gray-500">{{ $aenderung->date?->locale('de')->isoFormat('dd, DD.MM.') }} · {{ $aenderung->created_at->format('d.m. H:i') }}</div>
                                </div>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </section>
        @endif
    </div>

    {{-- Floating Button (mobil) --}}
    <button type="button" class="zw-fab" x-show.important="ansicht === 'tag'" @click="neuerTermin()" aria-label="Neuer Termin"><i class="fas fa-plus"></i></button>

    {{-- Toast --}}
    <div x-show="toast" x-cloak x-transition class="fixed left-1/2 -translate-x-1/2 bottom-24 lg:bottom-6 z-[1070] max-w-md w-[calc(100%-2rem)]">
        <div class="zw-alert shadow-lg" :class="{ 'zw-alert-success': toast?.type === 'success', 'zw-alert-warning': toast?.type === 'warning', 'zw-alert-danger': toast?.type === 'danger', 'zw-alert-info': toast?.type === 'info' }">
            <span class="flex-1" x-text="toast?.text"></span>
            <button type="button" @click="toast = null" aria-label="Schließen"><i class="fas fa-times"></i></button>
        </div>
    </div>

    {{-- ================= Dialog: Termin ================= --}}
    <div class="zw-dialog-backdrop" x-show.important="dialog === 'event'" x-cloak x-transition.opacity @click.self="schliessen()">
        <form class="zw-dialog" @submit.prevent="speichernTermin()" role="dialog" aria-modal="true">
            <div class="zw-dialog-head">
                <h3 class="text-base font-bold" x-text="ev.id ? 'Termin bearbeiten' : 'Neuer Termin'"></h3>
                <button type="button" class="zw-btn-icon is-sm" @click="schliessen()" aria-label="Schließen"><i class="fas fa-times"></i></button>
            </div>
            <div class="zw-dialog-body flex flex-col gap-4">
                <div>
                    <label class="zw-label" for="ev-name">Bezeichnung</label>
                    <input type="text" id="ev-name" x-ref="eventName" x-model="ev.event" list="event-namen" class="zw-input" required maxlength="190">
                    <datalist id="event-namen">
                        @foreach($eventNamen as $name)<option value="{{ $name }}"></option>@endforeach
                        <option value="Pause"></option>
                    </datalist>
                </div>
                <div class="grid grid-cols-3 gap-2">
                    <div class="col-span-3 sm:col-span-1">
                        <label class="zw-label" for="ev-date">Tag</label>
                        <select id="ev-date" x-model="ev.date" class="zw-select">
                            <template x-for="d in days" :key="d.date"><option :value="d.date" x-text="d.kurz + ', ' + d.tag" :selected="d.date === ev.date"></option></template>
                        </select>
                    </div>
                    <div>
                        <label class="zw-label" for="ev-start">Beginn</label>
                        <input type="time" id="ev-start" x-model="ev.start" class="zw-input" required step="300">
                    </div>
                    <div>
                        <label class="zw-label" for="ev-end">Ende</label>
                        <input type="time" id="ev-end" x-model="ev.end" class="zw-input" required step="300">
                    </div>
                </div>
                <div>
                    <span class="zw-label">Personen <span class="font-normal text-gray-400">(keine = Merkliste)</span></span>
                    <div class="flex flex-wrap gap-1.5">
                        <template x-for="emp in employes" :key="emp.id">
                            <button type="button" class="rounded-full border px-3 py-1.5 text-sm font-medium transition-colors"
                                    :class="ev.employes.includes(emp.id) ? 'bg-blue-600 border-blue-600 text-white' : 'bg-white border-gray-300 text-gray-700 hover:border-blue-400'"
                                    @click="toggleEmploye(emp.id)" x-text="emp.vorname" :title="emp.name"></button>
                        </template>
                    </div>
                </div>
                <p class="zw-error" x-show="fehler" x-text="fehler"></p>
            </div>
            <div class="zw-dialog-foot">
                <button type="button" class="zw-btn zw-btn-danger-ghost mr-auto" x-show="ev.id" @click="loeschenTermin()"><i class="fas fa-trash"></i> Löschen</button>
                <button type="button" class="zw-btn zw-btn-secondary" x-show="ev.id && ev.employes.length" @click="merken()"><i class="far fa-bookmark"></i> Merkliste</button>
                <button type="button" class="zw-btn zw-btn-secondary" @click="schliessen()">Abbrechen</button>
                <button type="submit" class="zw-btn zw-btn-primary" :disabled="busy"><i class="fas fa-save"></i> Speichern</button>
            </div>
        </form>
    </div>

    {{-- ================= Dialog: Arbeitszeit ================= --}}
    <div class="zw-dialog-backdrop" x-show.important="dialog === 'zeit'" x-cloak x-transition.opacity @click.self="schliessen()">
        <form class="zw-dialog" @submit.prevent="speichernZeit()" role="dialog" aria-modal="true">
            <div class="zw-dialog-head">
                <div>
                    <h3 class="text-base font-bold">Arbeitszeit</h3>
                    <p class="text-sm text-gray-500"><span x-text="zt.name"></span> · <span x-text="days.find(d => d.date === zt.date)?.label"></span></p>
                </div>
                <button type="button" class="zw-btn-icon is-sm" @click="schliessen()" aria-label="Schließen"><i class="fas fa-times"></i></button>
            </div>
            <div class="zw-dialog-body grid grid-cols-2 gap-3">
                <div>
                    <label class="zw-label" for="zt-start">Beginn</label>
                    <input type="time" id="zt-start" x-model="zt.start" class="zw-input" step="300">
                </div>
                <div>
                    <label class="zw-label" for="zt-end">Ende</label>
                    <input type="time" id="zt-end" x-model="zt.end" class="zw-input" step="300">
                </div>
                <div class="col-span-2">
                    <label class="zw-label" for="zt-function">Aufgabe</label>
                    <input type="text" id="zt-function" x-model="zt.function" class="zw-input" maxlength="190" placeholder="z. B. Frühdienst, Leitung …">
                </div>
                <p class="col-span-2 zw-error" x-show="fehler" x-text="fehler"></p>
            </div>
            <div class="zw-dialog-foot">
                <button type="button" class="zw-btn zw-btn-secondary mr-auto" @click="speichernZeit(true)"><i class="fas fa-bed"></i> Frei</button>
                <button type="button" class="zw-btn zw-btn-secondary" @click="schliessen()">Abbrechen</button>
                <button type="submit" class="zw-btn zw-btn-primary" :disabled="busy"><i class="fas fa-save"></i> Speichern</button>
            </div>
        </form>
    </div>

    {{-- ================= Dialog: Kopieren ================= --}}
    <div class="zw-dialog-backdrop" x-show.important="dialog === 'kopieren'" x-cloak x-transition.opacity @click.self="schliessen()">
        <form class="zw-dialog" action="{{ route('roster.copy', $roster->id) }}" method="post" role="dialog" aria-modal="true">
            @csrf
            <div class="zw-dialog-head">
                <h3 class="text-base font-bold">In weitere Wochen kopieren</h3>
                <button type="button" class="zw-btn-icon is-sm" @click="schliessen()" aria-label="Schließen"><i class="fas fa-times"></i></button>
            </div>
            <div class="zw-dialog-body">
                <p class="text-sm text-gray-600 mb-3">Dienste und Termine werden als neue Entwürfe angelegt. Feiertage und Abwesenheiten werden in jeder Woche automatisch berücksichtigt; bereits vorhandene Wochen werden übersprungen.</p>
                <div class="grid grid-cols-2 sm:grid-cols-3 gap-2">
                    @foreach($kopierWochen as $woche)
                        <label class="zw-check">
                            <input type="checkbox" name="wochen[]" value="{{ $woche->toDateString() }}">
                            KW {{ $woche->isoWeek() }} <span class="text-gray-400 text-xs">{{ $woche->format('d.m.') }}</span>
                        </label>
                    @endforeach
                </div>
            </div>
            <div class="zw-dialog-foot">
                <button type="button" class="zw-btn zw-btn-secondary" @click="schliessen()">Abbrechen</button>
                <button type="submit" class="zw-btn zw-btn-primary"><i class="far fa-copy"></i> Kopieren</button>
            </div>
        </form>
    </div>
</div>
@endsection
