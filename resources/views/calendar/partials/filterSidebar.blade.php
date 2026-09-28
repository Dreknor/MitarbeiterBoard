<aside x-show="sidebarVisible"
       x-cloak
       class="no-print w-60 shrink-0 bg-white border border-gray-200 rounded-lg p-3 shadow-sm
              max-md:fixed max-md:top-0 max-md:left-0 max-md:bottom-0 max-md:z-[70] max-md:w-72
              max-md:rounded-none max-md:overflow-y-auto max-md:shadow-xl">

    {{-- Mobile: Schließen --}}
    <div class="flex items-center justify-between mb-2 md:hidden">
        <span class="text-sm font-semibold text-gray-700">Ansicht</span>
        <button type="button" @click="sidebarVisible = false"
                class="w-8 h-8 inline-flex items-center justify-center rounded-md text-gray-500 hover:bg-gray-100 hover:text-gray-700"
                aria-label="Seitenleiste schließen">
            <i class="fas fa-times"></i>
        </button>
    </div>

    {{-- ─── Termin-Suche ─────────────────────────────────────────────────── --}}
    <div class="mb-4">
        <label for="cal-search" class="sr-only">Termin suchen</label>
        <div class="relative">
            <i class="fas fa-search absolute left-2.5 top-1/2 -translate-y-1/2 text-xs text-gray-400"></i>
            <input type="search"
                   id="cal-search"
                   x-model="searchQuery"
                   @input.debounce.300ms="performSearch()"
                   @keydown.escape="clearSearch()"
                   placeholder="Termin suchen…"
                   class="w-full border border-gray-300 rounded-md pl-7 pr-2.5 py-1.5 text-sm bg-white
                          focus:outline-none focus:ring-2 focus:ring-blue-200 focus:border-blue-400">
        </div>

        <div x-show="searchLoading" x-cloak class="mt-1 text-xs text-gray-400 italic">Suche…</div>

        <div x-show="searchResults.length > 0" x-cloak
             class="mt-1.5 max-h-64 overflow-y-auto rounded-md border border-gray-200 bg-white shadow-sm">
            <template x-for="result in searchResults" :key="result.id">
                <button type="button"
                        @click="goToSearchResult(result)"
                        class="block w-full text-left px-2 py-1.5 hover:bg-blue-50 transition-colors text-sm border-b border-gray-100 last:border-b-0">
                    <span class="flex items-center gap-1.5">
                        <span class="w-2 h-2 rounded-full shrink-0" :style="`background-color: ${result.kalender.farbe}`"></span>
                        <span class="font-medium text-gray-800 truncate" x-text="result.titel"></span>
                    </span>
                    <span class="block text-xs text-gray-500 ml-3.5" x-text="result.beginn"></span>
                    <span x-show="result.ort" class="block text-xs text-gray-400 ml-3.5 truncate" x-text="result.ort"></span>
                </button>
            </template>
        </div>

        <div x-show="searchQuery.length >= 2 && searchResults.length === 0 && !searchLoading"
             x-cloak
             class="mt-1.5 text-xs text-gray-400 italic">
            Keine Termine gefunden.
        </div>
    </div>

    {{-- ─── Kalender ─────────────────────────────────────────────────────── --}}
    <div class="cal-sidebar-heading pb-1.5 mb-1.5 border-b border-gray-200">
        <span>Kalender</span>
        <span class="flex items-center gap-2 normal-case tracking-normal font-normal">
            <button type="button" @click="showAllCalendars()" class="text-blue-600 hover:text-blue-800 hover:underline" title="Alle einblenden">alle</button>
            <button type="button" @click="hideAllCalendars()" class="text-gray-500 hover:text-gray-700 hover:underline" title="Alle ausblenden">keine</button>
        </span>
    </div>

    <div class="flex flex-col gap-0.5">
        <template x-for="cal in allCalendars.filter(c => c.typ !== 'ical')" :key="cal.id">
            <div class="flex items-center gap-1.5">
                <label class="flex items-center gap-2 px-1.5 py-1 rounded cursor-pointer hover:bg-gray-100 select-none flex-1 min-w-0">
                    <input type="checkbox"
                           :value="cal.id"
                           :checked="activeCalendars.includes(cal.id)"
                           @change="toggleCalendar(cal.id)"
                           class="w-3.5 h-3.5 shrink-0 cursor-pointer accent-blue-600">
                    <span class="w-2.5 h-2.5 rounded-full shrink-0" :style="'background-color: ' + getEffectiveColor(cal.id)"></span>
                    <span class="text-sm text-gray-700 truncate" x-text="cal.name" :title="cal.name"></span>
                </label>
                <input type="color"
                       :value="getEffectiveColor(cal.id)"
                       @input.debounce.500ms="setCustomColor(cal.id, $event.target.value)"
                       class="calendar-color-input w-4 h-4 shrink-0"
                       title="Kalenderfarbe anpassen">
                <button type="button"
                        x-show="customColors[String(cal.id)]"
                        x-cloak
                        @click="resetCustomColor(cal.id)"
                        class="text-gray-400 hover:text-red-500 shrink-0 p-0.5"
                        title="Farbe zurücksetzen">
                    <i class="fas fa-undo text-[10px]"></i>
                </button>
            </div>
        </template>
        <p x-show="allCalendars.filter(c => c.typ !== 'ical').length === 0" class="text-xs text-gray-400 italic px-1.5">
            Keine Kalender freigegeben.
        </p>
    </div>

    {{-- ─── Räume aus der Raumplanung ───────────────────────────────────── --}}
    <template x-if="allRooms.length > 0">
        <div class="mt-4">
            <div class="cal-sidebar-heading pb-1.5 mb-1.5 border-b border-gray-200">
                <span><i class="fas fa-door-open mr-1"></i>Räume</span>
                <button type="button"
                        x-show="activeRooms.length > 0"
                        @click="clearRooms()"
                        class="normal-case tracking-normal font-normal text-gray-500 hover:text-gray-700 hover:underline">
                    keine
                </button>
            </div>

            <input type="search"
                   x-show="allRooms.length > 8"
                   x-model="roomFilter"
                   placeholder="Raum filtern…"
                   class="w-full mb-1.5 border border-gray-300 rounded-md px-2 py-1 text-xs bg-white focus:outline-none focus:ring-2 focus:ring-blue-200">

            <div class="flex flex-col gap-0.5 max-h-56 overflow-y-auto pr-1">
                <template x-for="room in filteredRooms" :key="room.id">
                    <label class="flex items-center gap-2 px-1.5 py-1 rounded cursor-pointer hover:bg-gray-100 select-none">
                        <input type="checkbox"
                               :checked="activeRooms.includes(room.id)"
                               @change="toggleRoom(room.id)"
                               class="w-3.5 h-3.5 shrink-0 cursor-pointer accent-teal-600">
                        <span class="w-2.5 h-2.5 rounded-sm shrink-0" :style="'background-color: ' + room.farbe"></span>
                        <span class="text-sm text-gray-700 truncate" x-text="room.name" :title="room.name"></span>
                        <span x-show="room.nummer" class="ml-auto text-[11px] text-gray-400 shrink-0" x-text="room.nummer"></span>
                    </label>
                </template>
            </div>
            <p x-show="activeRooms.length >= maxRooms" x-cloak class="mt-1 text-[11px] text-amber-700">
                Maximal <span x-text="maxRooms"></span> Räume gleichzeitig.
            </p>
        </div>
    </template>

    {{-- ─── Meine iCal-Feeds ───────────────────────────────────────────── --}}
    <div class="mt-4">
        <div class="cal-sidebar-heading pb-1.5 mb-1.5 border-b border-gray-200">
            <span><i class="fas fa-rss mr-1"></i>Meine Feeds</span>
            <button type="button"
                    @click="showIcalFeedModal = true"
                    class="normal-case tracking-normal font-normal text-blue-600 hover:text-blue-800 hover:underline">
                + Hinzufügen
            </button>
        </div>

        <div class="flex flex-col gap-0.5">
            <template x-for="cal in allCalendars.filter(c => c.typ === 'ical')" :key="cal.id">
                <div class="group flex items-center gap-1.5">
                    <label class="flex items-center gap-2 px-1.5 py-1 rounded cursor-pointer hover:bg-gray-100 select-none flex-1 min-w-0">
                        <input type="checkbox"
                               :value="cal.id"
                               :checked="activeCalendars.includes(cal.id)"
                               @change="toggleCalendar(cal.id)"
                               class="w-3.5 h-3.5 shrink-0 cursor-pointer accent-blue-600">
                        <span class="w-2.5 h-2.5 rounded-full shrink-0" :style="'background-color: ' + getEffectiveColor(cal.id)"></span>
                        <span class="text-sm text-gray-700 truncate" x-text="cal.name" :title="cal.name"></span>
                        <i x-show="cal.fehler" x-cloak class="fas fa-exclamation-triangle text-red-400 text-xs shrink-0" :title="'Fehler: ' + cal.fehler"></i>
                    </label>
                    <input type="color"
                           :value="getEffectiveColor(cal.id)"
                           @input.debounce.500ms="setCustomColor(cal.id, $event.target.value)"
                           class="calendar-color-input w-4 h-4 shrink-0"
                           title="Kalenderfarbe anpassen">
                    <button type="button"
                            @click="deleteIcalFeed(cal.delete_url, cal.name)"
                            class="opacity-0 group-hover:opacity-100 focus:opacity-100 transition-opacity text-gray-400 hover:text-red-500 shrink-0 p-0.5"
                            title="Feed entfernen">
                        <i class="fas fa-times text-xs"></i>
                    </button>
                </div>
            </template>

            <p x-show="allCalendars.filter(c => c.typ === 'ical').length === 0" class="text-xs text-gray-400 italic px-1.5">
                Noch keine Feeds abonniert.
            </p>
        </div>
    </div>

    {{-- ─── Kalender-Abo (eigener iCal-Feed) ──────────────────────────────── --}}
    <div class="mt-4" x-data="{ feedVisible: false }">
        <button type="button"
                @click="feedVisible = !feedVisible"
                class="cal-sidebar-heading w-full pb-1.5 border-b border-gray-200 hover:text-gray-700">
            <span><i class="fas fa-link mr-1"></i>Kalender-Abo</span>
            <i class="fas text-[10px]" :class="feedVisible ? 'fa-chevron-up' : 'fa-chevron-down'"></i>
        </button>

        <div x-show="feedVisible" x-cloak class="mt-2">
            @if($feedToken ?? null)
                <p class="text-xs text-gray-500 mb-1.5 leading-snug">
                    Feed-URL für Outlook, Google Calendar o. ä.:
                </p>
                <div class="flex gap-1 mb-1.5">
                    <input type="text" readonly
                           id="ical-feed-url"
                           value="{{ route('calendar.feed', ['token' => $feedToken]) }}"
                           class="flex-1 min-w-0 text-[10px] font-mono border border-gray-200 rounded px-1.5 py-1 bg-gray-50 text-gray-600 focus:outline-none focus:border-blue-300">
                    <button type="button"
                            onclick="copyFeedUrl()"
                            title="URL kopieren"
                            class="shrink-0 px-2 py-1 rounded border border-gray-200 bg-white text-gray-500 hover:bg-blue-50 hover:text-blue-600 text-xs">
                        <i class="fas fa-copy"></i>
                    </button>
                </div>
                <form action="{{ route('calendar.feed.token') }}" method="POST">
                    @csrf
                    <button type="submit"
                            onclick="return confirm('Token erneuern? Der alte Feed-Link wird ungültig.')"
                            class="flex items-center gap-1.5 w-full px-2 py-1 text-left text-xs border border-gray-200 bg-white rounded text-gray-500 hover:bg-red-50 hover:border-red-200 hover:text-red-600">
                        <i class="fas fa-sync-alt"></i> Token erneuern
                    </button>
                </form>
            @else
                <p class="text-xs text-gray-500 mb-1.5 leading-snug">
                    Kalender als Abo in externe Apps einbinden:
                </p>
                <form action="{{ route('calendar.feed.token') }}" method="POST">
                    @csrf
                    <button type="submit"
                            class="flex items-center gap-1.5 w-full px-2 py-1 text-left text-xs border border-blue-200 bg-blue-50 rounded text-blue-700 hover:bg-blue-100 hover:border-blue-300 font-medium">
                        <i class="fas fa-link"></i> Feed-Token generieren
                    </button>
                </form>
            @endif
        </div>
    </div>

    @can('manage calendar')
        <div class="mt-4">
            <p class="cal-sidebar-heading pb-1.5 mb-1.5 border-b border-gray-200">Admin</p>
            <a href="{{ route('calendar.admin') }}"
               class="flex items-center gap-1.5 w-full px-2 py-1 text-[13px] rounded text-gray-600 hover:bg-amber-50 hover:text-amber-800">
                <i class="fas fa-cog text-xs w-4"></i> Kalender verwalten
            </a>
            <a href="{{ route('calendar.admin.logs') }}"
               class="flex items-center gap-1.5 w-full px-2 py-1 text-[13px] rounded text-gray-600 hover:bg-amber-50 hover:text-amber-800">
                <i class="fas fa-list-alt text-xs w-4"></i> Sync-Logs
            </a>
        </div>
    @endcan
</aside>
