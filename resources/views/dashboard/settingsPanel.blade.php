{{-- Einstellungs-Panel (Slide-in von rechts) --}}
<div x-show="showSettings"
     x-cloak
     class="fixed inset-0 z-50 flex justify-end"
     @keydown.escape.window="showSettings = false">

    {{-- Overlay --}}
    <div class="absolute inset-0 bg-black/30"
         @click="showSettings = false"></div>

    {{-- Panel --}}
    <div class="relative bg-white shadow-xl max-w-sm w-full h-full overflow-y-auto flex flex-col"
         x-transition:enter="transition ease-out duration-300"
         x-transition:enter-start="translate-x-full"
         x-transition:enter-end="translate-x-0"
         x-transition:leave="transition ease-in duration-200"
         x-transition:leave-start="translate-x-0"
         x-transition:leave-end="translate-x-full">

        {{-- Panel-Header --}}
        <div class="flex items-center justify-between px-4 py-4 border-b border-gray-100">
            <div class="flex items-center gap-2">
                <i class="fas fa-cog text-blue-600"></i>
                <h3 class="font-semibold text-gray-800">Dashboard einrichten</h3>
            </div>
            <button @click="showSettings = false"
                    class="text-gray-400 hover:text-gray-600 text-lg leading-none">
                ✕
            </button>
        </div>

        {{-- Card-Liste --}}
        <div class="flex-1 p-4 space-y-2">
            <p class="text-xs text-gray-500 mb-3">Karten ein-/ausblenden und Größe anpassen.</p>

            <template x-for="card in cards" :key="card.id">
                <div class="bg-gray-50 rounded-xl p-3 flex items-center gap-3">
                    {{-- Checkbox --}}
                    <input type="checkbox"
                           :checked="card.active"
                           @change="toggleCard(card.id)"
                           class="w-4 h-4 rounded text-blue-600">

                    {{-- Label --}}
                    <span class="flex-1 text-sm text-gray-700 font-medium" x-text="card.title"></span>

                    {{-- Größen-Select --}}
                    <select x-model="card.width"
                            @change="resizeCard(card.id, card.width)"
                            class="text-xs border border-gray-200 rounded-lg px-2 py-1 text-gray-600 bg-white">
                        <option value="sm">Klein</option>
                        <option value="md">Mittel</option>
                        <option value="lg">Groß</option>
                        <option value="full">Volle Breite</option>
                    </select>
                </div>
            </template>
        </div>

        {{-- Panel-Footer --}}
        <div class="p-4 border-t border-gray-100 space-y-2">
            <button @click="saveLayout()"
                    class="w-full flex items-center justify-center gap-2 px-4 py-2.5 bg-blue-600 text-white rounded-lg text-sm font-medium hover:bg-blue-700">
                <i class="fas fa-save"></i>
                Speichern
            </button>
            <button @click="resetLayout()"
                    class="w-full flex items-center justify-center gap-2 px-4 py-2.5 text-red-600 hover:bg-red-50 rounded-lg text-sm font-medium">
                <i class="fas fa-undo"></i>
                Layout zurücksetzen
            </button>
        </div>

    </div>
</div>

