{{--
    Kartenliste eines Feldes oder Unterbereichs (SortableJS-Container) + „+ Karte“.
    $f = Name der Alpine-Variable des Feldes (feld|unter).
--}}
<div class="erg-karten"
     :class="{ 'is-raster-2': {{ $f }}.raster === 2, 'is-leer': !karten({{ $f }}.schluessel).length }"
     :data-leer="schreibbar ? 'Karten hierher ziehen' : 'Noch keine Karten'"
     x-init="sortableInit($el, {{ $f }}.schluessel)">
    <template x-for="k in karten({{ $f }}.schluessel)" :key="k.id">
        <div class="erg-karte" role="button" tabindex="0" :data-karte-id="k.id"
             :class="{
                'is-klein': {{ $f }}.raster === 2 || !!(seitenfeld && seitenfeld.schluessel === {{ $f }}.schluessel),
                'is-breit': k.breit && {{ $f }}.raster === 2,
                'is-fokus': k.fokus,
                'is-erledigt': k.aufgabe && k.aufgabe.erledigt
             }"
             :aria-label="'Karte ' + k.titel + (k.fokus ? ', Arbeitsschwerpunkt' : '')"
             @click="karteOeffnen(k)" @keydown.enter.prevent="karteOeffnen(k)" @keydown.space.prevent="karteOeffnen(k)">
            <span class="erg-etikett" x-show.important="k.etikett" x-text="k.etikett"></span>
            <div class="erg-karte-titel" x-text="k.titel"></div>
            <div class="erg-karte-zusatz" x-show.important="k.zusatz" x-text="k.zusatz"></div>
            <div class="erg-karte-meta" x-show.important="k.faellig_am || k.zustaendige.length || k.anhaenge.length || k.aufgabe || k.kommentare || k.beschreibung || k.checkliste.length">
                <span class="inline-flex items-center" x-show.important="k.beschreibung" title="Mit Beschreibung">
                    <i class="fas fa-align-left"></i>
                </span>
                <span class="erg-checkliste-stand inline-flex items-center gap-1" x-show.important="k.checkliste.length"
                      :class="{ 'is-fertig': k.checkliste.length && k.checkliste.every(p => p.erledigt) }"
                      :title="'Checkliste: ' + fortschritt(k) + ' erledigt'">
                    <i class="far fa-check-square"></i><span x-text="fortschritt(k)"></span>
                </span>
                <span class="erg-faellig inline-flex items-center gap-1" x-show.important="k.faellig_am"
                      :class="{ 'is-ueberfaellig': k.ueberfaellig }" :title="k.ueberfaellig ? 'Überfällig' : 'Fällig am'">
                    <i class="far fa-calendar-alt"></i><span x-text="datum(k.faellig_am)"></span>
                </span>
                <span class="inline-flex items-center gap-1" x-show.important="k.anhaenge.length"
                      :title="k.anhaenge.length + (k.anhaenge.length === 1 ? ' Anhang' : ' Anhänge')">
                    <i class="fas fa-paperclip"></i><span x-text="k.anhaenge.length"></span>
                </span>
                <span class="inline-flex items-center gap-1 erg-no-export" x-show.important="k.kommentare"
                      :title="k.kommentare + (k.kommentare === 1 ? ' Kommentar' : ' Kommentare')">
                    <i class="far fa-comment"></i><span x-text="k.kommentare"></span>
                </span>
                <template x-if="k.aufgabe">
                    <span class="erg-aufgabe-status" :class="{ 'is-erledigt': k.aufgabe.erledigt }"
                          :title="k.aufgabe.erledigt ? 'Aufgabe erledigt' : 'Aufgabe offen'">
                        <i :class="k.aufgabe.erledigt ? 'fas fa-check-circle' : 'far fa-check-circle'"></i>
                        <span x-text="k.aufgabe.fortschritt || (k.aufgabe.erledigt ? 'erledigt' : 'Aufgabe')"></span>
                    </span>
                </template>
                <span class="erg-avatare ml-auto" x-show.important="k.zustaendige.length">
                    <template x-for="z in k.zustaendige.slice(0, 4)" :key="z.id">
                        <span class="erg-avatar" :title="z.name" x-text="z.kuerzel"></span>
                    </template>
                    <span class="erg-avatar" x-show.important="k.zustaendige.length > 4" x-text="'+' + (k.zustaendige.length - 4)"></span>
                </span>
            </div>
            {{-- Abstimmung: Punkte der Karte, eigene Punkte setzen/zurücknehmen --}}
            <div class="erg-stimmen" x-show.important="abstimmungSichtbar" @click.stop @keydown.stop>
                <span class="erg-punkte" :title="abstimmung.sichtbar ? punkteFuer(k) + ' Punkte' : 'Ergebnis verdeckt bis zum Ende'">
                    <i class="fas fa-circle"></i>
                    <span x-text="abstimmung.sichtbar ? punkteFuer(k) : '?'"></span>
                </span>
                <span class="erg-meine-punkte" x-show.important="meinePunkte(k)"
                      x-text="'davon ' + meinePunkte(k) + ' von mir'"></span>
                <span class="ml-auto inline-flex gap-1 erg-no-export" x-show.important="abstimmungLaeuft">
                    <button type="button" class="erg-stimme-btn" @click="stimme(k, -1)" :disabled="!meinePunkte(k) || laedt"
                            :aria-label="'Punkt von ' + k.titel + ' zurücknehmen'" title="Punkt zurücknehmen">−</button>
                    <button type="button" class="erg-stimme-btn is-plus" @click="stimme(k, 1)" :disabled="!abstimmung.rest || laedt"
                            :aria-label="'Punkt auf ' + k.titel + ' setzen'" title="Punkt setzen">+</button>
                </span>
            </div>
            <span class="erg-stern" x-show.important="k.fokus" aria-hidden="true">★</span>
        </div>
    </template>
</div>
<button type="button" class="erg-plus erg-no-export" x-show.important="schreibbar" @click="neueKarte({{ $f }}.schluessel)"
        :aria-label="'Karte hinzufügen in ' + {{ $f }}.titel">
    <i class="fas fa-plus"></i> Karte
</button>
