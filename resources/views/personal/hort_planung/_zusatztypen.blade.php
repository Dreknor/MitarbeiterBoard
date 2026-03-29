{{-- Partial: Zusatzstunden-Typen-Verwaltung --}}
{{-- Wird eingebunden in: edit.blade.php --}}

<div class="bg-white rounded-2xl border border-gray-200 shadow-sm overflow-hidden"
     x-data="{ showAddForm: false }">

    <div class="px-5 py-3.5 border-b border-gray-100 bg-gray-50 flex items-center justify-between">
        <div>
            <h2 class="text-sm font-semibold text-gray-700 uppercase tracking-wide">Zusatzstunden-Kategorien</h2>
            <p class="text-xs text-gray-400 mt-0.5">Monatliche Zusatzstunden (Weg, Beratung, Sonstiges …)</p>
        </div>
        <button @click="showAddForm = !showAddForm"
                class="inline-flex items-center gap-1 px-3 py-1.5 bg-blue-600 hover:bg-blue-700 text-white text-xs font-medium rounded-lg">
            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
            </svg>
            Kategorie hinzufügen
        </button>
    </div>

    <div class="divide-y divide-gray-100">
        @forelse($planung->zusatzstundenTypen->sortBy('position') as $typ)
        <div class="flex items-center justify-between gap-3 px-5 py-3 @if(!$typ->aktiv) opacity-50 @endif">
            <div class="flex items-center gap-2.5 min-w-0">
                <span class="text-xs font-mono text-gray-400 w-5 text-right shrink-0">{{ $typ->position }}</span>
                <div>
                    <p class="text-sm font-medium text-gray-800">{{ $typ->bezeichnung }}</p>
                    <p class="text-xs font-mono text-gray-400">{{ $typ->kuerzel }}</p>
                </div>
                @if(!$typ->aktiv)
                    <span class="text-xs bg-gray-100 text-gray-500 px-2 py-0.5 rounded-full">deaktiviert</span>
                @endif
            </div>
            @if($typ->aktiv)
            <form action="{{ route('hort-planung.deleteZusatzTyp', [$planung, $typ]) }}" method="POST"
                  onsubmit="return confirm('Typ deaktivieren?')">
                @csrf @method('DELETE')
                <button type="submit"
                        class="px-2 py-1 text-xs bg-gray-100 hover:bg-red-50 hover:text-red-600 text-gray-500 rounded-lg">
                    Deaktivieren
                </button>
            </form>
            @endif
        </div>
        @empty
        <p class="p-5 text-sm text-gray-400 text-center">Noch keine Kategorien definiert.</p>
        @endforelse
    </div>

    {{-- Neue Kategorie hinzufügen --}}
    <div x-show="showAddForm" x-cloak
         class="border-t border-gray-100 p-5 bg-gray-50">
        <h3 class="text-sm font-semibold text-gray-700 mb-3">Neue Kategorie anlegen</h3>
        <form action="{{ route('hort-planung.storeZusatzTyp', $planung) }}" method="POST" class="space-y-3">
            @csrf
            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">Kürzel <span class="text-red-500">*</span></label>
                    <input type="text" name="kuerzel" placeholder="z. B. inklusion"
                           class="w-full border border-gray-300 rounded-lg px-2.5 py-1.5 text-sm focus:ring-1 focus:ring-blue-500 outline-none font-mono"
                           pattern="[a-zA-Z0-9_\-]+" required>
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">Bezeichnung <span class="text-red-500">*</span></label>
                    <input type="text" name="bezeichnung" placeholder="z. B. Inklusionsstunden"
                           class="w-full border border-gray-300 rounded-lg px-2.5 py-1.5 text-sm focus:ring-1 focus:ring-blue-500 outline-none" required>
                </div>
            </div>
            <div class="flex gap-2">
                <button type="submit"
                        class="px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white text-xs font-semibold rounded-lg">
                    Kategorie anlegen
                </button>
                <button type="button" @click="showAddForm = false"
                        class="px-4 py-2 bg-white border border-gray-300 text-gray-600 text-xs font-medium rounded-lg hover:bg-gray-50">
                    Abbrechen
                </button>
            </div>
        </form>
    </div>

    <div class="px-5 py-2.5 bg-gray-50 border-t border-gray-100">
        <p class="text-xs text-gray-400">
            ℹ Werte werden monatlich in der Matrix-Ansicht gepflegt. Kategorien können jederzeit hinzugefügt oder deaktiviert werden.
        </p>
    </div>
</div>

