@php
    $posId    = $position['id'];
    $hasKids  = !empty($position['children']);
    $color    = $position['color'] ?? null;
    $isLead   = $position['leadership'] ?? false;
@endphp

{{-- Nur anzeigen wenn Suche passt (oder leer) – Filterung via Alpine.js x-show --}}
<div class="flex flex-col items-center" x-show="!search || matchesSearch({{ json_encode($position) }}, search)">

    {{-- Positions-Karte --}}
    <div class="relative cursor-pointer group"
         @click="selectPosition({{ json_encode($position) }})">
        <div class="rounded-xl shadow-md px-4 py-3 min-w-[140px] max-w-[180px] text-center transition-all border-2 select-none"
             :class="selectedPosition && selectedPosition.id === {{ $posId }}
                 ? 'border-blue-500 bg-blue-50 shadow-lg'
                 : 'border-gray-200 bg-white hover:border-gray-400 hover:shadow-md'"
             @if($color) style="border-color: {{ $color }};" @endif>

            @if($isLead)
            <div class="text-xs text-yellow-600 font-semibold mb-1">★ Leitung</div>
            @endif

            <p class="font-semibold text-sm text-gray-900 leading-tight">{{ $position['name'] }}</p>

            {{-- Stelleninhaber --}}
            @foreach($position['users'] as $user)
            <div class="mt-1 text-xs text-gray-600">{{ $user['name'] }}</div>
            @endforeach

            {{-- Stellvertreter --}}
            @if(!empty($position['deputy']))
            <div class="mt-1 text-xs text-gray-400">Stv.: {{ $position['deputy'][0]['name'] }}</div>
            @endif

            @if(empty($position['users']))
            <div class="mt-1 text-xs text-gray-300 italic">unbesetzt</div>
            @endif
        </div>

        {{-- Expand/Collapse Button --}}
        @if($hasKids)
        <button @click.stop="toggle({{ $posId }})"
                class="absolute -bottom-3 left-1/2 -translate-x-1/2 w-6 h-6 bg-white border border-gray-300 rounded-full text-xs flex items-center justify-center hover:bg-gray-100 shadow z-10">
            <span x-text="isExpanded({{ $posId }}) ? '−' : '+'">+</span>
        </button>
        @endif
    </div>

    {{-- Verbindungslinie nach unten --}}
    @if($hasKids)
    <div class="w-px h-6 bg-gray-300 mt-3" x-show="isExpanded({{ $posId }})"></div>
    @endif

    {{-- Kinder-Ebene --}}
    @if($hasKids)
    <div x-show="isExpanded({{ $posId }})"
         x-transition:enter="transition ease-out duration-200"
         x-transition:enter-start="opacity-0 -translate-y-2"
         x-transition:enter-end="opacity-100 translate-y-0"
         class="flex gap-6 relative">

        {{-- Horizontale Verbindungslinie über Kinder --}}
        @if(count($position['children']) > 1)
        <div class="absolute top-0 left-8 right-8 h-px bg-gray-300"></div>
        @endif

        @foreach($position['children'] as $child)
        <div class="flex flex-col items-center">
            {{-- Vertikale Linie von oben zum Kind --}}
            <div class="w-px h-6 bg-gray-300"></div>
            @include('personal.orgchart._node', ['position' => $child])
        </div>
        @endforeach
    </div>
    @endif

</div>

