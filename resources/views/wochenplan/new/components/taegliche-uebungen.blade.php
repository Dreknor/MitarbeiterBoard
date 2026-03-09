{{--
    Komponente: taeglich-uebungen.blade.php
    Zeigt den optionalen Bereich "Tägliche Übungen" oberhalb der Fachspezifischen Aufgaben.
    Variablen: $wpPlan (WpPlan)
--}}
@php
    // Berechne die Wochentage im Planungszeitraum (Mo–Fr)
    $wochentage = [];
    if ($wpPlan->gueltig_von && $wpPlan->gueltig_bis) {
        $current = $wpPlan->gueltig_von->copy();
        while ($current->lte($wpPlan->gueltig_bis)) {
            if ($current->isWeekday()) {
                $wochentage[] = $current->copy();
            }
            $current->addDay();
        }
    }
    $tagNamen = ['Mo', 'Di', 'Mi', 'Do', 'Fr'];
@endphp

<div class="bg-white rounded-lg border border-blue-200 mb-4"
     x-data="{ addingNew: false, newText: '' }">

    {{-- Header --}}
    <div class="flex items-center justify-between px-4 py-3 border-b border-blue-100 bg-blue-50 rounded-t-lg">
        <div class="flex items-center gap-2">
            <span class="text-blue-600 font-medium text-sm">✏️ Tägliche Übungen</span>
            @if($wpPlan->taeglicheUebungen->isEmpty())
                <span class="text-xs text-blue-400">(noch keine Übungen hinterlegt)</span>
            @endif
        </div>
        @canany(['create wochenplan', 'create Wochenplan'])
            <div class="flex items-center gap-2">
                <button type="button"
                        @click="addingNew = !addingNew"
                        class="inline-flex items-center px-2 py-1 text-xs font-medium text-blue-700 bg-blue-100 rounded-md hover:bg-blue-200">
                    + Übung hinzufügen
                </button>
                {{-- Bereich deaktivieren --}}
                <form method="POST" action="{{ route('wp.taegliche-uebungen.toggle', $wpPlan) }}">
                    @csrf
                    <button type="submit"
                            class="inline-flex items-center px-2 py-1 text-xs font-medium text-gray-500 bg-white border border-gray-300 rounded-md hover:bg-gray-50"
                            title="Tägliche Übungen ausblenden">
                        ✕ Ausblenden
                    </button>
                </form>
            </div>
        @endcanany
    </div>

    <div class="p-4">

        {{-- Neue Übung hinzufügen --}}
        @canany(['create wochenplan', 'create Wochenplan'])
            <div x-show="addingNew" x-cloak class="mb-4">
                <form method="POST" action="{{ route('wp.taegliche-uebungen.store', $wpPlan) }}"
                      @submit.prevent="$el.submit(); addingNew = false; newText = ''">
                    @csrf
                    <div class="flex gap-2">
                        <input type="text"
                               name="aufgabe"
                               x-model="newText"
                               placeholder="z.B. 10 min lesen, 5 min Kopfrechnen…"
                               required
                               class="flex-1 px-3 py-1.5 border border-blue-300 rounded-md text-sm focus:outline-none focus:ring-2 focus:ring-blue-400">
                        <button type="submit"
                                class="px-3 py-1.5 bg-blue-600 text-white text-sm font-medium rounded-md hover:bg-blue-700">
                            Speichern
                        </button>
                        <button type="button" @click="addingNew = false; newText = ''"
                                class="px-3 py-1.5 bg-gray-100 text-gray-600 text-sm font-medium rounded-md hover:bg-gray-200">
                            Abbrechen
                        </button>
                    </div>
                </form>
            </div>
        @endcanany

        @if($wpPlan->taeglicheUebungen->isNotEmpty())

            {{-- Aufgaben-Liste --}}
            <div class="space-y-2 mb-4">
                @foreach($wpPlan->taeglicheUebungen as $uebung)
                    <div x-data="{ editing: false, text: @js($uebung->aufgabe) }" class="group">

                        {{-- Anzeige-Modus --}}
                        <div x-show="!editing" class="flex items-center gap-2">
                            <span class="text-gray-400 text-xs w-4 flex-shrink-0">{{ $loop->iteration }}.</span>
                            <span class="text-sm text-gray-800 flex-1" x-text="text"></span>
                            @canany(['create wochenplan', 'create Wochenplan'])
                                <div class="flex gap-1 opacity-0 group-hover:opacity-100 transition-opacity flex-shrink-0">
                                    <button type="button" @click="editing = true"
                                            class="text-xs text-blue-600 hover:text-blue-800 px-1.5 py-0.5 rounded hover:bg-blue-50">
                                        ✏️
                                    </button>
                                    <form method="POST" action="{{ route('wp.taegliche-uebungen.destroy', $uebung) }}"
                                          onsubmit="return confirm('Übung wirklich löschen?')">
                                        @csrf @method('DELETE')
                                        <button type="submit"
                                                class="text-xs text-red-500 hover:text-red-700 px-1.5 py-0.5 rounded hover:bg-red-50">
                                            🗑️
                                        </button>
                                    </form>
                                </div>
                            @endcanany
                        </div>

                        {{-- Bearbeitungs-Modus --}}
                        @canany(['create wochenplan', 'create Wochenplan'])
                            <div x-show="editing" x-cloak>
                                <form method="POST" action="{{ route('wp.taegliche-uebungen.update', $uebung) }}"
                                      @submit.prevent="$el.submit(); editing = false">
                                    @csrf @method('PUT')
                                    <div class="flex gap-2">
                                        <input type="text"
                                               name="aufgabe"
                                               x-model="text"
                                               required
                                               class="flex-1 px-3 py-1.5 border border-blue-300 rounded-md text-sm focus:outline-none focus:ring-2 focus:ring-blue-400">
                                        <button type="submit"
                                                class="px-2 py-1.5 bg-blue-600 text-white text-xs font-medium rounded-md hover:bg-blue-700">
                                            ✓
                                        </button>
                                        <button type="button" @click="editing = false; text = @js($uebung->aufgabe)"
                                                class="px-2 py-1.5 bg-gray-100 text-gray-600 text-xs font-medium rounded-md hover:bg-gray-200">
                                            ✕
                                        </button>
                                    </div>
                                </form>
                            </div>
                        @endcanany
                    </div>
                @endforeach
            </div>

            {{-- Abhak-Tabelle pro Tag --}}
            @if(count($wochentage) > 0)
                <div class="overflow-x-auto">
                    <table class="text-xs border-collapse w-full">
                        <thead>
                            <tr>
                                <th class="text-left font-medium text-gray-600 pr-3 py-1 whitespace-nowrap">Übung</th>
                                @foreach($wochentage as $tag)
                                    <th class="text-center font-medium text-gray-600 px-2 py-1 whitespace-nowrap min-w-[3rem]">
                                        <div>{{ $tagNamen[($tag->dayOfWeek + 6) % 7] ?? '?' }}</div>
                                        <div class="text-gray-400 font-normal">{{ $tag->format('d.m.') }}</div>
                                    </th>
                                @endforeach
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($wpPlan->taeglicheUebungen as $uebung)
                                <tr class="{{ $loop->even ? 'bg-blue-50/40' : '' }}">
                                    <td class="pr-3 py-1.5 text-gray-700 font-medium">{{ $uebung->aufgabe }}</td>
                                    @foreach($wochentage as $tag)
                                        <td class="text-center px-2 py-1.5">
                                            <div class="w-6 h-6 border border-gray-400 rounded mx-auto bg-white"></div>
                                        </td>
                                    @endforeach
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <p class="text-xs text-gray-400 mt-2">Die Tabelle dient zum Abhaken beim Ausdrucken.</p>
            @endif

        @else
            <p class="text-sm text-gray-400 text-center py-4">
                Noch keine täglichen Übungen hinterlegt. Klicke auf „+ Übung hinzufügen".
            </p>
        @endif

    </div>
</div>

