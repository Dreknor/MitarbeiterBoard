{{-- Vorschlagsliste für @-Erwähnungen unter einem Kommentarfeld ($ziel, $feld) --}}
<div class="erg-vorschlaege" role="listbox" aria-label="Person erwähnen"
     x-show.important="erw.offen && erw.ziel === '{{ $ziel }}' && erw.feld === '{{ $feld }}' && erwaehnungVorschlaege.length">
    <template x-for="(p, i) in erwaehnungVorschlaege" :key="p.id">
        <button type="button" role="option" :aria-selected="(i === erw.markiert).toString()"
                :class="{ 'is-markiert': i === erw.markiert }"
                @mousedown.prevent="erwaehnungWaehlen(p, $el.closest('.relative').querySelector('textarea'))">
            <span class="erg-avatar" x-text="p.kuerzel"></span>
            <span class="truncate" x-text="p.name"></span>
        </button>
    </template>
</div>
