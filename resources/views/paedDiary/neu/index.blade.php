{{--
    Pädagogisches Tagebuch – neue Wochenansicht (parallel zur bisherigen Ansicht paedDiary.v2.index)
    Layouts: klassisch | kompakt | karten (pro Person gespeichert, siehe User::paedDiaryDarstellung()).
    Logik: resources/js/paed-diary-neu.js (nutzt Store und Komponenten der bisherigen Ansicht).
--}}
@extends('layouts.app')

@section('content')
<div class="pdn"
     x-data="diaryNeu(@js($darstellung))"
     x-init="$store.diary.bootstrap({{ $klasse->id }}, {{ $selectedGroup?->id ?? 'null' }}); initNeu()"
     :class="wrapperKlassen">

    {{-- Modals der bisherigen Ansicht (Kopplungen, Aufgaben, Termine) --}}
    @include('paedDiary.v2.modals._group-modal')
    @include('paedDiary.v2.modals._task-modal')
    @include('paedDiary.v2.modals._appointment-modal')

    {{-- Eingabedialog (z. B. Grund der Tages-Pause) --}}
    <template x-if="$store.diary.textDialog">
        <div class="pdn-dialog" role="dialog" aria-modal="true" aria-labelledby="pdnDialogTitel"
             @keydown.escape.window="$store.diary.closeTextDialog(null)">
            <form @submit.prevent="$store.diary.closeTextDialog($refs.pdnDialogInput.value.trim())">
                <h2 id="pdnDialogTitel" style="font-size:1.1em;font-weight:800" x-text="$store.diary.textDialog.title"></h2>
                <label class="pdn-field" for="pdnDialogInput">
                    <span x-text="$store.diary.textDialog.label"></span>
                    <input id="pdnDialogInput" x-ref="pdnDialogInput" type="text" maxlength="100"
                           :value="$store.diary.textDialog.value" x-init="$nextTick(() => $el.select())">
                </label>
                <div style="display:flex;justify-content:flex-end;gap:8px">
                    <button type="button" class="pdn-btn" @click="$store.diary.closeTextDialog(null)">Abbrechen</button>
                    <button type="submit" class="pdn-btn pdn-btn--pri">Übernehmen</button>
                </div>
            </form>
        </div>
    </template>

    {{-- Spaltenverwaltung der bisherigen Ansicht (aufklappbar) --}}
    <div class="row">
        @include('paedDiary.v2.partials._columns-manager')
    </div>

    <div class="pdn-screen" data-tour="pdn-woche">
        {{-- ── Zeile 1: Klasse, Kopplung, Woche ── --}}
        <div class="pdn-tb">
            <h1 class="pdn-title">Pädagogisches Tagebuch</h1>

            <label class="sr-only" for="pdnKlasse">Klasse</label>
            <select id="pdnKlasse" class="pdn-sel" data-tour="pdn-klasse"
                    :value="$store.diary.selectedKlasseId"
                    @change="$store.diary.changeKlasse($event.target.value)">
                @foreach($klassen as $k)
                    <option value="{{ $k->id }}">{{ $k->name }} ({{ $k->schueler_count }})</option>
                @endforeach
            </select>

            <label class="sr-only" for="pdnKopplung">Kopplung</label>
            <select id="pdnKopplung" class="pdn-sel"
                    :value="$store.diary.selectedGroupId || ''"
                    @change="$store.diary.changeGroup($event.target.value)">
                <option value="">Keine Kopplung</option>
                <template x-for="g in $store.diary.groups" :key="g.id">
                    <option :value="g.id" x-text="g.name" :selected="$store.diary.selectedGroupId == g.id"></option>
                </template>
            </select>
            <button type="button" class="pdn-iconbtn" x-data="groupManager()" @click="openModal()"
                    title="Kopplungen verwalten" aria-label="Kopplungen verwalten">
                <i class="fas fa-object-group" aria-hidden="true"></i>
            </button>

            <span class="pdn-spacer"></span>

            <span class="pdn-wk" data-tour="pdn-wochennav">
                <button type="button" class="pdn-btn" @click="$store.diary.prevWeek()" aria-label="Vorherige Woche">‹</button>
                <button type="button" class="pdn-btn" @click="$store.diary.goToday()">Heute</button>
                <button type="button" class="pdn-btn" @click="$store.diary.nextWeek()" aria-label="Nächste Woche">›</button>
                <span x-text="$store.diary.weekLabel"></span>
            </span>
        </div>

        {{-- ── Zeile 2: Aktionen, Filter, Darstellung ── --}}
        <div class="pdn-tb">
            <button type="button" class="pdn-btn pdn-btn--pri" data-tour="pdn-notiz"
                    @click="$dispatch('diary-new-entry', {})">
                <i class="fas fa-plus" aria-hidden="true"></i><span class="pdn-btn-text">Notiz</span>
            </button>
            <button type="button" class="pdn-btn" x-data="taskPanel()" @click="openCreateModal()">
                <i class="fas fa-tasks" aria-hidden="true"></i><span class="pdn-btn-text">Aufgabe</span>
            </button>
            <button type="button" class="pdn-btn" x-data="appointmentManager()" @click="openCreateModal()">
                <i class="fas fa-calendar-alt" aria-hidden="true"></i><span class="pdn-btn-text">Termin</span>
            </button>
            <a class="pdn-btn" href="{{ route('gradingDocumentation.index') }}">
                <i class="fas fa-clipboard-check" aria-hidden="true"></i><span class="pdn-btn-text">Graduierung</span>
            </a>
            <a class="pdn-btn pdn-btn--ghost" :href="exportUrl" title="Excel-Export der Woche">
                <i class="fas fa-file-excel" aria-hidden="true"></i><span class="pdn-btn-text">Excel-Export</span>
            </a>

            <span class="pdn-spacer"></span>

            {{-- Filter --}}
            <div class="pdn-pop" @click.outside="filterOffen = false">
                <button type="button" class="pdn-btn pdn-btn--ghost" @click="filterOffen = !filterOffen"
                        :aria-expanded="filterOffen" aria-controls="pdnFilter">
                    <i class="fas fa-filter" aria-hidden="true"></i><span class="pdn-btn-text">Filter</span>
                </button>
                <div class="pdn-panel" id="pdnFilter" x-show="filterOffen" x-cloak>
                    <h3>Notizen filtern</h3>
                    <template x-for="cat in $store.diary.categories" :key="cat.id">
                        <label class="pdn-check">
                            <input type="checkbox" :checked="isCategoryVisible(cat.id)" @change="toggleCategoryHidden(cat.id)">
                            <span class="pdn-dot" :style="cat.color ? 'background:' + cat.color : ''"></span>
                            <span x-text="cat.name"></span>
                        </label>
                    </template>
                    <label class="pdn-check">
                        <input type="checkbox" :checked="!$store.diary.filterUncategorized" @change="toggleFilterUncategorized()">
                        <span class="pdn-dot"></span><span>Ohne Kategorie</span>
                    </label>
                    <div style="display:flex;flex-wrap:wrap;gap:6px">
                        <button type="button" class="pdn-btn" x-data="columnsManager()" @click="toggleColumnsCard(); filterOffen = false"
                                :disabled="!!$store.diary.selectedGroupId">Spalten verwalten</button>
                        <a class="pdn-btn" href="{{ route('paedDiary.categories.manage') }}">Kategorien</a>
                    </div>
                </div>
            </div>

            {{-- Darstellung --}}
            <div class="pdn-pop" @click.outside="darstellungOffen = false" data-tour="pdn-darstellung">
                <button type="button" class="pdn-btn" @click="darstellungOffen = !darstellungOffen"
                        :aria-expanded="darstellungOffen" aria-controls="pdnDarstellung">
                    <i class="fas fa-sliders-h" aria-hidden="true"></i><span class="pdn-btn-text">Darstellung</span>
                </button>
                <div class="pdn-panel" id="pdnDarstellung" x-show="darstellungOffen" x-cloak>
                    <h3>Darstellung</h3>
                    <div class="pdn-row">Ansicht
                        <div class="pdn-seg" role="group" aria-label="Ansicht">
                            <button type="button" @click="layoutWaehlen('klassisch')" :aria-pressed="darstellung.layout === 'klassisch'">Klassisch</button>
                            <button type="button" @click="layoutWaehlen('kompakt')" :aria-pressed="darstellung.layout === 'kompakt'">Kompakt</button>
                            <button type="button" @click="layoutWaehlen('karten')" :aria-pressed="darstellung.layout === 'karten'">Tageskarten</button>
                        </div>
                    </div>
                    <div class="pdn-row">Schriftgröße
                        <div class="pdn-seg" role="group" aria-label="Schriftgröße">
                            <button type="button" @click="setze('schrift', 'standard')" :aria-pressed="darstellung.schrift === 'standard'">Standard</button>
                            <button type="button" @click="setze('schrift', 'gross')" :aria-pressed="darstellung.schrift === 'gross'">Groß</button>
                        </div>
                    </div>
                    <label class="pdn-switch">Kategorien farbig
                        <input type="checkbox" :checked="darstellung.kategorie_farben" @change="setze('kategorie_farben', $event.target.checked)"><i></i>
                    </label>
                    <label class="pdn-switch">Pausierte Notizen zeigen
                        <input type="checkbox" :checked="darstellung.pausierte" @change="setze('pausierte', $event.target.checked)"><i></i>
                    </label>
                    <p class="pdn-label-sm">Wird für dein Konto gespeichert und gilt auf allen Geräten.</p>
                    <form method="post" action="{{ route('paedDiary.settings.darstellung') }}" class="pdn-ansicht-aktionen">
                        @csrf
                        <input type="hidden" name="ansicht" value="bisher">
                        <button type="submit" class="pdn-btn" style="width:100%;justify-content:center">Zur bisherigen Ansicht wechseln</button>
                    </form>
                </div>
            </div>

            <button type="button" class="pdn-btn pdn-btn--ghost" data-tour-start="paed-diary-neu" title="Tour durch die neue Ansicht">
                <i class="fas fa-route" aria-hidden="true"></i><span class="pdn-btn-text">Tour</span>
            </button>
        </div>

        {{-- Meldungen (Fehler/Hinweise) --}}
        <div x-show="$store.diary.errorMessage" x-cloak role="alert" class="pdn-meldung"
             :class="$store.diary.messageType === 'info' ? 'pdn-meldung--info' : 'pdn-meldung--danger'">
            <span x-text="$store.diary.errorMessage"></span>
            <button type="button" aria-label="Meldung schließen" @click="$store.diary.errorMessage = ''">&times;</button>
        </div>

        <div x-show="$store.diary.loading" x-cloak class="pdn-leer" role="status">
            <i class="fas fa-spinner fa-spin" aria-hidden="true"></i> Woche wird geladen …
        </div>

        <div x-show="!$store.diary.loading && $store.diary.schueler.length === 0" x-cloak class="pdn-leer">
            Keine Schüler in der gewählten Klasse oder Kopplung.
        </div>

        {{-- ═══════════ Layout: Klassisch und Kompakt (Tabelle) ═══════════ --}}
        <template x-if="darstellung.layout !== 'karten' && !$store.diary.loading && $store.diary.schueler.length">
            <div>
                {{-- Legende der Kategoriefarben (nur Kompakt) --}}
                <div class="pdn-legend" x-show="darstellung.layout === 'kompakt' && darstellung.kategorie_farben && genutzteKategorien.length">
                    <template x-for="cat in genutzteKategorien" :key="'lg-' + cat.id">
                        <span><i :style="'background:' + (cat.color || '#d9dee6')"></i><span x-text="cat.name"></span></span>
                    </template>
                </div>

                <div class="pdn-scroll">
                    <table class="pdn-grid">
                        <thead>
                            <tr>
                                <th class="pdn-name" scope="col">Schüler</th>
                                <template x-for="day in $store.diary.days" :key="'h-' + day.date">
                                    <th scope="col" :class="{ 'is-today': day.date === $store.diary.todayStr, 'is-ferien': day.is_ferien }">
                                        <div class="pdn-dayhead">
                                            <span x-text="day.label + (day.date === $store.diary.todayStr ? ' · heute' : '')"></span>
                                            <template x-if="!day.is_ferien">
                                                <button type="button" class="pdn-iconbtn" @click="toggleDayPause(day.date)"
                                                        :aria-pressed="isDayPaused(day.date)"
                                                        :title="isDayPaused(day.date) ? 'Tages-Pause aufheben (' + getDayPauseReason(day.date) + ')' : 'Alle Notizen dieses Tages pausieren'"
                                                        :aria-label="isDayPaused(day.date) ? 'Tages-Pause aufheben' : 'Tag pausieren'">
                                                    <i class="fas fa-pause" aria-hidden="true"></i>
                                                </button>
                                            </template>
                                        </div>
                                        <span class="pdn-pause-lbl" x-show="isDayPaused(day.date)" x-text="'Pausiert: ' + getDayPauseReason(day.date)"></span>
                                        <span class="pdn-pause-lbl" x-show="day.is_ferien" x-text="day.ferien_name || 'Ferien'"></span>
                                        <template x-for="apt in getHeaderAppointments(day.date)" :key="'ha-' + apt.id">
                                            <button type="button" class="pdn-apt" :title="apt.description || apt.title"
                                                    @click="$dispatch('edit-appointment', apt)" x-text="formatAppointmentLabel(apt)"></button>
                                        </template>
                                    </th>
                                </template>
                            </tr>
                        </thead>
                        <template x-for="grp in diaryGroups" :key="grp.key">
                            <tbody>
                                <template x-if="grp.showDivider">
                                    <tr class="pdn-trenner"><td :colspan="$store.diary.days.length + 1" x-text="grp.klasseName"></td></tr>
                                </template>
                                <template x-for="stu in grp.students" :key="stu.id">
                                    <tr>
                                        <th scope="row" class="pdn-name">
                                            <div class="pdn-stu">
                                                <span x-data="stageDropdown()">
                                                    <button type="button" class="pdn-stage pdn-stage--klickbar"
                                                            x-show="$store.diary.can_change_stage"
                                                            @click.stop.prevent="openDropdown(stu.id, stu.klasse_id, $event.currentTarget)"
                                                            :aria-label="'Stufe von ' + stu.name + ' ändern'"
                                                            :title="stu.stage ? stu.stage.name : 'Keine Stufe'">
                                                        <span x-html="stageHtml(stu)"></span>
                                                    </button>
                                                    <span class="pdn-stage" x-show="!$store.diary.can_change_stage"
                                                          :title="stu.stage ? stu.stage.name : 'Keine Stufe'" x-html="stageHtml(stu)"></span>
                                                    <template x-teleport="body">
                                                        <div x-show="dropdownOpen && dropdownStuId === stu.id" x-cloak
                                                             class="bg-white border rounded shadow-sm p-1"
                                                             :style="`position:fixed; top:${dropdownTop}px; left:${dropdownLeft}px; z-index:9999; min-width:180px; max-height:300px; overflow-y:auto;`">
                                                            <div x-show="stageLoading" class="small text-muted p-1">Lade …</div>
                                                            <template x-if="!stageLoading">
                                                                <div style="display:flex;flex-direction:column;gap:4px;">
                                                                    <button type="button" class="btn btn-sm btn-outline-secondary text-left" @click="selectStage('')">— Keine Stufe</button>
                                                                    <template x-for="stage in stages" :key="stage.id">
                                                                        <button type="button" class="btn btn-sm btn-outline-secondary text-left" @click="selectStage(stage.id)"
                                                                                x-text="stage.name || stage.symbol"></button>
                                                                    </template>
                                                                </div>
                                                            </template>
                                                        </div>
                                                    </template>
                                                </span>
                                                <a :href="'/paed-diary/schueler/' + stu.id" x-text="stu.name" title="Schülerakte öffnen"></a>
                                                <template x-if="(stu.absence_alerts || []).length">
                                                    <span class="pdn-warnbadge" :title="stu.absence_alerts.map(a => a.label + ': ' + a.summary).join('\n')"
                                                          x-text="'⚠ ' + stu.absence_alerts.length"></span>
                                                </template>
                                            </div>
                                            <div class="pdn-open">
                                                <template x-for="entry in getOpenEntriesForStudent(stu.id)" :key="'oe-' + entry.id">
                                                    <div class="pdn-pill pdn-pill--note">
                                                        <span x-text="trimText(entry.content, darstellung.layout === 'kompakt' ? 40 : 70)" :title="entry.content"></span>
                                                        <button type="button" @click="completeOpenEntry(entry.id)" aria-label="Notiz abschließen" title="Notiz abschließen">✓</button>
                                                    </div>
                                                </template>
                                                <template x-for="task in getOpenTasksForStudent(stu.id)" :key="'ot-' + task.id">
                                                    <div class="pdn-pill pdn-pill--task" :class="task.highlighted && 'pdn-pill--wichtig'">
                                                        <span x-text="task.title + (task.due_date ? ' · bis ' + new Date(task.due_date).toLocaleDateString('de-DE') : '')"></span>
                                                        <button type="button" @click="closeOpenTask(task.id)" aria-label="Aufgabe erledigt" title="Aufgabe erledigt">✓</button>
                                                    </div>
                                                </template>
                                            </div>
                                        </th>

                                        <template x-for="day in $store.diary.days" :key="stu.id + '-' + day.date">
                                            <td class="pdn-cell"
                                                :class="{ 'is-today': day.date === $store.diary.todayStr, 'is-ferien': day.is_ferien, 'pdn-absent': $store.diary.isAbsent(stu.id, day.date) }">
                                                <button type="button" class="pdn-cell-add" @click="neueNotiz(stu.id, day.date)"
                                                        :aria-label="'Neue Notiz für ' + stu.name + ', ' + day.label"></button>
                                                <div class="pdn-cell-top">
                                                    <button type="button" class="pdn-iconbtn"
                                                            @click="toggleAbsence(stu.id, stu.klasse_id, day.date)"
                                                            :aria-pressed="$store.diary.isAbsent(stu.id, day.date)"
                                                            :aria-label="($store.diary.isAbsent(stu.id, day.date) ? 'Abwesenheit aufheben: ' : 'Als abwesend markieren: ') + stu.name"
                                                            :title="$store.diary.isAbsent(stu.id, day.date) ? 'Abwesenheit aufheben' : 'Als abwesend markieren'">
                                                        <i class="fas" :class="$store.diary.isAbsent(stu.id, day.date) ? 'fa-user-slash' : 'fa-user-check'" aria-hidden="true"></i>
                                                    </button>
                                                </div>

                                                <template x-if="$store.diary.isAbsent(stu.id, day.date)">
                                                    <div><span class="pdn-absent-lbl">Abwesend</span><div class="pdn-label-sm">Notizen pausiert</div></div>
                                                </template>

                                                <template x-if="!$store.diary.isAbsent(stu.id, day.date)">
                                                    <div>
                                                        {{-- Notizen (Klassisch: mit Kategorie-Überschrift; Kompakt: einzeilige Chips) --}}
                                                        <template x-for="group in getGroupedEntries(stu.id, day.date)" :key="stu.id + day.date + group.category">
                                                            <div>
                                                                <div class="pdn-cathead" x-show="darstellung.layout === 'klassisch' && group.category" x-text="group.category"></div>
                                                                <template x-for="entry in group.entries" :key="entry.id + '-' + day.date">
                                                                    <div class="pdn-entry" role="button" tabindex="0"
                                                                         :data-farbe="entry.category_color ? '' : null" :style="farbe(entry)"
                                                                         @click.stop="bearbeiten(entry, day.date)"
                                                                         @keydown.enter.prevent="bearbeiten(entry, day.date)"
                                                                         :title="entry.content">
                                                                        <span class="pdn-entry__text">
                                                                            <span x-text="darstellung.layout === 'kompakt' ? entry.content : trimText(entry.content, 140)"></span>
                                                                            <span class="pdn-entry__who" x-show="entry.user" x-text="' · ' + entry.user"></span>
                                                                        </span>
                                                                        <span class="pdn-entry__actions" x-show="!entry.completed_at">
                                                                            <button type="button" @click.stop="completeEntry(entry.id, stu.id, day.date)" aria-label="Notiz abschließen" title="Abschließen"><i class="fas fa-check" aria-hidden="true"></i></button>
                                                                            <button type="button" @click.stop="pauseEntry(entry.id, stu.id, day.date)" aria-label="Notiz an diesem Tag pausieren" title="An diesem Tag pausieren"><i class="fas fa-pause" aria-hidden="true"></i></button>
                                                                        </span>
                                                                    </div>
                                                                </template>
                                                            </div>
                                                        </template>

                                                        <template x-if="$store.diary.showPaused">
                                                            <div>
                                                                <template x-for="entry in getPausedEntries(stu.id, day.date)" :key="'p-' + entry.id + day.date">
                                                                    <div class="pdn-entry pdn-entry--paused">
                                                                        <span class="pdn-entry__text" x-text="'Pausiert: ' + trimText(entry.content, 80)"></span>
                                                                        <span class="pdn-entry__actions">
                                                                            <button type="button" @click.stop="unpauseEntry(entry.id, stu.id, day.date)" aria-label="An diesem Tag wieder zeigen" title="Wieder zeigen"><i class="fas fa-play" aria-hidden="true"></i></button>
                                                                        </span>
                                                                    </div>
                                                                </template>
                                                            </div>
                                                        </template>

                                                        {{-- Zusatzspalten --}}
                                                        <div class="pdn-cols" x-data="columnCell()">
                                                            <template x-for="col in getColumnsForStudent(stu)" :key="col.id">
                                                                <span>
                                                                    <template x-if="col.type === 'boolean'">
                                                                        <button type="button" class="pdn-tog" :class="boolKlasse(getColumnValue(col.id, stu.id, day.date))"
                                                                                @click.stop="toggleBoolColumn(col.id, stu.id, day.date)"
                                                                                :aria-pressed="getColumnValue(col.id, stu.id, day.date) === '1'"
                                                                                :title="col.name"
                                                                                x-text="darstellung.layout === 'kompakt' ? col.name.slice(0, 1) : col.name"></button>
                                                                    </template>
                                                                    <template x-if="col.type === 'ampel'">
                                                                        <button type="button" class="pdn-tog" :class="ampelKlasse(getColumnValue(col.id, stu.id, day.date))"
                                                                                @click.stop="toggleAmpelColumn(col.id, stu.id, day.date)"
                                                                                :title="col.name + ': ' + ampelState(col.id, stu.id, day.date).label"
                                                                                x-text="darstellung.layout === 'kompakt' ? col.name.slice(0, 1) : col.name"></button>
                                                                    </template>
                                                                    <template x-if="col.type !== 'boolean' && col.type !== 'ampel'">
                                                                        <input type="text" maxlength="255" class="pdn-colinput"
                                                                               :class="hasSaveError(col.id, stu.id, day.date) && 'is-fehler'"
                                                                               :value="getColumnValue(col.id, stu.id, day.date)"
                                                                               @click.stop
                                                                               @input.stop="debouncedSaveColumn(col.id, stu.id, day.date, $event.target.value)"
                                                                               :placeholder="col.name" :aria-label="col.name + ' für ' + stu.name">
                                                                    </template>
                                                                </span>
                                                            </template>
                                                        </div>

                                                        <template x-for="apt in getClassAppointmentsForStudent(stu.id, day.date).concat(getStudentAppointments(stu.id, day.date))" :key="'a-' + stu.id + apt.id + day.date">
                                                            <button type="button" class="pdn-apt" @click.stop="$dispatch('edit-appointment', apt)"
                                                                    :title="apt.description || apt.title" x-text="formatAppointmentShortLabel(apt)"></button>
                                                        </template>
                                                    </div>
                                                </template>
                                            </td>
                                        </template>
                                    </tr>
                                </template>
                            </tbody>
                        </template>
                    </table>
                </div>
            </div>
        </template>

        {{-- ═══════════ Layout: Tageskarten ═══════════ --}}
        <template x-if="darstellung.layout === 'karten' && !$store.diary.loading && $store.diary.schueler.length">
            <div>
                <div class="pdn-days" role="group" aria-label="Tag wählen">
                    <template x-for="day in $store.diary.days" :key="'d-' + day.date">
                        <button type="button" @click="$store.diary.mobileDay = day.date"
                                :aria-pressed="day.date === $store.diary.mobileDay"
                                :class="day.is_ferien && 'is-ferien'">
                            <span x-text="day.label.split(' ')[0]"></span>
                            <small x-text="day.date === $store.diary.todayStr ? 'heute' : day.label.split(' ')[1]"></small>
                        </button>
                    </template>
                </div>

                <template x-for="day in $store.diary.days.filter(d => d.date === $store.diary.mobileDay)" :key="'kd-' + day.date">
                    <div>
                        <div class="pdn-tb" x-show="isDayPaused(day.date) || getHeaderAppointments(day.date).length || day.is_ferien">
                            <span class="pdn-pause-lbl" x-show="day.is_ferien" x-text="day.ferien_name || 'Ferien'"></span>
                            <span class="pdn-pause-lbl" x-show="isDayPaused(day.date)" x-text="'Tag pausiert: ' + getDayPauseReason(day.date)"></span>
                            <template x-for="apt in getHeaderAppointments(day.date)" :key="'kha-' + apt.id">
                                <button type="button" class="pdn-apt" @click="$dispatch('edit-appointment', apt)" x-text="formatAppointmentLabel(apt)"></button>
                            </template>
                        </div>
                        <div class="pdn-cards">
                            <template x-for="stu in $store.diary.schueler" :key="'k-' + stu.id">
                                <article class="pdn-card" :class="$store.diary.isAbsent(stu.id, day.date) && 'is-absent'">
                                    <header>
                                        <span class="pdn-stage" :title="stu.stage ? stu.stage.name : 'Keine Stufe'" x-data="stageDropdown()" x-html="stageHtml(stu)"></span>
                                        <a :href="'/paed-diary/schueler/' + stu.id" x-text="stu.name"></a>
                                        <button type="button" class="pdn-presence"
                                                :class="$store.diary.isAbsent(stu.id, day.date) && 'is-absent'"
                                                @click="toggleAbsence(stu.id, stu.klasse_id, day.date)"
                                                :aria-pressed="$store.diary.isAbsent(stu.id, day.date)"
                                                x-text="$store.diary.isAbsent(stu.id, day.date) ? 'Abwesend' : 'Anwesend'"></button>
                                    </header>

                                    <template x-if="$store.diary.isAbsent(stu.id, day.date)">
                                        <p class="pdn-label-sm">Notizen sind für heute pausiert.</p>
                                    </template>

                                    <template x-if="!$store.diary.isAbsent(stu.id, day.date)">
                                        <div style="display:flex;flex-direction:column;gap:8px">
                                            <template x-for="entry in notizen(stu.id, day.date)" :key="'kn-' + entry.id">
                                                <button type="button" class="pdn-entry" :data-farbe="entry.category_color ? '' : null" :style="farbe(entry)"
                                                        @click="bearbeiten(entry, day.date)">
                                                    <span class="pdn-entry__text" x-text="entry.content"></span>
                                                </button>
                                            </template>
                                            <template x-for="task in getOpenTasksForStudent(stu.id)" :key="'kt-' + task.id">
                                                <div class="pdn-pill pdn-pill--task" :class="task.highlighted && 'pdn-pill--wichtig'">
                                                    <span x-text="task.title"></span>
                                                    <button type="button" @click="closeOpenTask(task.id)" aria-label="Aufgabe erledigt">✓</button>
                                                </div>
                                            </template>
                                            <div class="pdn-cols" x-data="columnCell()">
                                                <template x-for="col in getColumnsForStudent(stu).filter(c => c.type === 'boolean' || c.type === 'ampel')" :key="'kc-' + col.id">
                                                    <button type="button" class="pdn-tog"
                                                            :class="col.type === 'ampel' ? ampelKlasse(getColumnValue(col.id, stu.id, day.date)) : boolKlasse(getColumnValue(col.id, stu.id, day.date))"
                                                            @click="col.type === 'ampel' ? toggleAmpelColumn(col.id, stu.id, day.date) : toggleBoolColumn(col.id, stu.id, day.date)"
                                                            :title="col.type === 'ampel' ? col.name + ': ' + ampelState(col.id, stu.id, day.date).label : col.name"
                                                            x-text="col.name"></button>
                                                </template>
                                            </div>
                                            <template x-for="apt in getClassAppointmentsForStudent(stu.id, day.date).concat(getStudentAppointments(stu.id, day.date))" :key="'ka-' + stu.id + apt.id">
                                                <button type="button" class="pdn-apt" @click="$dispatch('edit-appointment', apt)" x-text="formatAppointmentShortLabel(apt)"></button>
                                            </template>
                                            <button type="button" class="pdn-card-add" @click="neueNotiz(stu.id, day.date)"
                                                    x-text="'+ Notiz für ' + stu.name.split(' ')[0]"></button>
                                        </div>
                                    </template>
                                </article>
                            </template>
                        </div>
                    </div>
                </template>
            </div>
        </template>
    </div>

    {{-- Offene Aufgaben (wie bisher) --}}
    <div class="row mt-3">
        @include('paedDiary.v2.partials._tasks-panel')
    </div>

    {{-- ═══════════ Seitenleiste: Notiz erfassen/bearbeiten ═══════════ --}}
    <div x-data="notizSeite()"
         @diary-edit-entry.window="openForEdit($event.detail)"
         @diary-new-entry.window="openForNew($event.detail)"
         @keydown.escape.window="editorOpen && closeEditor()">
        <template x-if="editorOpen">
            <div>
                <div class="pdn-drawer-backdrop" @click="closeEditor()"></div>
                <aside class="pdn-drawer" role="dialog" aria-modal="true" aria-labelledby="pdnNotizTitel">
                    <header>
                        <h2 id="pdnNotizTitel" x-text="editingEntryId ? 'Notiz bearbeiten' : 'Notiz erfassen'"></h2>
                        <button type="button" class="pdn-iconbtn" @click="closeEditor()" aria-label="Schließen">✕</button>
                    </header>
                    <form id="pdnNotizForm" @submit.prevent="saveEntry()">
                        <label class="pdn-field">
                            <span x-text="editingEntryId ? 'Begonnen am' : 'Datum'"></span>
                            <input type="date" x-model="formDate" required>
                        </label>
                        <fieldset class="pdn-field" style="border:0;padding:0;margin:0">
                            <legend style="font-size:1em;margin:0 0 4px;color:inherit">Schüler</legend>
                            <label class="pdn-check">
                                <input type="checkbox" :checked="isAllSelected" @change="toggleAll($event.target.checked)">
                                <span>Alle auswählen</span>
                            </label>
                            <div class="pdn-chips">
                                <template x-for="stu in $store.diary.schueler" :key="'ns-' + stu.id">
                                    <label><input type="checkbox" :value="String(stu.id)" x-model="formSchuelerIds"><span x-text="stu.name"></span></label>
                                </template>
                            </div>
                        </fieldset>
                        <label class="pdn-field">Notiz
                            <textarea x-ref="notizText" x-model="formContent" required maxlength="20000"></textarea>
                        </label>
                        <label class="pdn-field">Kategorie
                            <select x-model="formCategoryId">
                                <option value="">Ohne Kategorie</option>
                                <template x-for="cat in $store.diary.categories" :key="'nc-' + cat.id">
                                    <option :value="String(cat.id)" x-text="cat.name"></option>
                                </template>
                            </select>
                        </label>
                        <label class="pdn-field">Oder neue Kategorie
                            <input type="text" x-model="formNewCategory" maxlength="100" placeholder="z. B. Lernen">
                        </label>
                        <label class="pdn-check"><input type="checkbox" x-model="formCompleted"><span>Erledigt (nicht weiterführen)</span></label>
                        <label class="pdn-check"><input type="checkbox" x-model="formDossierOnly"><span class="pdn-lock">Vertraulich: nur in Schülerakte und Dossier</span></label>
                        <p class="pdn-hinweis" x-show="editingEntryId">Das Startdatum bleibt erhalten, auch wenn du die Notiz über eine andere Tageszelle öffnest.</p>
                    </form>
                    <footer>
                        <button type="submit" form="pdnNotizForm" class="pdn-btn pdn-btn--pri" :disabled="saving">
                            <i class="fas fa-spinner fa-spin" x-show="saving" aria-hidden="true"></i> Speichern
                        </button>
                        <button type="button" class="pdn-btn" x-show="editingEntryId" @click="deleteEntry()">Löschen</button>
                        <span class="pdn-hinweis" x-text="statusText" role="status"></span>
                    </footer>
                </aside>
            </div>
        </template>
    </div>
</div>

<x-tour id="paed-diary-neu" :steps="[
    ['title' => 'Willkommen in der neuen Ansicht', 'text' => 'Die Funktionen sind dieselben wie bisher, nur übersichtlicher angeordnet. Diese Tour zeigt dir in fünf Schritten das Wichtigste.

Du kannst jederzeit zur bisherigen Ansicht zurückwechseln.'],
    ['target' => 'pdn-klasse', 'title' => 'Klasse und Kopplung', 'text' => 'Hier wählst du die Klasse oder eine Kopplung mehrerer Klassen. Neben der Kopplung verwaltest du deine Kopplungen.'],
    ['target' => 'pdn-wochennav', 'title' => 'Woche wechseln', 'text' => 'Mit ‹ und › blätterst du durch die Wochen, „Heute“ springt zurück zur aktuellen Woche.'],
    ['target' => 'pdn-notiz', 'title' => 'Notizen erfassen', 'text' => 'Neue Notizen legst du hier oder per Klick in eine leere Tageszelle an. Bearbeitet wird in einer Seitenleiste, die Woche bleibt sichtbar.

Ein Klick auf eine Notiz öffnet sie zum Bearbeiten.'],
    ['target' => 'pdn-woche', 'title' => 'Die Woche im Blick', 'text' => 'Notizen erscheinen in der Farbe ihrer Kategorie. Mit dem Personen-Symbol markierst du Abwesenheit, mit dem Pause-Symbol im Tageskopf pausierst du einen ganzen Tag (z. B. Wandertag).

Ein Klick auf den Namen öffnet die Schülerakte.'],
    ['target' => 'pdn-darstellung', 'title' => 'Darstellung anpassen', 'text' => 'Wähle zwischen Klassisch, Kompakt (viele Schüler auf einen Blick) und Tageskarten (ideal für das iPad). Auch Schriftgröße und Kategoriefarben stellst du hier ein.

Hier wechselst du auch zurück zur bisherigen Ansicht.'],
]" />
@endsection

@push('js')
    @vite(['resources/js/paed-diary-neu.js'])
@endpush
