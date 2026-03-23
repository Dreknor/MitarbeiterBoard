{{--
    PaedDiary v2 – Diary-Table Partial
    Haupttabelle: Schüler × Wochentage, Einträge, Spalten, Termine
--}}
<div class="table-responsive" x-show="!$store.diary.loading" x-data="diaryTable()" x-cloak>
    <table class="table table-sm table-bordered mb-0" id="diaryTable">
        {{-- ── Tabellenkopf ─────────────────────────────────────────── --}}
        <thead class="thead-light">
            <tr>
                <th class="name_column">Schüler</th>
                <template x-for="day in $store.diary.days" :key="day.date">
                    <th class="text-center"
                        :class="{
                            'today-header': day.date === $store.diary.todayStr,
                            'ferien-header': day.is_ferien
                        }"
                        :title="day.ferien_name || ''"
                        :data-date="day.date">
                        <span x-text="day.label"></span>
                        <span x-show="day.is_ferien"> 🏖️</span>

                        {{-- Klassen-/Gruppentermine im Header --}}
                        <template x-for="apt in getHeaderAppointments(day.date)" :key="apt.id">
                            <div class="appointment-item bg-warning text-dark px-1 mb-1 rounded mt-1"
                                 style="font-size:0.7rem; cursor:pointer"
                                 :title="apt.description || apt.title"
                                 @click="$dispatch('edit-appointment', apt)">
                                <span x-text="formatAppointmentLabel(apt)"></span>
                            </div>
                        </template>
                    </th>
                </template>
            </tr>
        </thead>

        {{-- ── Tabellenkörper ───────────────────────────────────────── --}}
        <tbody>
            <template x-for="(stu, stuIndex) in $store.diary.schueler" :key="stu.id">
                <tr>
                    {{-- Klassentrennzeile im Gruppenmodus --}}
                    {{-- Hinweis: x-for + conditional divider-row in reinem Alpine schwierig,
                         daher als zusätzliche Logik in der Zelle gelöst --}}

                    {{-- Schülername --}}
                    <th class="align-top schueler_name_field" style="font-size:.72rem;">
                        <a :href="'/paed-diary/schueler/' + stu.id"
                           class="text-decoration-none"
                           title="Detailansicht öffnen">
                            <span x-text="stu.name"></span>
                            <i class="fas fa-external-link-alt small ml-1"></i>
                        </a>
                        <span class="badge badge-light ml-1" x-text="getKlasseKuerzel(stu.klasse_id)"></span>

                        {{-- Stufen-Symbol --}}
                        <span x-data="stageDropdown()" class="ml-1 position-relative" style="cursor:pointer">
                            <span @click.stop="openDropdown(stu.id, stu.klasse_id)"
                                  x-show="$store.diary.can_manage_grading"
                                  x-html="stageHtml(stu)"></span>
                            <span x-show="!$store.diary.can_manage_grading"
                                  x-html="stageHtml(stu)"></span>

                            {{-- Stufen-Dropdown (inline) --}}
                            <div x-show="dropdownOpen && dropdownStuId === stu.id"
                                 x-cloak
                                 @click.outside="closeDropdown()"
                                 class="position-absolute bg-white border rounded shadow-sm p-1"
                                 style="z-index:9999; min-width:140px; top:100%; left:0;">
                                <div x-show="stageLoading" class="small text-muted p-1">Lade...</div>
                                <template x-if="!stageLoading">
                                    <div style="display:flex; flex-direction:column; gap:4px;">
                                        <button type="button" class="btn btn-sm btn-outline-secondary text-left"
                                                @click="selectStage('')">
                                            <span style="width:20px;display:inline-block;text-align:center;">—</span> Keine Stufe
                                        </button>
                                        <template x-for="stage in stages" :key="stage.id">
                                            <button type="button" class="btn btn-sm btn-outline-secondary text-left d-flex align-items-center"
                                                    style="gap:8px"
                                                    @click="selectStage(stage.id)">
                                                <template x-if="stage.image_url">
                                                    <img :src="stage.image_url" :alt="stage.name" style="width:20px;height:20px;object-fit:contain;">
                                                </template>
                                                <template x-if="!stage.image_url && stage.symbol">
                                                    <span class="badge badge-info" x-text="stage.symbol"></span>
                                                </template>
                                                <span x-text="stage.name || stage.symbol || ('Stufe ' + stage.id)"></span>
                                            </button>
                                        </template>
                                    </div>
                                </template>
                            </div>
                        </span>
                    </th>

                    {{-- Tages-Zellen --}}
                    <template x-for="day in $store.diary.days" :key="day.date">
                        <td class="note-cell"
                            :class="{
                                'today-cell': day.date === $store.diary.todayStr,
                                'ferien-cell': day.is_ferien,
                                'absent-cell': $store.diary.isAbsent(stu.id, day.date),
                                'stu-has-task-cell': hasTaskForStudent(stu.id)
                            }"
                            :data-stu="stu.id"
                            :data-date="day.date">

                            {{-- Abwesenheits-Toggle + Click-Bereich --}}
                            <div class="entry-add-space" style="min-height:18px; cursor:pointer;"
                                 title="Neue Notiz erstellen"
                                 @click="$dispatch('diary-new-entry', { date: day.date, stuId: stu.id })">
                                <div style="float:right">
                                    <button type="button"
                                            class="absence-toggle diary-btn"
                                            :class="$store.diary.isAbsent(stu.id, day.date) ? 'diary-btn-absent' : 'diary-btn-present'"
                                            @click.stop="toggleAbsence(stu.id, stu.klasse_id, day.date)"
                                            :title="$store.diary.isAbsent(stu.id, day.date) ? 'Abwesenheit aufheben' : 'Als abwesend markieren'">
                                        <span x-text="$store.diary.isAbsent(stu.id, day.date) ? '🚫' : '👤'"></span>
                                    </button>
                                </div>
                            </div>

                            {{-- Abwesend-Banner --}}
                            <template x-if="$store.diary.isAbsent(stu.id, day.date)">
                                <div class="absent-banner text-danger" style="font-size:.68rem;font-weight:bold;padding:1px 2px;">
                                    🚫 Abwesend
                                </div>
                            </template>

                            {{-- Einträge (gruppiert nach Kategorie) – bei Abwesenheit komplett ausblenden --}}
                             <div class="entry-list"
                                  x-show="!$store.diary.isAbsent(stu.id, day.date)">
                                <template x-for="group in getGroupedEntries(stu.id, day.date)" :key="group.category">
                                    <div>
                                        {{-- Kategorie-Überschrift --}}
                                        <template x-if="!$store.diary.hideAllCategoryHeadings">
                                            <div class="entry-category-header"
                                                 x-text="group.category || 'Ohne Kategorie'"></div>
                                        </template>

                                        {{-- Einträge der Kategorie --}}
                                        <div class="category-entries">
                                            <template x-for="entry in group.entries" :key="entry.id + '-' + day.date">
                                                <div class="entry-item d-flex align-items-start"
                                                     @click.stop="$dispatch('diary-edit-entry', { entryId: entry.id, date: day.date })">
                                                    <div class="flex-grow-1">
                                                        <template x-if="entry.user">
                                                            <span class="author" x-text="entry.user"></span>
                                                        </template>
                                                        <span class="text" x-text="trimText(entry.content, 120)"></span>
                                                        <template x-if="!entry.completed_at && entry.virtual_date !== entry.date">
                                                            <span class="badge badge-warning badge-pill ml-1" title="Fortlaufende offene Notiz">laufend</span>
                                                        </template>
                                                    </div>
                                                    <div class="ml-1 d-flex">
                                                        <template x-if="!entry.completed_at">
                                                            <button class="diary-btn diary-btn-complete entry-complete-btn"
                                                                    @click.stop="completeEntry(entry.id, day.date)"
                                                                    title="Notiz abschließen" aria-label="Abschließen">✔</button>
                                                        </template>
                                                        <template x-if="!entry.completed_at">
                                                            <button class="diary-btn diary-btn-pause entry-pause-btn"
                                                                    @click.stop="pauseEntry(entry.id, stu.id, day.date)"
                                                                    title="Notiz an diesem Tag ausblenden" aria-label="Pausieren">⏸</button>
                                                        </template>
                                                    </div>
                                                </div>
                                            </template>
                                        </div>
                                    </div>
                                </template>
                            </div>

                            {{-- Pausierte Einträge (bei Abwesenheit ebenfalls ausblenden) --}}
                            <template x-if="$store.diary.showPaused && !$store.diary.isAbsent(stu.id, day.date)">
                                <div class="paused-entries">
                                    <template x-for="entry in getPausedEntries(stu.id, day.date)" :key="entry.id + '-paused-' + day.date">
                                        <div class="entry-item paused-entry d-flex align-items-start text-muted">
                                            <div class="flex-grow-1">
                                                <em x-text="trimText(entry.content, 100)"></em>
                                                <span class="badge badge-light ml-1" title="Pausiert">Pause</span>
                                            </div>
                                            <div class="ml-1 d-flex">
                                                <button class="diary-btn diary-btn-unpause entry-unpause-btn"
                                                        @click.stop="unpauseEntry(entry.id, stu.id, day.date)"
                                                        title="Notiz an diesem Tag wieder anzeigen" aria-label="Reaktivieren">▶</button>
                                            </div>
                                        </div>
                                    </template>
                                </div>
                            </template>

                            {{-- Spalten-Inputs (bei Abwesenheit ausblenden) --}}
                            <div class="col-inputs-row" x-data="columnsManager()"
                                 x-show="!$store.diary.isAbsent(stu.id, day.date)">
                                <div class="col-inputs">
                                    <template x-for="col in getColumnsForStudent(stu)" :key="col.id">
                                        <div>
                                            {{-- Boolean-Button --}}
                                            <template x-if="col.type === 'boolean'">
                                                <button type="button"
                                                        class="btn btn-xs bool-btn"
                                                        :class="getColumnValue(col.id, stu.id, day.date) === '1' ? 'btn-success' : 'btn-outline-secondary'"
                                                        @click.stop="toggleBoolColumn(col.id, stu.id, day.date)"
                                                        :title="col.name"
                                                        x-text="col.name">
                                                </button>
                                            </template>
                                            {{-- Text-Input --}}
                                            <template x-if="col.type !== 'boolean'">
                                                <input type="text" maxlength="255"
                                                       class="form-control form-control-sm col-val-input"
                                                       :value="getColumnValue(col.id, stu.id, day.date)"
                                                       @input.stop="debouncedSaveColumn(col.id, stu.id, day.date, $event.target.value)"
                                                       :placeholder="col.name"
                                                       :title="col.name">
                                            </template>
                                        </div>
                                    </template>
                                </div>
                            </div>

                            {{-- Klassen-Termine in Schüler-Zelle --}}
                            <template x-for="apt in getClassAppointmentsForStudent(stu.id, day.date)" :key="'cls-apt-' + apt.id">
                                <div class="appointment-item bg-warning text-dark px-1 mb-1 rounded"
                                     style="font-size:0.6rem; opacity:0.8; border-left:2px solid #ffc107; cursor:pointer;"
                                     :title="apt.description || apt.title"
                                     @click.stop="$dispatch('edit-appointment', apt)">
                                    <span x-text="formatAppointmentShortLabel(apt)"></span>
                                </div>
                            </template>

                            {{-- Individuelle Schüler-Termine --}}
                            <template x-for="apt in getStudentAppointments(stu.id, day.date)" :key="'stu-apt-' + apt.id">
                                <div class="appointment-item bg-info text-white px-1 mb-1 rounded"
                                     style="font-size:0.65rem; cursor:pointer"
                                     :title="apt.description || apt.title"
                                     @click.stop="$dispatch('edit-appointment', apt)">
                                    <span x-text="formatAppointmentLabel(apt)"></span>
                                </div>
                            </template>
                        </td>
                    </template>
                </tr>
            </template>
        </tbody>
    </table>

    {{-- Hinweis bei leerer Tabelle --}}
    <div x-show="$store.diary.schueler.length === 0 && !$store.diary.loading" x-cloak
         class="text-center py-4 text-muted">
        <i class="fas fa-info-circle"></i> Keine Schüler in der gewählten Klasse/Gruppe.
    </div>
</div>

