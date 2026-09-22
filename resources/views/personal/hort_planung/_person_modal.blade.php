{{--
    Modal: Person zur Planung hinzufügen
    ─────────────────────────────────────────────────────────────────────
    Erwartet: $planung, $alleNutzer (Collection<{id, name}>)
    Alpine: showPersonModal (Boolean) im x-data des Parent-Components
--}}
@can('manage hort planung')
<div x-show="showPersonModal"
     x-cloak
     @keydown.escape.window="showPersonModal = false"
     class="hp-modal-overlay"
     @click.self="showPersonModal = false">

    <div class="hp-modal-box" @click.stop>

        {{-- Header --}}
        <div class="flex items-center justify-between px-5 py-4 border-b border-gray-100 bg-gray-50">
            <h3 class="text-sm font-semibold text-gray-800 flex items-center gap-2">
                <svg class="w-4 h-4 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                          d="M18 9v3m0 0v3m0-3h3m-3 0h-3m-2-5a4 4 0 11-8 0 4 4 0 018 0zM3 20a6 6 0 0112 0v1H3v-1z"/>
                </svg>
                Person zur Planung hinzufügen
            </h3>
            <button @click="showPersonModal = false" class="text-gray-400 hover:text-gray-600">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                </svg>
            </button>
        </div>

        {{-- Form --}}
        <form method="POST" action="{{ route('hort-planung.addPerson', $planung) }}" class="p-5 space-y-4">
            @csrf

            <p class="text-sm text-gray-500">
                Die Person wird für <strong class="text-gray-700">alle {{ $monatsListe->count() }} Monate</strong>
                der Planung angelegt (mit leeren Stundenwerten, die danach ausgefüllt werden können).
            </p>

            {{-- Personen-Auswahl --}}
            <div x-data="{ search: '', selected: '' }">
                <label class="block text-sm font-medium text-gray-700 mb-1">
                    Mitarbeiter wählen <span class="text-red-500">*</span>
                </label>

                {{-- Such-Filter --}}
                <input type="text" x-model="search" placeholder="Namen filtern…"
                       class="w-full border border-gray-300 rounded-xl px-3 py-2 text-sm mb-2
                              focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none">

                <select name="user_id" required
                        class="w-full border border-gray-300 rounded-xl px-3 py-2 text-sm
                               focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none"
                        size="8">
                    @foreach($alleNutzer as $nutzer)
                    <option value="{{ $nutzer->id }}"
                            x-show="!search || '{{ strtolower($nutzer->name) }}'.includes(search.toLowerCase())">
                        {{ $nutzer->name }}
                    </option>
                    @endforeach
                </select>
                <p class="text-[10px] text-gray-400 mt-1">Mehrfachauswahl nicht möglich – beim nächsten Öffnen erneut auswählen.</p>
            </div>

            {{-- Aktionen --}}
            <div class="flex gap-3 pt-1">
                <button type="submit"
                        class="flex-1 py-2.5 bg-emerald-600 hover:bg-emerald-700 text-white font-semibold
                               rounded-xl text-sm transition-colors">
                    Person hinzufügen
                </button>
                <button type="button" @click="showPersonModal = false"
                        class="px-4 py-2.5 bg-white border border-gray-300 hover:bg-gray-50 text-gray-700
                               font-medium rounded-xl text-sm">
                    Abbrechen
                </button>
            </div>
        </form>
    </div>
</div>
@endcan

