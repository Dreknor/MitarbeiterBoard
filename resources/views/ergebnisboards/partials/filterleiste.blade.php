{{-- Suchen & Filtern (nur Ansicht): Text, Zuständige, Etikett, Fälligkeit --}}
<div id="erg-filterleiste" class="erg-filterleiste" x-show.important="filterOffen || filterAktiv" x-cloak role="search">
    <div class="erg-filter-suche">
        <i class="fas fa-search text-slate-400"></i>
        <input type="search" x-ref="filterText" class="erg-input erg-input-sm" x-model.debounce.150ms="filter.text"
               placeholder="Titel, Text, Checkliste, Person …" aria-label="Karten durchsuchen"
               @keydown.escape.stop="filter.text ? filter.text = '' : (filterOffen = false)">
    </div>
    <select class="erg-select erg-input-sm" x-model="filter.person" aria-label="Nach Zuständigen filtern">
        <option value="">Alle Zuständigen</option>
        <option value="keine">ohne Zuständige</option>
        <template x-for="p in personen" :key="p.id">
            <option :value="String(p.id)" x-text="p.name"></option>
        </template>
    </select>
    <select class="erg-select erg-input-sm" x-model="filter.etikett" aria-label="Nach Etikett filtern" x-show.important="etiketten.length">
        <option value="">Alle Etiketten</option>
        <template x-for="e in etiketten" :key="e">
            <option :value="e" x-text="e"></option>
        </template>
    </select>
    <select class="erg-select erg-input-sm" x-model="filter.faellig" aria-label="Nach Fälligkeit filtern">
        <option value="">Jede Fälligkeit</option>
        <option value="ueberfaellig">überfällig</option>
        <option value="woche">in den nächsten 7 Tagen</option>
        <option value="mit">mit Fälligkeit</option>
        <option value="ohne">ohne Fälligkeit</option>
    </select>
    <span class="text-xs text-slate-500 whitespace-nowrap" x-show.important="filterAktiv"
          x-text="sichtbareAnzahl + ' von ' + board.karten.length + ' Karten'" aria-live="polite"></span>
    <button type="button" class="erg-btn erg-btn-ghost erg-btn-sm" x-show.important="filterAktiv" @click="filterZuruecksetzen()">
        <i class="fas fa-times"></i> Zurücksetzen
    </button>
</div>
