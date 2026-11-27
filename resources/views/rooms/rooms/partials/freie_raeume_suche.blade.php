{{--
    Suche nach freien Räumen zu einem Zeitpunkt (Datum + Von/Bis).
    Erwartet: $suche (null oder Ergebnis aus RaumSucheService::suche() + datum/von/bis), $stunden (Schnellauswahl Zeitraster)
--}}
@php
    $jetzt      = now();
    $vorschlag  = $jetzt->copy()->setTime($jetzt->hour, (int) (ceil($jetzt->minute / 5) * 5));
    $sucheDatum = old('datum', request('datum', $suche ? $suche['datum']->toDateString() : $jetzt->toDateString()));
    $sucheVon   = old('von', request('von', $suche['von'] ?? $vorschlag->format('H:i')));
    $sucheBis   = old('bis', request('bis', $suche['bis'] ?? $vorschlag->copy()->addMinutes(45)->format('H:i')));
@endphp

<div class="mb-6 bg-white rounded-2xl border border-gray-200 shadow-sm overflow-hidden"
     id="freie-raeume"
     x-data="{
        von: @js($sucheVon),
        bis: @js($sucheBis),
        stunden: @js($stunden),
        stundeVon: '',
        stundeBis: '',
        stundenUebernehmen() {
            const a = this.stunden.find(s => String(s.stunde) === String(this.stundeVon));
            if (!a) return;
            if (this.stundeBis === '' || Number(this.stundeBis) < a.stunde) this.stundeBis = String(a.stunde);
            const b = this.stunden.find(s => String(s.stunde) === String(this.stundeBis)) || a;
            this.von = a.von;
            this.bis = b.bis;
        }
     }">
    <div class="px-5 py-4 border-b border-gray-100">
        <h2 class="font-semibold text-gray-800 flex items-center gap-2">
            <svg class="w-5 h-5 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
            </svg>
            Freien Raum finden
        </h2>
        <p class="text-xs text-gray-500 mt-0.5">Zeigt alle buchbaren Räume, die im gewählten Zeitraum frei sind – inkl. Stundenplan, A/B-Woche und Ausfällen laut Vertretungsplan.</p>
    </div>

    <form method="get" action="{{ url('rooms/rooms') }}#freie-raeume" class="px-5 py-4">
        @if($errors->hasAny(['datum', 'von', 'bis']))
            <div class="mb-3 rounded-xl bg-red-50 border border-red-200 px-4 py-2 text-sm text-red-700">
                @foreach(['datum', 'von', 'bis'] as $feld)
                    @error($feld)<div>{{ $message }}</div>@enderror
                @endforeach
            </div>
        @endif

        <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 items-end">
            <div class="col-span-2 sm:col-span-1">
                <label for="suche_datum" class="block text-xs font-medium text-gray-600 mb-1">Datum</label>
                <input id="suche_datum" type="date" name="datum" required value="{{ $sucheDatum }}"
                       class="w-full rounded-xl border border-gray-300 px-2 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
            </div>
            <div>
                <label for="suche_von" class="block text-xs font-medium text-gray-600 mb-1">Von</label>
                <input id="suche_von" type="time" name="von" required step="300" x-model="von"
                       class="w-full rounded-xl border border-gray-300 px-2 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
            </div>
            <div>
                <label for="suche_bis" class="block text-xs font-medium text-gray-600 mb-1">Bis</label>
                <input id="suche_bis" type="time" name="bis" required step="300" x-model="bis"
                       class="w-full rounded-xl border border-gray-300 px-2 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
            </div>
            <div class="col-span-2 sm:col-span-1">
                <button type="submit"
                        class="w-full inline-flex items-center justify-center gap-1.5 px-4 py-2 bg-green-600 hover:bg-green-700 text-white text-sm font-semibold rounded-xl shadow-sm">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-4.35-4.35M17 11A6 6 0 105 11a6 6 0 0012 0z"/>
                    </svg>
                    Freie Räume suchen
                </button>
            </div>
        </div>

        @if($stunden->isNotEmpty())
            <div class="mt-3 flex flex-wrap items-center gap-2 text-xs text-gray-600">
                <span>Oder nach Unterrichtsstunde:</span>
                <select x-model="stundeVon" @change="stundenUebernehmen()" aria-label="Von Stunde"
                        class="rounded-lg border border-gray-300 px-2 py-1 text-xs focus:outline-none focus:ring-2 focus:ring-blue-500">
                    <option value="">Stunde …</option>
                    @foreach($stunden as $s)
                        <option value="{{ $s['stunde'] }}">{{ $s['stunde'] }}. Stunde ({{ $s['von'] }})</option>
                    @endforeach
                </select>
                <span>bis</span>
                <select x-model="stundeBis" @change="stundenUebernehmen()" aria-label="Bis Stunde"
                        class="rounded-lg border border-gray-300 px-2 py-1 text-xs focus:outline-none focus:ring-2 focus:ring-blue-500">
                    <option value="">Stunde …</option>
                    @foreach($stunden as $s)
                        <option value="{{ $s['stunde'] }}">{{ $s['stunde'] }}. Stunde ({{ $s['bis'] }})</option>
                    @endforeach
                </select>
            </div>
        @endif
    </form>

    @if($suche)
        @php
            $wocheUrl = $suche['wochen_typ'] ?? 'A';
            $datumStr = $suche['datum']->toDateString();
        @endphp
        <div class="border-t border-gray-100 px-5 py-4" data-testid="raumsuche-ergebnis">
            <p class="text-sm text-gray-700 mb-3">
                <strong>{{ $suche['frei']->count() }}</strong>
                {{ $suche['frei']->count() === 1 ? 'Raum ist' : 'Räume sind' }} frei am
                <strong>{{ $suche['datum']->locale('de')->isoFormat('dd, DD.MM.YYYY') }}</strong>
                von <strong>{{ $suche['von'] }}</strong> bis <strong>{{ $suche['bis'] }}</strong> Uhr
                @if($suche['wochen_typ'])
                    <span class="text-gray-400">({{ $suche['wochen_typ'] }}-Woche)</span>
                @endif
            </p>

            @if(!$suche['wochen_typ'])
                <p class="mb-3 text-xs text-amber-700 bg-amber-50 border border-amber-200 rounded-lg px-3 py-2">
                    Für diese Woche ist keine A/B-Woche hinterlegt – Buchungen beider Wochen werden vorsichtshalber als belegt gewertet.
                </p>
            @endif

            @if($suche['frei']->isEmpty())
                <p class="text-sm text-gray-500">Im gewählten Zeitraum ist leider kein buchbarer Raum frei.</p>
            @else
                <div class="rounded-xl border border-green-200 divide-y divide-green-100 overflow-hidden">
                    @foreach($suche['frei'] as $eintrag)
                        @php $raum = $eintrag['room']; @endphp
                        <div class="flex items-center gap-3 px-4 py-2.5 bg-green-50/40">
                            <span class="shrink-0 w-2 h-2 rounded-full bg-green-400"></span>
                            <div class="flex-1 min-w-0">
                                <span class="font-medium text-sm text-gray-800 truncate block">{{ $raum->name }}</span>
                                <span class="text-xs text-gray-500">
                                    @if($raum->room_number)Nr. {{ $raum->room_number }} · @endif
                                    @if(!$eintrag['frei_ab'] && !$eintrag['frei_bis'])
                                        ganzer Tag frei
                                    @else
                                        frei {{ $eintrag['frei_ab'] ? 'ab '.$eintrag['frei_ab'] : 'vorher' }}
                                        {{ $eintrag['frei_bis'] ? 'bis '.$eintrag['frei_bis'] : 'bis Tagesende' }}
                                    @endif
                                </span>
                            </div>
                            <a href="{{ url('rooms/rooms/'.$raum->id.'/'.$wocheUrl.'/'.$datumStr) }}?{{ http_build_query(['von' => $suche['von'], 'bis' => $suche['bis']]) }}"
                               class="shrink-0 inline-flex items-center gap-1 px-2.5 py-1 rounded-lg text-xs font-medium bg-green-600 text-white hover:bg-green-700">
                                Buchen
                            </a>
                        </div>
                    @endforeach
                </div>
            @endif

            @if($suche['belegt']->isNotEmpty())
                <details class="mt-3">
                    <summary class="cursor-pointer text-xs text-gray-500 hover:text-gray-700">
                        {{ $suche['belegt']->count() }} belegte {{ $suche['belegt']->count() === 1 ? 'Raum' : 'Räume' }} anzeigen
                    </summary>
                    <ul class="mt-2 space-y-1 text-xs text-gray-600">
                        @foreach($suche['belegt'] as $eintrag)
                            <li>
                                <a href="{{ url('rooms/rooms/'.$eintrag['room']->id.'/'.$wocheUrl.'/'.$datumStr) }}" class="font-medium text-gray-700 hover:underline">{{ $eintrag['room']->name }}</a>:
                                @foreach($eintrag['buchungen'] as $b)
                                    {{ $b->name ?: 'Belegt' }}@if($b->klassen) ({{ $b->klassen }})@endif
                                    {{ \Carbon\Carbon::parse($b->start)->format('H:i') }}–{{ \Carbon\Carbon::parse($b->end)->format('H:i') }}@if(!$loop->last), @endif
                                @endforeach
                            </li>
                        @endforeach
                    </ul>
                </details>
            @endif
        </div>
    @endif
</div>
