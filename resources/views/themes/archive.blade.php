@extends('layouts.app')

@push('css')
<!-- Tailwind CSS CDN -->
<script src="https://cdn.tailwindcss.com"></script>
<script>
    tailwind.config = {
        theme: {
            extend: {
                colors: {
                    primary: '#3b82f6',
                    secondary: '#64748b',
                }
            }
        }
    }
</script>
<style>
    [x-cloak] { display: none !important; }
</style>
@endpush

@section('content')
<div x-data="archiveApp()" x-cloak class="min-h-screen bg-gradient-to-br from-gray-50 to-gray-100 pb-8">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">

        <!-- Sticky Header -->
        <div class="sticky top-0 z-30 bg-white/95 backdrop-blur-sm shadow-lg rounded-b-2xl mb-6">
            <!-- Kompakter Header -->
            <div class="px-4 md:px-6 py-3 md:py-4">
                <div class="flex flex-col lg:flex-row lg:items-center gap-3">
                    <!-- Titel & Monat-Dropdown -->
                    <div class="flex items-center gap-3 flex-wrap">
                        <div class="flex items-center space-x-2">
                            <div class="p-2 bg-gradient-to-br from-blue-500 to-blue-600 rounded-xl shadow-lg">
                                <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 8h14M5 8a2 2 0 110-4h14a2 2 0 110 4M5 8v10a2 2 0 002 2h10a2 2 0 002-2V8m-9 4h4"></path>
                                </svg>
                            </div>
                            <h1 class="text-lg md:text-xl font-bold bg-gradient-to-r from-blue-600 to-blue-800 bg-clip-text text-transparent">
                                Archiv
                            </h1>
                        </div>

                        <!-- Monatsauswahl Dropdown -->
                        <div class="relative" x-data="{ monthOpen: false }" @click.away="monthOpen = false">
                            <button @click="monthOpen = !monthOpen"
                                    class="inline-flex items-center gap-2 px-3 md:px-4 py-2 bg-gradient-to-r from-blue-50 to-blue-100 hover:from-blue-500 hover:to-blue-600 text-blue-700 hover:text-white rounded-lg font-medium transition-all duration-200 shadow-sm hover:shadow-md text-sm md:text-base">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
                                </svg>
                                <span>Zeitraum wählen</span>
                                <svg :class="{'rotate-180': monthOpen}" class="w-4 h-4 transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
                                </svg>
                            </button>

                            <div x-show="monthOpen"
                                 x-transition:enter="transition ease-out duration-200"
                                 x-transition:enter-start="opacity-0 transform scale-95"
                                 x-transition:enter-end="opacity-100 transform scale-100"
                                 x-transition:leave="transition ease-in duration-100"
                                 x-transition:leave-start="opacity-100 transform scale-100"
                                 x-transition:leave-end="opacity-0 transform scale-95"
                                 class="absolute left-0 mt-2 w-64 md:w-80 max-h-96 overflow-y-auto bg-white rounded-xl shadow-2xl border border-gray-200 z-50"
                                 style="display: none;">
                                <div class="p-2 grid grid-cols-2 gap-1">
                                    @for($x = \Carbon\Carbon::now(); $x->greaterThanOrEqualTo($oldest); $x->subMonth())
                                        <a href="{{url(request()->segment(1)."/archive/".$x->format('Y-m'))}}"
                                           class="px-3 py-2 text-sm hover:bg-blue-50 rounded-lg transition-colors duration-150 flex items-center justify-between group">
                                            <span class="font-medium text-gray-700 group-hover:text-blue-600">
                                                {{$x->locale('de')->monthName}} {{$x->format('Y')}}
                                            </span>
                                            <svg class="w-4 h-4 text-gray-400 group-hover:text-blue-600 opacity-0 group-hover:opacity-100 transition-opacity" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path>
                                            </svg>
                                        </a>
                                    @endfor
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Suche -->
                    <div class="relative flex-1 lg:max-w-md">
                        <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                            <svg class="h-5 w-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path>
                            </svg>
                        </div>
                        <input
                            x-model="searchQuery"
                            type="text"
                            class="block w-full pl-10 pr-3 py-2 text-sm md:text-base border border-gray-300 rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-all duration-200 shadow-sm"
                            placeholder="Themen durchsuchen...">
                    </div>
                </div>
            </div>

            <!-- Kompakte Filter -->
            <div class="px-4 md:px-6 py-3 border-t border-gray-200">
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-2">
                    <div>
                        <select x-model="filterType"
                                class="w-full px-3 py-2 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-all duration-200 shadow-sm">
                            <option value="">🏷️ Alle Typen</option>
                            <template x-for="type in availableTypes" :key="type">
                                <option :value="type" x-text="type"></option>
                            </template>
                        </select>
                    </div>
                    <div>
                        <select x-model="filterCreator"
                                class="w-full px-3 py-2 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-all duration-200 shadow-sm">
                            <option value="">👤 Alle Ersteller</option>
                            <template x-for="creator in availableCreators" :key="creator">
                                <option :value="creator" x-text="creator"></option>
                            </template>
                        </select>
                    </div>
                    <button @click="resetFilters()"
                            class="px-3 py-2 bg-gradient-to-r from-gray-100 to-gray-200 hover:from-gray-200 hover:to-gray-300 text-gray-700 rounded-lg font-medium transition-all duration-200 shadow-sm hover:shadow-md flex items-center justify-center gap-2 text-sm">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path>
                        </svg>
                        <span class="hidden sm:inline">Zurücksetzen</span>
                        <span class="sm:hidden">Reset</span>
                    </button>
                    <div class="hidden lg:block">
                        <div class="flex items-center justify-end h-full text-sm text-gray-500">
                            <span x-text="getFilteredThemesCount()"></span> Thema(en)
                        </div>
                    </div>
                </div>
            </div>

            <!-- Pagination -->
            <div class="px-4 md:px-6 py-2 border-t border-gray-200">
                {{$themes->links()}}
            </div>
        </div>

        @if (count($themes) == 0)
            <!-- Empty State -->
            <div class="bg-white rounded-2xl shadow-lg p-12 text-center">
                <div class="w-20 h-20 bg-gradient-to-br from-gray-100 to-gray-200 rounded-full flex items-center justify-center mx-auto mb-4">
                    <svg class="w-10 h-10 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0v5a2 2 0 01-2 2H6a2 2 0 01-2-2v-5m16 0h-2.586a1 1 0 00-.707.293l-2.414 2.414a1 1 0 01-.707.293h-3.172a1 1 0 01-.707-.293l-2.414-2.414A1 1 0 006.586 13H4"></path>
                    </svg>
                </div>
                <p class="text-gray-500 text-lg">Es gibt keine abgeschlossenen Themen</p>
            </div>
        @else
            <!-- Themes Container -->
            <div class="space-y-6">
                @foreach($themes as $day => $dayThemes)
                    <div x-show="isDaySectionVisible('day-{{$loop->index}}')"
                         x-transition:enter="transition ease-out duration-200"
                         x-transition:enter-start="opacity-0 transform translate-y-4"
                         x-transition:enter-end="opacity-100 transform translate-y-0"
                         class="bg-white rounded-2xl shadow-lg overflow-hidden day-section"
                         data-day-id="day-{{$loop->index}}">

                        <!-- Day Header -->
                        <div @click="toggleDay('day-{{$loop->index}}')"
                             class="px-6 py-4 bg-gradient-to-r from-blue-600 to-blue-700 cursor-pointer hover:from-blue-700 hover:to-blue-800 transition-all duration-200">
                            <div class="flex justify-between items-center">
                                <div class="flex items-center space-x-3">
                                    <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
                                    </svg>
                                    <h2 class="text-xl font-bold text-white">{{$day}}</h2>
                                </div>
                                <div class="flex items-center space-x-3">
                                    <span class="px-3 py-1 bg-white/20 backdrop-blur-sm rounded-full text-sm font-medium text-white">
                                        <span x-text="getVisibleThemesCount('day-{{$loop->index}}')"></span> Thema(en)
                                    </span>
                                    <svg :class="{'rotate-180': openDays.includes('day-{{$loop->index}}')}"
                                         class="w-5 h-5 text-white transition-transform duration-200"
                                         fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
                                    </svg>
                                </div>
                            </div>
                        </div>

                        <!-- Themes Content -->
                        <div x-show="openDays.includes('day-{{$loop->index}}')"
                             x-collapse>
                            <div class="p-6">
                                <!-- Desktop Table -->
                                <div class="hidden lg:block overflow-x-auto">
                                    <table class="min-w-full divide-y divide-gray-200">
                                        <thead>
                                            <tr class="bg-gray-50">
                                                <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider rounded-tl-lg">Von</th>
                                                <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">Thema</th>
                                                <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">Typ</th>
                                                <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">Ziel</th>
                                                <th class="px-4 py-3 text-center text-xs font-semibold text-gray-600 uppercase tracking-wider rounded-tr-lg">Aktionen</th>
                                            </tr>
                                        </thead>
                                        <tbody class="bg-white divide-y divide-gray-100">
                                            @foreach($dayThemes as $theme)
                                                <tr x-show="isThemeVisible('{{strtolower($theme->theme)}}', '{{strtolower($theme->type->type)}}', '{{strtolower($theme->ersteller->name)}}', '{{strtolower($theme->goal)}}')"
                                                    class="theme-row hover:bg-blue-50 transition-colors duration-150"
                                                    data-theme="{{strtolower($theme->theme)}}"
                                                    data-type="{{strtolower($theme->type->type)}}"
                                                    data-creator="{{strtolower($theme->ersteller->name)}}"
                                                    data-goal="{{strtolower($theme->goal)}}">
                                                    <td class="px-4 py-4">
                                                        <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-medium bg-gray-100 text-gray-800">
                                                            {{$theme->ersteller->name}}
                                                        </span>
                                                    </td>
                                                    <td class="px-4 py-4 font-semibold text-gray-900">{{$theme->theme}}</td>
                                                    <td class="px-4 py-4">
                                                        <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-medium bg-blue-100 text-blue-800">
                                                            {{$theme->type->type}}
                                                        </span>
                                                    </td>
                                                    <td class="px-4 py-4 text-sm text-gray-600 max-w-xs truncate">{{$theme->goal}}</td>

                                                    <td class="px-4 py-4">
                                                        <div class="flex justify-center space-x-2">
                                                            <a href="{{url(request()->segment(1)."/themes/$theme->id")}}"
                                                               class="inline-flex items-center px-3 py-1.5 bg-blue-500 hover:bg-blue-600 text-white rounded-lg text-sm font-medium transition-all duration-200 shadow-sm hover:shadow-md transform hover:-translate-y-0.5">
                                                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path>
                                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path>
                                                                </svg>
                                                            </a>
                                                            @can('unarchive theme')
                                                                <a href="{{url("/unarchiv/$theme->id")}}"
                                                                   class="inline-flex items-center px-3 py-1.5 bg-green-500 hover:bg-green-600 text-white rounded-lg text-sm font-medium transition-all duration-200 shadow-sm hover:shadow-md transform hover:-translate-y-0.5">
                                                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path>
                                                                    </svg>
                                                                </a>
                                                            @endcan
                                                        </div>
                                                    </td>
                                                </tr>
                                            @endforeach
                                        </tbody>
                                    </table>
                                </div>

                                <!-- Mobile Cards -->
                                <div class="lg:hidden space-y-4">
                                    @foreach($dayThemes as $theme)
                                        <div x-show="isThemeVisible('{{strtolower($theme->theme)}}', '{{strtolower($theme->type->type)}}', '{{strtolower($theme->ersteller->name)}}', '{{strtolower($theme->goal)}}')"
                                             x-transition:enter="transition ease-out duration-200"
                                             x-transition:enter-start="opacity-0 transform scale-95"
                                             x-transition:enter-end="opacity-100 transform scale-100"
                                             class="theme-card bg-gradient-to-br from-white to-gray-50 rounded-xl p-4 border-l-4 border-blue-500 shadow-md hover:shadow-xl transition-all duration-200"
                                             data-theme="{{strtolower($theme->theme)}}"
                                             data-type="{{strtolower($theme->type->type)}}"
                                             data-creator="{{strtolower($theme->ersteller->name)}}"
                                             data-goal="{{strtolower($theme->goal)}}">

                                            <div class="flex justify-between items-start mb-3">
                                                <h3 class="text-lg font-bold text-gray-900 flex-1">{{$theme->theme}}</h3>
                                                <span class="inline-flex items-center px-2.5 py-1 rounded-lg text-xs font-medium bg-blue-100 text-blue-800 ml-2">
                                                    {{$theme->type->type}}
                                                </span>
                                            </div>

                                            <div class="mb-3">
                                                <div class="flex items-start space-x-2 text-sm text-gray-600">
                                                    <svg class="w-4 h-4 mt-0.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                                    </svg>
                                                    <span>{{$theme->goal}}</span>
                                                </div>
                                            </div>

                                            <div class="grid grid-cols-2 gap-3 mb-3 text-sm">
                                                <div class="flex items-center space-x-2 text-gray-600">
                                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path>
                                                    </svg>
                                                    <span>{{$theme->ersteller->name}}</span>
                                                </div>
                                                <div class="flex items-center space-x-2 text-gray-600 justify-end">
                                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                                    </svg>
                                                    <span>{{$theme->duration}} Min.</span>
                                                </div>
                                            </div>

                                            <div class="mb-4">
                                                <div class="flex items-center justify-between text-xs text-gray-600 mb-1">
                                                    <span>Priorität</span>
                                                    <span class="font-semibold">{{100-$theme->priority}}%</span>
                                                </div>
                                                <div class="bg-gray-200 rounded-full h-2.5 overflow-hidden">
                                                    <div class="bg-gradient-to-r from-green-400 to-green-600 h-2.5 rounded-full transition-all duration-300"
                                                         style="width: {{100-$theme->priority}}%"></div>
                                                </div>
                                            </div>

                                            <div class="flex space-x-2">
                                                <a href="{{url(request()->segment(1)."/themes/$theme->id")}}"
                                                   class="flex-1 inline-flex items-center justify-center px-4 py-2.5 bg-blue-500 hover:bg-blue-600 text-white rounded-lg text-sm font-medium transition-all duration-200 shadow-sm hover:shadow-md">
                                                    <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path>
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path>
                                                    </svg>
                                                    Anzeigen
                                                </a>
                                                @can('unarchive theme')
                                                    <a href="{{url("/unarchiv/$theme->id")}}"
                                                       class="flex-1 inline-flex items-center justify-center px-4 py-2.5 bg-green-500 hover:bg-green-600 text-white rounded-lg text-sm font-medium transition-all duration-200 shadow-sm hover:shadow-md">
                                                        <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path>
                                                        </svg>
                                                        Reaktivieren
                                                    </a>
                                                @endcan
                                            </div>
                                        </div>
                                    @endforeach
                                </div>
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
        @endif
    </div>
</div>
@stop

@push('js')
<!-- Alpine.js CDN -->
<script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
<script>
function archiveApp() {
    return {
        searchQuery: '',
        filterType: '',
        filterCreator: '',
        openDays: [@foreach($themes as $day => $dayThemes)'day-{{$loop->index}}'@if(!$loop->last),@endif @endforeach],
        availableTypes: [],
        availableCreators: [],

        init() {
            // Sammle verfügbare Typen und Ersteller
            const types = new Set();
            const creators = new Set();

            document.querySelectorAll('.theme-row, .theme-card').forEach(item => {
                types.add(item.dataset.type);
                creators.add(item.dataset.creator);
            });

            this.availableTypes = Array.from(types).map(t => t.charAt(0).toUpperCase() + t.slice(1));
            this.availableCreators = Array.from(creators).map(c => c.charAt(0).toUpperCase() + c.slice(1));
        },

        isThemeVisible(theme, type, creator, goal) {
            const searchTerm = this.searchQuery.toLowerCase();
            const selectedType = this.filterType.toLowerCase();
            const selectedCreator = this.filterCreator.toLowerCase();

            const matchesSearch = !searchTerm ||
                theme.includes(searchTerm) ||
                goal.includes(searchTerm) ||
                type.includes(searchTerm) ||
                creator.includes(searchTerm);

            const matchesType = !selectedType || type === selectedType;
            const matchesCreator = !selectedCreator || creator === selectedCreator;

            return matchesSearch && matchesType && matchesCreator;
        },

        isDaySectionVisible(dayId) {
            const section = document.querySelector(`[data-day-id="${dayId}"]`);
            if (!section) return false;

            const themes = section.querySelectorAll('.theme-row, .theme-card');
            return Array.from(themes).some(theme => {
                return this.isThemeVisible(
                    theme.dataset.theme,
                    theme.dataset.type,
                    theme.dataset.creator,
                    theme.dataset.goal
                );
            });
        },

        getVisibleThemesCount(dayId) {
            const section = document.querySelector(`[data-day-id="${dayId}"]`);
            if (!section) return 0;

            const themes = section.querySelectorAll('.theme-row, .theme-card');
            return Array.from(themes).filter(theme => {
                return this.isThemeVisible(
                    theme.dataset.theme,
                    theme.dataset.type,
                    theme.dataset.creator,
                    theme.dataset.goal
                );
            }).length;
        },

        toggleDay(dayId) {
            const index = this.openDays.indexOf(dayId);
            if (index > -1) {
                this.openDays.splice(index, 1);
            } else {
                this.openDays.push(dayId);
            }
        },

        resetFilters() {
            this.searchQuery = '';
            this.filterType = '';
            this.filterCreator = '';
        },

        getFilteredThemesCount() {
            const themes = document.querySelectorAll('.theme-row, .theme-card');
            return Array.from(themes).filter(theme => {
                return this.isThemeVisible(
                    theme.dataset.theme,
                    theme.dataset.type,
                    theme.dataset.creator,
                    theme.dataset.goal
                );
            }).length;
        }
    }
}
</script>
@endpush
