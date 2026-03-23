{{--
    PaedDiary v2 – Header Partial
    Toolbar: Klasse/Gruppe-Auswahl, Wochen-Navigation, Buttons
--}}
<div class="card-header d-flex flex-wrap align-items-center justify-content-between">
    <div class="d-flex align-items-center flex-wrap">
        <h5 class="mb-0 mr-3">Pädagogische Dokumentation</h5>

        {{-- Klassen-Auswahl --}}
        <div class="form-inline mr-3">
            <label class="mr-2 mb-0">Klasse</label>
            <select class="form-control form-control-sm"
                    :value="$store.diary.selectedKlasseId"
                    @change="$store.diary.changeKlasse($event.target.value)">
                @foreach($klassen as $k)
                    <option value="{{ $k->id }}">{{ $k->name }} ({{ $k->schueler_count }})</option>
                @endforeach
            </select>
        </div>

        {{-- Gruppen-Auswahl --}}
        <div class="form-inline mr-3">
            <label class="mr-2 mb-0">Kopplung</label>
            <select class="form-control form-control-sm"
                    :value="$store.diary.selectedGroupId || ''"
                    @change="$store.diary.changeGroup($event.target.value)">
                <option value="">-- Gruppe --</option>
                <template x-for="g in $store.diary.groups" :key="g.id">
                    <option :value="g.id" x-text="g.name" :selected="$store.diary.selectedGroupId == g.id"></option>
                </template>
            </select>
            <button class="btn btn-outline-secondary btn-sm ml-2"
                    x-data="groupManager()"
                    @click="openModal()"
                    title="Kopplungen verwalten">
                <i class="fas fa-object-group"></i>
            </button>
        </div>

        {{-- Wochen-Navigation --}}
        <div class="btn-group btn-group-sm mr-2" role="group">
            <button class="btn btn-outline-secondary" @click="$store.diary.prevWeek()" title="Vorherige Woche">&laquo;</button>
            <button class="btn btn-outline-secondary" @click="$store.diary.goToday()" title="Aktuelle Woche">Heute</button>
            <button class="btn btn-outline-secondary" @click="$store.diary.nextWeek()" title="Nächste Woche">&raquo;</button>
        </div>

        {{-- Wochenlabel --}}
        <span class="font-weight-bold small" x-text="$store.diary.weekLabel"></span>

        {{-- Gruppenmodus-Badge --}}
        <span class="badge badge-info ml-2" x-show="$store.diary.is_group" x-cloak>Gruppenmodus</span>

        {{-- Pausierte-Toggle --}}
        <div class="paused-toggle ml-3" title="Pausierte Einträge anzeigen / ausblenden">
            <input type="checkbox" id="showPausedToggleV2" class="paused-toggle-input"
                   x-model="$store.diary.showPaused" />
            <label for="showPausedToggleV2" class="paused-toggle-label">
                <span class="paused-toggle-track" aria-hidden="true"></span>
                <span class="paused-toggle-text small">Pausierte</span>
            </label>
        </div>

        {{-- Kategorie-Filter Dropdown --}}
        <div class="dropdown ml-3" x-data="diaryTable()">
            <button class="btn btn-outline-secondary btn-sm dropdown-toggle" type="button"
                    data-toggle="dropdown" aria-haspopup="true" aria-expanded="false"
                    title="Notizkategorien ein-/ausblenden">
                <i class="fas fa-tag"></i> Notizkategorien
            </button>
            <div class="dropdown-menu py-2" @click.stop style="min-width:200px;">
                {{-- Überschriften-Toggle --}}
                <div class="px-3 pt-2 mb-1">
                    <div class="custom-control custom-switch">
                        <input type="checkbox" class="custom-control-input" id="showAllHeadingsToggleV2"
                               :checked="!hideAllCategoryHeadings"
                               @change="toggleCategoryHeadings()">
                        <label class="custom-control-label small font-weight-bold" for="showAllHeadingsToggleV2">
                            Überschriften anzeigen
                        </label>
                    </div>
                </div>

                <div class="dropdown-divider my-2"></div>
                <div class="px-3 mb-1">
                    <small class="text-muted text-uppercase" style="font-size:.65rem;letter-spacing:.05em;">Einträge filtern</small>
                </div>

                {{-- Pro-Kategorie-Filter --}}
                <template x-for="cat in $store.diary.categories" :key="cat.id">
                    <div class="px-3 mb-1">
                        <div class="custom-control custom-switch">
                            <input type="checkbox" class="custom-control-input"
                                   :id="'catFilterV2_' + cat.id"
                                   :checked="isCategoryVisible(cat.id)"
                                   @change="toggleCategoryHidden(cat.id)">
                            <label class="custom-control-label small" :for="'catFilterV2_' + cat.id" x-text="cat.name"></label>
                        </div>
                    </div>
                </template>

                {{-- Ohne-Kategorie-Filter --}}
                <div class="px-3 mb-2">
                    <div class="custom-control custom-switch">
                        <input type="checkbox" class="custom-control-input" id="filterUncategorizedToggleV2"
                               :checked="!filterUncategorized"
                               @change="toggleFilterUncategorized()">
                        <label class="custom-control-label small" for="filterUncategorizedToggleV2">
                            Ohne Kategorie
                        </label>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="d-flex flex-wrap align-items-center">
        {{-- Spalten verwalten --}}
        <button class="btn btn-sm btn-outline-secondary mb-1 mr-2"
                x-data="columnsManager()"
                @click="toggleColumnsCard()"
                :class="{ 'disabled': $store.diary.selectedGroupId }"
                title="Spalten verwalten">
            <i class="fas fa-columns"></i> Spalten
        </button>

        {{-- Kategorien & Gruppen verwalten --}}
        <a href="{{ route('paedDiary.categories.manage') }}" class="btn btn-sm btn-outline-secondary mb-1 mr-2"
           title="Notizkategorien &amp; Spaltengruppen verwalten">
            <i class="fas fa-tags"></i> Notizkategorien &amp; Gruppen
        </a>

        {{-- Graduierungsdokumentation --}}
        <a href="{{ route('gradingDocumentation.index') }}" class="btn btn-sm btn-outline-primary mb-1 mr-2"
           title="Graduierungssystem-Dokumentation">
            <i class="fas fa-clipboard-check"></i> Dokumentation
        </a>

        {{-- CSV Export --}}
        <a x-data="diaryTable()" :href="exportUrl" class="btn btn-sm btn-outline-primary mb-1 mr-2" title="CSV Export">
            <i class="fas fa-file-csv"></i>
        </a>

        {{-- Termin-Button --}}
        <button class="btn btn-sm btn-warning mb-1 mr-2"
                x-data="appointmentManager()"
                @click="openCreateModal()">
            <i class="fas fa-calendar-alt"></i> Termin
        </button>

        {{-- Aufgabe-Button --}}
        <button class="btn btn-sm btn-success mb-1 mr-2"
                x-data="taskPanel()"
                @click="openCreateModal()">
            Aufgabe
        </button>

        {{-- Neuer Eintrag --}}
        <button class="btn btn-sm btn-info mb-1"
                @click="$dispatch('diary-new-entry', {})">
            Neuer Eintrag
        </button>
    </div>
</div>

