{{-- Plan-Karte für die Übersicht --}}
<div class="bg-white rounded-lg shadow-sm border border-gray-200 p-4 flex flex-col gap-3 hover:shadow-md transition-shadow">

    {{-- Typ-Badge + Titel --}}
    <div class="flex items-start justify-between gap-2">
        <div>
            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium
                {{ $plan->is_vorlage ? 'bg-purple-100 text-purple-700' : ($plan->isSchuelerplan() ? 'bg-blue-100 text-blue-700' : 'bg-green-100 text-green-700') }}">
                {{ $plan->typ }}
            </span>
            <h3 class="font-semibold text-gray-900 text-sm mt-1 leading-tight">{{ $plan->name }}</h3>
        </div>
    </div>

    {{-- Meta-Infos --}}
    <div class="text-xs text-gray-500 space-y-0.5">
        @if($plan->klasse)
            <div class="flex items-center gap-1">
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"/>
                </svg>
                {{ $plan->klasse->name }}
            </div>
        @endif
        @if($plan->schueler)
            <div class="flex items-center gap-1">
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>
                </svg>
                {{ $plan->schueler->vorname }} {{ $plan->schueler->nachname }}
            </div>
            @if($plan->parentPlan)
                <div class="flex items-center gap-1 text-gray-400">
                    <span>↑</span>
                    <a href="{{ route('wp.edit', $plan->parentPlan) }}" class="hover:text-primary-600 hover:underline">
                        {{ $plan->parentPlan->name }}
                    </a>
                </div>
            @endif
        @endif
        @if($plan->gueltig_von && $plan->gueltig_bis)
            <div class="flex items-center gap-1">
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                </svg>
                {{ $plan->zeitraum }}
            </div>
        @endif
    </div>

    {{-- Statistiken --}}
    <div class="flex gap-3 text-xs text-gray-400">
        <span>{{ $plan->planFaecher->count() }} Fächer</span>
        @if($plan->kinderPlaene->count() > 0)
            <span>{{ $plan->kinderPlaene->count() }} Kinderpläne</span>
        @endif
    </div>

    {{-- Aktions-Buttons --}}
    <div class="flex items-center gap-2 mt-auto pt-2 border-t border-gray-100">
        @if($plan->is_vorlage)
            @canany(['create wochenplan', 'create Wochenplan'])
            <a href="{{ route('wp.create') }}?vorlage_id={{ $plan->id }}"
               class="flex-1 text-center px-3 py-1.5 bg-green-50 text-green-700 text-xs font-medium rounded-md hover:bg-green-100 transition-colors">
                + Plan erstellen
            </a>
            @endcanany
        @else
            @canany(['create wochenplan', 'create Wochenplan'])
            <a href="{{ route('wp.edit', $plan) }}"
               class="flex-1 text-center px-3 py-1.5 bg-primary-50 text-primary-700 text-xs font-medium rounded-md hover:bg-primary-100 transition-colors">
                Bearbeiten
            </a>
            @endcanany
        @endif
        <a href="{{ route('wp.export.vorschau', $plan) }}" target="_blank"
           class="px-3 py-1.5 bg-gray-50 text-gray-600 text-xs font-medium rounded-md hover:bg-gray-100 transition-colors">
            Vorschau
        </a>
        <a href="{{ route('wp.export.pdf', $plan) }}" target="_blank"
           class="px-3 py-1.5 bg-red-50 text-red-600 text-xs font-medium rounded-md hover:bg-red-100 transition-colors">
            PDF
        </a>
    </div>
</div>

