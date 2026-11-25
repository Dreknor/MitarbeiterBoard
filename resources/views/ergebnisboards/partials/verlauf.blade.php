{{--
    Änderungsverlauf (Karte im Dialog oder ganzes Board in der Seitenleiste).
    $ziel = 'karte' | 'board' – Zustand in verl[$ziel], Logik in ergebnisboards.js (Abschnitt Verlauf).
--}}
<div class="flex flex-col gap-3">
    @if($ziel === 'board')
        <div class="erg-segment self-start" role="group" aria-label="Verlauf filtern">
            @foreach(['alle' => 'Alles', 'board' => 'Nur Board', 'karten' => 'Nur Karten'] as $wert => $label)
                <button type="button" :class="{ 'is-active': verl.board.filter === '{{ $wert }}' }"
                        :aria-pressed="(verl.board.filter === '{{ $wert }}').toString()"
                        @click="verl.board.filter = '{{ $wert }}'; verlaufLaden('board')">{{ $label }}</button>
            @endforeach
        </div>
    @endif

    <p class="text-xs text-slate-400 flex items-center gap-2" x-show.important="verl.{{ $ziel }}.laedt && !verl.{{ $ziel }}.eintraege.length">
        <span class="erg-spinner"></span> Verlauf wird geladen …
    </p>
    <p class="text-sm text-slate-400" x-show.important="verl.{{ $ziel }}.geladen && !verl.{{ $ziel }}.laedt && !verl.{{ $ziel }}.eintraege.length">
        Noch keine Änderungen erfasst.
    </p>

    <ol class="erg-verlauf">
        <template x-for="e in verl.{{ $ziel }}.eintraege" :key="e.id">
            <li class="erg-verlauf-eintrag" :class="{ 'is-geloescht': e.aktion === 'karte_geloescht' }">
                <span class="erg-verlauf-icon"><i :class="e.icon"></i></span>
                <div class="flex-1 min-w-0">
                    <p class="text-sm text-slate-700">
                        <span class="font-semibold text-slate-900" x-text="e.person"></span>
                        @if($ziel === 'board')
                            <template x-if="verlaufKarte(e)">
                                <button type="button" class="erg-verlauf-link" @click="karteOeffnen(verlaufKarte(e))" x-text="e.text"
                                        title="Karte öffnen"></button>
                            </template>
                            <template x-if="!verlaufKarte(e)">
                                <span x-text="e.text"></span>
                            </template>
                        @else
                            <span x-text="e.text"></span>
                        @endif
                    </p>
                    <ul class="erg-verlauf-zeilen" x-show.important="e.zeilen.length">
                        <template x-for="(z, i) in e.zeilen" :key="i">
                            <li x-text="z"></li>
                        </template>
                    </ul>
                    <div class="flex flex-wrap items-center gap-2 mt-0.5">
                        <span class="text-xs text-slate-400" x-text="zeit(e.zeit)"></span>
                        <template x-if="e.wiederherstellbar && verl.{{ $ziel }}.darfWiederherstellen">
                            <button type="button" class="erg-btn erg-btn-secondary erg-btn-sm" @click="karteWiederherstellen('{{ $ziel }}', e)" :disabled="laedt">
                                <i class="fas fa-undo"></i> Wiederherstellen
                            </button>
                        </template>
                        <span class="text-xs text-slate-400" x-show.important="e.wiederherstellbar && e.endgueltig_am"
                              x-text="'wird am ' + datum(e.endgueltig_am) + ' endgültig gelöscht'"></span>
                    </div>
                </div>
            </li>
        </template>
    </ol>

    <button type="button" class="erg-btn erg-btn-secondary erg-btn-sm self-center" x-show.important="verl.{{ $ziel }}.mehr"
            @click="verlaufLaden('{{ $ziel }}', true)" :disabled="verl.{{ $ziel }}.laedt">
        <i class="fas fa-chevron-down"></i> Ältere Einträge laden
    </button>
</div>
