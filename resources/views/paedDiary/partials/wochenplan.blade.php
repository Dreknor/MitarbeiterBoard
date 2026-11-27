{{--
    Wochenplan im Pädagogischen Tagebuch (beide Darstellungen): Konfiguration und Seitenleiste.
    Nur mit Tagebuch-Anbindung (Einstellung wochenplan_tagebuch_anbindung) und Leserecht am Wochenplan.
    Store: resources/js/paed-diary-v2/wochenplan.js (Alpine.store('wochenplan')).
--}}
@php
    $wpTagebuchAktiv = \App\Services\Wochenplan\WpStatusService::tagebuchAktiv()
        && auth()->user()?->can('viewAny', \App\Models\Wochenplan\WpPlan::class);
@endphp
<script type="application/json" id="wp-tagebuch-config">@json(['aktiv' => $wpTagebuchAktiv, 'basisUrl' => url('wp')])</script>

@if($wpTagebuchAktiv)
    <div x-data>
    <template x-if="$store.wochenplan && $store.wochenplan.panel">
        <div>
            <div class="wpt-overlay" @click="$store.wochenplan.schliessen()"></div>
            <aside class="wpt-panel" role="dialog" aria-modal="true" aria-labelledby="wpt-titel"
                   @keydown.escape.window="$store.wochenplan.schliessen()">
                <div class="wpt-head">
                    <div>
                        <h2 class="wpt-titel" id="wpt-titel" x-text="'Wochenplan – ' + $store.wochenplan.panel.schueler.name"></h2>
                        <p class="wpt-sub">
                            <span x-show="$store.wochenplan.panel.zusammenfassung" x-text="$store.wochenplan.text($store.wochenplan.panel.zusammenfassung)"></span>
                            · Klick: offen → teilweise → erledigt
                        </p>
                    </div>
                    <button type="button" class="wpt-x" aria-label="Schließen" @click="$store.wochenplan.schliessen()">×</button>
                </div>
                <div class="wpt-body">
                    <div class="wpt-fehler" x-show="$store.wochenplan.fehler" x-text="$store.wochenplan.fehler"></div>
                    <p class="wpt-leer" x-show="$store.wochenplan.laedt">Lädt …</p>
                    <p class="wpt-leer" x-show="!$store.wochenplan.laedt && !$store.wochenplan.panel.plaene.length">Für diese Woche gibt es keinen Wochenplan.</p>
                    <template x-for="plan in $store.wochenplan.panel.plaene" :key="plan.id">
                        <section class="wpt-plan">
                            <h3 class="wpt-plan-name">
                                <span x-text="plan.name + ' (' + plan.typ + ')'"></span>
                                <a :href="plan.url">öffnen</a>
                            </h3>
                            <template x-for="fach in plan.faecher" :key="fach.id">
                                <div>
                                    <p class="wpt-fach" x-text="fach.name"></p>
                                    <template x-for="a in fach.aufgaben" :key="a.id">
                                        <div class="wpt-aufgabe">
                                            <button type="button" class="wpt-status" :class="'is-' + a.status"
                                                    :aria-label="a.aufgabe + ': ' + a.status"
                                                    @click="$store.wochenplan.setzen(a)"
                                                    x-text="a.status === 'erledigt' ? '✓' : ''"></button>
                                            <div class="wpt-text">
                                                <span class="wpt-aufgabe-text" x-text="a.aufgabe"></span>
                                                <span class="wpt-meta">
                                                    <span x-show="a.tagebuch">📓 Tagebuch-Aufgabe · </span>
                                                    <span x-show="a.von" x-text="(a.von || '') + ', ' + (a.am || '') + ' · '"></span>
                                                    <button type="button" class="wpt-notiz-btn" @click="$store.wochenplan.notizFuer = ($store.wochenplan.notizFuer === a.id ? null : a.id)"
                                                            x-text="a.notiz ? 'Notiz: ' + a.notiz : '+ Notiz'"></button>
                                                </span>
                                                <template x-if="$store.wochenplan.notizFuer === a.id">
                                                    <input type="text" class="wpt-notiz" maxlength="255" :value="a.notiz || ''" placeholder="z. B. Seite 12 fehlt"
                                                           @keydown.enter.prevent="$store.wochenplan.setzen(a, a.status, $event.target.value); $store.wochenplan.notizFuer = null"
                                                           @blur="$store.wochenplan.setzen(a, a.status, $event.target.value); $store.wochenplan.notizFuer = null"
                                                           x-init="$nextTick(() => $el.focus())">
                                                </template>
                                            </div>
                                        </div>
                                    </template>
                                </div>
                            </template>
                        </section>
                    </template>
                </div>
            </aside>
        </div>
    </template>
    </div>
@endif
