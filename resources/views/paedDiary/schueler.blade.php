@extends('layouts.app')

@section('content')
<div class="container-fluid" id="schueler-diary-app">
    <!-- Moderner Tailwind-Header -->
    <div class="mb-6">
        <!-- Hauptüberschrift und Aktionen -->
        <div class="bg-gradient-to-r from-blue-600 to-blue-700 rounded-lg shadow-lg p-4 sm:p-6 mb-4">
            <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-4">
                <!-- Titel und Info -->
                <div class="flex-1">
                    <div class="flex items-center gap-3 mb-2">
                        <div class="bg-white/20 backdrop-blur-sm rounded-full p-2">
                            <i class="fas fa-user-graduate text-white text-xl"></i>
                        </div>
                        <div>
                            <h1 class="text-2xl sm:text-3xl font-bold text-white mb-1">
                                {{ $schueler->vorname }} {{ $schueler->nachname }}
                            </h1>
                            <div class="flex items-center gap-2 text-blue-100">
                                <i class="fas fa-users text-sm"></i>
                                <span class="text-sm font-medium">Klasse: {{ $klasse->name }}</span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Aktions-Buttons -->
                <div class="flex flex-wrap gap-2">
                    <a href="{{ route('paedDiary.index', ['klasse' => $klasse->id]) }}"
                       class="inline-flex items-center gap-2 px-4 py-2 bg-white/10 hover:bg-white/20 backdrop-blur-sm text-white rounded-lg transition-all duration-200 hover:scale-105 active:scale-95 font-medium">
                        <i class="fas fa-arrow-left"></i>
                        <span class="hidden sm:inline">Zurück zur Übersicht</span>
                        <span class="sm:hidden">Zurück</span>
                    </a>
                    <button id="exportWordBtn"
                            class="inline-flex items-center gap-2 px-4 py-2 bg-green-500 hover:bg-green-600 text-white rounded-lg transition-all duration-200 hover:scale-105 active:scale-95 font-medium shadow-md"
                            title="Excel Export">
                        <i class="fas fa-file-excel"></i>
                        <span class="hidden sm:inline">Excel Export</span>
                        <span class="sm:hidden">Export</span>
                    </button>
                </div>
            </div>
        </div>

        <!-- Filter-Bereich mit Tailwind -->
        <div class="bg-white rounded-lg shadow-md p-4 sm:p-6">
            <!-- Zeitraum-Filter -->
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-12 gap-4 mb-4">
                <!-- Von Datum -->
                <div class="lg:col-span-3">
                    <label for="dateFrom" class="block text-sm font-medium text-gray-700 mb-2">
                        <i class="fas fa-calendar-alt text-blue-500 mr-1"></i> Von:
                    </label>
                    <input type="date"
                           id="dateFrom"
                           class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-all"
                           value="{{ now()->subDays(30)->format('Y-m-d') }}">
                </div>

                <!-- Bis Datum -->
                <div class="lg:col-span-3">
                    <label for="dateTo" class="block text-sm font-medium text-gray-700 mb-2">
                        <i class="fas fa-calendar-alt text-blue-500 mr-1"></i> Bis:
                    </label>
                    <input type="date"
                           id="dateTo"
                           class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-all"
                           value="{{ now()->format('Y-m-d') }}">
                </div>

                <!-- Daten laden Button -->
                <div class="lg:col-span-3 flex items-end">
                    <button id="loadDataBtn"
                            class="w-full px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white font-medium rounded-lg transition-all duration-200 hover:scale-105 active:scale-95 shadow-md">
                        <i class="fas fa-sync-alt mr-2"></i>Daten laden
                    </button>
                </div>

                <!-- Schnell-Filter -->
                <div class="lg:col-span-3 flex items-end">
                    <div class="w-full flex gap-2">
                        <button type="button"
                                class="flex-1 px-3 py-2 text-sm font-medium text-gray-700 bg-gray-100 hover:bg-gray-200 rounded-lg transition-colors duration-200"
                                id="last7Days">
                            7T
                        </button>
                        <button type="button"
                                class="flex-1 px-3 py-2 text-sm font-medium text-gray-700 bg-gray-100 hover:bg-gray-200 rounded-lg transition-colors duration-200"
                                id="last30Days">
                            30T
                        </button>
                        <button type="button"
                                class="flex-1 px-3 py-2 text-sm font-medium text-gray-700 bg-gray-100 hover:bg-gray-200 rounded-lg transition-colors duration-200"
                                id="last90Days">
                            90T
                        </button>
                    </div>
                </div>
            </div>

            <!-- Trennlinie -->
            <div class="border-t border-gray-200 my-4"></div>

            <!-- Loading Indicator -->
            <div id="loadingIndicator" class="text-center py-12 hidden">
                <div class="inline-block animate-spin rounded-full h-12 w-12 border-4 border-blue-500 border-t-transparent mb-4"></div>
                <div class="text-gray-600 font-medium">Daten werden geladen...</div>
            </div>

            <!-- Zusammenfassung - Moderne Statistik-Karten -->
            <div id="summarySection" class="hidden">
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
                    <!-- Zeitraum -->
                    <div class="bg-gradient-to-br from-gray-50 to-gray-100 rounded-lg p-4 border border-gray-200 shadow-sm hover:shadow-md transition-shadow">
                        <div class="flex items-center justify-between mb-2">
                            <div class="bg-gray-200 rounded-full p-2">
                                <i class="fas fa-calendar-alt text-gray-600"></i>
                            </div>
                        </div>
                        <h3 class="text-sm font-medium text-gray-600 mb-1">Zeitraum</h3>
                        <p class="text-lg font-bold text-gray-800" id="periodText"></p>
                    </div>

                    <!-- Einträge -->
                    <div class="bg-gradient-to-br from-blue-500 to-blue-600 rounded-lg p-4 shadow-md hover:shadow-lg transition-all hover:scale-105">
                        <div class="flex items-center justify-between mb-2">
                            <div class="bg-white/20 backdrop-blur-sm rounded-full p-2">
                                <i class="fas fa-file-alt text-white"></i>
                            </div>
                        </div>
                        <h3 class="text-sm font-medium text-blue-100 mb-1">Einträge</h3>
                        <p class="text-3xl font-bold text-white" id="entriesCount">0</p>
                    </div>

                    <!-- Aufgaben -->
                    <div class="bg-gradient-to-br from-cyan-500 to-cyan-600 rounded-lg p-4 shadow-md hover:shadow-lg transition-all hover:scale-105">
                        <div class="flex items-center justify-between mb-2">
                            <div class="bg-white/20 backdrop-blur-sm rounded-full p-2">
                                <i class="fas fa-tasks text-white"></i>
                            </div>
                        </div>
                        <h3 class="text-sm font-medium text-cyan-100 mb-1">Aufgaben</h3>
                        <p class="text-3xl font-bold text-white" id="tasksCount">0</p>
                    </div>

                    <!-- Tage mit Einträgen -->
                    <div class="bg-gradient-to-br from-green-500 to-green-600 rounded-lg p-4 shadow-md hover:shadow-lg transition-all hover:scale-105">
                        <div class="flex items-center justify-between mb-2">
                            <div class="bg-white/20 backdrop-blur-sm rounded-full p-2">
                                <i class="fas fa-check-circle text-white"></i>
                            </div>
                        </div>
                        <h3 class="text-sm font-medium text-green-100 mb-1">Tage mit Einträgen</h3>
                        <p class="text-3xl font-bold text-white" id="daysWithEntriesCount">0</p>
                    </div>
                </div>
            </div>

            <!-- Daten-Anzeige -->
            <div id="dataSection" class="hidden">
                        <!-- Moderne Tab-Navigation -->
                        <div class="mb-6">
                            <div class="border-b border-gray-200">
                                <ul class="flex flex-wrap -mb-px text-sm font-medium text-center" id="viewTabs" role="tablist">
                                    <li class="mr-2" role="presentation">
                                        <button class="inline-flex items-center gap-2 p-4 border-b-2 border-blue-600 rounded-t-lg text-blue-600 active group"
                                                id="entries-tab"
                                                data-toggle="tab"
                                                href="#entries"
                                                role="tab"
                                                aria-selected="true">
                                            <i class="fas fa-file-alt"></i>
                                            <span>Einträge</span>
                                            <span class="ml-1 px-2 py-0.5 text-xs font-semibold rounded-full bg-blue-100 text-blue-600" id="entriesBadge">0</span>
                                        </button>
                                    </li>
                                    <li class="mr-2" role="presentation">
                                        <button class="inline-flex items-center gap-2 p-4 border-b-2 border-transparent rounded-t-lg hover:text-gray-600 hover:border-gray-300 group"
                                                id="tasks-tab"
                                                data-toggle="tab"
                                                href="#tasks"
                                                role="tab"
                                                aria-selected="false">
                                            <i class="fas fa-tasks"></i>
                                            <span>Aufgaben</span>
                                            <span class="ml-1 px-2 py-0.5 text-xs font-semibold rounded-full bg-gray-200 text-gray-600" id="tasksBadge">0</span>
                                        </button>
                                    </li>
                                    <li class="mr-2" role="presentation">
                                        <button class="inline-flex items-center gap-2 p-4 border-b-2 border-transparent rounded-t-lg hover:text-gray-600 hover:border-gray-300 group"
                                                id="columns-tab"
                                                data-toggle="tab"
                                                href="#columns"
                                                role="tab"
                                                aria-selected="false">
                                            <i class="fas fa-columns"></i>
                                            <span>Spalten</span>
                                            <span class="ml-1 px-2 py-0.5 text-xs font-semibold rounded-full bg-gray-200 text-gray-600" id="columnsBadge">0</span>
                                        </button>
                                    </li>
                                    <li class="mr-2" role="presentation">
                                        <button class="inline-flex items-center gap-2 p-4 border-b-2 border-transparent rounded-t-lg hover:text-gray-600 hover:border-gray-300 group"
                                                id="graduations-tab"
                                                data-toggle="tab"
                                                href="#graduations"
                                                role="tab"
                                                aria-selected="false">
                                            <i class="fas fa-graduation-cap"></i>
                                            <span>Dokumentation</span>
                                            <span class="ml-1 px-2 py-0.5 text-xs font-semibold rounded-full bg-gray-200 text-gray-600">{{ $gradingSessions->count() }}</span>
                                        </button>
                                    </li>
                                </ul>
                            </div>
                        </div>

                        <!-- Tab Content Container -->
                        <div class="tab-content" id="viewTabContent">
                            <!-- Einträge Tab -->
                            <div class="tab-pane fade show active" id="entries" role="tabpanel" aria-labelledby="entries-tab">
                                <!-- Filter für Einträge -->
                                <div class="bg-gray-50 rounded-lg p-4 mb-4">
                                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-12 gap-4">
                                        <!-- Kategorie-Filter -->
                                        <div class="lg:col-span-4">
                                            <label for="categoryFilter" class="block text-sm font-medium text-gray-700 mb-2">
                                                <i class="fas fa-filter text-blue-500 mr-1"></i> Kategorie
                                            </label>
                                            <select id="categoryFilter"
                                                    class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 bg-white transition-all">
                                                <option value="">Alle Kategorien</option>
                                                <!-- Kategorien werden clientseitig gefüllt -->
                                            </select>
                                        </div>

                                        <!-- Textsuche -->
                                        <div class="lg:col-span-6">
                                            <label for="searchNotes" class="block text-sm font-medium text-gray-700 mb-2">
                                                <i class="fas fa-search text-blue-500 mr-1"></i> Suche Notizen
                                            </label>
                                            <input type="text"
                                                   id="searchNotes"
                                                   class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-all"
                                                   placeholder="Textsuche in Notizen (Inhalt, Autor)">
                                        </div>

                                        <!-- Info -->
                                        <div class="lg:col-span-2 flex items-end">
                                            <small class="text-xs text-gray-500">
                                                <i class="fas fa-info-circle mr-1"></i>
                                                Clientseitige Filterung
                                            </small>
                                        </div>
                                    </div>
                                </div>

                                <!-- Tabelle mit Einträgen -->
                                <div class="bg-white rounded-lg shadow overflow-hidden">
                                    <div class="overflow-x-auto">
                                        <table class="min-w-full divide-y divide-gray-200" id="entriesTable">
                                            <thead class="bg-gray-50">
                                                <tr>
                                                    <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider" style="width: 100px;">
                                                        Datum
                                                    </th>
                                                    <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider" style="width: 50%;">
                                                        Notiz
                                                    </th>
                                                    <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                                        Kategorie
                                                    </th>
                                                    <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider" style="width: 120px;">
                                                        Autor
                                                    </th>
                                                </tr>
                                            </thead>
                                            <tbody id="entriesTableBody" class="bg-white divide-y divide-gray-200"></tbody>
                                        </table>
                                    </div>
                                </div>
                            </div>

                            <!-- Aufgaben Tab -->
                            <div class="tab-pane fade" id="tasks" role="tabpanel" aria-labelledby="tasks-tab">
                                <div class="bg-white rounded-lg shadow overflow-hidden">
                                    <div class="overflow-x-auto">
                                        <table class="min-w-full divide-y divide-gray-200" id="tasksTable">
                                            <thead class="bg-gray-50">
                                                <tr>
                                                    <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider" style="width: 120px;">Erstellt</th>
                                                    <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Titel</th>
                                                    <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Beschreibung</th>
                                                    <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider" style="width: 100px;">Fällig</th>
                                                    <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider" style="width: 80px;">Status</th>
                                                    <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider" style="width: 80px;">Priorität</th>
                                                </tr>
                                            </thead>
                                            <tbody id="tasksTableBody" class="bg-white divide-y divide-gray-200"></tbody>
                                        </table>
                                    </div>
                                </div>
                            </div>

                            <!-- Spalten Tab -->
                            <div class="tab-pane fade" id="columns" role="tabpanel" aria-labelledby="columns-tab">
                                <div class="bg-white rounded-lg shadow overflow-hidden">
                                    <div class="overflow-x-auto">
                                        <table class="min-w-full divide-y divide-gray-200" id="columnsTable">
                                            <thead class="bg-gray-50">
                                                <tr id="columnCategoryHeaders">
                                                    <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider" style="width: 100px;" rowspan="2">Datum</th>
                                                </tr>
                                                <tr id="columnHeaders">

                                                </tr>
                                            </thead>
                                            <tbody id="columnsTableBody" class="bg-white divide-y divide-gray-200"></tbody>
                                            <tfoot id="columnsTableFooter" class="bg-gray-50"></tfoot>
                                        </table>
                                    </div>
                                </div>
                            </div>

                            <!-- Graduierungen Tab -->
                            <div class="tab-pane fade" id="graduations" role="tabpanel" aria-labelledby="graduations-tab">
                                @if($gradingSessions->isEmpty())
                                    <div class="alert alert-info">
                                        <i class="fas fa-info-circle"></i> Für diesen Schüler liegen noch keine Graduierungs-Dokumentationen vor.
                                    </div>
                                @else
                                    <!-- Entwicklungs-Übersicht -->
                                    <div class="grid grid-cols-1 lg:grid-cols-2 gap-4 mb-4">
                                        <div>
                                            <div class="bg-white rounded-lg shadow-md overflow-hidden">
                                                <div class="bg-blue-600 text-white px-4 py-3">
                                                    <h6 class="mb-0 font-semibold">
                                                        <i class="fas fa-chart-radar"></i> Aktuelle Kompetenzen (Letzte Session)
                                                    </h6>
                                                </div>
                                                <div class="p-4">
                                                    <canvas id="radarChart" height="280"></canvas>
                                                    <div class="text-center mt-2">
                                                        <small class="text-gray-500">
                                                            <span class="inline-block w-3 h-3 bg-cyan-500 rounded-full"></span> Schüler-Einschätzung &nbsp;
                                                            <span class="inline-block w-3 h-3 bg-green-500 rounded-full"></span> Lehrer-Bewertung
                                                        </small>
                                                    </div>
                                                    <div class="mt-3 pt-2 border-t border-gray-200">
                                                        <small class="text-gray-500 block mb-1"><strong>Bewertungsskala:</strong></small>
                                                        <div class="flex justify-between items-center px-2">
                                                            <div class="text-center flex-1">
                                                                <i class="fas fa-frown text-danger" style="font-size: 1.5rem;"></i>
                                                                <div><small class="text-gray-500">1</small></div>
                                                            </div>
                                                            <div class="text-center flex-1">
                                                                <i class="fas fa-frown-open text-warning" style="font-size: 1.5rem;"></i>
                                                                <div><small class="text-gray-500">2</small></div>
                                                            </div>
                                                            <div class="text-center flex-1">
                                                                <i class="fas fa-meh text-secondary" style="font-size: 1.5rem;"></i>
                                                                <div><small class="text-gray-500">3</small></div>
                                                            </div>
                                                            <div class="text-center flex-1">
                                                                <i class="fas fa-smile text-info" style="font-size: 1.5rem;"></i>
                                                                <div><small class="text-gray-500">4</small></div>
                                                            </div>
                                                            <div class="text-center flex-1">
                                                                <i class="fas fa-grin-stars text-success" style="font-size: 1.5rem;"></i>
                                                                <div><small class="text-gray-500">5</small></div>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                        <div>
                                            <div class="bg-white rounded-lg shadow-md overflow-hidden">
                                                <div class="bg-green-600 text-white px-4 py-3">
                                                    <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-2">
                                                        <h6 class="mb-0 font-semibold">
                                                            <i class="fas fa-chart-line"></i> Entwicklung über Zeit
                                                        </h6>
                                                        <div class="w-full sm:w-auto">
                                                            <select id="lineChartQuestionSelector" class="w-full sm:max-w-xs px-3 py-1 rounded border border-gray-300 bg-white text-gray-800 text-sm">
                                                                <option value="average">Durchschnitt aller Fragen</option>
                                                            </select>
                                                        </div>
                                                    </div>
                                                </div>
                                                <div class="p-4">
                                                    <canvas id="lineChart" height="280"></canvas>
                                                    <div class="text-center mt-2">
                                                        <small class="text-gray-500" id="lineChartDescription">Durchschnittliche Bewertung über alle Fragen</small>
                                                    </div>
                                                    <div class="mt-3 pt-2 border-t border-gray-200">
                                                        <small class="text-gray-500 block mb-1"><strong>Bewertungsskala:</strong></small>
                                                        <div class="flex justify-between items-center px-2">
                                                            <div class="text-center flex-1">
                                                                <i class="fas fa-frown text-danger" style="font-size: 1.5rem;"></i>
                                                                <div><small class="text-gray-500">1</small></div>
                                                            </div>
                                                            <div class="text-center flex-1">
                                                                <i class="fas fa-frown-open text-warning" style="font-size: 1.5rem;"></i>
                                                                <div><small class="text-gray-500">2</small></div>
                                                            </div>
                                                            <div class="text-center flex-1">
                                                                <i class="fas fa-meh text-secondary" style="font-size: 1.5rem;"></i>
                                                                <div><small class="text-gray-500">3</small></div>
                                                            </div>
                                                            <div class="text-center flex-1">
                                                                <i class="fas fa-smile text-info" style="font-size: 1.5rem;"></i>
                                                                <div><small class="text-gray-500">4</small></div>
                                                            </div>
                                                            <div class="text-center flex-1">
                                                                <i class="fas fa-grin-stars text-success" style="font-size: 1.5rem;"></i>
                                                                <div><small class="text-gray-500">5</small></div>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>


                                    <!-- Einzelne Dokumentations-Sessions -->
                                    <h6 class="mb-3 font-semibold text-gray-700"><i class="fas fa-list"></i> Einzelne Reflexions-Sessions</h6>
                                    <div class="space-y-2" id="documentationAccordion">
                                        @foreach($gradingSessions as $session)
                                            <div class="bg-white rounded-lg shadow-md overflow-hidden">
                                                <div class="bg-gray-100 px-4 py-3" id="heading{{ $session->id }}">
                                                    <h6 class="mb-0">
                                                        <button class="w-full text-left font-medium text-gray-700 hover:text-blue-600 transition-colors" type="button" data-toggle="collapse"
                                                                data-target="#collapse{{ $session->id }}" aria-expanded="false">
                                                            <i class="fas fa-calendar-alt"></i>
                                                            {{ $session->completed_at->format('d.m.Y H:i') }} Uhr
                                                            <span class="inline-block px-2 py-1 text-xs font-semibold rounded bg-blue-100 text-blue-800 ml-2">{{ $session->gradingSystem->name }}</span>
                                                            <span class="inline-block px-2 py-1 text-xs font-semibold rounded bg-gray-200 text-gray-800 ml-1">
                                                                {{ $session->isGroupSession() ? 'Gruppe' : 'Einzeln' }}
                                                            </span>
                                                        </button>
                                                    </h6>
                                                </div>
                                                <div id="collapse{{ $session->id }}" class="collapse" data-parent="#documentationAccordion">
                                                    <div class="p-4">
                                                        <p class="text-gray-600 mb-3">
                                                            <strong>Lehrer:</strong> {{ $session->user->name }}
                                                        </p>
                                                        <div class="overflow-x-auto">
                                                            <table class="min-w-full divide-y divide-gray-200 border border-gray-300">
                                                                <thead class="bg-gray-50">
                                                                    <tr>
                                                                        <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider" style="width: 40%;" rowspan="2">Frage</th>
                                                                        <th scope="col" class="px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider" style="width: 30%" colspan="2">Einschätzung</th>
                                                                        <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider" rowspan="2">Kommentar</th>
                                                                    </tr>
                                                                    <tr>
                                                                        <th scope="col" class="px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider" style="width: 15%;">Schüler</th>
                                                                        <th scope="col" class="px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider" style="width: 15%;">Lehrer</th>
                                                                    </tr>
                                                                </thead>
                                                                <tbody class="bg-white divide-y divide-gray-200">
                                                                    @foreach($session->gradingSystem->questions as $question)
                                                                        @php
                                                                            $studentAnswer = $session->studentAnswers->where('schueler_id', $schueler->id)
                                                                                                                     ->where('question_id', $question->id)
                                                                                                                     ->first();
                                                                            $teacherAssessment = $session->teacherAssessments->where('schueler_id', $schueler->id)
                                                                                                                              ->where('question_id', $question->id)
                                                                                                                              ->first();
                                                                        @endphp
                                                                        <tr>
                                                                            <td class="px-4 py-3">{{ $question->question }}</td>
                                                                            <td class="px-4 py-3 text-center">
                                                                                @if($studentAnswer)
                                                                                    @php
                                                                                        $rating = $studentAnswer->self_rating;
                                                                                        $icons = [
                                                                                            1 => 'fas fa-frown text-danger',
                                                                                            2 => 'fas fa-frown-open text-warning',
                                                                                            3 => 'fas fa-meh text-secondary',
                                                                                            4 => 'fas fa-smile text-info',
                                                                                            5 => 'fas fa-grin-stars text-success'
                                                                                        ];
                                                                                        $labels = [
                                                                                            1 => 'Sehr schlecht',
                                                                                            2 => 'Schlecht',
                                                                                            3 => 'Mittel',
                                                                                            4 => 'Gut',
                                                                                            5 => 'Sehr gut'
                                                                                        ];
                                                                                    @endphp
                                                                                    <i class="{{ $icons[$rating] }}" style="font-size: 1.5rem;"
                                                                                       title="{{ $labels[$rating] }}"></i>
                                                                                @else
                                                                                    <span class="text-gray-400">-</span>
                                                                                @endif
                                                                            </td>
                                                                            <td class="px-4 py-3 text-center">
                                                                                @if($teacherAssessment && $teacherAssessment->teacher_rating)
                                                                                    @php
                                                                                        $rating = $teacherAssessment->teacher_rating;
                                                                                        $icons = [
                                                                                            1 => 'fas fa-frown text-danger',
                                                                                            2 => 'fas fa-frown-open text-warning',
                                                                                            3 => 'fas fa-meh text-secondary',
                                                                                            4 => 'fas fa-smile text-info',
                                                                                            5 => 'fas fa-grin-stars text-success'
                                                                                        ];
                                                                                        $labels = [
                                                                                            1 => 'Sehr schlecht',
                                                                                            2 => 'Schlecht',
                                                                                            3 => 'Mittel',
                                                                                            4 => 'Gut',
                                                                                            5 => 'Sehr gut'
                                                                                        ];
                                                                                    @endphp
                                                                                    <i class="{{ $icons[$rating] }}" style="font-size: 1.5rem;"
                                                                                       title="{{ $labels[$rating] }}"></i>
                                                                                @else
                                                                                    <span class="text-gray-400">-</span>
                                                                                @endif
                                                                            </td>
                                                                            <td class="px-4 py-3">
                                                                                @if($teacherAssessment && $teacherAssessment->comment)
                                                                                    {{ $teacherAssessment->comment }}
                                                                                @else
                                                                                    <span class="text-gray-400">-</span>
                                                                                @endif
                                                                            </td>
                                                                        </tr>
                                                                    @endforeach
                                                                </tbody>
                                                            </table>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        @endforeach
                                    </div>
                                @endif
                            </div>
                        </div>
                    </div>

                    <!-- Stage & History (sichtbar in Schüleransicht) -->
                    <div class="grid grid-cols-1 md:grid-cols-12 gap-4 mb-4" id="stageHistoryRow" style="display:none">
                        <div class="md:col-span-4">
                            <div class="bg-white rounded-lg shadow-md overflow-hidden">
                                <div class="p-4 text-center">
                                    <h6 class="font-semibold text-gray-700 mb-3">Aktuelle Stufe</h6>
                                    <div id="stageCard" class="mt-2">
                                        <div class="text-gray-400">—</div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="md:col-span-8">
                            <div class="bg-white rounded-lg shadow-md overflow-hidden">
                                <div class="p-4">
                                    <h6 class="font-semibold text-gray-700 mb-3">Stufen-Historie</h6>
                                    <div class="overflow-x-auto">
                                        <table class="min-w-full divide-y divide-gray-200" id="historyTable">
                                            <thead class="bg-gray-50">
                                                <tr>
                                                    <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider" style="width:120px">Datum</th>
                                                    <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Neu</th>
                                                    <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Vorher</th>
                                                    <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider" style="width:120px">Geändert von</th>
                                                </tr>
                                            </thead>
                                            <tbody id="historyTableBody" class="bg-white divide-y divide-gray-200"></tbody>
                                        </table>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <!-- Ende von dataSection -->

            <!-- Keine Daten Nachricht -->
            <div id="noDataMessage" class="text-center py-12 hidden">
                <div class="inline-flex items-center justify-center w-20 h-20 bg-gray-100 rounded-full mb-4">
                    <i class="fas fa-info-circle text-4xl text-gray-400"></i>
                </div>
                <h5 class="text-xl font-semibold text-gray-600 mb-2">Keine Daten gefunden</h5>
                <p class="text-gray-500">Für den gewählten Zeitraum wurden keine Einträge gefunden.</p>
            </div>
        </div>
        <!-- Ende bg-white Filter/Content Card -->
    </div>
    <!-- Ende mb-6 Hauptcontainer -->
</div>
<!-- Ende container-fluid -->
@endsection

@push('css')
<link rel="stylesheet" href="{{ asset('css/tablet-scroll-optimization.css?v=20251110') }}">
<style>
/* Tailwind-kompatible Styles */
.card-title {
    font-size: 0.9rem;
}
.card-text {
    font-size: 1.2rem;
    font-weight: bold;
}
.badge {
    font-size: 0.7rem;
}

/* Tabellen-Styling */
#entriesTable tbody tr:hover {
    background-color: #f9fafb;
}
#entriesTable td:nth-child(2) {
    max-width: 400px;
    word-wrap: break-word;
}
#tasksTable td:nth-child(3) {
    max-width: 300px;
    word-wrap: break-word;
}

/* Status und Priorität */
.status-open {
    color: #dc3545;
    font-weight: bold;
}
.status-closed {
    color: #28a745;
}
.priority-high {
    color: #dc3545;
    font-weight: bold;
}

/* Responsive Tabellen mit optimierter Höhe für iPads */
.overflow-x-auto {
    max-height: 70vh;
    overflow-y: auto;
    -webkit-overflow-scrolling: touch;
}

/* Tab-Pane Animation */
.tab-pane {
    display: none;
}
.tab-pane.show.active {
    display: block;
    animation: fadeIn 0.2s ease-in;
}

@keyframes fadeIn {
    from { opacity: 0; transform: translateY(-10px); }
    to { opacity: 1; transform: translateY(0); }
}

/* Tab-Button Übergangseffekte */
[data-toggle="tab"] {
    transition: all 0.2s ease-in-out;
}

/* Chart Styling */
#radarChart, #lineChart {
    max-height: 280px;
}

.border-l-3 {
    border-left-width: 3px;
}

.border-blue-400 {
    border-color: #60a5fa;
}

/* Collapse Funktionalität mit Transition */
.collapse {
    display: none;
    transition: all 0.3s ease-in-out;
}

.collapse.show {
    display: block;
    animation: slideDown 0.3s ease-out;
}

@keyframes slideDown {
    from {
        opacity: 0;
        max-height: 0;
        overflow: hidden;
    }
    to {
        opacity: 1;
        max-height: 2000px;
    }
}

/* Kategorie-Header Styling */
#columnsTable thead tr:first-child th {
    background-color: #2c5f8d;
    color: white;
    font-weight: bold;
    text-align: center;
    vertical-align: middle;
    padding: 0.75rem 1rem;
}
#columnsTable thead tr:nth-child(2) th {
    background-color: #4a90e2;
    color: white;
    padding: 0.75rem 1rem;
}

/* Vertikale Trennlinien zwischen Kategorien */
#columnsTable thead tr:first-child th:not(:first-child) {
    border-left: 2px solid #1a3a5a;
}

#columnsTable tbody tr td {
    padding: 0.5rem 1rem;
}

#columnsTable tbody tr td:first-child {
    border-right: 2px solid #2c5f8d;
}

/* Trennlinie für den Beginn einer neuen Kategorie */
#columnsTable th.category-start,
#columnsTable td.category-start {
    border-left: 2px solid #2c5f8d !important;
}

/* Collapse Funktionalität */
.collapse {
    display: none;
}

.collapse.show {
    display: block;
}

/* Badge Styles für Tailwind-Kompatibilität */
.badge {
    display: inline-block;
    padding: 0.25em 0.6em;
    font-size: 75%;
    font-weight: 700;
    line-height: 1;
    text-align: center;
    white-space: nowrap;
    vertical-align: baseline;
    border-radius: 0.25rem;
}

.badge-success {
    background-color: #28a745;
    color: white;
}

.badge-secondary {
    background-color: #6c757d;
    color: white;
}

.badge-warning {
    background-color: #ffc107;
    color: #212529;
}

.badge-info {
    background-color: #17a2b8;
    color: white;
}

/* Progress Bar */
.progress {
    display: flex;
    height: 1rem;
    overflow: hidden;
    font-size: 0.75rem;
    background-color: #e9ecef;
    border-radius: 0.25rem;
}

.progress-bar {
    display: flex;
    flex-direction: column;
    justify-content: center;
    overflow: hidden;
    color: #fff;
    text-align: center;
    white-space: nowrap;
    background-color: #007bff;
    transition: width 0.6s ease;
}

.bg-danger {
    background-color: #dc3545 !important;
}

.bg-warning {
    background-color: #ffc107 !important;
}

.bg-info {
    background-color: #17a2b8 !important;
}

/* Alert Styles */
.alert {
    position: relative;
    padding: 0.75rem 1.25rem;
    margin-bottom: 1rem;
    border: 1px solid transparent;
    border-radius: 0.25rem;
}

.alert-success {
    color: #155724;
    background-color: #d4edda;
    border-color: #c3e6cb;
}

.alert-info {
    color: #0c5460;
    background-color: #d1ecf1;
    border-color: #bee5eb;
}

/* Responsive Optimierungen für iPads und Tablets */
@media (max-width: 1024px) {
    /* iPad Landscape und kleiner */
    .overflow-x-auto {
        max-height: 60vh;
    }

    /* Kompaktere Charts auf Tablets */
    #radarChart, #lineChart {
        max-height: 220px;
    }
}

@media (max-width: 768px) {
    /* iPad Portrait und kleiner */
    .bg-gradient-to-r {
        padding: 1rem !important;
    }

    /* Kompaktere Tab-Navigation */
    [data-toggle="tab"] {
        padding: 0.75rem 1rem !important;
        font-size: 0.875rem;
    }

    [data-toggle="tab"] span:not([id$="Badge"]) {
        display: none;
    }

    /* Tabellen scrollbar für kleine Bildschirme */
    .overflow-x-auto {
        max-height: 50vh;
    }

    /* Kleinere Charts auf mobilen Geräten */
    #radarChart, #lineChart {
        max-height: 200px;
    }

    /* Kompaktere Tabellen-Padding */
    #entriesTable td,
    #tasksTable td,
    #columnsTable td,
    #historyTable td {
        padding: 0.5rem !important;
        font-size: 0.875rem;
    }

    #entriesTable th,
    #tasksTable th,
    #columnsTable th,
    #historyTable th {
        padding: 0.5rem !important;
        font-size: 0.75rem;
    }
}

/* Touch-Optimierung für iPads */
@media (hover: none) and (pointer: coarse) {
    /* Größere Touch-Targets */
    button, a, [data-toggle="tab"], [data-toggle="collapse"] {
        min-height: 44px;
        min-width: 44px;
    }

    /* Verbesserte Scroll-Performance */
    .overflow-x-auto {
        scroll-behavior: smooth;
    }
}

/* Kleine Mobilgeräte */
@media (max-width: 640px) {
    /* Noch kompaktere Darstellung */
    .overflow-x-auto {
        max-height: 45vh;
    }

    #radarChart, #lineChart {
        max-height: 180px;
    }

    /* Stack charts vertikal */
    .grid > div {
        margin-bottom: 1rem;
    }
}
</style>
@endpush

@push('js')
<script src="{{ asset('/js/paed-diary.js') }}"></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<script src="{{ asset('/js/tablet-scroll-optimization.js?v=20251110')}}"></script>
<script>
(function(){
    const schuelerID = {{ $schueler->id }};

    // DOM Elemente
    const dateFromInput = document.getElementById('dateFrom');
    const dateToInput = document.getElementById('dateTo');
    const loadDataBtn = document.getElementById('loadDataBtn');
    const exportWordBtn = document.getElementById('exportWordBtn');
    const loadingIndicator = document.getElementById('loadingIndicator');
    const summarySection = document.getElementById('summarySection');
    const dataSection = document.getElementById('dataSection');
    const noDataMessage = document.getElementById('noDataMessage');

    // Neue Filter-Elemente
    const categoryFilter = document.getElementById('categoryFilter');
    const searchNotesInput = document.getElementById('searchNotes');
    const clearSearchBtn = document.getElementById('clearSearch');

    // Quick Date Buttons
    const last7DaysBtn = document.getElementById('last7Days');
    const last30DaysBtn = document.getElementById('last30Days');
    const last90DaysBtn = document.getElementById('last90Days');

    // Data Storage
    let currentData = null;
    let filteredEntries = [];
    // Map für Kategorien (id -> name), damit renderEntries zuverlässig Namen findet
    let categoryMap = new Map();

    // Utils
    function formatDate(date) {
        return date.toISOString().split('T')[0];
    }

    function formatDisplayDate(dateString) {
        return new Date(dateString).toLocaleDateString('de-DE');
    }

    function escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    function debounce(fn, wait){
        let t;
        return function(...args){
            clearTimeout(t);
            t = setTimeout(()=> fn.apply(this, args), wait);
        };
    }

    // Quick Date Functions
    function setDateRange(days) {
        const today = new Date();
        const fromDate = new Date();
        fromDate.setDate(today.getDate() - days);

        dateFromInput.value = formatDate(fromDate);
        dateToInput.value = formatDate(today);
    }

    // Load Data
    function loadData() {
        const dateFrom = dateFromInput.value;
        const dateTo = dateToInput.value;

        if (!dateFrom || !dateTo) {
            alert('Bitte wählen Sie einen gültigen Zeitraum aus.');
            return;
        }

        if (new Date(dateFrom) > new Date(dateTo)) {
            alert('Das Startdatum muss vor dem Enddatum liegen.');
            return;
        }

        // UI Updates
        loadingIndicator.classList.remove('hidden');
        summarySection.classList.add('hidden');
        dataSection.classList.add('hidden');
        noDataMessage.classList.add('hidden');

        // Fetch Data
        const params = new URLSearchParams({
            date_from: dateFrom,
            date_to: dateTo
        });

        fetch(`/paed-diary/schueler/${schuelerID}/data?${params}`)
            .then(response => response.json())
            .then(data => {
                currentData = data;
                console.log('Loaded Data:', data);
                populateCategories(data.categories || []);
                applyFiltersAndRender();
            })
            .catch(error => {
                console.error('Error loading data:', error);
                alert('Fehler beim Laden der Daten.');
            })
            .finally(() => {
                loadingIndicator.classList.add('hidden');
            });
    }

    function populateCategories(categories){
        if (!categoryFilter) return;
        // Preserve current selection
        const cur = categoryFilter.value;
        categoryFilter.innerHTML = '<option value="">Alle Kategorien</option>';

        // Build map id -> name from provided categories
        const catMap = new Map();
        if (categories) {
            // If categories is an object mapping id->name
            if (typeof categories === 'object' && !Array.isArray(categories)) {
                Object.keys(categories).forEach(k => {
                    const v = categories[k];
                    // if v is string, use it, else try to read properties
                    if (typeof v === 'string') catMap.set(String(k), v);
                    else if (v && (v.name || v.title || v.label)) catMap.set(String(k), v.name || v.title || v.label);
                });
            } else if (Array.isArray(categories)) {
                categories.forEach(c => {
                    if (!c) return;
                    const id = c.id != null ? String(c.id) : (c.value != null ? String(c.value) : null);
                    const name = c.name || c.title || c.label || c.text || '';
                    if (id) catMap.set(id, name || id);
                });
            }
        }

        // If no categories provided, try to extract from currentData.entries
        if (catMap.size === 0 && currentData && Array.isArray(currentData.entries)) {
            currentData.entries.forEach(e => {
                const cid = e.category_id || (e.category && (e.category.id || e.categoryId)) || e.kategorie_id || e.kategorieId || null;
                const cname = (e.category && (e.category.name || e.category.title)) || e.category_name || e.kategorie || e.kategorie_name || '';
                if (cid) catMap.set(String(cid), cname || ('Kategorie ' + cid));
            });
        }

        // Populate select from map and set global categoryMap
        categoryMap = new Map(catMap);
        Array.from(catMap.entries()).forEach(([id,name]) => {
            const opt = document.createElement('option');
            opt.value = id;
            opt.textContent = name || id;
            categoryFilter.appendChild(opt);
        });

        if (cur) categoryFilter.value = cur;
    }

    // Apply filters (category + search) and render
    function applyFiltersAndRender(){
        if (!currentData) return;
        const entries = currentData.entries || [];
        const searchTerm = (searchNotesInput && searchNotesInput.value || '').trim().toLowerCase();
        const category = (categoryFilter && categoryFilter.value) || '';

        filteredEntries = entries.filter(e => {
            // Category filter: support e.category_id or e.category?.id or e.category_id as string
            if (category){
                const entryCat = e.category_id || (e.category && e.category.id) || (e.category && e.category_id) || '';
                if (String(entryCat) !== String(category)) return false;
            }
            if (!searchTerm) return true;
            // Search in content and user and (category name)
            const fields = [e.content || '', e.user || '', (e.category && e.category.name) || ''].join(' ').toLowerCase();
            return fields.indexOf(searchTerm) !== -1;
        });

        // Update summary counts based on filtered entries
        document.getElementById('periodText').textContent = `${currentData.period.from} - ${currentData.period.to}`;
        document.getElementById('entriesCount').textContent = filteredEntries.length;
        document.getElementById('tasksCount').textContent = (currentData.tasks || []).length;

        const uniqueDays = new Set(filteredEntries.map(e => e.date));
        document.getElementById('daysWithEntriesCount').textContent = uniqueDays.size;

        // Update Badges
        document.getElementById('entriesBadge').textContent = filteredEntries.length;
        document.getElementById('tasksBadge').textContent = (currentData.tasks || []).length;

        // Determine active columns: if a column has an explicit `active` flag use it, otherwise treat as active
        const allColumns = (currentData.columns || []);
        const endDate = dateToInput && dateToInput.value ? new Date(dateToInput.value) : null;
        const activeColumns = allColumns.filter(c => {
            if (!c) return false;
            // If active flag exists, only include when truthy (supports 1/'1'/true/'true')
            if (typeof c.active !== 'undefined' && c.active !== null) {
                const isActiveFlag = (c.active === 1 || c.active === '1' || c.active === true || c.active === 'true');
                if (!isActiveFlag) return false;
            }

            // If deactivated_from is set and it's on or before the selected end date, consider the column deactivated
            if (c.deactivated_from && endDate) {
                const deact = new Date(c.deactivated_from);
                if (!isNaN(deact.getTime()) && deact <= endDate) return false;
            }

            return true;
        });

        document.getElementById('columnsBadge').textContent = activeColumns.length;

        // Render Tables with filtered entries
        renderEntries(filteredEntries);
        renderTasks(currentData.tasks || []);
        renderColumns(activeColumns, currentData.column_values || {});

        renderStage(currentData.current_stage);
        renderHistory(currentData.stage_history || []);

        summarySection.classList.remove('hidden');
        dataSection.classList.remove('hidden');

        if (filteredEntries.length === 0 && (currentData.tasks || []).length === 0) {
            noDataMessage.classList.remove('hidden');
        } else {
            noDataMessage.classList.add('hidden');
        }
    }

    // Render Entries (zeigt Kategorie als Badge falls vorhanden)
    function renderEntries(entries) {
        const tbody = document.getElementById('entriesTableBody');
        tbody.innerHTML = '';

        if (!entries || entries.length === 0) {
            const tr = document.createElement('tr');
            tr.innerHTML = `<td colspan="4" class="px-4 py-3 text-center text-gray-500">Keine Einträge</td>`;
            tbody.appendChild(tr);
            return;
        }

        entries.forEach(entry => {
            const row = document.createElement('tr');

            // determine category name robustly
            let categoryName = '';
            if (entry.category) categoryName = entry.category || '';

            // Wenn noch keine Kategorie-Name, versuche Lookup von ID in categoryMap
            if (!categoryName) {
                const cid = entry.category_id || (entry.category && (entry.category.id || entry.categoryId)) || entry.kategorie_id || entry.kategorieId || null;
                if (cid && categoryMap.has(String(cid))) categoryName = categoryMap.get(String(cid));
            }


            row.innerHTML = `
                <td class="px-4 py-3 text-center whitespace-nowrap">${formatDisplayDate(entry.date)}</td>
                <td class="px-4 py-3">${escapeHtml(entry.content)}</td>
                <td class="px-4 py-3">${escapeHtml(categoryName)}</td>
                <td class="px-4 py-3 text-center">${escapeHtml(entry.user || '')}</td>
            `;
            tbody.appendChild(row);
        });
    }

    // Render Tasks
    function renderTasks(tasks) {
        const tbody = document.getElementById('tasksTableBody');
        tbody.innerHTML = '';

        if (!tasks || tasks.length === 0) {
            const tr = document.createElement('tr');
            tr.innerHTML = `<td colspan="6" class="px-4 py-3 text-center text-gray-500">Keine Aufgaben</td>`;
            tbody.appendChild(tr);
            return;
        }

        tasks.forEach(task => {
            const row = document.createElement('tr');
            const statusClass = task.status === 'open' ? 'status-open' : 'status-closed';
            const priorityClass = task.highlighted ? 'priority-high' : '';

            row.innerHTML = `
                <td class="px-4 py-3 text-center whitespace-nowrap">${escapeHtml(task.created_at)}</td>
                <td class="px-4 py-3">${escapeHtml(task.title)}</td>
                <td class="px-4 py-3">${escapeHtml(task.description || '')}</td>
                <td class="px-4 py-3 text-center whitespace-nowrap">${escapeHtml(task.due_date || '')}</td>
                <td class="px-4 py-3 text-center"><span class="${statusClass}">${task.status === 'open' ? 'Offen' : 'Geschlossen'}</span></td>
                <td class="px-4 py-3 text-center"><span class="${priorityClass}">${task.highlighted ? 'Hoch' : 'Normal'}</span></td>
            `;
            tbody.appendChild(row);
        });
    }

    // Render Columns
    function renderColumns(columns, columnValues) {
        const categoryHeaders = document.getElementById('columnCategoryHeaders');
        const headers = document.getElementById('columnHeaders');
        const tbody = document.getElementById('columnsTableBody');

        // Reset headers
        categoryHeaders.innerHTML = '<th style="width: 100px;" rowspan="2">Datum</th>';
        headers.innerHTML = '';
        tbody.innerHTML = '';

        if (columns.length === 0) {
            // If no columns, show a placeholder
            categoryHeaders.innerHTML = '<th style="width:100px;" rowspan="2">Datum</th><th>Keine Spalten konfiguriert</th>';
            return;
        }

        // Group columns by category
        const columnsByCategory = {};
        columns.forEach(column => {
            const cat = column.category || 'Unkategorisiert';
            if (!columnsByCategory[cat]) {
                columnsByCategory[cat] = [];
            }
            columnsByCategory[cat].push(column);
        });

        // Create category headers with colspan
        const categories = Object.keys(columnsByCategory).sort((a, b) => {
            if (a === 'Unkategorisiert') return 1;
            if (b === 'Unkategorisiert') return -1;
            return a.localeCompare(b, 'de');
        });

        categories.forEach(category => {
            const th = document.createElement('th');
            th.textContent = category;
            th.colSpan = columnsByCategory[category].length;
            th.className = 'text-center';
            th.style.borderBottom = '2px solid #dee2e6';
            categoryHeaders.appendChild(th);
        });

        // Create column name headers
        let isFirstColumnInCategory = true;
        categories.forEach(category => {
            isFirstColumnInCategory = true;
            columnsByCategory[category].forEach(column => {
                const th = document.createElement('th');
                th.textContent = column.name || '';
                th.style.minWidth = '100px';
                // Markiere die erste Spalte jeder Kategorie für vertikale Trennlinie
                if (isFirstColumnInCategory && category !== categories[0]) {
                    th.classList.add('category-start');
                }
                headers.appendChild(th);
                isFirstColumnInCategory = false;
            });
        });

        // Create flat array of columns in the same order as headers (grouped by category)
        const sortedColumns = [];
        categories.forEach(category => {
            sortedColumns.push(...columnsByCategory[category]);
        });

        // Prepare counts for boolean "true" values per column
        const trueCounts = {};
        sortedColumns.forEach(column => { trueCounts[column.id] = 0; });

         // Group column values by date
         const valuesByDate = {};
         Object.keys(columnValues).forEach(date => {
             valuesByDate[date] = columnValues[date];
         });

         // Create rows for each date that has column values
         const sortedDates = Object.keys(valuesByDate).sort();
         sortedDates.forEach(date => {
             const row = document.createElement('tr');

             // Date column
             const dateCell = document.createElement('td');
             dateCell.textContent = formatDisplayDate(date);
             dateCell.className = 'text-center';
             row.appendChild(dateCell);

             // Value columns (in sorted order) - track category boundaries
             let currentCategory = sortedColumns[0]?.category || 'Unkategorisiert';

             sortedColumns.forEach((column) => {
                 const cell = document.createElement('td');
                 const value = valuesByDate[date] ? valuesByDate[date][column.id] : null;

                 // Check if this is the start of a new category
                 const colCategory = column.category || 'Unkategorisiert';
                 if (colCategory !== currentCategory) {
                     cell.classList.add('category-start');
                     currentCategory = colCategory;
                 }

                 if (value) {
                     if (column.type === 'boolean') {
                         const isTrue = (value.value === '1' || value.value === 1 || value.value === true || value.value === 'true');
                         cell.innerHTML = isTrue ?
                            '<span class="badge badge-success">Ja</span>' :
                            '<span class="badge badge-secondary">Nein</span>';
                         if (isTrue) {
                            trueCounts[column.id] = (trueCounts[column.id] || 0) + 1;
                         }
                     } else {
                         cell.textContent = value.value;
                     }
                 } else {
                     cell.innerHTML = '<span class="text-muted">-</span>';
                 }

                 row.appendChild(cell);
             });

             tbody.appendChild(row);
         });

         if (sortedDates.length === 0) {
             const row = document.createElement('tr');
             row.innerHTML = `<td colspan="${sortedColumns.length + 1}" class="text-center text-muted">Keine Spaltenwerte im gewählten Zeitraum</td>`;
             tbody.appendChild(row);
         }

        // Append a counts row under the values: Anzahl (Ja) pro Spalte
        const countsRow = document.createElement('tr');
        const countsLabelCell = document.createElement('td');
        countsLabelCell.className = 'text-center font-weight-bold';
        countsLabelCell.textContent = 'Anzahl (Ja)';
        countsRow.appendChild(countsLabelCell);

        let currentCountCategory = sortedColumns[0]?.category || 'Unkategorisiert';
        sortedColumns.forEach(column => {
            const ccell = document.createElement('td');
            ccell.className = 'font-weight-bold';

            // Check if this is the start of a new category
            const colCategory = column.category || 'Unkategorisiert';
            if (colCategory !== currentCountCategory) {
                ccell.classList.add('category-start');
                currentCountCategory = colCategory;
            }

            if (column.type === 'boolean') {
                ccell.textContent = String(trueCounts[column.id] || 0);
            } else {
                ccell.innerHTML = '<span class="text-muted">-</span>';
            }
            countsRow.appendChild(ccell);
        });
        tbody.appendChild(countsRow);
     }

    // Export Word
    function exportWord() {
        if (!currentData) {
            alert('Bitte laden Sie zuerst Daten.');
            return;
        }

        const params = new URLSearchParams({
            date_from: dateFromInput.value,
            date_to: dateToInput.value
        });

        // include filters
        if (categoryFilter && categoryFilter.value) params.append('category', categoryFilter.value);
        if (searchNotesInput && searchNotesInput.value.trim()) params.append('search', searchNotesInput.value.trim());

        window.location.href = `/paed-diary/schueler/${schuelerID}/export/word?${params}`;
    }

    // Render current stage
    function renderStage(stage){
        const container = document.getElementById('stageCard');
        const row = document.getElementById('stageHistoryRow');
        if (!container) return;
        container.innerHTML = '';
        if (!stage){
            container.innerHTML = '<div class="text-muted">Keine Stufe zugewiesen</div>';
            row.style.display = 'none';
            return;
        }
        row.style.display = '';
        const box = document.createElement('div');
        box.className = 'd-flex align-items-center justify-content-center';
        // image or symbol
        if (stage.image_url){
            const img = document.createElement('img');
            img.src = stage.image_url;
            img.alt = stage.name;
            img.style.width = '56px'; img.style.height = '56px'; img.style.objectFit = 'cover'; img.style.borderRadius = '6px'; img.className = 'mr-2';
            box.appendChild(img);
        } else if (stage.symbol){
            const i = document.createElement('i');
            i.className = stage.symbol + ' fa-2x mr-2';
            i.setAttribute('aria-hidden','true');
            box.appendChild(i);
        } else {
            const placeholder = document.createElement('div');
            placeholder.style.width='56px'; placeholder.style.height='56px'; placeholder.style.background='#f0f0f0'; placeholder.style.borderRadius='6px'; placeholder.className='mr-2';
            box.appendChild(placeholder);
        }
        const txt = document.createElement('div');
        txt.innerHTML = `<div><strong>${escapeHtml(stage.name)}</strong></div>`;
        box.appendChild(txt);
        container.appendChild(box);
    }

    // Render grading history
    function renderHistory(history){
        const tbody = document.getElementById('historyTableBody');
        if (!tbody) return;
        tbody.innerHTML = '';
        if (!history || history.length === 0){
            // hide row if no history and no current stage
            // keep stage card visible if stage exists; handled elsewhere
            const tr = document.createElement('tr');
            tr.innerHTML = `<td colspan="4" class="text-center text-muted">Keine Historie vorhanden</td>`;
            tbody.appendChild(tr);
            return;
        }
        // history items assumed to be in chronological order — show newest first
        const sorted = history.slice().sort((a,b)=> new Date(b.at) - new Date(a.at));
        sorted.forEach(h => {
            const tr = document.createElement('tr');
            const changer = h.changed_by_name || h.changed_by || '';
            tr.innerHTML = `
                <td class="text-center">${formatDisplayDate(h.at)}</td>
                <td>${escapeHtml(h.stage_name || '-')}</td>
                <td>${escapeHtml(h.previous_stage_name || '-')}</td>
                <td class="text-center">${escapeHtml(changer)}</td>
            `;
            tbody.appendChild(tr);
        });
    }

    // Event Listeners
    loadDataBtn.addEventListener('click', loadData);
    exportWordBtn.addEventListener('click', exportWord);

    last7DaysBtn.addEventListener('click', () => {
        setDateRange(7);
        loadData();
    });

    last30DaysBtn.addEventListener('click', () => {
        setDateRange(30);
        loadData();
    });

    last90DaysBtn.addEventListener('click', () => {
        setDateRange(90);
        loadData();
    });

    // Enter key on date inputs
    dateFromInput.addEventListener('keypress', (e) => {
        if (e.key === 'Enter') loadData();
    });

    dateToInput.addEventListener('keypress', (e) => {
        if (e.key === 'Enter') loadData();
    });

    // Category / Search listeners
    if (categoryFilter) categoryFilter.addEventListener('change', () => applyFiltersAndRender());
    if (searchNotesInput) searchNotesInput.addEventListener('input', debounce(() => applyFiltersAndRender(), 300));
    if (clearSearchBtn) clearSearchBtn.addEventListener('click', () => { if (searchNotesInput) { searchNotesInput.value = ''; applyFiltersAndRender(); } });

    // Initial load
    loadData();

    // ===== Graduierungs-Visualisierung =====
    // Daten für Graduierungen aus Blade-Template
    const gradingSessions = @json($gradingSessions ?? []);

    // Globale Variable für das Chart-Objekt
    let lineChartInstance = null;

    if (gradingSessions && gradingSessions.length > 0) {
        initializeGraduationCharts();
    }

    function initializeGraduationCharts() {
        // Letzte Session für Radar-Chart
        const latestSession = gradingSessions[gradingSessions.length - 1];

        // Extrahiere alle einzigartigen Fragen aus allen Sessions
        const allQuestionsMap = new Map();
        gradingSessions.forEach(session => {
            const sessionQuestions = session.grading_system?.questions || [];
            sessionQuestions.forEach(q => {
                if (!allQuestionsMap.has(q.id)) {
                    allQuestionsMap.set(q.id, q);
                }
            });
        });
        const allQuestions = Array.from(allQuestionsMap.values());

        // Extrahiere Fragen aus letzter Session für Radar Chart
        const questions = latestSession.grading_system?.questions || [];

        // Bereite Daten für Radar-Chart vor (letzte Session)
        const radarLabels = questions.map(q => truncateText(q.question, 30));
        const studentRatings = [];
        const teacherRatings = [];

        questions.forEach(question => {
            // Finde Student Answer
            const studentAnswer = latestSession.student_answers?.find(
                sa => sa.schueler_id === {{ $schueler->id }} && sa.question_id === question.id
            );
            studentRatings.push(studentAnswer?.self_rating || 0);

            // Finde Teacher Assessment
            const teacherAssessment = latestSession.teacher_assessments?.find(
                ta => ta.schueler_id === {{ $schueler->id }} && ta.question_id === question.id
            );
            teacherRatings.push(teacherAssessment?.teacher_rating || 0);
        });

        // Erstelle Radar Chart
        const radarCtx = document.getElementById('radarChart');
        if (radarCtx) {
            new Chart(radarCtx, {
                type: 'radar',
                data: {
                    labels: radarLabels,
                    datasets: [{
                        label: 'Schüler-Einschätzung',
                        data: studentRatings,
                        borderColor: 'rgba(23, 162, 184, 1)',
                        backgroundColor: 'rgba(23, 162, 184, 0.2)',
                        pointBackgroundColor: 'rgba(23, 162, 184, 1)',
                        pointBorderColor: '#fff',
                        pointHoverBackgroundColor: '#fff',
                        pointHoverBorderColor: 'rgba(23, 162, 184, 1)'
                    }, {
                        label: 'Lehrer-Bewertung',
                        data: teacherRatings,
                        borderColor: 'rgba(40, 167, 69, 1)',
                        backgroundColor: 'rgba(40, 167, 69, 0.2)',
                        pointBackgroundColor: 'rgba(40, 167, 69, 1)',
                        pointBorderColor: '#fff',
                        pointHoverBackgroundColor: '#fff',
                        pointHoverBorderColor: 'rgba(40, 167, 69, 1)'
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: true,
                    scales: {
                        r: {
                            beginAtZero: true,
                            max: 5,
                            min: 0,
                            ticks: {
                                stepSize: 1
                            },
                            pointLabels: {
                                font: {
                                    size: 11
                                }
                            }
                        }
                    },
                    plugins: {
                        legend: {
                            display: false
                        },
                        tooltip: {
                            callbacks: {
                                label: function(context) {
                                    const labels = ['', 'Sehr schlecht', 'Schlecht', 'Mittel', 'Gut', 'Sehr gut'];
                                    return context.dataset.label + ': ' + labels[context.parsed.r] + ' (' + context.parsed.r + ')';
                                }
                            }
                        }
                    }
                }
            });
        }

        // Fülle das Dropdown mit allen Fragen
        populateQuestionSelector(allQuestions);

        // Erstelle das Linien-Chart (Entwicklung über Zeit)
        createLineChart('average', allQuestions);
    }


    function populateQuestionSelector(questions) {
        const selector = document.getElementById('lineChartQuestionSelector');
        if (!selector) {
            console.error('lineChartQuestionSelector nicht gefunden');
            return;
        }

        // Behalte die "Durchschnitt"-Option
        selector.innerHTML = '<option value="average">Durchschnitt aller Fragen</option>';

        // Füge alle Fragen hinzu
        questions.forEach((question) => {
            const option = document.createElement('option');
            option.value = question.id;
            option.textContent = truncateText(question.question, 50);
            selector.appendChild(option);
        });


        // Event-Listener für Änderungen
        selector.addEventListener('change', function() {
            const selectedValue = this.value;
            createLineChart(selectedValue, questions);
        });
    }

    function createLineChart(selectedOption, allQuestions) {
        // Bereite Daten vor
        const sessionDates = gradingSessions.map(s => {
            const date = new Date(s.completed_at);
            return date.toLocaleDateString('de-DE', { day: '2-digit', month: '2-digit', year: '2-digit' });
        });

        let studentRatingsData = [];
        let teacherRatingsData = [];
        let chartTitle = 'Durchschnittliche Bewertung über alle Fragen';

        if (selectedOption === 'average') {
            // Durchschnitt aller Fragen
            gradingSessions.forEach(session => {
                const questions = session.grading_system?.questions || [];
                let studentSum = 0, studentCount = 0;
                let teacherSum = 0, teacherCount = 0;

                questions.forEach(question => {
                    const studentAnswer = session.student_answers?.find(
                        sa => sa.schueler_id === {{ $schueler->id }} && sa.question_id === question.id
                    );
                    if (studentAnswer && studentAnswer.self_rating) {
                        studentSum += studentAnswer.self_rating;
                        studentCount++;
                    }

                    const teacherAssessment = session.teacher_assessments?.find(
                        ta => ta.schueler_id === {{ $schueler->id }} && ta.question_id === question.id
                    );
                    if (teacherAssessment && teacherAssessment.teacher_rating) {
                        teacherSum += teacherAssessment.teacher_rating;
                        teacherCount++;
                    }
                });

                studentRatingsData.push(studentCount > 0 ? parseFloat((studentSum / studentCount).toFixed(2)) : null);
                teacherRatingsData.push(teacherCount > 0 ? parseFloat((teacherSum / teacherCount).toFixed(2)) : null);
            });
        } else {
            // Spezifische Frage
            const selectedQuestionId = parseInt(selectedOption);
            const selectedQuestion = allQuestions.find(q => q.id === selectedQuestionId);

            if (selectedQuestion) {
                chartTitle = truncateText(selectedQuestion.question, 60);

                gradingSessions.forEach(session => {
                    const studentAnswer = session.student_answers?.find(
                        sa => sa.schueler_id === {{ $schueler->id }} && sa.question_id === selectedQuestionId
                    );
                    studentRatingsData.push(studentAnswer?.self_rating || null);

                    const teacherAssessment = session.teacher_assessments?.find(
                        ta => ta.schueler_id === {{ $schueler->id }} && ta.question_id === selectedQuestionId
                    );
                    teacherRatingsData.push(teacherAssessment?.teacher_rating || null);
                });
            }
        }

        // Aktualisiere die Beschreibung
        const descriptionElement = document.getElementById('lineChartDescription');
        if (descriptionElement) {
            descriptionElement.textContent = chartTitle;
        }

        // Erstelle oder aktualisiere das Chart
        const lineCtx = document.getElementById('lineChart');
        if (lineCtx) {
            // Zerstöre das alte Chart, falls vorhanden
            if (lineChartInstance) {
                lineChartInstance.destroy();
            }

            // Erstelle neues Chart
            lineChartInstance = new Chart(lineCtx, {
                type: 'line',
                data: {
                    labels: sessionDates,
                    datasets: [{
                        label: 'Schüler-Einschätzung',
                        data: studentRatingsData,
                        borderColor: 'rgba(23, 162, 184, 1)',
                        backgroundColor: 'rgba(23, 162, 184, 0.1)',
                        tension: 0.4,
                        fill: true,
                        spanGaps: true
                    }, {
                        label: 'Lehrer-Bewertung',
                        data: teacherRatingsData,
                        borderColor: 'rgba(40, 167, 69, 1)',
                        backgroundColor: 'rgba(40, 167, 69, 0.1)',
                        tension: 0.4,
                        fill: true,
                        spanGaps: true
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: true,
                    scales: {
                        y: {
                            beginAtZero: true,
                            max: 5,
                            min: 0,
                            ticks: {
                                stepSize: 1
                            }
                        }
                    },
                    plugins: {
                        legend: {
                            display: true,
                            position: 'bottom'
                        },
                        tooltip: {
                            callbacks: {
                                label: function(context) {
                                    const labels = ['', 'Sehr schlecht', 'Schlecht', 'Mittel', 'Gut', 'Sehr gut'];
                                    const value = context.parsed.y;
                                    if (value === null || value === 0) {
                                        return context.dataset.label + ': Keine Bewertung';
                                    }
                                    const labelText = Math.round(value) >= 1 && Math.round(value) <= 5 ? labels[Math.round(value)] : '';
                                    return context.dataset.label + ': ' + value + (labelText ? ' (' + labelText + ')' : '');
                                }
                            }
                        }
                    }
                }
            });
        }
    }


    function truncateText(text, maxLength) {
        if (!text) return '';
        return text.length > maxLength ? text.substring(0, maxLength) + '...' : text;
    }

    // Tab-Funktionalität (ersetzt Bootstrap-Tabs)
    function initTabs() {
        const tabButtons = document.querySelectorAll('[data-toggle="tab"]');

        tabButtons.forEach(button => {
            button.addEventListener('click', function(e) {
                e.preventDefault();

                const targetId = this.getAttribute('href');
                const targetPane = document.querySelector(targetId);

                if (!targetPane) return;

                // Alle Tabs deaktivieren
                tabButtons.forEach(btn => {
                    btn.classList.remove('border-blue-600', 'text-blue-600', 'active');
                    btn.classList.add('border-transparent', 'text-gray-500');
                    btn.setAttribute('aria-selected', 'false');
                    // Badge Farbe zurücksetzen
                    const badge = btn.querySelector('span[id$="Badge"]');
                    if (badge && badge.id !== 'entriesBadge' && badge.id !== 'tasksBadge' && badge.id !== 'columnsBadge') {
                        badge.classList.remove('bg-blue-100', 'text-blue-600');
                        badge.classList.add('bg-gray-200', 'text-gray-600');
                    }
                });

                // Alle Tab-Panes verstecken
                document.querySelectorAll('.tab-pane').forEach(pane => {
                    pane.classList.remove('show', 'active');
                });

                // Aktiven Tab aktivieren
                this.classList.remove('border-transparent', 'text-gray-500');
                this.classList.add('border-blue-600', 'text-blue-600', 'active');
                this.setAttribute('aria-selected', 'true');

                // Badge Farbe aktualisieren
                const activeBadge = this.querySelector('span[id$="Badge"]');
                if (activeBadge) {
                    activeBadge.classList.remove('bg-gray-200', 'text-gray-600');
                    activeBadge.classList.add('bg-blue-100', 'text-blue-600');
                }

                // Ziel-Pane anzeigen
                targetPane.classList.add('show', 'active');
            });
        });
    }

    // Initialisiere Tabs beim Laden
    initTabs();

    // Initialisiere Collapse-Funktionalität für Accordion mit Event-Delegation
    function initCollapse() {
        // Event-Delegation auf document-level um sicherzustellen, dass alle Elemente erfasst werden
        document.addEventListener('click', function(e) {
            // Prüfe ob ein collapse-button oder dessen Kind-Element geklickt wurde
            const button = e.target.closest('[data-toggle="collapse"]');

            if (!button) return;

            // Verhindere Standard-Verhalten und Event-Propagation
            e.preventDefault();
            e.stopPropagation();

            const targetId = button.getAttribute('data-target');
            const targetElement = document.querySelector(targetId);

            if (!targetElement) {
                console.error('Collapse target not found:', targetId);
                return;
            }

            // Toggle collapse
            const isCurrentlyOpen = targetElement.classList.contains('show');

            if (isCurrentlyOpen) {
                // Schließen
                targetElement.classList.remove('show');
                button.setAttribute('aria-expanded', 'false');
                button.classList.remove('text-blue-600');
                button.classList.add('text-gray-700');
            } else {
                // Optional: Schließe andere Items im selben Accordion
                const parent = button.getAttribute('data-parent');
                if (parent) {
                    const parentElement = document.querySelector(parent);
                    if (parentElement) {
                        const siblings = parentElement.querySelectorAll('.collapse.show');
                        siblings.forEach(sibling => {
                            if (sibling !== targetElement) {
                                sibling.classList.remove('show');
                                const siblingButton = document.querySelector(`[data-target="#${sibling.id}"]`);
                                if (siblingButton) {
                                    siblingButton.setAttribute('aria-expanded', 'false');
                                    siblingButton.classList.remove('text-blue-600');
                                    siblingButton.classList.add('text-gray-700');
                                }
                            }
                        });
                    }
                }

                // Öffnen
                targetElement.classList.add('show');
                button.setAttribute('aria-expanded', 'true');
                button.classList.remove('text-gray-700');
                button.classList.add('text-blue-600');
            }
        });
    }

    // Initialisiere Collapse beim Laden
    initCollapse();
})();
</script>
@endpush
