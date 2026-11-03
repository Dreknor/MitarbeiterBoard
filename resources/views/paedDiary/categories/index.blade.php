{{--
    Pädagogisches Tagebuch – Kategorien & Spaltengruppen (Stil der neuen Ansicht, für alle Nutzer)
    Notizkategorien: Name, Farbe (Palette oder eigene), Sichtbarkeit in der eigenen Woche, Löschen.
    Spaltengruppen: Übersicht und – mit Recht „manage global paed diary categories“ – Umbenennen.
    Logik: kategorienVerwaltung() in resources/js/paed-diary-neu.js
--}}
@extends('layouts.app')

@section('content')
<div class="pdn pdn--farben" x-data="kategorienVerwaltung(@js(['darfGlobal' => $canManageGlobal]))">
    <div class="pdn-screen">
        <div class="pdn-tb">
            <h1 class="pdn-title">Kategorien &amp; Spaltengruppen</h1>
            <span class="pdn-spacer"></span>
            <a class="pdn-btn" href="{{ route('paedDiary.index') }}"><i class="fas fa-arrow-left" aria-hidden="true"></i> Zurück zur Woche</a>
        </div>

        <div class="pdn-main">
            <div class="pdn-tabs" role="tablist" aria-label="Bereiche">
                <button type="button" role="tab" :aria-selected="tab === 'kategorien'" @click="tab = 'kategorien'">Notizkategorien <em x-text="kategorien.length"></em></button>
                <button type="button" role="tab" :aria-selected="tab === 'gruppen'" @click="tab = 'gruppen'; ladeGruppen()">Spaltengruppen <em x-text="gruppen.length || ''"></em></button>
            </div>

            <div x-show="meldung" x-cloak role="alert" class="pdn-meldung" :class="meldungTyp === 'ok' ? 'pdn-meldung--info' : 'pdn-meldung--danger'" style="border-radius:8px">
                <span x-text="meldung"></span>
                <button type="button" aria-label="Meldung schließen" @click="meldung = ''">&times;</button>
            </div>

            {{-- ═══════════ Notizkategorien ═══════════ --}}
            <section x-show="tab === 'kategorien'" aria-label="Notizkategorien" style="display:flex;flex-direction:column;gap:16px">

                {{-- Neue Kategorie --}}
                <form class="pdn-panelbox" @submit.prevent="anlegen()">
                    <h3>Neue Kategorie</h3>
                    <div class="pdn-katform">
                        <label class="pdn-field" style="flex:1 1 220px">Name
                            <input type="text" x-model="neu.name" maxlength="100" required placeholder="z. B. Lernen, Verhalten, Elternkontakt">
                        </label>
                        <div class="pdn-field">Farbe
                            <div class="pdn-palette" role="radiogroup" aria-label="Farbe der neuen Kategorie">
                                <template x-for="f in palette" :key="'n' + f.wert">
                                    <button type="button" class="pdn-swatch" role="radio" :aria-checked="neu.color === f.wert"
                                            :style="'--swatch:' + f.wert" :title="f.name" :aria-label="f.name" @click="neu.color = f.wert"></button>
                                </template>
                                <label class="pdn-swatch pdn-swatch--eigen" title="Eigene Farbe" :style="'--swatch:' + (istPalette(neu.color) ? '#ffffff' : neu.color)">
                                    <input type="color" :value="neu.color || '#1d4ed8'" @input="neu.color = $event.target.value" aria-label="Eigene Farbe wählen">
                                    <i class="fas fa-eye-dropper" aria-hidden="true"></i>
                                </label>
                                <button type="button" class="pdn-swatch pdn-swatch--keine" role="radio" :aria-checked="!neu.color" title="Keine Farbe" aria-label="Keine Farbe" @click="neu.color = null"></button>
                            </div>
                        </div>
                        <template x-if="darfGlobal">
                            <label class="pdn-check" style="align-self:flex-end"><input type="checkbox" x-model="neu.global"><span>Für alle (globale Kategorie)</span></label>
                        </template>
                        <div style="display:flex;align-items:flex-end;gap:10px">
                            <span class="pdn-entry pdn-vorschau" :data-farbe="neu.color ? '' : null" :style="neu.color ? '--kat:' + neu.color : ''">
                                <span class="pdn-entry__text" x-text="neu.name || 'Vorschau einer Notiz'"></span>
                            </span>
                            <button type="submit" class="pdn-btn pdn-btn--pri" :disabled="!neu.name.trim() || speichert">Anlegen</button>
                        </div>
                    </div>
                </form>

                {{-- Liste --}}
                <div class="pdn-panelbox">
                    <div style="display:flex;flex-wrap:wrap;gap:8px;align-items:center;justify-content:space-between">
                        <h3>Vorhandene Kategorien</h3>
                        <p class="pdn-label-sm">Farbe und Name ändern per Klick. „In der Woche zeigen“ gilt nur für dich.</p>
                    </div>
                    <p class="pdn-leer" x-show="geladen && !kategorien.length" x-cloak>Noch keine Kategorien angelegt.</p>
                    <div class="pdn-kattabelle" x-show="kategorien.length">
                        <table class="pdn-table">
                            <thead>
                                <tr><th style="width:56px">Farbe</th><th>Name</th><th>Vorschau</th><th>Art</th><th style="text-align:right">Einträge</th><th>In der Woche zeigen</th><th></th></tr>
                            </thead>
                            <tbody>
                                <template x-for="k in kategorien" :key="'k' + k.id">
                                    <tr>
                                        <td>
                                            <div class="pdn-pop" @click.outside="farbOffen === k.id && (farbOffen = null)">
                                                <button type="button" class="pdn-swatch" :class="!k.color && 'pdn-swatch--keine'" :style="k.color ? '--swatch:' + k.color : ''"
                                                        :disabled="!k.can_edit" @click="farbOffen = farbOffen === k.id ? null : k.id"
                                                        :aria-label="'Farbe von ' + k.name + ' ändern'" :aria-expanded="farbOffen === k.id"></button>
                                                <div class="pdn-panel" x-show="farbOffen === k.id" x-cloak style="left:0;right:auto;width:auto">
                                                    <div class="pdn-palette" role="radiogroup" :aria-label="'Farbe von ' + k.name">
                                                        <template x-for="f in palette" :key="k.id + f.wert">
                                                            <button type="button" class="pdn-swatch" role="radio" :aria-checked="k.color === f.wert"
                                                                    :style="'--swatch:' + f.wert" :title="f.name" :aria-label="f.name" @click="farbeSetzen(k, f.wert)"></button>
                                                        </template>
                                                        <label class="pdn-swatch pdn-swatch--eigen" title="Eigene Farbe" :style="'--swatch:' + (istPalette(k.color) ? '#ffffff' : (k.color || '#ffffff'))">
                                                            <input type="color" :value="k.color || '#1d4ed8'" @change="farbeSetzen(k, $event.target.value)" aria-label="Eigene Farbe wählen">
                                                            <i class="fas fa-eye-dropper" aria-hidden="true"></i>
                                                        </label>
                                                        <button type="button" class="pdn-swatch pdn-swatch--keine" role="radio" :aria-checked="!k.color" title="Keine Farbe" aria-label="Keine Farbe" @click="farbeSetzen(k, null)"></button>
                                                    </div>
                                                </div>
                                            </div>
                                        </td>
                                        <td>
                                            <template x-if="bearbeitet !== k.id">
                                                <button type="button" class="pdn-namebtn" :disabled="!k.can_edit" @click="bearbeiten(k)" :title="k.can_edit ? 'Umbenennen' : ''">
                                                    <span x-text="k.name"></span> <i class="fas fa-pen" aria-hidden="true" x-show="k.can_edit"></i>
                                                </button>
                                            </template>
                                            <template x-if="bearbeitet === k.id">
                                                <form @submit.prevent="umbenennen(k)" style="display:flex;gap:6px;align-items:center">
                                                    <label class="sr-only" :for="'kn' + k.id">Neuer Name</label>
                                                    <input :id="'kn' + k.id" type="text" class="pdn-colinput" style="max-width:220px" x-model="neuerName" maxlength="100"
                                                           x-init="$nextTick(() => $el.select())" @keydown.escape="bearbeitet = null">
                                                    <button type="submit" class="pdn-btn">Speichern</button>
                                                    <button type="button" class="pdn-btn pdn-btn--ghost" @click="bearbeitet = null">Abbrechen</button>
                                                </form>
                                            </template>
                                        </td>
                                        <td><span class="pdn-entry pdn-vorschau" :data-farbe="k.color ? '' : null" :style="k.color ? '--kat:' + k.color : ''"><span class="pdn-entry__text">Beispielnotiz</span></span></td>
                                        <td><span class="pdn-tag" x-text="k.is_global ? 'Für alle' : 'Eigene'"></span></td>
                                        <td style="text-align:right;font-variant-numeric:tabular-nums" x-text="k.entries_count"></td>
                                        <td>
                                            <label class="pdn-switch" style="justify-content:flex-start">
                                                <input type="checkbox" :checked="!k.hidden" @change="sichtbarkeit(k)" :aria-label="'Kategorie ' + k.name + ' in der Woche zeigen'"><i></i>
                                            </label>
                                        </td>
                                        <td style="text-align:right;white-space:nowrap">
                                            <template x-if="k.can_delete && loeschen !== k.id">
                                                <button type="button" class="pdn-btn pdn-btn--ghost" @click="loeschen = k.id" :aria-label="'Kategorie ' + k.name + ' löschen'"><i class="fas fa-trash" aria-hidden="true"></i></button>
                                            </template>
                                            <template x-if="loeschen === k.id">
                                                <span style="display:inline-flex;gap:6px;align-items:center">
                                                    <span class="pdn-label-sm" x-text="k.entries_count ? k.entries_count + ' Einträge werden ohne Kategorie.' : 'Wirklich löschen?'"></span>
                                                    <button type="button" class="pdn-btn" style="color:var(--pdn-danger)" @click="entfernen(k)">Löschen</button>
                                                    <button type="button" class="pdn-btn pdn-btn--ghost" @click="loeschen = null">Abbrechen</button>
                                                </span>
                                            </template>
                                        </td>
                                    </tr>
                                </template>
                            </tbody>
                        </table>
                    </div>
                </div>
            </section>

            {{-- ═══════════ Spaltengruppen ═══════════ --}}
            <section x-show="tab === 'gruppen'" x-cloak aria-label="Spaltengruppen" class="pdn-panelbox">
                <h3>Spaltengruppen</h3>
                <p class="pdn-label-sm">Spaltengruppen fassen Zusatzspalten zusammen, z. B. „Mitarbeit: + 0 -“. Angelegt werden sie in der Wochenansicht unter „Filter → Spalten verwalten“.
                    @unless($canManageGlobal) Umbenennen können nur Personen mit Verwaltungsrecht, weil es alle Klassen betrifft. @endunless
                </p>
                <p class="pdn-leer" x-show="gruppenGeladen && !gruppen.length">Noch keine Spaltengruppen vorhanden.</p>
                <table class="pdn-table" x-show="gruppen.length">
                    <thead><tr><th>Name</th><th style="text-align:right">Spalten</th><th></th></tr></thead>
                    <tbody>
                        <template x-for="g in gruppen" :key="'g' + g.name">
                            <tr>
                                <td>
                                    <template x-if="gruppeBearbeitet !== g.name"><b x-text="g.name"></b></template>
                                    <template x-if="gruppeBearbeitet === g.name">
                                        <form @submit.prevent="gruppeUmbenennen(g)" style="display:flex;gap:6px;align-items:center">
                                            <label class="sr-only" :for="'gn' + g.name">Neuer Name</label>
                                            <input :id="'gn' + g.name" type="text" class="pdn-colinput" style="max-width:220px" x-model="neuerGruppenName" maxlength="100" x-init="$nextTick(() => $el.select())">
                                            <button type="submit" class="pdn-btn">Speichern</button>
                                            <button type="button" class="pdn-btn pdn-btn--ghost" @click="gruppeBearbeitet = null">Abbrechen</button>
                                        </form>
                                    </template>
                                </td>
                                <td style="text-align:right" x-text="g.count"></td>
                                <td style="text-align:right">
                                    <template x-if="darfGlobal && gruppeBearbeitet !== g.name">
                                        <button type="button" class="pdn-btn pdn-btn--ghost" @click="gruppeBearbeitet = g.name; neuerGruppenName = g.name"><i class="fas fa-pen" aria-hidden="true"></i> Umbenennen</button>
                                    </template>
                                </td>
                            </tr>
                        </template>
                    </tbody>
                </table>
            </section>
        </div>
    </div>
</div>
@endsection

@push('js')
    @vite(['resources/js/paed-diary-neu.js'])
@endpush
