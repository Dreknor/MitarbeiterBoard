{{--
    Neue Ansicht – offene Aufgaben und offene Notizen je Schüler (Logik: taskPanel()).
    Aufgaben öffnen sich zum Bearbeiten in der Seitenleiste (Event pdn-aufgabe-bearbeiten).
--}}
<section class="pdn-screen pdn-offen" x-data="taskPanel()" x-show="hasOpenItems" x-cloak aria-labelledby="pdnOffenTitel">
    <div class="pdn-tb">
        <h2 class="pdn-title" id="pdnOffenTitel" style="font-size:1em">Offene Aufgaben und Notizen</h2>
        <span class="pdn-spacer"></span>
        <button type="button" class="pdn-btn pdn-btn--ghost" @click="$store.diary.loadWeek()" aria-label="Aktualisieren" title="Aktualisieren">
            <i class="fas fa-sync" aria-hidden="true"></i>
        </button>
    </div>
    <div class="pdn-offen__liste">
        <template x-for="group in itemsByStudent" :key="'og' + group.stuId">
            <div class="pdn-offen__schueler">
                <div class="pdn-offen__name">
                    <b x-text="group.name"></b>
                    <button type="button" class="pdn-btn pdn-btn--ghost" style="min-height:26px;padding:0 6px"
                            @click="$dispatch('pdn-aufgabe-neu', { stuId: group.stuId })" :aria-label="'Aufgabe für ' + group.name + ' anlegen'">
                        <i class="fas fa-plus" aria-hidden="true"></i>
                    </button>
                </div>
                <template x-for="item in group.items" :key="'oi' + group.stuId + item.id">
                    <div class="pdn-pill" :class="item.is_entry ? 'pdn-pill--note' : (item.highlighted ? 'pdn-pill--task pdn-pill--wichtig' : 'pdn-pill--task')">
                        <i class="fas" :class="item.is_entry ? 'fa-comment-alt' : 'fa-tasks'" aria-hidden="true" style="margin-top:3px"></i>
                        <template x-if="item.is_entry">
                            <span x-text="trimText(item.title, 80) + (item.user ? ' · ' + item.user : '')"></span>
                        </template>
                        <template x-if="!item.is_entry">
                            <span role="button" tabindex="0" style="cursor:pointer" title="Aufgabe bearbeiten"
                                  @click="$dispatch('pdn-aufgabe-bearbeiten', item)" @keydown.enter.prevent="$dispatch('pdn-aufgabe-bearbeiten', item)"
                                  x-text="item.title + (item.due_date ? ' · ' + (new Date(item.due_date) < new Date() ? 'fällig seit ' : 'bis ') + new Date(item.due_date).toLocaleDateString('de-DE') : '')"></span>
                        </template>
                        <button type="button" x-show="item.is_entry" @click="completeEntryFromPanel(item.entry_id, group.stuId)" aria-label="Notiz abschließen" title="Notiz abschließen">✓</button>
                        <button type="button" x-show="!item.is_entry" @click="closeTask(item.id)" aria-label="Aufgabe erledigt" title="Aufgabe erledigt">✓</button>
                    </div>
                </template>
            </div>
        </template>
    </div>
</section>
