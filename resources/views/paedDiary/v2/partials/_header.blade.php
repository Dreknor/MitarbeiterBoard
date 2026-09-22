{{--
    PaedDiary v2 – Header Partial (Tailwind + Alpine.js)
    Responsive Toolbar: Kompakt auf Mobile, voll aufgeklappt ab md.
--}}
<div class="card-header" style="padding:0;" x-data="diaryTable()">

    {{-- ── Zeile 1: Titel + Selects + Wochennavigation ─────────────── --}}
    <div class="flex flex-wrap items-center gap-2 px-3 py-2 border-b border-gray-100">

        {{-- Titel (nur Desktop) --}}
        <h5 class="hidden md:block text-sm font-semibold text-gray-800 mr-1 whitespace-nowrap" style="margin:0;">
            Päd. Dokumentation
        </h5>

        {{-- Klassen-Auswahl --}}
        <div class="flex items-center gap-1.5 min-w-0">
            <label class="hidden sm:inline text-xs text-gray-500 whitespace-nowrap">Klasse</label>
            <select class="text-xs rounded-md border border-gray-300 bg-white pl-2 pr-6 py-1 focus:ring-2 focus:ring-blue-400 focus:border-blue-400 outline-none"
                    style="max-width:140px;"
                    :value="$store.diary.selectedKlasseId"
                    @change="$store.diary.changeKlasse($event.target.value)">
                @foreach($klassen as $k)
                    <option value="{{ $k->id }}">{{ $k->name }} ({{ $k->schueler_count }})</option>
                @endforeach
            </select>
        </div>

        {{-- Gruppen-Auswahl --}}
        <div class="flex items-center gap-1">
            <select class="text-xs rounded-md border border-gray-300 bg-white pl-2 pr-6 py-1 focus:ring-2 focus:ring-blue-400 focus:border-blue-400 outline-none"
                    style="max-width:140px;"
                    :value="$store.diary.selectedGroupId || ''"
                    @change="$store.diary.changeGroup($event.target.value)">
                <option value="">Kopplung…</option>
                <template x-for="g in $store.diary.groups" :key="g.id">
                    <option :value="g.id" x-text="g.name" :selected="$store.diary.selectedGroupId == g.id"></option>
                </template>
            </select>
            <button class="inline-flex items-center justify-center w-7 h-7 rounded-md border border-gray-300 text-gray-500 hover:bg-gray-50 hover:text-gray-700 transition-colors"
                    x-data="groupManager()"
                    @click="openModal()"
                    title="Kopplungen verwalten">
                <i class="fas fa-object-group text-xs"></i>
            </button>
        </div>

        {{-- Gruppenmodus-Badge --}}
        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[0.65rem] font-medium bg-blue-100 text-blue-700"
              x-show="$store.diary.is_group" x-cloak>
            Gruppe
        </span>

        {{-- Spacer (schiebt Wochennav nach rechts auf Desktop) --}}
        <div class="hidden md:block flex-1"></div>

        {{-- Wochen-Navigation --}}
        <div class="flex items-center gap-1 ml-auto md:ml-0">
            <button class="inline-flex items-center justify-center w-7 h-7 rounded-md border border-gray-300 text-gray-600 hover:bg-gray-50 hover:text-gray-900 transition-colors"
                    @click="$store.diary.prevWeek()" title="Vorherige Woche">
                <i class="fas fa-chevron-left text-xs"></i>
            </button>
            <button class="inline-flex items-center justify-center h-7 px-2.5 rounded-md border border-gray-300 text-xs font-medium text-gray-600 hover:bg-gray-50 hover:text-gray-900 transition-colors"
                    @click="$store.diary.goToday()" title="Aktuelle Woche">
                Heute
            </button>
            <button class="inline-flex items-center justify-center w-7 h-7 rounded-md border border-gray-300 text-gray-600 hover:bg-gray-50 hover:text-gray-900 transition-colors"
                    @click="$store.diary.nextWeek()" title="Nächste Woche">
                <i class="fas fa-chevron-right text-xs"></i>
            </button>
            <span class="text-xs font-semibold text-gray-700 whitespace-nowrap ml-1" x-text="$store.diary.weekLabel"></span>
        </div>
    </div>

    {{-- ── Zeile 2: Aktions-Buttons + Filter ───────────────────────── --}}
    <div class="flex flex-wrap items-center gap-1.5 px-3 py-1.5">

        {{-- Dokumentation --}}
        <a href="{{ route('gradingDocumentation.index') }}"
           class="inline-flex items-center gap-1 h-7 px-2 rounded-md text-xs font-medium text-blue-600 border border-blue-200 bg-blue-50 hover:bg-blue-100 transition-colors"
           title="Graduierungssystem-Dokumentation">
            <i class="fas fa-clipboard-check text-[0.6rem]"></i>
            <span class="hidden sm:inline">Dokumentation</span>
        </a>

        {{-- CSV Export --}}
        <a :href="exportUrl"
           class="inline-flex items-center justify-center w-7 h-7 rounded-md text-xs text-blue-600 border border-blue-200 bg-blue-50 hover:bg-blue-100 transition-colors"
           title="CSV Export">
            <i class="fas fa-file-csv"></i>
        </a>

        {{-- Termin-Button --}}
        <button x-data="appointmentManager()"
                @click="openCreateModal()"
                class="inline-flex items-center gap-1 h-7 px-2 rounded-md text-xs font-medium text-amber-700 border border-amber-300 bg-amber-50 hover:bg-amber-100 transition-colors">
            <i class="fas fa-calendar-alt text-[0.6rem]"></i>
            <span class="hidden sm:inline">Termin</span>
        </button>

        {{-- Aufgabe-Button --}}
        <button x-data="taskPanel()"
                @click="openCreateModal()"
                class="inline-flex items-center gap-1 h-7 px-2 rounded-md text-xs font-medium text-emerald-700 border border-emerald-300 bg-emerald-50 hover:bg-emerald-100 transition-colors">
            <i class="fas fa-tasks text-[0.6rem]"></i>
            <span class="hidden sm:inline">Aufgabe</span>
        </button>

        {{-- Neuer Eintrag --}}
        <button @click="$dispatch('diary-new-entry', {})"
                class="inline-flex items-center gap-1 h-7 px-2.5 rounded-md text-xs font-medium text-white bg-blue-600 hover:bg-blue-700 shadow-sm transition-colors">
            <i class="fas fa-plus text-[0.6rem]"></i>
            <span class="hidden sm:inline">Eintrag</span>
        </button>

        {{-- Spacer --}}
        <div class="flex-1"></div>

        {{-- Pausierte-Toggle (reines Tailwind) --}}
        <label class="inline-flex items-center gap-1.5 cursor-pointer select-none group" title="Pausierte Einträge anzeigen / ausblenden">
            <span class="relative inline-block w-8 h-[18px]">
                <input type="checkbox" class="peer sr-only" x-model="$store.diary.showPaused">
                <span class="block w-full h-full rounded-full bg-gray-300 peer-checked:bg-emerald-600 transition-colors"></span>
                <span class="absolute top-[2px] left-[2px] w-[14px] h-[14px] rounded-full bg-white shadow-sm transition-transform peer-checked:translate-x-[14px]"></span>
            </span>
            <span class="text-[0.7rem] text-gray-500 group-hover:text-gray-700 whitespace-nowrap">Pausierte</span>
        </label>

        {{-- Vertikaler Trenner --}}
        <div class="hidden sm:block w-px h-5 bg-gray-200"></div>

        {{-- Kategorie-Filter (Alpine-Dropdown statt Bootstrap) --}}
        <div class="relative" x-data="{ catOpen: false }" @click.outside="catOpen = false">
            <button @click="catOpen = !catOpen"
                    class="inline-flex items-center gap-1 h-7 px-2 rounded-md border text-xs font-medium transition-colors"
                    :class="catOpen ? 'border-blue-400 bg-blue-50 text-blue-700' : 'border-gray-300 text-gray-600 hover:bg-gray-50'">
                <i class="fas fa-tag text-[0.6rem]"></i>
                <span class="hidden sm:inline">Kategorienfilter</span>
                <i class="fas fa-chevron-down text-[0.5rem] ml-0.5 transition-transform" :class="catOpen && 'rotate-180'"></i>
            </button>

            <div x-show="catOpen" x-cloak x-transition.opacity
                 class="absolute right-0 top-full mt-1 z-50 w-56 rounded-lg border border-gray-200 bg-white shadow-lg ring-1 ring-black/5 py-1"
                 @click.stop>

                {{-- Überschriften-Toggle --}}
                <label class="flex items-center gap-2 px-3 py-1.5 cursor-pointer hover:bg-gray-50">
                    <input type="checkbox" class="rounded border-gray-300 text-blue-600 focus:ring-blue-500"
                           style="width:14px;height:14px;"
                           :checked="!$store.diary.hideAllCategoryHeadings"
                           @change="toggleCategoryHeadings()">
                    <span class="text-xs font-semibold text-gray-700">Überschriften anzeigen</span>
                </label>

                <div class="border-t border-gray-100 my-1"></div>
                <div class="px-3 py-1">
                    <span class="text-[0.6rem] font-semibold uppercase tracking-wider text-gray-400">Einträge filtern</span>
                </div>

                {{-- Pro-Kategorie-Filter --}}
                <template x-for="cat in $store.diary.categories" :key="cat.id">
                    <label class="flex items-center gap-2 px-3 py-1 cursor-pointer hover:bg-gray-50">
                        <input type="checkbox" class="rounded border-gray-300 text-blue-600 focus:ring-blue-500"
                               style="width:14px;height:14px;"
                               :checked="isCategoryVisible(cat.id)"
                               @change="toggleCategoryHidden(cat.id)">
                        <span class="text-xs text-gray-600" x-text="cat.name"></span>
                    </label>
                </template>

                {{-- Ohne-Kategorie-Filter --}}
                <label class="flex items-center gap-2 px-3 py-1 cursor-pointer hover:bg-gray-50">
                    <input type="checkbox" class="rounded border-gray-300 text-blue-600 focus:ring-blue-500"
                           style="width:14px;height:14px;"
                           :checked="!$store.diary.filterUncategorized"
                           @change="toggleFilterUncategorized()">
                    <span class="text-xs text-gray-600">Ohne Kategorie</span>
                </label>

                {{-- ── Spaltengruppen anzeigen (server-persistiert) ── --}}
                <div class="border-t border-gray-100 my-1"></div>
                <div class="px-3 py-1">
                    <span class="text-[0.6rem] font-semibold uppercase tracking-wider text-gray-400">Spaltengruppen</span>
                </div>
                <label class="flex items-center gap-2 px-3 py-1.5 cursor-pointer hover:bg-gray-50">
                    <input type="checkbox" class="rounded border-gray-300 text-blue-600 focus:ring-blue-500"
                           style="width:14px;height:14px;"
                           :checked="$store.diary.show_column_categories"
                           @change="$store.diary.toggleShowColumnCategories()">
                    <span class="text-xs font-semibold text-gray-700">Spaltengruppen-Überschriften</span>
                </label>
            </div>
        </div>

        {{-- Spalten verwalten --}}
        <button x-data="columnsManager()"
                @click="toggleColumnsCard()"
                :class="$store.diary.selectedGroupId ? 'opacity-40 pointer-events-none' : 'hover:bg-gray-50'"
                class="inline-flex items-center gap-1 h-7 px-2 rounded-md border border-gray-300 text-xs font-medium text-gray-600 transition-colors"
                title="Spalten verwalten">
            <i class="fas fa-columns text-[0.6rem]"></i>
            <span class="hidden sm:inline">Spalten</span>
        </button>

        {{-- Kategorien & Gruppen verwalten --}}
        <a href="{{ route('paedDiary.categories.manage') }}"
           class="inline-flex items-center gap-1 h-7 px-2 rounded-md border border-gray-300 text-xs font-medium text-gray-600 hover:bg-gray-50 transition-colors"
           title="Notizkategorien & Spaltengruppen verwalten">
            <i class="fas fa-tags text-[0.6rem]"></i>
            <span class="hidden lg:inline">Kategorien bearb.</span>
        </a>
    </div>
</div>

