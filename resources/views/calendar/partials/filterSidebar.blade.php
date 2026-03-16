<aside x-show="sidebarVisible"
       x-transition
       class="no-print w-[220px] shrink-0 mr-4 bg-gray-50 border border-gray-200 rounded-md p-3
              max-md:absolute max-md:top-0 max-md:left-0 max-md:z-[100] max-md:w-[200px]
              max-md:h-full max-md:rounded-none max-md:shadow-lg max-md:bg-white max-md:mr-0">

    {{-- ─── Termin-Suche (TODO 27) ────────────────────────────────────────── --}}
    <div class="mb-3">
        <label class="block text-[11px] font-semibold text-gray-500 uppercase tracking-wide mb-1">
            🔍 Termin suchen
        </label>
        <input type="text"
               x-model="searchQuery"
               @input.debounce.300ms="performSearch()"
               @keydown.escape="clearSearch()"
               placeholder="Titel, Ort, Beschreibung…"
               class="w-full border border-gray-300 rounded-md px-2.5 py-1.5 text-sm
                      focus:ring-2 focus:ring-blue-300 focus:border-blue-300 bg-white">

        {{-- Lade-Indikator --}}
        <div x-show="searchLoading" x-cloak class="mt-1 text-[11px] text-gray-400 italic">
            Suche…
        </div>

        {{-- Ergebnisliste --}}
        <div x-show="searchResults.length > 0" x-cloak
             class="mt-1.5 max-h-64 overflow-y-auto rounded border border-gray-200 bg-white shadow-sm">
            <template x-for="result in searchResults" :key="result.id">
                <button type="button"
                        @click="goToSearchResult(result)"
                        class="w-full text-left px-2 py-1.5 hover:bg-blue-50 transition-colors
                               text-sm border-b border-gray-100 last:border-0">
                    <div class="flex items-center gap-1.5">
                        <span class="w-2 h-2 rounded-full flex-shrink-0"
                              :style="`background-color: ${result.kalender.farbe}`"></span>
                        <span class="font-medium text-gray-800 truncate" x-text="result.titel"></span>
                    </div>
                    <div class="text-[11px] text-gray-500 ml-3.5" x-text="result.beginn"></div>
                    <div x-show="result.ort"
                         class="text-[11px] text-gray-400 ml-3.5 truncate"
                         x-text="result.ort"></div>
                </button>
            </template>
        </div>

        {{-- Keine Ergebnisse --}}
        <div x-show="searchQuery.length >= 2 && searchResults.length === 0 && !searchLoading"
             x-cloak
             class="mt-1.5 text-[11px] text-gray-400 italic">
            Keine Termine gefunden.
        </div>
    </div>

    <p class="text-[11px] font-semibold text-gray-500 uppercase tracking-wide pb-2 mb-2 border-b border-gray-200">
        Kalender
    </p>

    <div class="flex flex-col gap-0.5">
        <template x-for="cal in allCalendars" :key="cal.id">
            <div class="group flex items-center gap-1.5 py-0.5">
                {{-- Checkbox + Farbpunkt + Name --}}
                <label class="flex items-center gap-2 px-1.5 py-1 rounded cursor-pointer hover:bg-gray-100
                              select-none font-normal m-0 flex-1 min-w-0">
                    <input type="checkbox"
                           :value="cal.id"
                           :checked="activeCalendars.includes(cal.id)"
                           @change="toggleCalendar(cal.id)"
                           class="rounded cursor-pointer accent-blue-500 w-[14px] h-[14px] shrink-0 m-0">
                    <span class="w-2.5 h-2.5 rounded-full shrink-0 inline-block"
                          :style="'background-color: ' + getEffectiveColor(cal.id)"></span>
                    <span class="text-sm text-gray-700 truncate flex-1" x-text="cal.name"></span>
                    {{-- iCal-Feed-Indikator --}}
                    <span x-show="cal.typ === 'ical'"
                          class="text-[10px] text-indigo-400 shrink-0"
                          title="Externer iCal-Feed">📡</span>
                </label>

                {{-- Farbwähler – immer sichtbar  --}}
                <div class="flex-shrink-0 flex items-center gap-0.5">
                    <input type="color"
                           :value="getEffectiveColor(cal.id)"
                           @input.debounce.500ms="setCustomColor(cal.id, $event.target.value)"
                           class="calendar-color-input w-4 h-4 rounded cursor-pointer"
                           title="Kalenderfarbe anpassen">
                    {{-- Reset-Button (nur wenn eigene Farbe gesetzt) --}}
                    <button x-show="customColors[String(cal.id)]"
                            x-cloak
                            @click="resetCustomColor(cal.id)"
                            class="text-gray-400 hover:text-red-500 flex-shrink-0 p-0.5"
                            title="Farbe zurücksetzen">
                        <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                  d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/>
                        </svg>
                    </button>
                </div>

                {{-- iCal-Feed löschen (nur für Feeds, per Hover sichtbar) --}}
                <template x-if="cal.typ === 'ical'">
                    <button @click="deleteIcalFeed(cal.delete_url, cal.name)"
                            class="opacity-0 group-hover:opacity-100 transition-opacity
                                   text-gray-400 hover:text-red-500 flex-shrink-0 p-0.5"
                            title="Feed entfernen">
                        <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                  d="M6 18L18 6M6 6l12 12"/>
                        </svg>
                    </button>
                </template>
            </div>
        </template>
    </div>

    <div class="flex flex-col gap-1 mt-3 pt-2.5 border-t border-gray-200">
        <button type="button"
                class="flex items-center gap-1.5 w-full px-2 py-1 text-left text-[13px]
                       border border-gray-200 bg-white rounded text-gray-600
                       hover:bg-blue-50 hover:border-blue-200 hover:text-blue-700 transition-colors"
                @click="showAllCalendars()">
            <i class="fas fa-eye text-xs"></i> Alle einblenden
        </button>
        <button type="button"
                class="flex items-center gap-1.5 w-full px-2 py-1 text-left text-[13px]
                       border border-gray-200 bg-white rounded text-gray-500
                       hover:bg-gray-50 hover:border-gray-300 hover:text-gray-700 transition-colors"
                @click="hideAllCalendars()">
            <i class="fas fa-eye-slash text-xs"></i> Alle ausblenden
        </button>
    </div>

    {{-- ─── iCal-Feed ─────────────────────────────────────────────────── --}}
    <div class="mt-3 pt-2.5 border-t border-gray-200"
         x-data="{ feedVisible: false }">
        <button type="button"
                @click="feedVisible = !feedVisible"
                class="flex items-center justify-between w-full text-[11px] font-semibold text-gray-400
                       uppercase tracking-wide mb-0 hover:text-gray-600 transition-colors">
            <span>📅 Kalender-Abo</span>
            <i class="fas text-[10px] transition-transform" :class="feedVisible ? 'fa-chevron-up' : 'fa-chevron-down'"></i>
        </button>

        <div x-show="feedVisible" x-transition class="mt-1.5">
            @if($feedToken ?? null)
                {{-- Token vorhanden: URL anzeigen + kopieren --}}
                <p class="text-[11px] text-gray-500 mb-1.5 leading-tight">
                    Feed-URL für Outlook, Google&nbsp;Calendar&nbsp;o.&nbsp;ä.:
                </p>
                <div class="flex gap-1 mb-1.5">
                    <input type="text" readonly
                           id="ical-feed-url"
                           value="{{ route('calendar.feed', ['token' => $feedToken]) }}"
                           class="flex-1 text-[10px] font-mono border border-gray-200 rounded px-1.5 py-1
                                  bg-gray-50 text-gray-600 min-w-0 truncate focus:outline-none
                                  focus:border-blue-300 focus:ring-1 focus:ring-blue-200">
                    <button type="button"
                            onclick="copyFeedUrl()"
                            title="URL kopieren"
                            class="shrink-0 px-2 py-1 rounded border border-gray-200 bg-white text-gray-500
                                   hover:bg-blue-50 hover:text-blue-600 hover:border-blue-200 transition-colors text-[11px]">
                        <i class="fas fa-copy"></i>
                    </button>
                </div>
                <form action="{{ route('calendar.feed.token') }}" method="POST">
                    @csrf
                    <button type="submit"
                            onclick="return confirm('Token erneuern? Der alte Feed-Link wird ungültig.')"
                            class="flex items-center gap-1.5 w-full px-2 py-1 text-left text-[12px]
                                   border border-gray-200 bg-white rounded text-gray-500
                                   hover:bg-red-50 hover:border-red-200 hover:text-red-600 transition-colors">
                        <i class="fas fa-sync-alt text-xs"></i> Token erneuern
                    </button>
                </form>
            @else
                {{-- Noch kein Token: Generieren-Button --}}
                <p class="text-[11px] text-gray-500 mb-1.5 leading-tight">
                    Kalender als Abo in externe Apps einbinden:
                </p>
                <form action="{{ route('calendar.feed.token') }}" method="POST">
                    @csrf
                    <button type="submit"
                            class="flex items-center gap-1.5 w-full px-2 py-1 text-left text-[12px]
                                   border border-blue-200 bg-blue-50 rounded text-blue-700
                                   hover:bg-blue-100 hover:border-blue-300 transition-colors font-medium">
                        <i class="fas fa-link text-xs"></i> Feed-Token generieren
                    </button>
                </form>
            @endif
        </div>
    </div>

    {{-- ─── Meine iCal-Feeds ──────────────────────────────────────────────── --}}
    <div class="mt-3 pt-2.5 border-t border-gray-200">
        <div class="flex items-center justify-between mb-1.5">
            <p class="text-[11px] font-semibold text-gray-500 uppercase tracking-wide">
                📡 Meine Feeds
            </p>
            <button type="button"
                    @click="showIcalFeedModal = true"
                    class="text-[11px] text-blue-600 hover:text-blue-800 hover:underline">
                + Hinzufügen
            </button>
        </div>
        <p class="text-[11px] text-gray-400 italic leading-tight">
            Abonnierte Feeds erscheinen in der Kalenderliste oben.
        </p>
    </div>

    @can('manage calendar')
        <div class="mt-3 pt-2.5 border-t border-gray-200">
            <p class="text-[11px] font-semibold text-gray-400 uppercase tracking-wide mb-1.5">Admin</p>
            <a href="{{ route('calendar.admin') }}"
               class="flex items-center gap-1.5 w-full px-2 py-1 text-left text-[13px]
                      border border-gray-200 bg-white rounded text-gray-600
                      hover:bg-amber-50 hover:border-amber-200 hover:text-amber-700 transition-colors">
                <i class="fas fa-cog text-xs"></i> Kalender verwalten
            </a>
            <a href="{{ route('calendar.admin.logs') }}"
               class="flex items-center gap-1.5 w-full px-2 py-1 text-left text-[13px]
                      border border-gray-200 bg-white rounded text-gray-600
                      hover:bg-amber-50 hover:border-amber-200 hover:text-amber-700 transition-colors mt-0.5">
                <i class="fas fa-list-alt text-xs"></i> Sync-Logs
            </a>
        </div>
    @endcan
</aside>
