{{--
    Modal: Snapshot erstellen
    ─────────────────────────────────────────────────────────────────────
    Alpine: showSnapshotModal (Boolean), snapshotName (String)
    POST → hort-planung.snapshot
--}}
@can('manage hort planung')
<div x-show="showSnapshotModal"
     x-cloak
     @keydown.escape.window="showSnapshotModal = false"
     class="hp-modal-overlay"
     @click.self="showSnapshotModal = false">

    <div class="hp-modal-box" @click.stop>

        {{-- Header --}}
        <div class="flex items-center justify-between px-5 py-4 border-b border-gray-100 bg-gray-50">
            <h3 class="text-sm font-semibold text-gray-800 flex items-center gap-2">
                <svg class="w-4 h-4 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                          d="M3 9a2 2 0 012-2h.93a2 2 0 001.664-.89l.812-1.22A2 2 0 0110.07 4h3.86a2 2 0 011.664.89l.812 1.22A2 2 0 0018.07 7H19a2 2 0 012 2v9a2 2 0 01-2 2H5a2 2 0 01-2-2V9z"/>
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                          d="M15 13a3 3 0 11-6 0 3 3 0 016 0z"/>
                </svg>
                Snapshot erstellen
            </h3>
            <button @click="showSnapshotModal = false" class="text-gray-400 hover:text-gray-600">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                </svg>
            </button>
        </div>

        {{-- Form --}}
        <form action="{{ route('hort-planung.snapshot', $planung) }}" method="POST" class="p-5 space-y-4">
            @csrf

            <p class="text-sm text-gray-500">
                Friert den <strong class="text-gray-700">aktuellen Stand</strong> der Planung
                <em>{{ $planung->name }}</em> als unveränderlichen JSON-Snapshot ein.
                Nützlich vor Verhandlungen, Abgaben oder Planungsänderungen.
            </p>

            {{-- Name --}}
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">
                    Snapshot-Name <span class="text-red-500">*</span>
                </label>
                <input type="text" name="name" required maxlength="255"
                       x-model="snapshotName"
                       placeholder="z.B. Stand vor Nachverhandlung, Abgabe Stadt Q2 2026"
                       class="w-full border border-gray-300 rounded-xl px-3 py-2 text-sm
                              focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 outline-none">
                <p class="text-[10px] text-gray-400 mt-1">
                    Aktuelles Datum: {{ now()->format('d.m.Y, H:i') }} Uhr –
                    wird automatisch mit gespeichert.
                </p>
            </div>

            {{-- Info-Box --}}
            <div class="p-3 bg-indigo-50 border border-indigo-200 rounded-xl text-xs text-indigo-700 flex gap-2">
                <svg class="w-4 h-4 shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                          d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                </svg>
                Der Snapshot enthält alle Monate, Personen und berechneten Werte zum Zeitpunkt der Erstellung.
                Er kann nicht bearbeitet werden, aber als Vergleichsbasis exportiert werden.
            </div>

            {{-- Vorschlag mit Datum --}}
            <div>
                <button type="button"
                        @click="snapshotName = 'Stand {{ now()->format('d.m.Y') }}'"
                        class="text-xs text-indigo-600 hover:underline">
                    ← Automatischer Name vorschlagen
                </button>
            </div>

            {{-- Aktionen --}}
            <div class="flex gap-3 pt-1">
                <button type="submit"
                        :disabled="!snapshotName.trim()"
                        class="flex-1 py-2.5 bg-indigo-600 hover:bg-indigo-700 disabled:opacity-40 disabled:cursor-not-allowed
                               text-white font-semibold rounded-xl text-sm">
                    Snapshot erstellen
                </button>
                <button type="button" @click="showSnapshotModal = false"
                        class="px-4 py-2.5 bg-white border border-gray-300 hover:bg-gray-50 text-gray-700
                               font-medium rounded-xl text-sm">
                    Abbrechen
                </button>
            </div>
        </form>
    </div>
</div>
@endcan

