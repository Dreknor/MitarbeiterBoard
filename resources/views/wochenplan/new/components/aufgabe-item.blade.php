{{-- Einzelne Aufgabe mit Inline-Edit --}}
<div x-data="aufgabeForm()" class="group flex items-start gap-2 py-2 border-b border-gray-100 last:border-0"
     draggable="true" data-id="{{ $aufgabe->id }}">

    {{-- Anzeige-Modus --}}
    <div x-show="!editing" class="flex-1 flex items-start justify-between gap-2">
        <div class="flex-1">
            <span class="text-sm text-gray-800">{{ $aufgabe->aufgabe }}</span>
            @if($aufgabe->dauer)
                <span class="ml-2 text-xs text-gray-400 italic">({{ $aufgabe->dauer }})</span>
            @endif
            @if($aufgabe->isSynced())
                <span class="ml-1 inline-flex items-center px-1.5 py-0.5 rounded text-xs bg-blue-100 text-blue-700"
                      title="Synchronisiert vom Klassenplan">🔗</span>
            @endif
        </div>
        @canany(['create wochenplan', 'create Wochenplan'])
            <div class="flex items-center gap-1 opacity-0 group-hover:opacity-100 transition-opacity flex-shrink-0">
                <button type="button" @click="startEdit('{{ addslashes($aufgabe->aufgabe) }}', '{{ $aufgabe->dauer }}')"
                        class="p-1 text-gray-400 hover:text-primary-600 transition-colors" title="Bearbeiten">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"/>
                    </svg>
                </button>
                <form method="POST" action="{{ route('wp.aufgabe.destroy', $aufgabe) }}"
                      onsubmit="return confirm('Aufgabe wirklich löschen?')" class="inline">
                    @csrf @method('DELETE')
                    <button type="submit" class="p-1 text-gray-400 hover:text-red-600 transition-colors" title="Löschen">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                        </svg>
                    </button>
                </form>
            </div>
        @endcanany
    </div>

    {{-- Edit-Modus --}}
    @canany(['create wochenplan', 'create Wochenplan'])
        <div x-show="editing" class="flex-1" x-cloak>
            <form method="POST" action="{{ route('wp.aufgabe.update', $aufgabe) }}">
                @csrf @method('PUT')
                <div class="space-y-2">
                    <input type="text" name="aufgabe" x-model="aufgabe" x-ref="input" required
                           class="w-full px-2 py-1 border border-primary-400 rounded text-sm focus:outline-none focus:ring-2 focus:ring-primary-500"
                           @keydown.escape="cancel()">
                    <div class="flex items-center gap-2">
                        <input type="text" name="dauer" x-model="dauer" placeholder="Dauer (optional)"
                               class="w-32 px-2 py-1 border border-gray-300 rounded text-xs focus:outline-none focus:ring-2 focus:ring-primary-500">
                        <button type="submit"
                                class="px-3 py-1 bg-primary-600 text-white text-xs rounded hover:bg-primary-700">
                            Speichern
                        </button>
                        <button type="button" @click="cancel()"
                                class="px-3 py-1 bg-gray-100 text-gray-600 text-xs rounded hover:bg-gray-200">
                            Abbrechen
                        </button>
                    </div>
                </div>
            </form>
        </div>
    @endcanany

</div>

