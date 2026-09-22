@extends('layouts.app')

@push('css')
    @vite('resources/css/procedure.css')
@endpush

@php
    $activeProcsJson = json_encode(
        $procedures->load('category', 'steps')->map(function ($p) {
            $total   = $p->steps->count();
            $done    = $p->steps->where('done', true)->count();
            $overdue = $p->steps->filter(fn ($s) => !$s->done && $s->endDate && $s->endDate->isPast())->count();
            $dueSoon = $p->steps->filter(fn ($s) => !$s->done && $s->endDate && !$s->endDate->isPast() && $s->endDate->diffInDays(now()) <= 3)->count();
            return [
                'id'             => $p->id,
                'name'           => $p->name,
                'description'    => $p->description,
                'category'       => $p->category ? ['id' => $p->category->id, 'name' => $p->category->name, 'color' => $p->category->color] : null,
                'started_at'     => $p->started_at?->format('d.m.Y'),
                'steps_total'    => $total,
                'steps_done'     => $done,
                'steps_overdue'  => $overdue,
                'steps_due_soon' => $dueSoon,
                'progress'       => $total ? round($done / $total * 100) : 0,
            ];
        })->values(),
        JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT
    );
@endphp

@section('content')
<div class="procedure-wrapper"
     x-data="procedureApp()"
     x-init="init()"
     x-cloak>

    {{-- Flash --}}
    @if(session('Meldung'))
        <div class="alert-{{ session('type', 'info') }}">{{ session('Meldung') }}</div>
    @endif

    {{-- Topbar --}}
    <div class="flex flex-wrap items-center justify-between gap-4 mb-4">
        <h1 class="text-2xl font-bold text-gray-900">Prozesse</h1>
        <div class="flex flex-wrap gap-2 items-center">
            <input x-model="search" type="search" placeholder="Suche…" class="input-procedure w-44 text-sm">
            <template x-if="activeTab === 'active'">
                <div class="flex gap-1 flex-wrap">
                    <button @click="statusFilter=''"
                            :class="statusFilter==='' ? 'btn-procedure-primary text-xs py-1 px-3' : 'btn-procedure-secondary text-xs py-1 px-3'">Alle</button>
                    <button @click="statusFilter='open'"
                            :class="statusFilter==='open' ? 'btn-procedure-primary text-xs py-1 px-3' : 'btn-procedure-secondary text-xs py-1 px-3'">Offen</button>
                    <button @click="statusFilter='due'"
                            :class="statusFilter==='due' ? 'btn-procedure-primary text-xs py-1 px-3' : 'btn-procedure-secondary text-xs py-1 px-3'">Bald fällig</button>
                    <button @click="statusFilter='overdue'"
                            :class="statusFilter==='overdue' ? 'btn-procedure-danger text-xs py-1 px-3' : 'btn-procedure-secondary text-xs py-1 px-3'">Überfällig</button>
                </div>
            </template>
        </div>
    </div>

    {{-- Tabs --}}
    <div class="border-b border-gray-200 mb-6 overflow-x-auto">
        <nav class="flex gap-1 min-w-max">
            <button @click="setTab('active')"
                    :class="activeTab==='active' ? 'procedure-tab procedure-tab-active' : 'procedure-tab procedure-tab-inactive'">
                Aktive Prozesse
                <span class="ml-1.5 bg-gray-100 text-gray-600 text-xs rounded-full px-2 py-0.5">{{ $procedures->count() }}</span>
            </button>
            @can('manage procedures')
            <button @click="setTab('templates')"
                    :class="activeTab==='templates' ? 'procedure-tab procedure-tab-active' : 'procedure-tab procedure-tab-inactive'">
                Vorlagen & Kategorien
                <span class="ml-1.5 bg-gray-100 text-gray-600 text-xs rounded-full px-2 py-0.5">{{ $proceduresTemplate->count() }}</span>
            </button>
            <button @click="setTab('automation')"
                    :class="activeTab==='automation' ? 'procedure-tab procedure-tab-active' : 'procedure-tab procedure-tab-inactive'">
                Automatisierung
            </button>
            @endcan
        </nav>
    </div>

    {{-- ── TAB A: Aktive Prozesse ──────────────────────────── --}}
    <div x-show="activeTab === 'active'">
        @if($procedures->count() > 0)
        <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-4"
             x-data="{ allProcs: {{ $activeProcsJson }} }">
            <template x-for="proc in filteredProcedures(allProcs)" :key="proc.id">
                <a :href="'/procedure/' + proc.id + '/start'"
                   class="procedure-card block group no-underline hover:shadow-md">
                    <div class="flex items-start justify-between gap-2 mb-2">
                        <template x-if="proc.category">
                            <span class="text-xs font-medium px-2 py-0.5 rounded-full truncate max-w-[60%]"
                                  :style="proc.category.color ? 'background-color:'+proc.category.color+'22;color:'+proc.category.color : 'background-color:#f3f4f6;color:#4b5563'"
                                  x-text="proc.category.name"></span>
                        </template>
                        <template x-if="proc.steps_overdue > 0">
                            <span class="badge-step-overdue shrink-0" x-text="proc.steps_overdue + ' überfällig'"></span>
                        </template>
                        <template x-if="proc.steps_overdue === 0 && proc.steps_due_soon > 0">
                            <span class="badge-step-due shrink-0" x-text="proc.steps_due_soon + ' bald fällig'"></span>
                        </template>
                        <template x-if="proc.steps_overdue === 0 && proc.steps_due_soon === 0 && proc.progress === 100">
                            <span class="badge-step-done shrink-0">Abgeschlossen</span>
                        </template>
                    </div>
                    <h3 class="font-semibold text-gray-900 text-sm leading-snug group-hover:text-blue-600 transition-colors" x-text="proc.name"></h3>
                    <div class="mt-4">
                        <div class="flex justify-between text-xs text-gray-400 mb-1">
                            <span x-text="proc.steps_done + ' / ' + proc.steps_total + ' erledigt'"></span>
                            <span x-text="proc.progress + '%'"></span>
                        </div>
                        <div class="w-full bg-gray-100 rounded-full h-1.5">
                            <div class="rounded-full h-1.5 transition-all duration-500"
                                 :style="'width:' + proc.progress + '%'"
                                 :class="{
                                     'bg-red-500':   proc.steps_overdue > 0,
                                     'bg-amber-400': proc.steps_overdue===0 && proc.steps_due_soon > 0,
                                     'bg-green-500': proc.progress === 100,
                                     'bg-blue-500':  proc.steps_overdue===0 && proc.steps_due_soon===0 && proc.progress < 100
                                 }"></div>
                        </div>
                    </div>
                    <div class="mt-2 text-xs text-gray-400" x-text="'Gestartet: ' + (proc.started_at ?? '–')"></div>
                </a>
            </template>
            <template x-if="filteredProcedures(allProcs).length === 0">
                <div class="col-span-full text-center py-8 text-gray-400 text-sm">Keine Prozesse entsprechen den Filterkriterien.</div>
            </template>
        </div>
        @else
        <div class="text-center py-16 text-gray-400">
            <svg class="w-12 h-12 mx-auto mb-3 opacity-30" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/>
            </svg>
            <p class="text-lg mb-3">Keine aktiven Prozesse</p>
            @can('manage procedures')
            <button @click="setTab('templates')" class="btn-procedure-primary text-sm">Vorlage anlegen oder starten</button>
            @endcan
        </div>
        @endif
    </div>

    {{-- ── TAB B: Vorlagen & Kategorien ───────────────────── --}}
    <div x-show="activeTab === 'templates'" style="display:none">
        @can('manage procedures')
        {{-- Kategorie-Filter + Neue Kategorie --}}
        <div class="flex flex-wrap items-center justify-between gap-3 mb-6">
            <div class="flex gap-2 flex-wrap">
                <button @click="categoryFilter=null"
                        :class="categoryFilter===null ? 'btn-procedure-primary text-xs py-1 px-3' : 'btn-procedure-secondary text-xs py-1 px-3'">Alle</button>
                @foreach($categories as $cat)
                <button @click="categoryFilter={{ $cat->id }}"
                        :class="categoryFilter==={{ $cat->id }} ? 'btn-procedure-primary text-xs py-1 px-3' : 'btn-procedure-secondary text-xs py-1 px-3'">
                    {{ $cat->name }}
                    <span class="ml-1 opacity-50">({{ $proceduresTemplate->where('category_id', $cat->id)->count() }})</span>
                </button>
                @endforeach
            </div>
            <div x-data="{showCat:false}" class="flex gap-2 items-center">
                <template x-if="showCat">
                    <form action="{{ url('procedure/categories') }}" method="post" class="flex gap-2 items-center">
                        @csrf
                        <input name="name" type="text" placeholder="Kategoriename" class="input-procedure text-sm w-36" required>
                        <button type="submit" class="btn-procedure-primary text-xs py-1 px-3">Anlegen</button>
                    </form>
                </template>
                <button type="button" @click="showCat=!showCat" class="btn-procedure-secondary text-xs">
                    <span x-text="showCat ? '✕ Abbrechen' : '+ Kategorie'"></span>
                </button>
            </div>
        </div>

        @foreach($categories as $category)
        <div x-show="categoryFilter===null || categoryFilter==={{ $category->id }}" class="mb-10">
            <div class="flex items-center gap-3 mb-4 pb-2 border-b border-gray-100 flex-wrap">
                <h2 class="text-base font-bold text-gray-700">{{ $category->name }}</h2>
                <span class="text-xs text-gray-400">{{ $proceduresTemplate->where('category_id', $category->id)->count() }} Vorlage(n)</span>
                <span x-data="{editing:false,catName:@js($category->name)}" class="flex items-center gap-1 ml-auto">
                    <template x-if="!editing">
                        <button @click="editing=true" class="text-gray-400 hover:text-gray-600 text-xs px-2 py-1">✏️ umbenennen</button>
                    </template>
                    <template x-if="editing">
                        <form @submit.prevent="
                            fetch('/procedure/categories/{{ $category->id }}', {
                                method:'PUT',
                                headers:{'Content-Type':'application/json','Accept':'application/json','X-CSRF-TOKEN':document.querySelector('meta[name=csrf-token]').content},
                                body:JSON.stringify({name:catName})
                            }).then(r=>r.json()).then(()=>{editing=false;window.location.reload()}).catch(()=>alert('Fehler'))"
                             class="flex gap-1 items-center">
                            <input x-model="catName" type="text" class="input-procedure text-xs w-32">
                            <button type="submit" class="btn-procedure-primary text-xs py-0.5 px-2">OK</button>
                            <button type="button" @click="editing=false" class="btn-procedure-secondary text-xs py-0.5 px-2">×</button>
                        </form>
                    </template>
                </span>
                @if($proceduresTemplate->where('category_id', $category->id)->count() === 0)
                <form action="{{ url('procedure/categories/'.$category->id) }}" method="post" class="inline"
                      onsubmit="return confirm('Kategorie »{{ $category->name }}« wirklich löschen?')">
                    @csrf @method('DELETE')
                    <button type="submit" class="text-red-400 hover:text-red-600 text-xs px-2 py-1">🗑 löschen</button>
                </form>
                @endif
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-4">
                @foreach($proceduresTemplate->where('category_id', $category->id) as $template)
                <div class="procedure-card"
                     x-show="!search || '{{ strtolower(str_replace("'", "\\'", $template->name)) }}'.includes(search.toLowerCase())">
                    <div class="flex items-start justify-between gap-2 mb-2">
                        <h3 class="font-semibold text-sm text-gray-900 leading-snug flex-1">{{ $template->name }}</h3>
                        <span class="badge-step-open shrink-0">Vorlage</span>
                    </div>
                    @if($template->description)
                    <p class="text-xs text-gray-500 line-clamp-2 mb-3">{{ $template->description }}</p>
                    @endif
                    <div class="flex gap-2 flex-wrap pt-3 border-t border-gray-50 mt-auto">
                        <a href="{{ url('procedure/'.$template->id.'/start') }}" class="btn-procedure-success text-xs py-1 px-3 no-underline">▶ Starten</a>
                        <a href="{{ url('procedure/'.$template->id.'/edit') }}" class="btn-procedure-secondary text-xs py-1 px-3 no-underline">✏️ Bearbeiten</a>
                        <form action="{{ url('procedure/templates/'.$template->id.'/clone') }}" method="post" class="inline"
                              title="Duplizieren">
                            @csrf
                            <button type="submit" class="btn-procedure-secondary text-xs py-1 px-3">⧉ Kopieren</button>
                        </form>
                        <form action="{{ url('procedure/'.$template->id) }}" method="post" class="inline"
                              onsubmit="return confirm('Vorlage »{{ $template->name }}« löschen?')">
                            @csrf @method('DELETE')
                            <button type="submit" class="btn-procedure-danger text-xs py-1 px-3">🗑</button>
                        </form>
                    </div>
                </div>
                @endforeach

                {{-- Neue Vorlage inline --}}
                <div class="procedure-card border-2 border-dashed border-gray-200 flex flex-col" x-data="{show:false}">
                    <template x-if="!show">
                        <button @click="show=true" class="flex flex-col items-center justify-center h-full min-h-[7rem] text-gray-400 hover:text-gray-600 gap-1">
                            <span class="text-3xl leading-none">+</span>
                            <span class="text-xs">Vorlage anlegen</span>
                        </button>
                    </template>
                    <template x-if="show">
                        <form action="{{ url('procedure/create/template') }}" method="post" class="space-y-3">
                            @csrf
                            <input type="hidden" name="category_id" value="{{ $category->id }}">
                            <div>
                                <label class="procedure-label">Name <span class="text-red-500">*</span></label>
                                <input name="name" type="text" class="input-procedure" required>
                            </div>
                            <div>
                                <label class="procedure-label">Beschreibung</label>
                                <textarea name="description" rows="2" class="input-procedure"></textarea>
                            </div>
                            <div class="flex gap-2">
                                <button type="submit" class="btn-procedure-primary text-xs">Anlegen</button>
                                <button type="button" @click="show=false" class="btn-procedure-secondary text-xs">Abbrechen</button>
                            </div>
                        </form>
                    </template>
                </div>
            </div>
        </div>
        @endforeach

        {{-- Mit neuer Kategorie --}}
        <div class="procedure-card mt-2" x-data="{show:false}">
            <button @click="show=!show" class="btn-procedure-secondary text-sm">
                <span x-text="show ? '✕ Abbrechen' : '+ Vorlage mit neuer Kategorie'"></span>
            </button>
            <form x-show="show" action="{{ url('procedure/create/template') }}" method="post" class="mt-4 space-y-4">
                @csrf
                @if($errors->any())
                <div class="alert-error"><ul class="list-disc list-inside text-sm">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>
                @endif
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="procedure-label">Name <span class="text-red-500">*</span></label>
                        <input name="name" type="text" class="input-procedure" required value="{{ old('name') }}">
                    </div>
                    <div>
                        <label class="procedure-label">Kategorie <span class="text-red-500">*</span></label>
                        <select name="category_id" class="input-procedure" required>
                            <option value="" disabled selected></option>
                            @foreach($categories as $cat)
                            <option value="{{ $cat->id }}" {{ old('category_id')==$cat->id ? 'selected' : '' }}>{{ $cat->name }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>
                <div>
                    <label class="procedure-label">Beschreibung</label>
                    <textarea name="description" rows="3" class="input-procedure">{{ old('description') }}</textarea>
                </div>
                <button type="submit" class="btn-procedure-primary">Anlegen</button>
            </form>
        </div>
        @else
        <p class="text-gray-500 text-sm">Keine Berechtigung zur Vorlagenverwaltung.</p>
        @endcan
    </div>

    {{-- ── TAB C: Automatisierung ──────────────────────────── --}}
    <div x-show="activeTab === 'automation'" style="display:none">
        @can('manage procedures')
        <div class="grid grid-cols-1 xl:grid-cols-2 gap-8">

            {{-- Wiederkehrende Prozesse --}}
            <div>
                <h2 class="text-lg font-bold text-gray-800 mb-4">Wiederkehrende Prozesse</h2>
                @if($recurringProcedures->count() > 0)
                <div class="space-y-3 mb-6">
                    @foreach($recurringProcedures as $rp)
                    <div class="procedure-card flex items-start sm:items-center gap-4 flex-col sm:flex-row">
                        <div class="flex-1 min-w-0">
                            <div class="flex items-center gap-2 flex-wrap mb-1">
                                <span class="font-medium text-sm text-gray-900">{{ $rp->name }}</span>
                                @if(isset($rp->active) && !$rp->active)
                                    <span class="badge-step-open">Pausiert</span>
                                @else
                                    <span class="badge-step-active">Aktiv</span>
                                @endif
                            </div>
                            <p class="text-xs text-gray-500">
                                @if($rp->faelligkeit_typ === 'datum') Jeweils am 1. {{ $monate[$rp->month] ?? '' }}
                                @elseif($rp->faelligkeit_typ === 'vor_ferien') {{ $rp->wochen }} Wochen vor den {{ $rp->ferien }}
                                @elseif($rp->faelligkeit_typ === 'nach_ferien') {{ $rp->wochen }} Wochen nach den {{ $rp->ferien }}
                                @elseif($rp->faelligkeit_typ === 'wochentag') Alle {{ $rp->weekday_interval ?? 1 }} Woche(n), {{ ['Mo','Di','Mi','Do','Fr','Sa','So'][$rp->weekday ?? 0] }}
                                @elseif($rp->faelligkeit_typ === 'schuljahres_stichtag') Am {{ $rp->schuljahres_tag }}.{{ $rp->schuljahres_monat ? '/'.$rp->schuljahres_monat : '' }} jedes Schuljahres
                                @endif
                                @if($rp->next_trigger_at) &middot; Nächste Auslösung: <strong>{{ $rp->next_trigger_at->format('d.m.Y') }}</strong> @endif
                                @if($rp->last_triggered_at) &middot; Zuletzt: {{ $rp->last_triggered_at->format('d.m.Y') }} @endif
                            </p>
                            @if($rp->procedure)<p class="text-xs text-gray-400 mt-0.5">Vorlage: {{ $rp->procedure->name }}</p>@endif
                        </div>
                        <div class="flex gap-2 shrink-0">
                            <form action="{{ url('procedure/recurring/'.$rp->id.'/toggle') }}" method="post">
                                @csrf @method('PATCH')
                                <button type="submit" class="btn-procedure-secondary text-xs py-1 px-2">
                                    {{ isset($rp->active) && !$rp->active ? '▶ Aktivieren' : '⏸ Pausieren' }}
                                </button>
                            </form>
                            <form action="{{ url('procedure/recurring/'.$rp->id.'/trigger') }}" method="post">
                                @csrf
                                <button type="submit" class="btn-procedure-primary text-xs py-1 px-2">▶ Starten</button>
                            </form>
                            <form action="{{ url('procedure/recurring/'.$rp->id) }}" method="post"
                                  onsubmit="return confirm('Wirklich löschen?')">
                                @csrf @method('DELETE')
                                <button type="submit" class="btn-procedure-danger text-xs py-1 px-2">🗑</button>
                            </form>
                        </div>
                    </div>
                    @endforeach
                </div>
                @else
                <p class="text-sm text-gray-400 italic mb-6">Keine wiederkehrenden Prozesse vorhanden.</p>
                @endif

                {{-- Neuen anlegen --}}
                <div class="procedure-card" x-data="recurringForm()">
                    <h3 class="font-semibold text-sm text-gray-800 mb-4">Neuen wiederkehrenden Prozess anlegen</h3>
                    <form action="{{ url('procedure/recurring') }}" method="post" class="space-y-4">
                        @csrf
                        <div>
                            <label class="procedure-label">Name <span class="text-red-400">*</span></label>
                            <input type="text" name="name" class="input-procedure" required value="{{ old('name') }}">
                        </div>
                        <div>
                            <label class="procedure-label">Prozessvorlage <span class="text-red-400">*</span></label>
                            <select name="procedure_id" class="input-procedure" required>
                                <option value="" disabled selected></option>
                                @foreach($proceduresTemplate as $tmpl)
                                <option value="{{ $tmpl->id }}" {{ old('procedure_id')==$tmpl->id ? 'selected' : '' }}>{{ $tmpl->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label class="procedure-label">Auslösertyp <span class="text-red-400">*</span></label>
                            <select name="faelligkeit_typ" x-model="typ" class="input-procedure" required>
                                <option value="" disabled selected></option>
                                <option value="datum">Datum (1. des Monats)</option>
                                <option value="vor_ferien">Wochen vor den Ferien</option>
                                <option value="nach_ferien">Wochen nach den Ferien</option>
                                <option value="wochentag">Wöchentlich/Intervall</option>
                                <option value="schuljahres_stichtag">Schuljahres-Stichtag</option>
                            </select>
                        </div>
                        <div x-show="showDatum">
                            <label class="procedure-label">Monat</label>
                            <select name="month" class="input-procedure">
                                <option value="" disabled selected></option>
                                @foreach($monate as $k => $m)
                                <option value="{{ $k }}" {{ old('month')==$k ? 'selected' : '' }}>{{ $m }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div x-show="showFerienFields" class="grid grid-cols-2 gap-4">
                            <div>
                                <label class="procedure-label">Wochen</label>
                                <input type="number" name="wochen" min="1" max="52" class="input-procedure" value="{{ old('wochen') }}">
                            </div>
                            <div>
                                <label class="procedure-label">Ferien</label>
                                <select name="ferien" class="input-procedure">
                                    <option value="" disabled selected></option>
                                    @foreach(['Winterferien','Osterferien','Sommerferien','Herbstferien','Weihnachtsferien'] as $f)
                                    <option value="{{ $f }}" {{ old('ferien')===$f ? 'selected' : '' }}>{{ $f }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                        <div x-show="showWochentag" class="grid grid-cols-2 gap-4">
                            <div>
                                <label class="procedure-label">Wochentag</label>
                                <select name="weekday" class="input-procedure">
                                    @foreach(['Montag','Dienstag','Mittwoch','Donnerstag','Freitag','Samstag','Sonntag'] as $i => $t)
                                    <option value="{{ $i }}" {{ old('weekday')==$i ? 'selected' : '' }}>{{ $t }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div>
                                <label class="procedure-label">Intervall (Wochen)</label>
                                <input type="number" name="weekday_interval" min="1" max="52" class="input-procedure" value="{{ old('weekday_interval', 1) }}">
                            </div>
                        </div>
                        <div x-show="showSchuljahresStichtag" class="grid grid-cols-2 gap-4">
                            <div>
                                <label class="procedure-label">Tag (1–31)</label>
                                <input type="number" name="schuljahres_tag" min="1" max="31" class="input-procedure" value="{{ old('schuljahres_tag', 1) }}">
                            </div>
                            <div>
                                <label class="procedure-label">Monat</label>
                                <select name="schuljahres_monat" class="input-procedure">
                                    @foreach($monate as $k => $m)
                                    <option value="{{ $k }}" {{ old('schuljahres_monat')==$k ? 'selected' : '' }}>{{ $m }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                        <button type="submit" class="btn-procedure-primary">Anlegen</button>
                    </form>
                </div>
            </div>

            {{-- Positionen --}}
            <div>
                <h2 class="text-lg font-bold text-gray-800 mb-4">Positionen</h2>
                <div class="space-y-4 mb-6">
                    @forelse($positions as $position)
                    <div class="procedure-card">
                        <div class="flex items-center justify-between mb-3">
                            <h3 class="font-semibold text-sm text-gray-900">{{ $position->name }}</h3>
                            <span class="text-xs text-gray-400">{{ $position->users->count() }} Mitglied(er)</span>
                        </div>
                        <div class="flex flex-wrap gap-2 mb-3 min-h-[1.75rem]">
                            @foreach($position->users as $user)
                            <div class="flex items-center gap-1.5 bg-gray-50 rounded-full pl-1.5 pr-2.5 py-1 text-xs border border-gray-100">
                                <div class="w-5 h-5 rounded-full bg-blue-100 text-blue-700 text-xs font-semibold flex items-center justify-center">{{ substr($user->name,0,1) }}</div>
                                <span class="text-gray-700">{{ $user->name }}</span>
                                <form action="{{ url('procedure/positions/'.$position->id.'/remove/'.$user->id) }}" method="post" class="inline">
                                    @csrf
                                    <button type="submit" class="text-gray-400 hover:text-red-500 ml-0.5 leading-none" title="Entfernen">×</button>
                                </form>
                            </div>
                            @endforeach
                            @if($position->users->isEmpty())<span class="text-xs text-gray-400 italic">Noch keine Mitglieder</span>@endif
                        </div>
                        <form action="{{ url('procedure/positions/'.$position->id.'/add') }}" method="post" class="flex gap-2">
                            @csrf
                            <select name="person_id" class="input-procedure text-xs flex-1">
                                <option value=""></option>
                                @foreach($users as $user)
                                <option value="{{ $user->id }}">{{ $user->name }}</option>
                                @endforeach
                            </select>
                            <button type="submit" class="btn-procedure-success text-xs px-3">+ Hinzufügen</button>
                        </form>
                    </div>
                    @empty
                    <p class="text-sm text-gray-400 italic">Noch keine Positionen angelegt.</p>
                    @endforelse
                </div>
                <div class="procedure-card border-2 border-dashed border-gray-200" x-data="{show:false}">
                    <template x-if="!show">
                        <button @click="show=true" class="text-gray-500 text-sm hover:text-gray-800 w-full text-left">+ Neue Position anlegen</button>
                    </template>
                    <template x-if="show">
                        <form action="{{ url('procedure/position') }}" method="post" class="flex gap-2">
                            @csrf
                            <input name="name" type="text" placeholder="Positionsname" class="input-procedure flex-1" required>
                            <button type="submit" class="btn-procedure-primary text-sm">Anlegen</button>
                            <button type="button" @click="show=false" class="btn-procedure-secondary text-sm">Abbrechen</button>
                        </form>
                    </template>
                </div>
            </div>
        </div>
        @else
        <p class="text-gray-500 text-sm">Keine Berechtigung.</p>
        @endcan
    </div>

    {{-- Toast-Container --}}
    <div class="fixed bottom-4 right-4 z-50 space-y-2 pointer-events-none">
        <template x-for="toast in toasts" :key="toast.id">
            <div class="pointer-events-auto flex items-center gap-3 px-4 py-3 rounded-lg shadow-lg text-white text-sm"
                 :class="{'bg-green-600':toast.type==='success','bg-red-600':toast.type==='error','bg-amber-500':toast.type==='warning','bg-blue-600':toast.type==='info'}"
                 x-transition:enter="transition ease-out duration-200"
                 x-transition:enter-start="opacity-0 translate-y-2"
                 x-transition:enter-end="opacity-100 translate-y-0"
                 x-transition:leave="transition ease-in duration-150"
                 x-transition:leave-start="opacity-100"
                 x-transition:leave-end="opacity-0 translate-y-2">
                <span x-text="toast.message" class="flex-1"></span>
                <button @click="removeToast(toast.id)" class="opacity-70 hover:opacity-100 text-lg font-bold leading-none">×</button>
            </div>
        </template>
    </div>

</div>
@endsection

@push('js')
    @vite('resources/js/procedure.js')
@endpush
