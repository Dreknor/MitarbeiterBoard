<aside x-show="sidebarVisible"
       x-transition
       class="no-print w-[220px] shrink-0 mr-4 bg-gray-50 border border-gray-200 rounded-md p-3
              max-md:absolute max-md:top-0 max-md:left-0 max-md:z-[100] max-md:w-[200px]
              max-md:h-full max-md:rounded-none max-md:shadow-lg max-md:bg-white max-md:mr-0">

    <p class="text-[11px] font-semibold text-gray-500 uppercase tracking-wide pb-2 mb-2 border-b border-gray-200">
        Kalender
    </p>

    <div class="flex flex-col gap-0.5">
        <template x-for="cal in allCalendars" :key="cal.id">
            <label class="flex items-center gap-2 px-1.5 py-1 rounded cursor-pointer hover:bg-gray-100
                          select-none font-normal m-0">
                <input type="checkbox"
                       :value="cal.id"
                       :checked="activeCalendars.includes(parseInt(cal.id))"
                       @change="toggleCalendar(cal.id)"
                       class="rounded cursor-pointer accent-blue-500 w-[14px] h-[14px] shrink-0 m-0">
                <span class="w-2.5 h-2.5 rounded-full shrink-0 inline-block"
                      :style="'background-color: ' + cal.farbe"></span>
                <span class="text-sm text-gray-700 truncate flex-1" x-text="cal.name"></span>
            </label>
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
