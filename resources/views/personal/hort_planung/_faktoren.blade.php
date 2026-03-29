{{-- Partial: Gesetzliche Faktoren-Verwaltung --}}
{{-- Wird eingebunden in: edit.blade.php --}}
{{-- Vollständige Alpine.js Inline-Bearbeitung → TODO #06 --}}

<div class="bg-white rounded-2xl border border-gray-200 shadow-sm overflow-hidden"
     x-data="{ showAddForm: false, showWertForm: null }">

    <div class="px-5 py-3.5 border-b border-gray-100 bg-gray-50 flex items-center justify-between">
        <div>
            <h2 class="text-sm font-semibold text-gray-700 uppercase tracking-wide">Gesetzliche Faktoren</h2>
            <p class="text-xs text-gray-400 mt-0.5">Berechnungsgrundlagen für VZÄ (§12 SächsKitaG)</p>
        </div>
        <button @click="showAddForm = !showAddForm"
                class="inline-flex items-center gap-1 px-3 py-1.5 bg-blue-600 hover:bg-blue-700 text-white text-xs font-medium rounded-lg">
            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
            </svg>
            Faktor hinzufügen
        </button>
    </div>

    {{-- Faktoren-Tabelle --}}
    <div class="divide-y divide-gray-100">
        @forelse($planung->faktoren->sortBy('position') as $faktor)
        <div class="p-4 @if(!$faktor->aktiv) opacity-50 @endif">
            <div class="flex items-start justify-between gap-3">
                <div class="flex-1 min-w-0">
                    <div class="flex items-center gap-2 flex-wrap">
                        <span class="text-xs font-mono bg-gray-100 text-gray-600 px-1.5 py-0.5 rounded">
                            #{{ $faktor->position }}
                        </span>
                        <span class="font-medium text-sm text-gray-800">{{ $faktor->bezeichnung }}</span>
                        @php
                            $typLabel = match($faktor->berechnungs_typ) {
                                'divisor'          => '÷ Grundschlüssel',
                                'faktor_auf_bs'    => '× BS-Aufschlag',
                                'faktor_auf_summe' => '× Summen-Aufschlag',
                                default            => $faktor->berechnungs_typ,
                            };
                            $typTitle = match($faktor->berechnungs_typ) {
                                'divisor'          => 'Grundschlüssel: Kinder ÷ Wert = Basis-VZÄ',
                                'faktor_auf_bs'    => 'BS-Aufschlag: Betreuungsschlüssel × Wert',
                                'faktor_auf_summe' => 'Summen-Aufschlag: VZÄ-Gesamtsumme × Wert',
                                default            => '',
                            };
                        @endphp
                        <span class="text-xs px-2 py-0.5 rounded-full
                            @if($faktor->berechnungs_typ === 'divisor') bg-orange-100 text-orange-700
                            @elseif($faktor->berechnungs_typ === 'faktor_auf_bs') bg-blue-100 text-blue-700
                            @else bg-purple-100 text-purple-700 @endif"
                             title="{{ $typTitle }}">
                            {{ $typLabel }}
                        </span>
                        @if(!$faktor->aktiv)
                            <span class="text-xs bg-gray-100 text-gray-500 px-2 py-0.5 rounded-full">deaktiviert</span>
                        @endif
                    </div>
                    @if($faktor->gesetzliche_grundlage)
                        <p class="text-xs text-gray-400 mt-0.5">{{ $faktor->gesetzliche_grundlage }}</p>
                    @endif

                    {{-- Werte-Timeline --}}
                    <div class="mt-2 space-y-1">
                        @foreach($faktor->werte->sortBy('gueltig_ab') as $wert)
                        <div class="flex items-center gap-2 text-xs">
                            <span class="text-gray-400 font-mono w-20 shrink-0">ab {{ $wert->gueltig_ab->format('M Y') }}</span>
                            <span class="font-semibold text-gray-700 font-mono">{{ number_format($wert->wert, 6) }}</span>
                            @if($wert->notiz)
                                <span class="text-gray-400">– {{ $wert->notiz }}</span>
                            @endif
                            @if($faktor->werte->count() > 1)
                            <form action="{{ route('hort-planung.deleteFaktorWert', [$planung, $wert]) }}" method="POST" class="ml-auto"
                                  onsubmit="return confirm('Wert löschen?')">
                                @csrf @method('DELETE')
                                <button type="submit" class="text-red-400 hover:text-red-600">
                                    <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                                    </svg>
                                </button>
                            </form>
                            @endif
                        </div>
                        @endforeach
                    </div>
                </div>

                {{-- Aktionen --}}
                <div class="flex gap-1 shrink-0">
                    <button @click="showWertForm = showWertForm === {{ $faktor->id }} ? null : {{ $faktor->id }}"
                            class="px-2 py-1 text-xs bg-gray-100 hover:bg-gray-200 text-gray-600 rounded-lg">
                        + Wert
                    </button>
                    @if($faktor->aktiv)
                    <form action="{{ route('hort-planung.deleteFaktor', [$planung, $faktor]) }}" method="POST"
                          onsubmit="return confirm('Faktor deaktivieren?')">
                        @csrf @method('DELETE')
                        <button type="submit" class="px-2 py-1 text-xs bg-gray-100 hover:bg-red-50 hover:text-red-600 text-gray-500 rounded-lg">
                            Deaktivieren
                        </button>
                    </form>
                    @endif
                </div>
            </div>

            {{-- Wert-hinzufügen-Form (inline) --}}
            <div x-show="showWertForm === {{ $faktor->id }}" x-cloak
                 class="mt-3 p-3 bg-gray-50 rounded-xl border border-gray-200">
                @include('personal.hort_planung._faktor_wert_modal', ['planung' => $planung, 'faktor' => $faktor])
            </div>
        </div>
        @empty
        <p class="p-5 text-sm text-gray-400 text-center">Noch keine Faktoren definiert.</p>
        @endforelse
    </div>

    {{-- Neuen Faktor hinzufügen --}}
    <div x-show="showAddForm" x-cloak
         class="border-t border-gray-100 p-5 bg-gray-50">
        <h3 class="text-sm font-semibold text-gray-700 mb-3">Neuen Faktor anlegen</h3>
        <form action="{{ route('hort-planung.storeFaktor', $planung) }}" method="POST" class="space-y-3">
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
                    <input type="text" name="bezeichnung" placeholder="z. B. Inklusion"
                           class="w-full border border-gray-300 rounded-lg px-2.5 py-1.5 text-sm focus:ring-1 focus:ring-blue-500 outline-none" required>
                </div>
            </div>
            <div class="grid grid-cols-3 gap-3">
                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">Typ <span class="text-red-500">*</span></label>
                    <select name="berechnungs_typ" required
                            class="w-full border border-gray-300 rounded-lg px-2.5 py-1.5 text-sm focus:ring-1 focus:ring-blue-500 outline-none">
                        <option value="divisor">÷ Grundschlüssel (Kinder ÷ Wert)</option>
                        <option value="faktor_auf_bs" selected>× BS-Aufschlag (× Betreuungsschlüssel)</option>
                        <option value="faktor_auf_summe">× Summen-Aufschlag (× VZÄ-Gesamtsumme)</option>
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">Position <span class="text-red-500">*</span></label>
                    <input type="number" name="position" min="1"
                           value="{{ ($planung->faktoren->max('position') ?? 0) + 1 }}"
                           class="w-full border border-gray-300 rounded-lg px-2.5 py-1.5 text-sm focus:ring-1 focus:ring-blue-500 outline-none" required>
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">Init. Wert <span class="text-red-500">*</span></label>
                    <input type="number" name="wert" step="0.000001" min="0" placeholder="0.0000"
                           class="w-full border border-gray-300 rounded-lg px-2.5 py-1.5 text-sm focus:ring-1 focus:ring-blue-500 outline-none" required>
                </div>
            </div>
            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">Gültig ab <span class="text-red-500">*</span></label>
                    <input type="month" name="gueltig_ab" value="{{ $planung->start_monat->format('Y-m') }}"
                           class="w-full border border-gray-300 rounded-lg px-2.5 py-1.5 text-sm focus:ring-1 focus:ring-blue-500 outline-none" required>
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">Gesetzl. Grundlage</label>
                    <input type="text" name="gesetzliche_grundlage" placeholder="z. B. §12 SächsKitaG"
                           class="w-full border border-gray-300 rounded-lg px-2.5 py-1.5 text-sm focus:ring-1 focus:ring-blue-500 outline-none">
                </div>
            </div>
            <div class="flex gap-2">
                <button type="submit"
                        class="px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white text-xs font-semibold rounded-lg">
                    Faktor anlegen
                </button>
                <button type="button" @click="showAddForm = false"
                        class="px-4 py-2 bg-white border border-gray-300 text-gray-600 text-xs font-medium rounded-lg hover:bg-gray-50">
                    Abbrechen
                </button>
            </div>
        </form>
    </div>

    <div class="px-5 py-2.5 bg-blue-50 border-t border-blue-100">
        <p class="text-xs text-blue-600">
            ℹ Die Reihenfolge ist entscheidend für den Typ <span class="font-semibold">× Summen-Aufschlag</span>:
            er multipliziert die VZÄ-Summe aller vorherigen Positionen.
        </p>
    </div>
</div>

