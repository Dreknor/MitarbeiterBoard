@extends('layouts.app')

@section('content')
<div class="container-fluid" x-data="{ activeTab: 'kategorien' }">

    {{-- ── Moderner Gradient-Header ─────────────────────────────────────────── --}}
    <div class="bg-gradient-to-r from-indigo-600 to-indigo-700 rounded-xl shadow-lg p-2 sm:p-4 mb-6">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
            <div class="flex items-center gap-4">
                <div class="bg-white/20 backdrop-blur-sm rounded-full p-3 shrink-0">
                    <i class="fas fa-tags text-white text-xl"></i>
                </div>
                <div>
                    <h5 class="text-2xl font-bold text-white leading-tight">
                        Kategorien &amp; Spaltengruppen
                    </h5>
                    <p class="text-indigo-200 text-sm mt-0.5">
                        Notizkategorien und Spaltengruppen verwalten
                    </p>
                </div>
            </div>
            <a href="{{ route('paedDiary.index') }}"
               class="inline-flex items-center gap-2 px-4 py-2 bg-white/10 hover:bg-white/20 backdrop-blur-sm text-white rounded-lg transition-all duration-200 hover:scale-105 active:scale-95 font-medium self-start sm:self-auto">
                <i class="fas fa-arrow-left text-sm"></i>
                <span>Zurück zur Übersicht</span>
            </a>
        </div>
    </div>

    {{-- ── Flash-Meldung ────────────────────────────────────────────────────── --}}
    @if(session('Meldung'))
        <div class="flex items-start gap-3 px-4 py-3 rounded-xl mb-5 shadow-sm
            @if(session('type') === 'success') bg-emerald-50 border border-emerald-200 text-emerald-800
            @elseif(session('type') === 'danger' || session('type') === 'error') bg-red-50 border border-red-200 text-red-800
            @elseif(session('type') === 'warning') bg-amber-50 border border-amber-200 text-amber-800
            @else bg-blue-50 border border-blue-200 text-blue-800 @endif">
            <i class="fas fa-circle-info mt-0.5 flex-shrink-0"></i>
            <span class="text-sm font-medium">{{ session('Meldung') }}</span>
        </div>
    @endif

    {{-- ── Tab-Navigation ───────────────────────────────────────────────────── --}}
    <div class="flex bg-white rounded-t-xl shadow-sm border border-gray-200 border-b-0 px-2 pt-1">
        <button @click="activeTab = 'kategorien'"
                :class="activeTab === 'kategorien'
                    ? 'border-b-2 border-indigo-600 text-indigo-700 font-semibold'
                    : 'border-b-2 border-transparent text-gray-500 hover:text-gray-700'"
                class="flex items-center gap-2 px-5 py-3.5 text-sm font-medium transition-all duration-200 -mb-px">
            <i class="fas fa-tags"></i>
            Notizkategorien
        </button>
        <button @click="activeTab = 'spaltengruppen'"
                :class="activeTab === 'spaltengruppen'
                    ? 'border-b-2 border-indigo-600 text-indigo-700 font-semibold'
                    : 'border-b-2 border-transparent text-gray-500 hover:text-gray-700'"
                class="flex items-center gap-2 px-5 py-3.5 text-sm font-medium transition-all duration-200 -mb-px">
            <i class="fas fa-layer-group"></i>
            Spaltengruppen
        </button>
    </div>

    {{-- ── Tab-Inhalt ───────────────────────────────────────────────────────── --}}
    <div class="bg-white rounded-b-xl rounded-tr-xl shadow-md border border-gray-200 border-t-0 p-5 sm:p-6">

        {{-- AJAX-Feedback --}}
        <div id="catFeedback" class="mb-4 text-sm hidden"></div>

        {{-- ═══════════════════════════════════════════════════════════════════ --}}
        {{-- Tab 1: Notizkategorien                                             --}}
        {{-- ═══════════════════════════════════════════════════════════════════ --}}
        <div x-show="activeTab === 'kategorien'" x-transition.opacity>

            {{-- Info-Banner --}}
            <div class="flex items-start gap-3 bg-blue-50 border border-blue-200 rounded-xl px-4 py-3 mb-6">
                <i class="fas fa-circle-info text-blue-500 mt-0.5 flex-shrink-0"></i>
                <p class="text-sm text-blue-800">
                    <strong>Globale Kategorien</strong> stehen allen Lehrkräften zur Verfügung und können nur von Nutzern mit der Berechtigung
                    <em>„manage global paed diary categories"</em> bearbeitet werden.
                    <strong>Eigene Kategorien</strong> sind nur für Sie persönlich sichtbar.
                </p>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">

                {{-- ── Globale Kategorien ─────────────────────────────────── --}}
                <div class="bg-gray-50 rounded-xl border border-gray-200 p-4">
                    <div class="flex items-center gap-2.5 mb-4">
                        <div class="w-8 h-8 bg-indigo-100 rounded-lg flex items-center justify-center shrink-0">
                            <i class="fas fa-globe-europe text-indigo-600 text-sm"></i>
                        </div>
                        <h2 class="text-xs font-bold text-gray-600 uppercase tracking-widest">
                            Globale Notizkategorien
                        </h2>
                    </div>

                    <ul id="globalCatList" class="space-y-1.5 mb-4 min-h-12">
                        <li class="text-gray-400 text-sm py-2 px-3 italic">Wird geladen…</li>
                    </ul>

                    @if($canManageGlobal)
                    <form id="addGlobalCatForm" class="flex gap-2">
                        <input type="text" name="name"
                               class="flex-1 min-w-0 px-3 py-1.5 text-sm border border-gray-300 rounded-lg
                                      focus:ring-2 focus:ring-indigo-400 focus:border-indigo-400 outline-none transition-all bg-white"
                               placeholder="Neue globale Kategorie" maxlength="100" required>
                        <button type="submit"
                                class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-indigo-600 hover:bg-indigo-700
                                       active:scale-95 text-white text-sm font-medium rounded-lg transition-all duration-150 shrink-0">
                            <i class="fas fa-plus text-xs"></i> Hinzufügen
                        </button>
                    </form>
                    @else
                    <p class="flex items-center gap-1.5 text-gray-400 text-xs mt-2">
                        <i class="fas fa-lock"></i>
                        Nur Administratoren können globale Kategorien bearbeiten.
                    </p>
                    @endif
                </div>

                {{-- ── Eigene Kategorien ──────────────────────────────────── --}}
                <div class="bg-gray-50 rounded-xl border border-gray-200 p-4">
                    <div class="flex items-center gap-2.5 mb-4">
                        <div class="w-8 h-8 bg-emerald-100 rounded-lg flex items-center justify-center shrink-0">
                            <i class="fas fa-user text-emerald-600 text-sm"></i>
                        </div>
                        <h2 class="text-xs font-bold text-gray-600 uppercase tracking-widest">
                            Meine Notizkategorien
                        </h2>
                    </div>

                    <ul id="ownCatList" class="space-y-1.5 mb-4 min-h-12">
                        <li class="text-gray-400 text-sm py-2 px-3 italic">Wird geladen…</li>
                    </ul>

                    <form id="addCatForm" class="flex gap-2">
                        <input type="text" name="name"
                               class="flex-1 min-w-0 px-3 py-1.5 text-sm border border-gray-300 rounded-lg
                                      focus:ring-2 focus:ring-emerald-400 focus:border-emerald-400 outline-none transition-all bg-white"
                               placeholder="Neue Kategorie" maxlength="100" required>
                        <button type="submit"
                                class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-emerald-600 hover:bg-emerald-700
                                       active:scale-95 text-white text-sm font-medium rounded-lg transition-all duration-150 shrink-0">
                            <i class="fas fa-plus text-xs"></i> Erstellen
                        </button>
                    </form>
                </div>

            </div>
        </div>

        {{-- ═══════════════════════════════════════════════════════════════════ --}}
        {{-- Tab 2: Spaltengruppen                                              --}}
        {{-- ═══════════════════════════════════════════════════════════════════ --}}
        <div x-show="activeTab === 'spaltengruppen'" x-transition.opacity>

            <div id="colGroupFeedback" class="mb-4 text-sm hidden"></div>

            {{-- Info-Banner --}}
            <div class="flex items-start gap-3 bg-gray-50 border border-gray-200 rounded-xl px-4 py-3 mb-6">
                <i class="fas fa-circle-info text-gray-400 mt-0.5 flex-shrink-0"></i>
                <p class="text-sm text-gray-600">
                    Spaltengruppen dienen zur visuellen Gruppierung der Zusatzspalten in der Wochenansicht.
                    @if($canManageGlobal)
                        Das Umbenennen einer Gruppe aktualisiert <strong>alle</strong> Spalten mit diesem Gruppenname.
                    @else
                        Das Umbenennen von Spaltengruppen erfordert die Berechtigung <em>„manage global paed diary categories"</em>.
                    @endif
                </p>
            </div>

            <div class="overflow-hidden rounded-xl border border-gray-200 shadow-sm">
                <table class="w-full text-sm">
                    <thead class="bg-gray-50 border-b border-gray-200">
                        <tr>
                            <th class="text-left px-4 py-3 text-xs font-bold text-gray-500 uppercase tracking-widest">
                                Gruppenname
                            </th>
                            <th class="text-center px-4 py-3 text-xs font-bold text-gray-500 uppercase tracking-widest w-36">
                                Anzahl Spalten
                            </th>
                            @if($canManageGlobal)
                            <th class="text-right px-4 py-3 text-xs font-bold text-gray-500 uppercase tracking-widest w-40">
                                Aktion
                            </th>
                            @endif
                        </tr>
                    </thead>
                    <tbody id="colGroupList" class="divide-y divide-gray-100">
                        <tr>
                            <td colspan="3" class="text-gray-400 text-sm text-center py-8 italic">
                                Wird geladen…
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

        </div>

    </div>{{-- /card --}}
</div>
@endsection

@push('js')
<script src="{{ asset('/js/paedDiary/categories.js?v=' . filemtime(public_path('js/paedDiary/categories.js'))) }}"></script>
@endpush
