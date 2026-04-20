{{-- Hortstunden-Planung Card v2 – nur Body-Inhalt (cardWrapper übernimmt Header) --}}
@can('view hort planung')
    @if(!$hortPlanung)
        <div class="px-4 py-8 text-center text-gray-400 text-sm">
            <i class="fas fa-child text-2xl mb-2 block opacity-40"></i>
            Keine aktive Hortstunden-Planung gefunden.
        </div>
        @can('manage hort planung')
            <div class="px-4 py-3 border-t border-gray-100">
                <a href="{{ route('hort-planung.create') }}"
                   class="flex items-center justify-center gap-2 px-4 py-2 rounded-lg text-sm font-medium
                          bg-blue-600 text-white hover:bg-blue-700 no-underline">
                    <i class="fas fa-plus"></i> Neue Planung
                </a>
            </div>
        @endcan
    @else
        {{-- Planungsinfos --}}
        <div class="px-4 py-3 border-b border-gray-100">
            <div class="flex items-center gap-2 mb-1">
                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-700">
                    aktiv
                </span>
                <span class="text-sm font-semibold text-gray-800">{{ $hortPlanung->name }}</span>
            </div>
            <div class="text-xs text-gray-500">
                {{ $hortPlanung->start_monat->format('M Y') }} – {{ $hortPlanung->end_monat->format('M Y') }}
                &middot; {{ $hortPlanung->department->name ?? '–' }}
            </div>
        </div>

        @if($hortAktuellerMonat)
            {{-- Kennzahlen --}}
            <div class="grid grid-cols-3 divide-x divide-gray-100 border-b border-gray-100">
                <div class="px-3 py-3 text-center">
                    <div class="text-xs text-gray-500 mb-1">VZÄ (SP1)</div>
                    <div class="text-lg font-bold text-gray-800">
                        {{ number_format($hortAktuellerMonat['summe_vz_sp1'], 2, ',', '.') }}
                    </div>
                </div>
                <div class="px-3 py-3 text-center">
                    <div class="text-xs text-gray-500 mb-1">Gesetzl. Min.</div>
                    <div class="text-lg font-bold text-gray-800">
                        {{ number_format($hortAktuellerMonat['summe_gesetz_vz'], 2, ',', '.') }}
                    </div>
                </div>
                <div class="px-3 py-3 text-center">
                    <div class="text-xs text-gray-500 mb-1">Budget-Rest</div>
                    <div class="text-lg font-bold {{ ($hortBudgetRest ?? 0) >= 0 ? 'text-green-600' : 'text-red-600' }}">
                        {{ number_format($hortBudgetRest ?? 0, 1, ',', '.') }}h
                    </div>
                </div>
            </div>

            {{-- Warnungen --}}
            @if($hortAbwesenheiten->isNotEmpty())
                <div class="px-4 py-2.5 bg-amber-50 border-b border-amber-100 flex items-center gap-2">
                    <i class="fas fa-exclamation-triangle text-amber-600 text-xs"></i>
                    <span class="text-xs text-amber-700">
                        {{ $hortAbwesenheiten->count() }} {{ $hortAbwesenheiten->count() === 1 ? 'Langzeitabwesenheit' : 'Langzeitabwesenheiten' }} im Planungszeitraum
                    </span>
                </div>
            @endif

            @if(($hortBudgetRest ?? 0) < 0)
                <div class="px-4 py-2.5 bg-red-50 border-b border-red-100 flex items-center gap-2">
                    <i class="fas fa-exclamation-circle text-red-600 text-xs"></i>
                    <span class="text-xs text-red-700">
                        Überplanung: {{ number_format(abs($hortBudgetRest), 1, ',', '.') }}h zu viel verplant
                    </span>
                </div>
            @endif
        @endif

        {{-- Footer mit Aktionen --}}
        <div class="px-4 py-3 border-t border-gray-100 flex items-center gap-2">
            <a href="{{ route('hort-planung.show', $hortPlanung) }}"
               class="flex items-center gap-1 text-sm text-blue-600 hover:text-blue-800 no-underline font-medium">
                <i class="fas fa-table text-xs"></i> Matrix
            </a>
            <span class="text-gray-300">·</span>
            <a href="{{ route('hort-planung.rueckblick', $hortPlanung) }}"
               class="flex items-center gap-1 text-sm text-gray-500 hover:text-gray-700 no-underline">
                <i class="fas fa-history text-xs"></i> Rückblick
            </a>
            @can('manage hort planung')
                <span class="text-gray-300 ml-auto">·</span>
                <a href="{{ route('hort-planung.export', $hortPlanung) }}"
                   class="flex items-center gap-1 text-sm text-green-600 hover:text-green-800 no-underline ml-auto">
                    <i class="fas fa-file-excel text-xs"></i> Export
                </a>
            @endcan
        </div>
    @endif
@else
    <div class="px-4 py-8 text-center text-gray-400 text-sm">
        Keine Berechtigung für die Hortstunden-Planung.
    </div>
@endcan

