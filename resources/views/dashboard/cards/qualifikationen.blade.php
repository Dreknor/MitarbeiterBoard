{{-- Qualifikationen & Fortbildungen Card (N6) – nur Body-Inhalt (cardWrapper übernimmt Header) --}}
{{-- Zeigt eigene ablaufende Qualifikationen + eigene kommende Fortbildungen --}}

@php
    $hatInhalt = ($eigeneAblaufendeQualifikationen->isNotEmpty() || $eigeneKommendeTrainings->isNotEmpty());
@endphp

@if(!$hatInhalt)
    <div class="px-4 py-8 text-center text-gray-400 text-sm">
        <i class="fas fa-graduation-cap text-2xl mb-2 block opacity-40"></i>
        Keine ablaufenden Qualifikationen oder Fortbildungen
    </div>
@else

    {{-- Ablaufende Qualifikationen --}}
    @if($eigeneAblaufendeQualifikationen->isNotEmpty())
        <div class="px-4 py-2 bg-amber-50 border-b border-amber-100">
            <span class="text-xs font-semibold text-amber-700 uppercase tracking-wide">
                <i class="fas fa-exclamation-triangle mr-1"></i> Qualifikationen mit Handlungsbedarf
            </span>
        </div>
        <div class="divide-y divide-gray-100">
            @foreach($eigeneAblaufendeQualifikationen as $qual)
                <div class="flex items-center gap-3 px-4 py-2.5">
                    <div class="flex-1 min-w-0">
                        <div class="text-sm text-gray-800 font-medium truncate">
                            {{ $qual->qualificationType->name ?? '–' }}
                        </div>
                        @if($qual->expiry_date)
                            <div class="text-xs text-gray-500">
                                Läuft ab: {{ $qual->expiry_date->format('d.m.Y') }}
                            </div>
                        @endif
                    </div>
                    <span class="shrink-0 inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium
                        {{ $qual->expiry_date?->isPast() ? 'bg-red-100 text-red-700' : 'bg-amber-100 text-amber-700' }}">
                        {{ $qual->expiry_date?->isPast() ? 'Abgelaufen' : 'Läuft ab' }}
                    </span>
                </div>
            @endforeach
        </div>
    @endif

    {{-- Kommende Fortbildungen --}}
    @if($eigeneKommendeTrainings->isNotEmpty())
        <div class="px-4 py-2 bg-blue-50 border-b border-blue-100 {{ $eigeneAblaufendeQualifikationen->isNotEmpty() ? 'border-t border-gray-100' : '' }}">
            <span class="text-xs font-semibold text-blue-700 uppercase tracking-wide">
                <i class="fas fa-chalkboard-teacher mr-1"></i> Meine Fortbildungen
            </span>
        </div>
        <div class="divide-y divide-gray-100">
            @foreach($eigeneKommendeTrainings as $training)
                <div class="flex items-center gap-3 px-4 py-2.5">
                    <div class="text-center min-w-[3rem]">
                        <div class="text-sm font-bold text-gray-800">{{ $training->start_date->format('d.m.') }}</div>
                        @if($training->end_date->gt($training->start_date))
                            <div class="text-xs text-gray-500">– {{ $training->end_date->format('d.m.') }}</div>
                        @endif
                    </div>
                    <div class="flex-1 min-w-0">
                        <div class="text-sm text-gray-800 font-medium truncate">{{ $training->title }}</div>
                        @if($training->location)
                            <div class="text-xs text-gray-500 truncate">
                                <i class="fas fa-map-marker-alt mr-1"></i>{{ $training->location }}
                            </div>
                        @endif
                    </div>
                </div>
            @endforeach
        </div>
    @endif
@endif

{{-- Footer --}}
<div class="px-4 py-3 border-t border-gray-100 flex items-center justify-between">
    @can('view qualifications')
        <a href="{{ url('personal/qualifications') }}"
           class="text-sm text-blue-600 hover:text-blue-800 no-underline font-medium">
            Qualifikationen →
        </a>
    @endcan
    @can('view trainings')
        <a href="{{ url('personal/trainings') }}"
           class="text-sm text-blue-600 hover:text-blue-800 no-underline font-medium">
            Fortbildungen →
        </a>
    @endcan
</div>

