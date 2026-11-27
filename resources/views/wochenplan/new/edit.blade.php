@extends('layouts.app')


@push('css')
    @vite('resources/css/wochenplan.css')
@endpush

@php
    $config = [
        'basisUrl' => url('wp'),
        'faecher' => $alleFaecher->map(fn ($f) => ['id' => $f->id, 'name' => $f->name])->values(),
        'formatvorlagen' => $formatvorlagen->map(fn ($f) => ['id' => $f->id, 'name' => $f->name])->values(),
        'klassen' => $klassen->map(fn ($k) => ['id' => $k->id, 'name' => $k->name])->values(),
        'niveaus' => \App\Models\Wochenplan\WpPlan::NIVEAUS,
        'tagebuchAktiv' => $tagebuchAktiv,
        'tagebuchAufgaben' => $diaryTasks->map(fn ($t) => [
            'id' => $t->id,
            'title' => $t->title,
            'description' => $t->description,
            'due_date' => $t->due_date?->format('d.m.Y'),
            'ueberfaellig' => $t->due_date?->isPast() ?? false,
            'highlighted' => (bool) $t->highlighted,
        ])->values(),
        'klassenstufe' => \App\Services\Wochenplan\WochenplanService::klassenstufe($daten['klasse']),
    ];
@endphp

@section('content')
<div class="wp-wrapper" x-data="wpEditor(@js($daten), @js($config))" x-cloak>

    {{-- Zurück --}}
    <template x-if="plan.eltern">
        <a :href="`${config.basisUrl}/${plan.eltern.id}/edit`" class="wp-back">← Zurück zum Klassenplan</a>
    </template>
    <template x-if="!plan.eltern">
        <a href="{{ $wpPlan->isVorlage() ? route('wp.vorlagen.index') : route('wp.index') }}" class="wp-back">← {{ $wpPlan->isVorlage() ? 'Vorlagen' : 'Übersicht' }}</a>
    </template>

    {{-- Kopf --}}
    <div class="wp-card mb-4" data-tour="wp-kopf">
        <div class="wp-card-body">
            <div class="flex flex-wrap items-start justify-between gap-4">
                <div class="min-w-0">
                    <div class="flex flex-wrap items-center gap-2 mb-1">
                        <span class="wp-badge"
                              :class="plan.ist_vorlage ? 'wp-badge-violet' : (plan.ist_kinderplan ? 'wp-badge-amber' : 'wp-badge-green')"
                              x-text="plan.typ"></span>
                        <template x-if="plan.niveau">
                            <span class="wp-niveau" x-text="'Niveau: ' + niveauName(plan.niveau)"></span>
                        </template>
                        <template x-if="ablageText()">
                            <span class="wp-badge" :class="ablageText()[1]" x-text="ablageText()[0]" :title="plan.ablage.fehler || ''"></span>
                        </template>
                        <template x-if="plan.ablage?.link">
                            <a :href="plan.ablage.link" target="_blank" rel="noopener" class="text-xs font-semibold text-blue-600 hover:text-blue-800">In Nextcloud öffnen ↗</a>
                        </template>
                    </div>
                    <h1 class="wp-title" x-text="plan.name"></h1>
                    <div class="wp-subtitle flex flex-wrap gap-x-3 gap-y-1">
                        <span x-show.important="plan.klasse" x-text="'Klasse ' + plan.klasse"></span>
                        <span x-show.important="plan.schueler" x-text="plan.schueler?.name"></span>
                        <span x-show.important="plan.zeitraum && !plan.ist_vorlage" x-text="plan.zeitraum"></span>
                        <span x-text="anzahlAufgaben + ' Aufgaben'"></span>
                    </div>
                    <template x-if="plan.eltern">
                        <p class="text-sm text-gray-600 mt-1">
                            Basiert auf <a :href="`${config.basisUrl}/${plan.eltern.id}/edit`" class="font-semibold text-blue-600 hover:text-blue-800" x-text="plan.eltern.name"></a>
                        </p>
                    </template>
                </div>
                <template x-if="darf">
                    <button type="button" class="wp-btn wp-btn-secondary wp-btn-sm" @click="metaOffen = !metaOffen; metaZuruecksetzen()">
                        <span x-text="metaOffen ? 'Schließen' : 'Eckdaten bearbeiten'"></span>
                    </button>
                </template>
            </div>

            {{-- Eckdaten --}}
            <form x-show.important="metaOffen" x-transition class="mt-4 pt-4 border-t border-gray-100" @submit.prevent="metaSpeichern()">
                <div class="grid gap-4 md:grid-cols-2">
                    <div class="md:col-span-2">
                        <label class="wp-label" for="wp-name">Bezeichnung</label>
                        <input id="wp-name" type="text" class="wp-input" x-model="meta.name" required maxlength="255">
                    </div>
                    <template x-if="plan.ist_vorlage">
                        <div class="md:col-span-2">
                            <label class="wp-label" for="wp-vorlagenname">Vorlagenname</label>
                            <input id="wp-vorlagenname" type="text" class="wp-input" x-model="meta.vorlage_name" maxlength="255">
                        </div>
                    </template>
                    <div>
                        <label class="wp-label" for="wp-von">Gültig von</label>
                        <input id="wp-von" type="date" class="wp-input" x-model="meta.gueltig_von" required :disabled="plan.ist_kinderplan && plan.eltern">
                    </div>
                    <div>
                        <label class="wp-label" for="wp-bis">Gültig bis</label>
                        <input id="wp-bis" type="date" class="wp-input" x-model="meta.gueltig_bis" required :disabled="plan.ist_kinderplan && plan.eltern">
                    </div>
                    <div>
                        <label class="wp-label" for="wp-selbst">Selbsteinschätzung</label>
                        <select id="wp-selbst" class="wp-select" x-model.number="meta.selbsteinschaetzung">
                            <option value="0">Keine</option>
                            <option value="1">Smileys</option>
                            <option value="2">Skala 1–10</option>
                        </select>
                    </div>
                    <div>
                        <label class="wp-label" for="wp-format">Formatvorlage</label>
                        <select id="wp-format" class="wp-select" x-model="meta.formatvorlage_id">
                            <option value="">Standard</option>
                            <template x-for="f in config.formatvorlagen" :key="f.id">
                                <option :value="f.id" x-text="f.name" :selected="Number(meta.formatvorlage_id) === f.id"></option>
                            </template>
                        </select>
                    </div>
                    <template x-if="plan.ist_klassenplan && plan.klasse_id">
                        <div>
                            <label class="wp-label" for="wp-klasse">Klasse</label>
                            <select id="wp-klasse" class="wp-select" x-model="meta.klasse_id" required>
                                <template x-for="k in config.klassen" :key="k.id">
                                    <option :value="k.id" x-text="k.name" :selected="Number(meta.klasse_id) === k.id"></option>
                                </template>
                            </select>
                        </div>
                    </template>
                    <template x-if="plan.ist_kinderplan">
                        <div>
                            <label class="wp-label" for="wp-niveau">Niveau des Kindes</label>
                            <select id="wp-niveau" class="wp-select" x-model="meta.niveau">
                                <option value="">alle Aufgaben</option>
                                <template x-for="(name, n) in config.niveaus" :key="n">
                                    <option :value="n" x-text="name" :selected="String(meta.niveau) === String(n)"></option>
                                </template>
                            </select>
                            <p class="wp-hint">Wirkt beim nächsten Abgleich mit dem Klassenplan.</p>
                        </div>
                    </template>
                </div>
                <p x-show.important="plan.ist_kinderplan && plan.eltern" class="wp-hint mt-2">Der Zeitraum eines Kinderplans folgt seinem Klassenplan.</p>
                <div class="flex gap-2 mt-4">
                    <button type="submit" class="wp-btn wp-btn-primary" :disabled="busy">Speichern</button>
                    <button type="button" class="wp-btn wp-btn-secondary" @click="metaOffen = false; metaZuruecksetzen()">Abbrechen</button>
                </div>
            </form>
        </div>

        {{-- Aktionen --}}
        <div class="flex flex-wrap items-center gap-2 border-t border-gray-100 px-4 py-3 sm:px-5" data-tour="wp-aktionen">
            <div class="relative" x-data="{ pdf: false }">
                <button type="button" class="wp-btn wp-btn-danger wp-btn-sm" @click="pdf = !pdf" @click.outside="pdf = false">📄 PDF ▾</button>
                <div x-show.important="pdf" x-transition class="absolute left-0 z-30 mt-1 w-64 rounded-xl border border-gray-200 bg-white py-1 shadow-lg">
                    <a :href="`${config.basisUrl}/export/${plan.id}/pdf`" target="_blank" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-50">
                        PDF <span x-show.important="plan.dateien.length" x-text="'mit ' + plan.dateien.length + ' Arbeitsblatt/-blättern'"></span>
                    </a>
                    <a x-show.important="plan.dateien.length" :href="`${config.basisUrl}/export/${plan.id}/pdf?attachments=0`" target="_blank" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-50">PDF ohne Arbeitsblätter</a>
                </div>
            </div>
            <a :href="`${config.basisUrl}/export/${plan.id}/word`" class="wp-btn wp-btn-primary wp-btn-sm">📝 Word</a>
            <a :href="`${config.basisUrl}/export/${plan.id}/vorschau`" target="_blank" class="wp-btn wp-btn-secondary wp-btn-sm">👁 Vorschau</a>
            @if($darfFortschritt)
                <a href="{{ route('wp.fortschritt', $wpPlan) }}" class="wp-btn wp-btn-success wp-btn-sm" data-tour="wp-fortschritt">✓ Fortschritt</a>
            @endif

            <template x-if="darf">
                <div class="flex flex-wrap items-center gap-2">
                    <template x-if="!plan.ist_vorlage">
                        <button type="button" class="wp-btn wp-btn-secondary wp-btn-sm" @click="dialog = 'fortschreiben'" data-tour="wp-fortschreiben">⏭ Nächste Woche</button>
                    </template>
                    <form method="POST" action="{{ route('wp.duplizieren', $wpPlan) }}">
                        @csrf
                        <button type="submit" class="wp-btn wp-btn-secondary wp-btn-sm">⧉ Duplizieren</button>
                    </form>
                    <template x-if="!plan.ist_vorlage">
                        <button type="button" class="wp-btn wp-btn-secondary wp-btn-sm" @click="vorlageName = plan.name; dialog = 'vorlage'">💾 Als Vorlage</button>
                    </template>
                </div>
            </template>
            <div class="ml-auto flex items-center gap-2">
                <a href="{{ route('wp.hilfe') }}" class="wp-btn wp-btn-ghost wp-btn-sm">❓ Hilfe</a>
                <template x-if="darf">
                    <button type="button" class="wp-btn wp-btn-danger-ghost wp-btn-sm" @click="dialog = 'loeschen'">🗑 Löschen</button>
                </template>
            </div>
        </div>
    </div>

    @unless($darfBearbeiten)
        <div class="wp-note wp-note-info mb-4">Sie sehen diesen Plan nur zum Lesen.</div>
    @endunless

    {{-- Kinderplan: Abgleich --}}
    <template x-if="plan.ist_kinderplan && plan.eltern && darf">
        <div class="wp-note wp-note-info mb-4 justify-between" data-tour="wp-abgleich">
            <div>
                <strong>Abgleich mit dem Klassenplan.</strong>
                Neue und geänderte Aufgaben des Klassenplans übernehmen – angepasste und eigene Aufgaben bleiben auf Wunsch erhalten, Häkchen bleiben bestehen.
            </div>
            <button type="button" class="wp-btn wp-btn-primary wp-btn-sm shrink-0" @click="abgleichOeffnen()">🔄 Abgleich prüfen</button>
        </div>
    </template>

    {{-- Klassenplan: Kinderpläne --}}
    <template x-if="plan.ist_klassenplan">
        <section class="wp-card mb-4" data-tour="wp-kinderplaene">
            <div class="wp-card-head">
                <h2 class="wp-card-title">👤 Kinderpläne <span class="wp-badge wp-badge-gray" x-text="plan.kinderplaene.length"></span></h2>
                <div class="wp-btn-group">
                    <template x-if="plan.kinderplaene.length">
                        <div class="wp-btn-group">
                            <a :href="`${config.basisUrl}/export/${plan.id}/kinderplaene-pdf`" class="wp-btn wp-btn-secondary wp-btn-sm">Alle als ein PDF</a>
                            <a :href="`${config.basisUrl}/export/${plan.id}/kinderplaene-zip`" class="wp-btn wp-btn-secondary wp-btn-sm">ZIP (PDF + Word)</a>
                        </div>
                    </template>
                    <template x-if="darf">
                        <a href="{{ route('wp.schuelerplan.create', $wpPlan) }}" class="wp-btn wp-btn-warning wp-btn-sm">+ Kinderplan erstellen</a>
                    </template>
                </div>
            </div>
            <div class="wp-card-body">
                <p x-show.important="!plan.kinderplaene.length" class="text-sm text-gray-500">Noch keine Kinderpläne. Ein Kinderplan übernimmt alle Aufgaben dieses Plans und kann danach individuell angepasst werden.</p>
                <ul class="grid gap-2 sm:grid-cols-2 lg:grid-cols-3">
                    <template x-for="k in plan.kinderplaene" :key="k.id">
                        <li class="flex items-center justify-between gap-2 rounded-xl border border-gray-200 bg-gray-50 px-3 py-2">
                            <div class="min-w-0">
                                <a :href="k.url" class="block truncate text-sm font-semibold text-gray-900 hover:text-blue-700" x-text="k.name"></a>
                                <span class="text-xs text-gray-500" x-text="[k.niveau ? 'Niveau ' + niveauName(k.niveau) : null, k.formatvorlage].filter(Boolean).join(' · ')"></span>
                            </div>
                            <a :href="k.pdf" target="_blank" class="text-xs font-semibold text-red-600 hover:text-red-800">PDF</a>
                        </li>
                    </template>
                </ul>
            </div>
        </section>
    </template>

    <div class="grid gap-4 lg:grid-cols-3">
        <div class="lg:col-span-2 space-y-4">

            {{-- Tägliche Übungen --}}
            <section class="wp-card">
                <div class="wp-card-head">
                    <h2 class="wp-card-title">🔁 Tägliche Übungen</h2>
                    <template x-if="darf">
                        <label class="wp-switch">
                            <input type="checkbox" :checked="plan.taegliche_uebungen_aktiv" @change="uebungenSchalten($event.target.checked)">
                            <span class="wp-switch-track"></span>
                            <span class="text-sm text-gray-600" x-text="plan.taegliche_uebungen_aktiv ? 'aktiv' : 'aus'"></span>
                        </label>
                    </template>
                </div>
                <div class="wp-card-body" x-show.important="plan.taegliche_uebungen_aktiv">
                    <p class="wp-hint mb-2">Erscheinen im PDF als Tabelle mit einem Kästchen je Wochentag.</p>
                    <ul x-init="sortierbarUebungen($el)">
                        <template x-for="u in plan.uebungen" :key="u.id">
                            <li class="wp-aufgabe" :data-uebung-id="u.id">
                                <span x-show.important="darf" class="wp-griff" data-griff-uebung title="Ziehen zum Sortieren">⠿</span>
                                <template x-if="uebungBearbeiten?.id !== u.id">
                                    <span class="flex-1 text-sm text-gray-800" x-text="u.aufgabe"></span>
                                </template>
                                <template x-if="uebungBearbeiten?.id === u.id">
                                    <form class="flex flex-1 gap-2" @submit.prevent="uebungSpeichern()">
                                        <input type="text" class="wp-input wp-input-sm" x-model="uebungBearbeiten.aufgabe" maxlength="1000" @keydown.escape="uebungBearbeiten = null">
                                        <button type="submit" class="wp-btn wp-btn-primary wp-btn-sm">OK</button>
                                    </form>
                                </template>
                                <div x-show.important="darf && uebungBearbeiten?.id !== u.id" class="wp-aufgabe-aktionen">
                                    <button type="button" class="wp-btn-icon" title="Bearbeiten" @click="uebungBearbeiten = { id: u.id, aufgabe: u.aufgabe, basis: u.aufgabe }">✎</button>
                                    <button type="button" class="wp-btn-icon is-danger" title="Löschen" @click="uebungLoeschen(u)">🗑</button>
                                </div>
                            </li>
                        </template>
                    </ul>
                    <p x-show.important="!plan.uebungen.length" class="text-sm text-gray-400">Noch keine Übungen.</p>
                    <form x-show.important="darf" class="mt-3 flex gap-2" @submit.prevent="uebungAnlegen()">
                        <input type="text" class="wp-input wp-input-sm" x-model="uebungNeu" placeholder="Neue Übung, z. B. 10 Minuten lesen" maxlength="1000">
                        <button type="submit" class="wp-btn wp-btn-primary wp-btn-sm" :disabled="!uebungNeu.trim()">Hinzufügen</button>
                    </form>
                </div>
            </section>

            {{-- Fächer & Aufgaben --}}
            <section data-tour="wp-faecher">
                <div class="mb-2 flex items-center justify-between">
                    <h2 class="wp-section-title">Fächer &amp; Aufgaben</h2>
                    <span x-show.important="darf" class="text-xs text-gray-400">Fächer und Aufgaben lassen sich am Griff ⠿ verschieben – auch zwischen Fächern.</span>
                </div>

                <div x-init="sortierbarFaecher($el)">
                    <template x-for="fach in plan.faecher" :key="fach.id">
                        <div class="wp-fach" :class="zu[fach.id] && 'is-zu'" :data-fach-id="fach.id">
                            <div class="wp-fach-head">
                                <span x-show.important="darf" class="wp-griff" data-griff-fach title="Fach verschieben">⠿</span>
                                <button type="button" class="flex flex-1 items-center gap-2 text-left min-w-0" @click="zu[fach.id] = !zu[fach.id]">
                                    <span class="text-gray-400 text-xs" x-text="zu[fach.id] ? '▸' : '▾'"></span>
                                    <span x-html="fach.symbol"></span>
                                    <span class="font-semibold text-gray-800 truncate" x-text="fach.name"></span>
                                    <span class="text-xs text-gray-400" x-text="'(' + fach.aufgaben.length + ')'"></span>
                                </button>
                                <template x-if="darf">
                                    <div class="flex items-center gap-0.5">
                                        <button type="button" class="wp-btn-icon" title="Aus der Bibliothek einfügen" @click="bibliothekOeffnen(fach)">📚</button>
                                        <template x-if="plan.ist_kinderplan && plan.eltern && fach.wp_fach_id">
                                            <button type="button" class="wp-btn-icon" title="Nur dieses Fach abgleichen" @click="abgleichOeffnen(fach.wp_fach_id)">🔄</button>
                                        </template>
                                        <button type="button" class="wp-btn-icon is-danger" title="Fach entfernen" @click="fachEntfernen(fach)">🗑</button>
                                    </div>
                                </template>
                            </div>

                            <div x-show.important="!zu[fach.id]">
                                <ul class="wp-aufgaben" :data-fach-liste="fach.id" x-init="sortierbarAufgaben($el)">
                                    <template x-for="a in fach.aufgaben" :key="a.id">
                                        <li class="wp-aufgabe" :data-aufgabe-id="a.id">
                                            <span x-show.important="darf" class="wp-griff mt-0.5" data-griff-aufgabe title="Aufgabe verschieben">⠿</span>

                                            {{-- Anzeige --}}
                                            <template x-if="bearbeiten?.id !== a.id">
                                                <div class="flex-1 min-w-0">
                                                    <span class="text-sm text-gray-800 whitespace-pre-line" x-text="a.aufgabe"></span>
                                                    <span x-show.important="a.dauer" class="ml-1 text-xs italic text-gray-400" x-text="'(' + a.dauer + ')'"></span>
                                                    <div class="mt-0.5 flex flex-wrap items-center gap-1">
                                                        <span x-show.important="a.niveau" class="wp-niveau" x-text="niveauName(a.niveau)"></span>
                                                        <template x-if="a.link">
                                                            <a :href="a.link" target="_blank" rel="noopener" class="text-xs text-blue-600 hover:text-blue-800" title="Link (im PDF als QR-Code)">🔗 Link</a>
                                                        </template>
                                                        <template x-if="plan.ist_kinderplan && plan.eltern">
                                                            <span class="wp-badge" :class="a.eigene ? 'wp-badge-green' : (a.angepasst ? 'wp-badge-amber' : 'wp-badge-blue')"
                                                                  x-text="a.eigene ? 'eigene' : (a.angepasst ? 'angepasst' : 'aus Klassenplan')"></span>
                                                        </template>
                                                        <template x-if="a.tagebuch">
                                                            <span class="wp-badge wp-badge-violet" :title="'Tagebuch-Aufgabe: ' + (a.tagebuch.status === 'closed' ? 'erledigt' : 'offen')">📓 Tagebuch</span>
                                                        </template>
                                                    </div>
                                                </div>
                                            </template>

                                            {{-- Bearbeiten --}}
                                            <template x-if="bearbeiten?.id === a.id">
                                                <form class="flex-1 space-y-2" @submit.prevent="aufgabeSpeichern()" @keydown.escape="bearbeiten = null">
                                                    <textarea :id="'wp-edit-' + a.id" class="wp-textarea" rows="2" x-model="bearbeiten.aufgabe" maxlength="2000" required></textarea>
                                                    <div class="grid gap-2 sm:grid-cols-3">
                                                        <input type="text" class="wp-input wp-input-sm" x-model="bearbeiten.dauer" placeholder="Dauer" maxlength="50">
                                                        <select class="wp-select wp-input-sm" x-model="bearbeiten.niveau">
                                                            <option value="">alle Niveaus</option>
                                                            <template x-for="(name, n) in config.niveaus" :key="n"><option :value="n" x-text="name" :selected="String(bearbeiten.niveau) === String(n)"></option></template>
                                                        </select>
                                                        <input type="url" class="wp-input wp-input-sm" x-model="bearbeiten.link" placeholder="Link (https://…)" maxlength="500">
                                                    </div>
                                                    <div class="flex gap-2">
                                                        <button type="submit" class="wp-btn wp-btn-primary wp-btn-sm" :disabled="busy">Speichern</button>
                                                        <button type="button" class="wp-btn wp-btn-secondary wp-btn-sm" @click="bearbeiten = null">Abbrechen</button>
                                                    </div>
                                                </form>
                                            </template>

                                            <div x-show.important="darf && bearbeiten?.id !== a.id" class="wp-aufgabe-aktionen">
                                                <button type="button" class="wp-btn-icon" title="Bearbeiten" @click="aufgabeStarten(a)">✎</button>
                                                <button type="button" class="wp-btn-icon" title="In die Bibliothek übernehmen" @click="inBibliothek(a)">📚</button>
                                                <button type="button" class="wp-btn-icon is-danger" title="Löschen" @click="aufgabeLoeschen(a)">🗑</button>
                                            </div>
                                        </li>
                                    </template>
                                </ul>
                                <p x-show.important="!fach.aufgaben.length" class="px-4 pb-2 text-xs italic text-gray-400">Noch keine Aufgaben.</p>

                                {{-- Aufgabe hinzufügen --}}
                                <template x-if="darf && neu[fach.id]">
                                    <form class="border-t border-gray-100 px-3 py-2 sm:px-4" @submit.prevent="aufgabeAnlegen(fach)">
                                        <div class="flex gap-2">
                                            <input :id="'wp-neu-' + fach.id" type="text" class="wp-input wp-input-sm" x-model="neu[fach.id].aufgabe"
                                                   placeholder="+ Aufgabe hinzufügen …" maxlength="2000" @focus="neu[fach.id].offen = true">
                                            <button type="submit" class="wp-btn wp-btn-primary wp-btn-sm" :disabled="busy || !neu[fach.id].aufgabe.trim()">Hinzufügen</button>
                                        </div>
                                        <div x-show.important="neu[fach.id].offen" class="mt-2 grid gap-2 sm:grid-cols-3">
                                            <input type="text" class="wp-input wp-input-sm" x-model="neu[fach.id].dauer" placeholder="Dauer (optional)" maxlength="50">
                                            <select class="wp-select wp-input-sm" x-model="neu[fach.id].niveau">
                                                <option value="">alle Niveaus</option>
                                                <template x-for="(name, n) in config.niveaus" :key="n"><option :value="n" x-text="name"></option></template>
                                            </select>
                                            <input type="url" class="wp-input wp-input-sm" x-model="neu[fach.id].link" placeholder="Link (optional)" maxlength="500">
                                        </div>
                                    </form>
                                </template>
                            </div>
                        </div>
                    </template>
                </div>

                <div x-show.important="!plan.faecher.length" class="wp-card wp-empty">
                    <div class="wp-empty-icon">📚</div>
                    Noch keine Fächer – unten ein Fach hinzufügen.
                </div>

                <template x-if="darf">
                    <form class="mt-3 flex gap-2" @submit.prevent="fachHinzufuegen()">
                        <select class="wp-select" x-model="fachNeu" aria-label="Fach hinzufügen">
                            <option value="">– Fach auswählen –</option>
                            <template x-for="f in freieFaecher" :key="f.id"><option :value="f.id" x-text="f.name"></option></template>
                        </select>
                        <button type="submit" class="wp-btn wp-btn-primary" :disabled="!fachNeu || busy">+ Fach</button>
                    </form>
                </template>
            </section>
        </div>

        {{-- Seitenspalte --}}
        <div class="space-y-4">
            {{-- Arbeitsblätter --}}
            <section class="wp-card" data-tour="wp-dateien">
                <div class="wp-card-head">
                    <h2 class="wp-card-title">📎 Arbeitsblätter</h2>
                    <template x-if="darf && hatFehler">
                        <button type="button" class="wp-btn wp-btn-secondary wp-btn-sm" @click="erneutVersuchen()">Erneut versuchen</button>
                    </template>
                </div>
                <div class="wp-card-body">
                    <template x-for="d in plan.dateien" :key="d.id">
                        <div class="wp-datei">
                            <span x-text="dateiSymbol(d)"></span>
                            <div class="flex-1 min-w-0">
                                <a :href="d.url" target="_blank" class="wp-datei-name block" x-text="d.name"></a>
                                <div class="flex flex-wrap items-center gap-1 mt-0.5">
                                    <span class="text-xs text-gray-400" x-text="d.groesse"></span>
                                    <span x-show.important="d.vom_klassenplan" class="wp-badge wp-badge-blue">vom Klassenplan</span>
                                    <span x-show.important="plan.ablage?.aktiv || d.status !== 'lokal'" class="wp-badge" :class="dateiStatus(d)[1]" x-text="dateiStatus(d)[0]" :title="d.fehler || ''"></span>
                                    <template x-if="d.nextcloud"><a :href="d.nextcloud" target="_blank" rel="noopener" class="text-xs text-blue-600">Nextcloud ↗</a></template>
                                </div>
                            </div>
                            <template x-if="darf && !d.vom_klassenplan">
                                <button type="button" class="wp-btn-icon is-danger" title="Entfernen" @click="dateiEntfernen(d)">🗑</button>
                            </template>
                        </div>
                    </template>
                    <p x-show.important="!plan.dateien.length" class="text-sm text-gray-400 mb-2">Keine Arbeitsblätter.</p>

                    <template x-if="darf">
                        <div class="mt-3 space-y-2">
                            <label class="wp-dropzone" :class="dragover && 'is-dragover'"
                                   @dragover.prevent="dragover = true" @dragleave.prevent="dragover = false"
                                   @drop.prevent="dragover = false; dateienHochladen($event.dataTransfer.files)">
                                <input type="file" multiple x-ref="dateiInput" accept=".pdf,.jpg,.jpeg,.png,.doc,.docx,.odt" @change="dateienHochladen($event.target.files)">
                                <span class="font-semibold" x-text="hochladen ? 'Wird hochgeladen …' : 'Dateien hierher ziehen oder auswählen'"></span>
                                <span class="text-xs">PDF, Bilder, Word, ODT · max. 20 MB je Datei</span>
                            </label>
                            <template x-if="plan.ablage?.aktiv">
                                <button type="button" class="wp-btn wp-btn-secondary wp-btn-sm w-full" @click="nextcloudOeffnen('')">Aus der Nextcloud wählen …</button>
                            </template>
                            <p x-show.important="plan.ist_kinderplan && plan.eltern" class="wp-hint">Arbeitsblätter des Klassenplans gelten automatisch auch hier.</p>
                        </div>
                    </template>
                </div>
            </section>

            {{-- Offene Tagebuch-Aufgaben --}}
            <template x-if="config.tagebuchAktiv && plan.ist_kinderplan && darf">
                <section class="wp-card" data-tour="wp-tagebuch">
                    <div class="wp-card-head">
                        <h2 class="wp-card-title">📓 Offene Tagebuch-Aufgaben</h2>
                        <span class="wp-badge wp-badge-amber" x-text="tagebuch.aufgaben.length"></span>
                    </div>
                    <div class="wp-card-body">
                        <p x-show.important="!tagebuch.aufgaben.length" class="text-sm text-gray-400">Keine offenen Aufgaben im Tagebuch.</p>
                        <ul class="space-y-2">
                            <template x-for="t in tagebuch.aufgaben" :key="t.id">
                                <li class="rounded-xl border px-3 py-2" :class="t.highlighted ? 'border-amber-300 bg-amber-50' : 'border-gray-200'">
                                    <div class="flex items-start justify-between gap-2">
                                        <div class="min-w-0">
                                            <p class="text-sm font-semibold text-gray-800" x-text="t.title"></p>
                                            <p x-show.important="t.due_date" class="text-xs" :class="t.ueberfaellig ? 'text-red-600 font-semibold' : 'text-gray-500'" x-text="'Fällig: ' + t.due_date"></p>
                                        </div>
                                        <button type="button" class="wp-btn wp-btn-primary wp-btn-sm" x-show.important="tagebuch.offen !== t.id" @click="tagebuchStarten(t)">Übernehmen</button>
                                    </div>
                                    <form x-show.important="tagebuch.offen === t.id" class="mt-2 space-y-2" @submit.prevent="tagebuchUebernehmen(t)">
                                        <select class="wp-select wp-input-sm" x-model="tagebuch.fachId" required>
                                            <template x-for="f in plan.faecher" :key="f.id"><option :value="f.id" x-text="f.name"></option></template>
                                        </select>
                                        <input type="text" class="wp-input wp-input-sm" x-model="tagebuch.text" maxlength="2000" required>
                                        <input type="text" class="wp-input wp-input-sm" x-model="tagebuch.dauer" placeholder="Dauer (optional)" maxlength="50">
                                        <div class="flex gap-2">
                                            <button type="submit" class="wp-btn wp-btn-primary wp-btn-sm">In den Plan</button>
                                            <button type="button" class="wp-btn wp-btn-secondary wp-btn-sm" @click="tagebuch.offen = null">Abbrechen</button>
                                        </div>
                                    </form>
                                </li>
                            </template>
                        </ul>
                        <p class="wp-hint mt-2">Erledigt im Wochenplan ⇄ erledigt im Tagebuch.</p>
                    </div>
                </section>
            </template>
        </div>
    </div>

    {{-- ── Dialoge ─────────────────────────────────────────────────────────── --}}

    {{-- Nächste Woche --}}
    <div x-show.important="dialog === 'fortschreiben'" x-transition.opacity class="wp-overlay" @keydown.escape.window="dialog = null" @click.self="dialog = null">
        <form method="POST" action="{{ route('wp.fortschreiben', $wpPlan) }}" class="wp-dialog">
            @csrf
            <div class="wp-dialog-head"><h3 class="text-lg font-bold">Für die nächste Woche fortschreiben</h3></div>
            <div class="wp-dialog-body space-y-3">
                <p class="text-sm text-gray-600">Legt eine Kopie mit allen Fächern, Aufgaben und täglichen Übungen an – eine Woche später. Arbeitsblätter und Häkchen werden nicht übernommen.</p>
                <template x-if="plan.ist_klassenplan && plan.kinderplaene.length">
                    <label class="wp-check"><input type="checkbox" name="mit_kinderplaenen" value="1" x-model="mitKinderplaenen"> Kinderpläne mit ihren individuellen Aufgaben übernehmen</label>
                </template>
            </div>
            <div class="wp-dialog-foot">
                <button type="button" class="wp-btn wp-btn-secondary" @click="dialog = null">Abbrechen</button>
                <button type="submit" class="wp-btn wp-btn-primary">Fortschreiben</button>
            </div>
        </form>
    </div>

    {{-- Als Vorlage --}}
    <div x-show.important="dialog === 'vorlage'" x-transition.opacity class="wp-overlay" @keydown.escape.window="dialog = null" @click.self="dialog = null">
        <form method="POST" action="{{ route('wp.vorlagen.speichern', $wpPlan) }}" class="wp-dialog">
            @csrf
            <div class="wp-dialog-head"><h3 class="text-lg font-bold">Als Vorlage speichern</h3></div>
            <div class="wp-dialog-body">
                <label class="wp-label" for="wp-vorlage-name">Name der Vorlage</label>
                <input id="wp-vorlage-name" type="text" name="vorlage_name" class="wp-input" x-model="vorlageName" required maxlength="255">
                <p class="wp-hint">Vorlagen stehen allen Pädagog:innen zur Verfügung (ohne Klasse und Kind).</p>
            </div>
            <div class="wp-dialog-foot">
                <button type="button" class="wp-btn wp-btn-secondary" @click="dialog = null">Abbrechen</button>
                <button type="submit" class="wp-btn wp-btn-violet">Speichern</button>
            </div>
        </form>
    </div>

    {{-- Löschen --}}
    <div x-show.important="dialog === 'loeschen'" x-transition.opacity class="wp-overlay" @keydown.escape.window="dialog = null" @click.self="dialog = null">
        <form method="POST" action="{{ route('wp.destroy', $wpPlan) }}" class="wp-dialog">
            @csrf @method('DELETE')
            <div class="wp-dialog-head"><h3 class="text-lg font-bold">Plan löschen?</h3></div>
            <div class="wp-dialog-body space-y-2 text-sm text-gray-600">
                <p x-show.important="plan.ist_klassenplan && plan.kinderplaene.length" class="font-semibold text-red-700" x-text="'Auch die ' + plan.kinderplaene.length + ' Kinderpläne werden gelöscht.'"></p>
                <p x-show.important="plan.ablage?.aktiv">Der Ordner in der Nextcloud wird nach „_gelöscht“ verschoben.</p>
            </div>
            <div class="wp-dialog-foot">
                <button type="button" class="wp-btn wp-btn-secondary" @click="dialog = null">Abbrechen</button>
                <button type="submit" class="wp-btn wp-btn-danger">Löschen</button>
            </div>
        </form>
    </div>

    {{-- Abgleich --}}
    <div x-show.important="dialog === 'abgleich'" x-transition.opacity class="wp-overlay" @keydown.escape.window="dialog = null" @click.self="dialog = null">
        <div class="wp-dialog wp-dialog-lg">
            <div class="wp-dialog-head">
                <h3 class="text-lg font-bold">Abgleich mit dem Klassenplan</h3>
                <button type="button" class="wp-btn-icon" @click="dialog = null" aria-label="Schließen">✕</button>
            </div>
            <div class="wp-dialog-body">
                <p x-show.important="abgleich.laedt" class="text-sm text-gray-500">Vorschau wird geladen …</p>
                <template x-if="abgleich.vorschau">
                    <div class="space-y-4">
                        <div class="flex flex-wrap gap-2 text-xs">
                            <span class="wp-badge wp-badge-green" x-text="abgleich.vorschau.summe.neu + ' neu'"></span>
                            <span class="wp-badge wp-badge-blue" x-text="abgleich.vorschau.summe.aktualisiert + ' aktualisiert'"></span>
                            <span class="wp-badge wp-badge-red" x-text="abgleich.vorschau.summe.entfallen + ' entfallen'"></span>
                            <span class="wp-badge wp-badge-amber" x-text="abgleich.vorschau.summe.angepasst + ' angepasst'"></span>
                            <span class="wp-badge wp-badge-gray" x-text="abgleich.vorschau.summe.eigene + ' eigene'"></span>
                            <span class="wp-badge wp-badge-outline" x-text="abgleich.vorschau.summe.unveraendert + ' unverändert'"></span>
                        </div>
                        <template x-for="f in abgleich.vorschau.faecher" :key="f.wp_fach_id">
                            <div x-show.important="f.neu.length || f.aktualisiert.length || f.entfallen.length || f.angepasst.length || f.eigene.length" class="rounded-xl border border-gray-200 p-3">
                                <h4 class="mb-2 text-sm font-bold" x-text="f.name"></h4>
                                <ul class="space-y-1 text-sm">
                                    <template x-for="t in f.neu"><li class="text-emerald-700">＋ <span x-text="t"></span></li></template>
                                    <template x-for="t in f.aktualisiert"><li class="text-blue-700">↻ <span x-text="t.alt"></span> → <strong x-text="t.neu"></strong></li></template>
                                    <template x-for="t in f.entfallen"><li class="text-red-700 line-through" x-text="t"></li></template>
                                    <template x-for="t in f.angepasst"><li class="text-amber-800">✎ <span x-text="t.kind"></span> <span class="text-xs text-gray-500" x-text="'(Klassenplan: ' + t.klasse + ')'"></span></li></template>
                                    <template x-for="t in f.eigene"><li class="text-gray-600">★ <span x-text="t"></span></li></template>
                                </ul>
                            </div>
                        </template>
                        <div class="space-y-2 rounded-xl bg-gray-50 p-3">
                            <label class="wp-check"><input type="checkbox" x-model="abgleich.angepassteBehalten"> Angepasste Aufgaben behalten (sonst durch die Fassung des Klassenplans ersetzen)</label>
                            <label class="wp-check"><input type="checkbox" x-model="abgleich.eigeneBehalten"> Eigene Aufgaben des Kinderplans behalten</label>
                        </div>
                        <p class="wp-hint">Häkchen (Status je Kind) bleiben bei aktualisierten Aufgaben erhalten.</p>
                    </div>
                </template>
            </div>
            <div class="wp-dialog-foot">
                <button type="button" class="wp-btn wp-btn-secondary" @click="dialog = null">Abbrechen</button>
                <button type="button" class="wp-btn wp-btn-primary" :disabled="!abgleich.vorschau || busy" @click="abgleichen()">Abgleichen</button>
            </div>
        </div>
    </div>

    {{-- Bibliothek --}}
    <div x-show.important="dialog === 'bibliothek'" x-transition.opacity class="wp-overlay" @keydown.escape.window="dialog = null" @click.self="dialog = null">
        <div class="wp-dialog wp-dialog-lg">
            <div class="wp-dialog-head">
                <h3 class="text-lg font-bold">Aus der Aufgaben-Bibliothek einfügen</h3>
                <button type="button" class="wp-btn-icon" @click="dialog = null" aria-label="Schließen">✕</button>
            </div>
            <div class="wp-dialog-body">
                <form class="mb-3 flex flex-wrap gap-2" @submit.prevent="bibliothekSuchen()">
                    <input type="search" class="wp-input wp-input-sm flex-1" x-model="bibliothek.q" placeholder="Suchen …">
                    <select class="wp-select wp-input-sm" style="max-width:14rem" x-model="bibliothek.fachId" @change="bibliothekSuchen()">
                        <option value="">alle Fächer</option>
                        <template x-for="f in config.faecher" :key="f.id"><option :value="f.id" x-text="f.name" :selected="String(bibliothek.fachId) === String(f.id)"></option></template>
                    </select>
                    <button type="submit" class="wp-btn wp-btn-secondary wp-btn-sm">Suchen</button>
                </form>
                <p x-show.important="bibliothek.laedt" class="text-sm text-gray-500">Lädt …</p>
                <p x-show.important="!bibliothek.laedt && !bibliothek.liste.length" class="text-sm text-gray-500">Keine passenden Aufgaben. Über 📚 an einer Aufgabe übernehmen Sie sie in die Bibliothek.</p>
                <ul class="space-y-2">
                    <template x-for="b in bibliothek.liste" :key="b.id">
                        <li class="flex items-start justify-between gap-2 rounded-xl border border-gray-200 px-3 py-2">
                            <div class="min-w-0">
                                <p class="text-sm text-gray-800 whitespace-pre-line" x-text="b.aufgabe"></p>
                                <p class="text-xs text-gray-500" x-text="[b.fach, b.klassenstufe ? 'Klasse ' + b.klassenstufe : null, b.dauer, b.niveau ? niveauName(b.niveau) : null].filter(Boolean).join(' · ')"></p>
                            </div>
                            <button type="button" class="wp-btn wp-btn-primary wp-btn-sm" @click="bausteinEinfuegen(b)">Einfügen</button>
                        </li>
                    </template>
                </ul>
            </div>
        </div>
    </div>

    {{-- Nextcloud-Dateiauswahl --}}
    <div x-show.important="dialog === 'nextcloud'" x-transition.opacity class="wp-overlay" @keydown.escape.window="dialog = null" @click.self="dialog = null">
        <div class="wp-dialog wp-dialog-lg">
            <div class="wp-dialog-head">
                <h3 class="text-lg font-bold">Datei aus der Nextcloud wählen</h3>
                <button type="button" class="wp-btn-icon" @click="dialog = null" aria-label="Schließen">✕</button>
            </div>
            <div class="wp-dialog-body">
                <div class="mb-2 flex items-center gap-2 text-sm">
                    <button type="button" class="wp-btn wp-btn-secondary wp-btn-sm" :disabled="!nextcloud.pfad" @click="nextcloudHoch()">↑ Nach oben</button>
                    <code x-text="'/' + nextcloud.pfad"></code>
                </div>
                <p x-show.important="nextcloud.laedt" class="text-sm text-gray-500">Lädt …</p>
                <ul class="divide-y divide-gray-100 rounded-xl border border-gray-200">
                    <template x-for="e in nextcloud.eintraege" :key="e.pfad">
                        <li class="flex items-center gap-2 px-3 py-2 text-sm">
                            <template x-if="e.ordner">
                                <button type="button" class="flex-1 text-left font-semibold text-gray-800 hover:text-blue-700" @click="nextcloudOeffnen(e.pfad)" x-text="'📁 ' + e.name"></button>
                            </template>
                            <template x-if="!e.ordner">
                                <label class="wp-check flex-1"><input type="checkbox" :checked="nextcloud.auswahl.includes(e.pfad)" @change="nextcloudUmschalten(e)"> <span x-text="e.name"></span></label>
                            </template>
                        </li>
                    </template>
                    <li x-show.important="!nextcloud.laedt && !nextcloud.eintraege.length" class="px-3 py-3 text-sm text-gray-500">Dieser Ordner ist leer.</li>
                </ul>
                <p class="wp-hint mt-2">Verknüpfte Dateien bleiben an ihrem Ort in der Nextcloud und werden nicht kopiert.</p>
            </div>
            <div class="wp-dialog-foot">
                <button type="button" class="wp-btn wp-btn-secondary" @click="dialog = null">Abbrechen</button>
                <button type="button" class="wp-btn wp-btn-primary" :disabled="!nextcloud.auswahl.length || busy" @click="nextcloudUebernehmen()" x-text="nextcloud.auswahl.length + ' Datei(en) verknüpfen'"></button>
            </div>
        </div>
    </div>

    {{-- Meldung --}}
    <div x-show.important="toast" x-transition class="wp-toast" :class="toast && ('is-' + toast.art)" role="status" aria-live="polite">
        <span x-text="toast?.text"></span>
    </div>
</div>

<x-tour id="wochenplan-editor" :version="1" :steps="[
    ['target' => 'wp-kopf', 'title' => 'Neu: Wochenplan-Editor', 'text' => 'Alle Änderungen werden sofort gespeichert – ohne Neuladen der Seite. Bearbeitet jemand anderes gleichzeitig denselben Plan, erscheint ein Hinweis und der aktuelle Stand wird geladen.'],
    ['target' => 'wp-faecher', 'title' => 'Fächer und Aufgaben', 'text' => 'Aufgaben direkt im Fach anlegen, mit Dauer, Niveau und Link (im PDF als QR-Code). Am Griff ⠿ lassen sich Fächer und Aufgaben verschieben – auch in ein anderes Fach.'],
    $wpPlan->isKlassenplan() ? ['target' => 'wp-kinderplaene', 'title' => 'Kinderpläne', 'text' => 'Kinderpläne übernehmen die Aufgaben dieses Plans – auf Wunsch nur ein Niveau. Alle Kinderpläne gibt es als ein PDF oder als ZIP.'] : null,
    $wpPlan->isSchuelerplan() && $wpPlan->parent_plan_id ? ['target' => 'wp-abgleich', 'title' => 'Abgleich mit Vorschau', 'text' => 'Vor dem Abgleich sehen Sie, was sich ändert. Angepasste und eigene Aufgaben bleiben auf Wunsch erhalten, Häkchen bleiben bestehen.'] : null,
    ['target' => 'wp-dateien', 'title' => 'Arbeitsblätter', 'text' => 'Dateien werden zentral in der Nextcloud abgelegt (wenn eingeschaltet). Kinderpläne nutzen automatisch die Arbeitsblätter ihres Klassenplans.'],
    ['target' => 'wp-fortschritt', 'title' => 'Fortschritt je Kind', 'text' => 'Hier haken Sie pro Kind und Aufgabe ab: offen, teilweise oder erledigt – mit optionaler Notiz.'],
    ['target' => 'wp-fortschreiben', 'title' => 'Nächste Woche', 'text' => 'Legt den Plan für die Folgewoche an – auf Wunsch mit allen Kinderplänen.'],
]" />
@endsection

@push('js')
    @vite('resources/js/wochenplan.js')
@endpush
