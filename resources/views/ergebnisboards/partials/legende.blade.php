{{-- Legende / „Arbeits-Fahrplan“ – $senkrecht: in der Seitenspalte untereinander, sonst nebeneinander --}}
<div class="erg-legende" @if(! $senkrecht) style="flex-direction: row; flex-wrap: wrap; align-items: center; column-gap: 1.25rem;" @endif>
    <div class="erg-legende-titel" x-text="board.legende.titel" spellcheck="false"
         :contenteditable="schreibbar ? 'true' : 'false'" data-placeholder="Legende"
         @keydown.enter="enterBlur($event)" @blur="legendeSpeichern(null, $el)"></div>
    <template x-for="(e, i) in board.legende.eintraege || []" :key="i">
        <div class="flex items-center gap-2" :class="'erg-farbe-' + (e.farbe || 'grau')">
            <span class="erg-punkt"></span>
            <template x-if="schreibbar">
                <span x-text="e.text" contenteditable="true" spellcheck="false" data-placeholder="Eintrag"
                      @keydown.enter="enterBlur($event)" @blur="legendeSpeichern(i, $el)"></span>
            </template>
            <template x-if="!schreibbar">
                <span><strong class="text-slate-800" x-text="legendeTeile(e.text)[0]"></strong><span x-text="legendeTeile(e.text)[1]"></span></span>
            </template>
        </div>
    </template>
    <div class="flex items-center gap-1.5 text-amber-700 font-semibold {{ $senkrecht ? 'pt-1.5 border-t border-slate-200' : '' }}" x-show.important="hatFokus">
        <span>★</span><span>Markierter Arbeitsschwerpunkt</span>
    </div>
</div>
