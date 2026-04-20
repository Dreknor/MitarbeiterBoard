<div class="bg-white rounded-2xl border border-gray-200 shadow-sm overflow-hidden">

    {{-- Drag-Handle (nur im Edit-Modus) --}}
    <div x-show="editMode"
         x-cloak
         class="flex items-center justify-between px-4 py-2 bg-gray-50 border-b border-gray-100">
        <div class="drag-handle flex items-center gap-2 cursor-grab text-gray-400 hover:text-gray-600 select-none">
            <i class="fas fa-grip-vertical"></i>
            <span class="text-xs text-gray-400">Verschieben</span>
        </div>
        {{-- Resize-Dropdown --}}
        <select
            class="text-xs border border-gray-200 rounded-lg px-2 py-1 text-gray-600 bg-white"
            @change="$dispatch('resize-card', { id: {{ $card->id }}, width: $event.target.value })"
            :value="(cards.find(c => c.id === {{ $card->id }}) || {}).width || 'md'">
            <option value="sm">Klein</option>
            <option value="md">Mittel</option>
            <option value="lg">Groß</option>
            <option value="full">Volle Breite</option>
        </select>
    </div>

    {{-- Card-Header (immer sichtbar) --}}
    <div class="flex items-center justify-between px-4 py-3 bg-gradient-to-r from-blue-600 to-blue-500 text-white">
        <div class="flex items-center gap-2">
            @if($card->dashboardCard->icon ?? null)
                <i class="{{ $card->dashboardCard->icon }} text-sm opacity-80"></i>
            @endif
            <span class="font-semibold text-sm leading-none !text-white m-0 p-0">{{ $card->dashboardCard->title ?? '' }}</span>
        </div>
        <button @click="$dispatch('toggle-card', { id: {{ $card->id }} })"
                class="!text-white !bg-transparent border-0 p-0 opacity-60 hover:opacity-100 text-base leading-none cursor-pointer"
                title="Ausblenden">
            ✕
        </button>
    </div>

    {{-- Card-Body: Skeleton → Inhalt → Fehler --}}
    <div :class="{ 'opacity-50 pointer-events-none': editMode }">

        {{-- Skeleton (solange nicht geladen) --}}
        <div x-show="!loaded" x-cloak>
            @php $skeletonType = $card->dashboardCard->skeleton ?? 'default'; @endphp
            @include('dashboard.skeletons.' . $skeletonType)
        </div>

        {{-- Geladener Inhalt --}}
        <div x-show="loaded && !error" x-html="html"></div>

        {{-- Fehler-Template --}}
        <div x-show="loaded && error" x-cloak>
            <div class="px-4 py-8 text-center text-gray-400 text-sm">
                <svg class="w-8 h-8 mx-auto mb-2 opacity-40" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                          d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                </svg>
                <p>Inhalt konnte nicht geladen werden.</p>
            </div>
        </div>

    </div>
</div>

