{{--
    Modal: Stunden ab Monat X ändern (Bulk-Update)
    ─────────────────────────────────────────────────────────────────────
    Alpine: showBulkModal (Boolean), bulkUserId (int), bulkUserName (String)
    POST → hort-planung.bulkUpdatePerson
--}}
@can('manage hort planung')
<div x-show="showBulkModal"
     x-cloak
     @keydown.escape.window="showBulkModal = false"
     class="hp-modal-overlay"
     @click.self="showBulkModal = false">

    <div class="hp-modal-box" @click.stop>

        {{-- Header --}}
        <div class="flex items-center justify-between px-5 py-4 border-b border-gray-100 bg-gray-50">
            <h3 class="text-sm font-semibold text-gray-800 flex items-center gap-2">
                <svg class="w-4 h-4 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                          d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                </svg>
                Stunden ab Monat ändern
            </h3>
            <button @click="showBulkModal = false" class="text-gray-400 hover:text-gray-600">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                </svg>
            </button>
        </div>

        {{-- Beschreibungstext --}}
        <div class="px-5 pt-4 pb-2">
            <p class="text-sm text-gray-600">
                Setzt die Stunden von
                <strong x-text="bulkUserName" class="text-gray-800"></strong>
                ab dem gewählten Monat für alle Folgemonate.
            </p>
            <div class="mt-2 p-3 bg-amber-50 border border-amber-200 rounded-xl text-xs text-amber-700 flex gap-2">
                <svg class="w-4 h-4 shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                          d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                </svg>
                Vorhandene Werte ab dem gewählten Monat werden überschrieben. Felder leer lassen = kein Update für dieses Feld.
            </div>
        </div>

        {{-- Form --}}
        <form :action="`/hort-planung/{{ $planung->id }}/person/${bulkUserId}/bulk`"
              method="POST"
              class="px-5 pb-5 space-y-4">
            @csrf @method('PUT')

            {{-- Ab Monat --}}
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">
                    Ab Monat <span class="text-red-500">*</span>
                </label>
                <select name="ab_monat" required x-model="bulkAbMonat"
                        class="w-full border border-gray-300 rounded-xl px-3 py-2 text-sm
                               focus:ring-2 focus:ring-blue-500 outline-none">
                    <option value="">– Monat wählen –</option>
                    @foreach($monatsListe as $m)
                    <option value="{{ $m->monat->format('Y-m-d') }}">
                        {{ $m->monat->locale('de')->isoFormat('MMMM YYYY') }}
                    </option>
                    @endforeach
                </select>
            </div>

            <div class="grid grid-cols-2 gap-4">
                {{-- SP1: Stunden gesamt --}}
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">
                        SP1 – Stunden gesamt
                        <span class="text-gray-400 font-normal text-xs">(Verein, optional)</span>
                    </label>
                    <input type="number" name="stunden_gesamt" step="0.5" min="0"
                           :value="bulkSp1"
                           placeholder="z.B. 37,5"
                           class="w-full border border-gray-300 rounded-xl px-3 py-2 text-sm
                                  focus:ring-2 focus:ring-blue-500 outline-none">
                </div>

                {{-- SP2: Stunden Stadt --}}
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">
                        SP2 – Stunden Stadt
                        <span class="text-gray-400 font-normal text-xs">(Abrechnung, optional)</span>
                    </label>
                    <input type="number" name="stunden_stadt" step="0.5" min="0"
                           :value="bulkSp2"
                           placeholder="z.B. 30"
                           class="w-full border border-gray-300 rounded-xl px-3 py-2 text-sm
                                  focus:ring-2 focus:ring-blue-500 outline-none">
                </div>
            </div>

            {{-- Kommentar --}}
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">
                    Kommentar <span class="text-gray-400 font-normal text-xs">(optional)</span>
                </label>
                <input type="text" name="kommentar" maxlength="255"
                       placeholder="z.B. Änderungsvertrag ab 01.04.2026"
                       class="w-full border border-gray-300 rounded-xl px-3 py-2 text-sm
                              focus:ring-2 focus:ring-blue-500 outline-none">
            </div>

            {{-- Aktionen --}}
            <div class="flex gap-3 pt-1">
                <button type="submit"
                        class="flex-1 py-2.5 bg-blue-600 hover:bg-blue-700 text-white font-semibold
                               rounded-xl text-sm">
                    Stunden setzen
                </button>
                <button type="button" @click="showBulkModal = false"
                        class="px-4 py-2.5 bg-white border border-gray-300 hover:bg-gray-50 text-gray-700
                               font-medium rounded-xl text-sm">
                    Abbrechen
                </button>
            </div>
        </form>
    </div>
</div>
@endcan

