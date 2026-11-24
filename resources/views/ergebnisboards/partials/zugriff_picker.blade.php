{{--
    Zugriffs-Picker (Alpine `ergZugriffPicker` aus resources/js/ergebnisboards.js)
    Erwartet: $optionen (['users'|'groups'|'roles' => [{id, name}]]), $auswahl ([{typ, id, recht}]), $formular (bool)
    Formular: erzeugt hidden inputs zugriffe[i][typ|id|recht]; sonst meldet er Änderungen per Event `erg-zugriffe`.
--}}
<div x-data="ergZugriffPicker(@js($optionen), @js($auswahl), {{ $formular ? 'true' : 'false' }})"
     @erg-zugriffe-setzen.window="setzen($event.detail)" class="flex flex-col gap-2">
    <div class="flex flex-wrap items-center justify-between gap-2">
        <div class="erg-tabs" role="tablist" aria-label="Art">
            <template x-for="t in tabs" :key="t.key">
                <button type="button" class="erg-tab" :class="{ 'is-active': tab === t.key }" role="tab"
                        :aria-selected="(tab === t.key).toString()"
                        @click="tab = t.key; query = ''; offen = true; $nextTick(() => $refs.suche.focus())">
                    <i :class="t.icon"></i><span x-text="t.label"></span>
                </button>
            </template>
        </div>
        <span class="text-xs text-slate-400" x-text="zusammenfassung"></span>
    </div>

    <div class="relative" @click.outside="offen = false">
        <i class="fas fa-search absolute left-3 top-1/2 -translate-y-1/2 text-slate-400 text-xs"></i>
        <input type="search" x-ref="suche" class="erg-input" style="padding-left: 2rem" autocomplete="off"
               x-model="query" :placeholder="aktuellerTab.placeholder" :aria-label="aktuellerTab.placeholder"
               @focus="offen = true" @input="offen = true; markiert = 0"
               @keydown.arrow-down.prevent="bewegen(1)" @keydown.arrow-up.prevent="bewegen(-1)"
               @keydown.enter.prevent="waehlen(treffer[markiert])" @keydown.escape.stop="offen = false">
        <div class="erg-picker-results" x-show.important="offen && treffer.length" x-cloak>
            <template x-for="(o, index) in treffer" :key="tab + o.id">
                <button type="button" class="erg-picker-option" :class="{ 'is-highlighted': index === markiert }"
                        @mouseenter="markiert = index" @click="waehlen(o)">
                    <i :class="aktuellerTab.icon" class="text-slate-400 w-4 text-center"></i>
                    <span x-text="o.name" class="flex-1"></span>
                    <i class="fas fa-plus text-xs text-slate-300"></i>
                </button>
            </template>
        </div>
    </div>

    <div class="erg-zugriff-liste" x-show.important="eintraege.length">
        <template x-for="(e, i) in eintraege" :key="e.typ + e.id">
            <div class="erg-zugriff-zeile">
                <i :class="icon(e.typ)" class="text-slate-400 w-4 text-center"></i>
                <span class="flex-1 min-w-0 truncate" x-text="name(e)"></span>
                <select class="erg-select erg-input-sm" style="width: auto" x-model="e.recht" :aria-label="'Recht für ' + name(e)">
                    <option value="lesen" :selected="e.recht === 'lesen'">lesen</option>
                    <option value="schreiben" :selected="e.recht === 'schreiben'">schreiben</option>
                    <template x-if="e.typ === 'user'">
                        <option value="verwalten" :selected="e.recht === 'verwalten'">verwalten</option>
                    </template>
                </select>
                <button type="button" class="erg-icon-btn" @click="entfernen(i)" :aria-label="name(e) + ' entfernen'"><i class="fas fa-times"></i></button>
                <template x-if="formular">
                    <span>
                        <input type="hidden" :name="'zugriffe[' + i + '][typ]'" :value="e.typ">
                        <input type="hidden" :name="'zugriffe[' + i + '][id]'" :value="e.id">
                        <input type="hidden" :name="'zugriffe[' + i + '][recht]'" :value="e.recht">
                    </span>
                </template>
            </div>
        </template>
    </div>
    <p class="erg-hint mt-0">
        <b>lesen</b> = ansehen &amp; exportieren, <b>schreiben</b> = Karten und Felder bearbeiten,
        <b>verwalten</b> (nur Personen) = Zugriffe ändern, archivieren, löschen. Sie selbst verwalten das Board immer.
    </p>
</div>
