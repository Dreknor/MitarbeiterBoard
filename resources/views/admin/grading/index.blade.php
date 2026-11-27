{{--
    Verwaltung der Graduierungssysteme (Systeme, Stufen, Fragen) im Design der neuen Ansicht des
    Pädagogischen Tagebuchs (pdn-*). Formulare senden klassisch (POST + Redirect) an den
    GradingAdminController; Sortieren per Drag & Drop speichert per fetch.
    Logik: graduierungVerwaltung() in resources/js/graduierung-verwaltung.js
--}}
@extends('layouts.app')

@php
    $daten = $systems->map(fn ($system) => [
        'id' => $system->id,
        'name' => $system->name,
        'slug' => $system->slug,
        'loeschenUrl' => route('admin.grading.system.delete', $system),
        'stufeNeuUrl' => route('admin.grading.stage.store', $system),
        'stufenSortierenUrl' => route('admin.grading.stage.reorder', $system),
        'frageNeuUrl' => route('admin.grading.question.store', $system),
        'fragenSortierenUrl' => route('admin.grading.question.reorder', $system),
        'stufen' => $system->stages->sortBy('sort_order')->values()->map(fn ($stage) => [
            'id' => $stage->id,
            'name' => $stage->name,
            'slug' => $stage->slug,
            'symbol' => $stage->symbol,
            'bild' => $stage->image_url,
            'sort_order' => (int) $stage->sort_order,
            'standard' => (bool) $stage->is_default,
            'speichernUrl' => route('admin.grading.stage.update', $stage),
            'loeschenUrl' => route('admin.grading.stage.delete', $stage),
        ]),
        'fragen' => $system->questions->sortBy('sort_order')->values()->map(fn ($frage) => [
            'id' => $frage->id,
            'text' => $frage->question,
            'sort_order' => (int) $frage->sort_order,
            'aktiv' => (bool) $frage->active,
            'stufen' => $frage->stages->pluck('id')->map(fn ($id) => (int) $id)->values(),
            'speichernUrl' => route('admin.grading.question.update', $frage),
            'loeschenUrl' => route('admin.grading.question.delete', $frage),
        ]),
    ])->values();
@endphp

@section('content')
<div class="pdn" x-data="graduierungVerwaltung(@js(['systeme' => $daten, 'fehler' => $errors->any()]))">
    <div class="pdn-screen pdn-gv-screen">
        <div class="pdn-tb">
            <h1 class="pdn-title">Graduierungssysteme</h1>
            <span class="pdn-label-sm">Systeme, Stufen und die Fragen der Reflexion</span>
            <span class="pdn-spacer"></span>
            <button type="button" class="pdn-btn pdn-btn--pri" @click="systemDialog = true">
                <i class="fas fa-plus" aria-hidden="true"></i> Neues System
            </button>
        </div>

        @if(session('success'))
            <div class="pdn-meldung pdn-meldung--info" role="status" x-data="{ offen: true }" x-show="offen">
                <span><i class="fas fa-check-circle" aria-hidden="true"></i> {{ session('success') }}</span>
                <button type="button" aria-label="Meldung schließen" @click="offen = false">&times;</button>
            </div>
        @endif
        @if($errors->any())
            <div class="pdn-meldung pdn-meldung--danger" role="alert" x-data="{ offen: true }" x-show="offen">
                <span>
                    <i class="fas fa-exclamation-triangle" aria-hidden="true"></i>
                    @foreach($errors->all() as $fehler){{ $fehler }}@if(!$loop->last)<br>@endif @endforeach
                </span>
                <button type="button" aria-label="Meldung schließen" @click="offen = false">&times;</button>
            </div>
        @endif
        <div class="pdn-meldung pdn-meldung--danger" role="alert" x-show="meldung" x-cloak>
            <span x-text="meldung"></span>
            <button type="button" aria-label="Meldung schließen" @click="meldung = ''">&times;</button>
        </div>

        @if($systems->isEmpty())
            <div class="pdn-gv-leer">
                <i class="fas fa-layer-group" aria-hidden="true"></i>
                <h2>Noch keine Graduierungssysteme</h2>
                <p>Lege ein System an, z. B. „Lesestufen“. Danach kannst du Stufen und Fragen ergänzen.</p>
                <button type="button" class="pdn-btn pdn-btn--pri" @click="systemDialog = true">
                    <i class="fas fa-plus" aria-hidden="true"></i> Neues System
                </button>
            </div>
        @else
            <div class="pdn-gv">
                {{-- Systemliste --}}
                <nav class="pdn-gv-systeme" aria-label="Graduierungssysteme">
                    <span class="pdn-k">Systeme</span>
                    <template x-for="s in systeme" :key="'s' + s.id">
                        <button type="button" class="pdn-gv-system" :aria-current="s.id === systemId ? 'true' : false" @click="waehleSystem(s.id)">
                            <span class="pdn-gv-system__name" x-text="s.name"></span>
                            <span class="pdn-label-sm" x-text="s.stufen.length + ' Stufen · ' + s.fragen.length + ' Fragen'"></span>
                        </button>
                    </template>
                    {{-- Schmale Fenster: Auswahl statt Liste --}}
                    <label class="pdn-field pdn-gv-systemwahl">System
                        <select :value="systemId" @change="waehleSystem(Number($event.target.value))">
                            <template x-for="s in systeme" :key="'o' + s.id">
                                <option :value="s.id" x-text="s.name" :selected="s.id === systemId"></option>
                            </template>
                        </select>
                    </label>
                </nav>

                <div class="pdn-main" x-show="system">
                    <div class="pdn-gv-kopf">
                        <div>
                            <h2 x-text="system?.name"></h2>
                            <span class="pdn-badge" x-show="system?.slug" x-text="system?.slug"></span>
                        </div>
                        <span class="pdn-spacer"></span>
                        <button type="button" class="pdn-btn pdn-gv-gefahr" @click="loeschenFragen('system', system)">
                            <i class="fas fa-trash" aria-hidden="true"></i> System löschen
                        </button>
                    </div>

                    <div class="pdn-tabs" role="tablist" aria-label="Bereiche">
                        <button type="button" role="tab" id="gvTabStufen" aria-controls="gvPanelStufen" :aria-selected="tab === 'stufen'" @click="tabSetzen('stufen')">
                            Stufen <em x-text="system?.stufen.length"></em>
                        </button>
                        <button type="button" role="tab" id="gvTabFragen" aria-controls="gvPanelFragen" :aria-selected="tab === 'fragen'" @click="tabSetzen('fragen')">
                            Fragen <em x-text="system?.fragen.length"></em>
                        </button>
                    </div>

                    {{-- ═══════════ Stufen ═══════════ --}}
                    <section id="gvPanelStufen" role="tabpanel" aria-labelledby="gvTabStufen" x-show="tab === 'stufen'" class="pdn-gv-panel">
                        <div class="pdn-gv-leiste">
                            <span class="pdn-hinweis"><i class="fas fa-grip-vertical" aria-hidden="true"></i> Reihenfolge per Ziehen ändern – von der ersten zur höchsten Stufe.</span>
                            <span class="pdn-spacer"></span>
                            <button type="button" class="pdn-btn pdn-btn--pri" @click="stufeBearbeiten(null)">
                                <i class="fas fa-plus" aria-hidden="true"></i> Neue Stufe
                            </button>
                        </div>

                        <p class="pdn-leer pdn-gv-rahmen" x-show="system && !system.stufen.length">Noch keine Stufen. Lege die erste Stufe an.</p>

                        <ol class="pdn-gv-liste" x-ref="stufenListe" x-show="system?.stufen.length">
                            <template x-for="(st, i) in (system?.stufen || [])" :key="'st' + st.id">
                                <li class="pdn-gv-zeile" :data-id="st.id">
                                    <span class="pdn-gv-griff" title="Ziehen zum Sortieren" aria-hidden="true"><i class="fas fa-grip-vertical"></i></span>
                                    <span class="pdn-stage pdn-gv-stufenbild" :class="{ 'pdn-stage--bild': st.bild }">
                                        <template x-if="st.bild"><img :src="st.bild" alt=""></template>
                                        <template x-if="!st.bild && st.symbol"><i :class="st.symbol" aria-hidden="true"></i></template>
                                        <template x-if="!st.bild && !st.symbol"><span x-text="i + 1"></span></template>
                                    </span>
                                    <div class="pdn-gv-zeile__inhalt">
                                        <div class="pdn-gv-zeile__titel">
                                            <b x-text="st.name"></b>
                                            <span class="pdn-tag pdn-gv-tag--ok" x-show="st.standard">Standard</span>
                                            <span class="pdn-label-sm" x-show="st.slug" x-text="st.slug"></span>
                                        </div>
                                        <span class="pdn-label-sm" x-text="fragenText(st)"></span>
                                    </div>
                                    <div class="pdn-gv-zeile__aktionen">
                                        <button type="button" class="pdn-btn" @click="zeigeFragenDerStufe(st)" :disabled="!system.fragen.length">
                                            <i class="fas fa-filter" aria-hidden="true"></i> <span class="pdn-gv-text">Fragen</span>
                                        </button>
                                        <button type="button" class="pdn-btn" @click="stufeBearbeiten(st)">
                                            <i class="fas fa-pen" aria-hidden="true"></i> <span class="pdn-gv-text">Bearbeiten</span>
                                        </button>
                                    </div>
                                </li>
                            </template>
                        </ol>
                    </section>

                    {{-- ═══════════ Fragen ═══════════ --}}
                    <section id="gvPanelFragen" role="tabpanel" aria-labelledby="gvTabFragen" x-show="tab === 'fragen'" x-cloak class="pdn-gv-panel">
                        <div class="pdn-gv-leiste">
                            <label class="pdn-gv-filter" x-show="system?.stufen.length">
                                <span class="pdn-label-sm">Fragen der Stufe</span>
                                <select class="pdn-sel" x-model.number="filterStufe">
                                    <option :value="0">alle anzeigen</option>
                                    <template x-for="st in (system?.stufen || [])" :key="'f' + st.id">
                                        <option :value="st.id" x-text="st.name"></option>
                                    </template>
                                </select>
                            </label>
                            <span class="pdn-spacer"></span>
                            <button type="button" class="pdn-btn pdn-btn--pri" @click="frageBearbeiten(null)">
                                <i class="fas fa-plus" aria-hidden="true"></i> Neue Frage
                            </button>
                        </div>
                        <p class="pdn-hinweis">
                            Kinder und Pädagog:innen sehen nur die Fragen der Graduierungsstufe des Kindes.
                            Eine Frage ohne gewählte Stufe gilt für alle Stufen.
                            <span x-show="!filterStufe"><i class="fas fa-grip-vertical" aria-hidden="true"></i> Reihenfolge per Ziehen ändern.</span>
                        </p>

                        <p class="pdn-leer pdn-gv-rahmen" x-show="system && !system.fragen.length">Noch keine Fragen. Lege die erste Frage an.</p>
                        <p class="pdn-leer pdn-gv-rahmen" x-show="system?.fragen.length && !sichtbareFragen.length">Für diese Stufe gibt es keine Fragen.</p>

                        <ol class="pdn-gv-liste" x-ref="fragenListe" x-show="system?.fragen.length" :class="{ 'is-gefiltert': filterStufe }">
                            <template x-for="f in (system?.fragen || [])" :key="'fr' + f.id">
                                <li class="pdn-gv-zeile" :data-id="f.id" x-show="frageSichtbar(f)" :class="{ 'is-inaktiv': !f.aktiv }">
                                    <span class="pdn-gv-griff" title="Ziehen zum Sortieren" aria-hidden="true"><i class="fas fa-grip-vertical"></i></span>
                                    <span class="pdn-gv-nr" x-text="system.fragen.indexOf(f) + 1"></span>
                                    <div class="pdn-gv-zeile__inhalt">
                                        <div class="pdn-gv-zeile__titel">
                                            <span class="pdn-gv-fragetext" x-text="f.text"></span>
                                            <span class="pdn-tag pdn-gv-tag--warn" x-show="!f.aktiv">inaktiv</span>
                                        </div>
                                        <div class="pdn-gv-stufenchips">
                                            <span class="pdn-gv-chip pdn-gv-chip--alle" x-show="!f.stufen.length">alle Stufen</span>
                                            <template x-for="name in stufenNamen(f)" :key="name">
                                                <span class="pdn-gv-chip" x-text="name"></span>
                                            </template>
                                        </div>
                                    </div>
                                    <div class="pdn-gv-zeile__aktionen">
                                        <button type="button" class="pdn-btn" @click="frageBearbeiten(f)">
                                            <i class="fas fa-pen" aria-hidden="true"></i> <span class="pdn-gv-text">Bearbeiten</span>
                                        </button>
                                    </div>
                                </li>
                            </template>
                        </ol>
                    </section>
                </div>
            </div>
        @endif
    </div>

    {{-- ═══════════ Seitenleiste: Stufe ═══════════ --}}
    <template x-if="stufe">
        <div>
            <div class="pdn-drawer-backdrop" @click="stufe = null"></div>
            <aside class="pdn-drawer" role="dialog" aria-modal="true" aria-labelledby="gvStufeTitel" @keydown.escape.window="stufe = null">
                <header>
                    <h2 id="gvStufeTitel" x-text="stufe.id ? 'Stufe bearbeiten' : 'Neue Stufe'"></h2>
                    <button type="button" class="pdn-iconbtn" aria-label="Schließen" @click="stufe = null"><i class="fas fa-times" aria-hidden="true"></i></button>
                </header>
                <form id="gvStufeForm" method="post" :action="stufe.id ? stufe.speichernUrl : system.stufeNeuUrl" enctype="multipart/form-data" @submit="merken()">
                    @csrf
                    <input type="hidden" name="sort_order" :value="stufe.sort_order">
                    <label class="pdn-field">Name
                        <input type="text" name="name" x-model="stufe.name" required maxlength="100" placeholder="z. B. Anfänger" x-init="$nextTick(() => $el.focus())">
                    </label>
                    <label class="pdn-field">Kürzel (Slug, optional)
                        <input type="text" name="slug" x-model="stufe.slug" maxlength="100" placeholder="z. B. anfaenger">
                    </label>
                    <div class="pdn-gv-bildwahl">
                        <span class="pdn-stage pdn-gv-stufenbild pdn-gv-stufenbild--gross" :class="{ 'pdn-stage--bild': vorschau || stufe.bild }">
                            <template x-if="vorschau || stufe.bild"><img :src="vorschau || stufe.bild" alt="Vorschau"></template>
                            <template x-if="!(vorschau || stufe.bild) && stufe.symbol"><i :class="stufe.symbol" aria-hidden="true"></i></template>
                            <template x-if="!(vorschau || stufe.bild) && !stufe.symbol"><i class="fas fa-image" aria-hidden="true"></i></template>
                        </span>
                        <div class="pdn-field" style="flex:1;min-width:0">
                            <span>Bild (optional, PNG/JPG/WebP/GIF)</span>
                            <input type="file" name="image" accept="image/png,image/jpeg,image/webp,image/gif" @change="bildGewaehlt($event)">
                        </div>
                    </div>
                    <label class="pdn-field">Symbol statt Bild (Font-Awesome-Klasse, optional)
                        <input type="text" name="symbol" x-model="stufe.symbol" maxlength="255" placeholder="z. B. fas fa-star">
                    </label>
                    <label class="pdn-switch">
                        <span>Standardstufe <small class="pdn-label-sm" style="display:block">Neue Schüler erhalten diese Stufe.</small></span>
                        <input type="hidden" name="is_default" value="0">
                        <input type="checkbox" name="is_default" value="1" x-model="stufe.standard"><i></i>
                    </label>
                    <p class="pdn-hinweis" x-show="stufe.id" x-text="fragenText(stufe)"></p>
                </form>
                <footer>
                    <button type="submit" form="gvStufeForm" class="pdn-btn pdn-btn--pri"><i class="fas fa-save" aria-hidden="true"></i> Speichern</button>
                    <button type="button" class="pdn-btn" @click="stufe = null">Abbrechen</button>
                    <span class="pdn-spacer"></span>
                    <button type="button" class="pdn-btn pdn-gv-gefahr" x-show="stufe.id" @click="loeschenFragen('stufe', stufe)">
                        <i class="fas fa-trash" aria-hidden="true"></i> Löschen
                    </button>
                </footer>
            </aside>
        </div>
    </template>

    {{-- ═══════════ Seitenleiste: Frage ═══════════ --}}
    <template x-if="frage">
        <div>
            <div class="pdn-drawer-backdrop" @click="frage = null"></div>
            <aside class="pdn-drawer" role="dialog" aria-modal="true" aria-labelledby="gvFrageTitel" @keydown.escape.window="frage = null">
                <header>
                    <h2 id="gvFrageTitel" x-text="frage.id ? 'Frage bearbeiten' : 'Neue Frage'"></h2>
                    <button type="button" class="pdn-iconbtn" aria-label="Schließen" @click="frage = null"><i class="fas fa-times" aria-hidden="true"></i></button>
                </header>
                <form id="gvFrageForm" method="post" :action="frage.id ? frage.speichernUrl : system.frageNeuUrl" @submit="merken()">
                    @csrf
                    <input type="hidden" name="sort_order" :value="frage.sort_order">
                    <label class="pdn-field">Frage
                        <textarea name="question" x-model="frage.text" required maxlength="1000" rows="3"
                                  placeholder="z. B. Ich kann einen kurzen Text allein lesen." x-init="$nextTick(() => $el.focus())"></textarea>
                    </label>
                    <div class="pdn-field">
                        <span>Gilt für Stufen</span>
                        <template x-if="system.stufen.length">
                            <div class="pdn-chips" role="group" aria-label="Gilt für Stufen">
                                <template x-for="st in system.stufen" :key="'c' + st.id">
                                    <label>
                                        <input type="checkbox" name="stage_ids[]" :value="st.id" :checked="frage.stufen.includes(st.id)"
                                               @change="stufeUmschalten(st.id, $event.target.checked)">
                                        <span x-text="st.name"></span>
                                    </label>
                                </template>
                            </div>
                        </template>
                        <span class="pdn-hinweis" x-text="frage.stufen.length ? 'Nur Kinder dieser Stufen sehen die Frage.' : 'Keine Stufe gewählt: die Frage gilt für alle Stufen.'"></span>
                    </div>
                    <template x-if="frage.id">
                        <label class="pdn-switch">
                            <span>Frage ist aktiv <small class="pdn-label-sm" style="display:block">Inaktive Fragen werden nicht mehr gestellt; Antworten bleiben erhalten.</small></span>
                            <input type="hidden" name="active" value="0">
                            <input type="checkbox" name="active" value="1" x-model="frage.aktiv"><i></i>
                        </label>
                    </template>
                </form>
                <footer>
                    <button type="submit" form="gvFrageForm" class="pdn-btn pdn-btn--pri"><i class="fas fa-save" aria-hidden="true"></i> Speichern</button>
                    <button type="button" class="pdn-btn" @click="frage = null">Abbrechen</button>
                    <span class="pdn-spacer"></span>
                    <button type="button" class="pdn-btn pdn-gv-gefahr" x-show="frage.id" @click="loeschenFragen('frage', frage)">
                        <i class="fas fa-trash" aria-hidden="true"></i> Löschen
                    </button>
                </footer>
            </aside>
        </div>
    </template>

    {{-- ═══════════ Dialog: Neues System ═══════════ --}}
    <template x-if="systemDialog">
        <div class="pdn-dialog" role="dialog" aria-modal="true" aria-labelledby="gvSystemTitel" @keydown.escape.window="systemDialog = false">
            <form method="post" action="{{ route('admin.grading.system.store') }}">
                @csrf
                <h2 id="gvSystemTitel" class="pdn-gv-dialogtitel">Neues Graduierungssystem</h2>
                <label class="pdn-field">Name
                    <input type="text" name="name" required maxlength="100" placeholder="z. B. Lesestufen" x-init="$nextTick(() => $el.focus())">
                </label>
                <label class="pdn-field">Kürzel (Slug, optional)
                    <input type="text" name="slug" maxlength="100" placeholder="z. B. lesestufen">
                </label>
                <p class="pdn-hinweis">Klassen wählen ihr Graduierungssystem in den Klasseneinstellungen.</p>
                <div class="pdn-gv-dialogknoepfe">
                    <button type="button" class="pdn-btn" @click="systemDialog = false">Abbrechen</button>
                    <button type="submit" class="pdn-btn pdn-btn--pri"><i class="fas fa-plus" aria-hidden="true"></i> Anlegen</button>
                </div>
            </form>
        </div>
    </template>

    {{-- ═══════════ Dialog: Löschen bestätigen ═══════════ --}}
    <template x-if="loeschen">
        <div class="pdn-dialog" role="alertdialog" aria-modal="true" aria-labelledby="gvLoeschenTitel" @keydown.escape.window="loeschen = null">
            <form method="post" :action="loeschen.url" @submit="merken()">
                @csrf
                <h2 id="gvLoeschenTitel" class="pdn-gv-dialogtitel" x-text="loeschen.titel"></h2>
                <p x-text="loeschen.text"></p>
                <div class="pdn-gv-dialogknoepfe">
                    <button type="button" class="pdn-btn" @click="loeschen = null">Abbrechen</button>
                    <button type="submit" class="pdn-btn pdn-gv-gefahr--voll"><i class="fas fa-trash" aria-hidden="true"></i> Löschen</button>
                </div>
            </form>
        </div>
    </template>
</div>
@endsection

@push('js')
    @vite(['resources/js/graduierung-verwaltung.js'])
@endpush
