{{-- Inline-Formular zum Hinzufügen einer neuen Aufgabe --}}
@canany(['create wochenplan', 'create Wochenplan'])
<div x-data="aufgabeForm()" class="mt-2">

    {{-- Hinzufügen-Button --}}
    <button type="button" @click="startAdd()" x-show="!adding"
            class="inline-flex items-center gap-1 text-xs text-primary-600 hover:text-primary-700 font-medium py-1">
        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
        </svg>
        Aufgabe hinzufügen
    </button>

    {{-- Inline-Formular --}}
    <div x-show="adding" x-cloak class="border border-primary-200 rounded-lg p-3 bg-primary-50">
        <form method="POST" action="{{ route('wp.aufgabe.store', $planFach) }}">
            @csrf
            <div class="space-y-2">
                <input type="text" name="aufgabe" x-model="aufgabe" x-ref="newInput" required
                       placeholder="Aufgabentext eingeben..."
                       class="w-full px-3 py-2 border border-gray-300 rounded-md text-sm focus:outline-none focus:ring-2 focus:ring-primary-500"
                       @keydown.escape="cancel()"
                       @keydown.enter.prevent="$el.closest('form').submit()">
                <div class="flex items-center gap-2">
                    <input type="text" name="dauer" x-model="dauer" placeholder="Dauer (optional)"
                           class="w-36 px-2 py-1.5 border border-gray-300 rounded-md text-xs focus:outline-none focus:ring-2 focus:ring-primary-500">
                    <button type="submit"
                            class="px-3 py-1.5 bg-primary-600 text-white text-xs font-medium rounded-md hover:bg-primary-700">
                        Speichern
                    </button>
                    <button type="button" @click="cancel()"
                            class="px-3 py-1.5 bg-white text-gray-600 text-xs font-medium rounded-md border border-gray-300 hover:bg-gray-50">
                        Abbrechen
                    </button>
                </div>
            </div>
        </form>
    </div>

</div>
@endcanany

