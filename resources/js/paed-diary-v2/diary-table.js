/**
 * PaedDiary v2 – Diary-Table Component
 *
 * Verantwortlich für:
 * - entryMap-Berechnung (welche Einträge pro Schüler/Tag sichtbar)
 * - Pause/Unpause/Complete-Aktionen
 * - Abwesenheits-Toggle
 * - Gruppiertes Rendering der Einträge nach Kategorie
 * - Termin-Anzeige in Header & Zellen
 */

import { formatDate, trimText, formatTime, escapeHtml, csrfToken, getBrightness } from './utils.js';

export function registerDiaryTable(Alpine) {
    Alpine.data('diaryTable', () => ({


        // ── Einträge pro Zelle berechnen ───────────────────────────

        /**
         * Gibt alle sichtbaren Einträge für eine Schüler/Tag-Kombination zurück.
         */
        getEntriesForCell(stuId, date) {
            const store = this.$store.diary;
            const hiddenCatIds = new Set((store.hidden_category_ids || []).map(Number));
            const weekDates = (store.days || []).map(d => d.date);
            if (!weekDates.length) return [];

            const weekStartStr = weekDates[0];
            const weekEndStr = weekDates[weekDates.length - 1];
            const results = [];

            (store.entries || []).forEach(e => {
                // Kategoriefilter
                if (e.category_id && hiddenCatIds.has(Number(e.category_id))) return;
                if (!e.category_id && store.filterUncategorized) return;
                if (!(e.schueler_ids || []).includes(stuId)) return;
                if (store.isPaused(e.id, stuId, date)) return;

                if (e.completed_at) {
                    // Erledigte Einträge nur am Original-Datum
                    if (e.date === date) {
                        results.push({ ...e, virtual_date: e.date });
                    }
                } else {
                    // Offene Einträge: von Startdatum bis Wochenende
                    if (date >= e.date && date <= weekEndStr) {
                        results.push({ ...e, virtual_date: date });
                    }
                }
            });

            return results;
        },

        /**
         * Gibt Einträge gruppiert nach Kategorie zurück.
         * Format: [{ category: 'Name', entries: [...] }, ...]
         */
        getGroupedEntries(stuId, date) {
            const entries = this.getEntriesForCell(stuId, date);
            const sorted = entries.slice().sort((a, b) => {
                const ca = (a.category || '').toLowerCase();
                const cb = (b.category || '').toLowerCase();
                if (ca === cb) return (a.id || 0) - (b.id || 0);
                if (!ca) return 1;
                if (!cb) return -1;
                return ca.localeCompare(cb, 'de');
            });

            const groups = {};
            const order = [];
            sorted.forEach(e => {
                const key = e.category || '';
                if (!(key in groups)) {
                    groups[key] = [];
                    order.push(key);
                }
                groups[key].push(e);
            });

            return order.map(cat => ({ category: cat, entries: groups[cat] }));
        },

        /**
         * Gibt pausierte Einträge für eine Zelle zurück.
         */
        getPausedEntries(stuId, date) {
            const store = this.$store.diary;
            return (store.entries || []).filter(e =>
                !e.completed_at &&
                (e.schueler_ids || []).includes(stuId) &&
                store.isPaused(e.id, stuId, date)
            );
        },

        /**
         * Hat der Schüler offene Aufgaben oder Notizen?
         */
        hasTaskForStudent(stuId) {
            const store = this.$store.diary;
            if ((store.tasks || []).some(t => t.schueler_id === stuId)) return true;
            return (store.open_entries || []).some(e => (e.schueler_ids || []).includes(stuId));
        },

        /**
         * Offene Notizen (open_entries) für einen Schüler.
         */
        getOpenEntriesForStudent(stuId) {
            const store = this.$store.diary;
            return (store.open_entries || []).filter(e => (e.schueler_ids || []).includes(stuId));
        },

        /**
         * Offene Aufgaben (tasks) für einen Schüler.
         */
        getOpenTasksForStudent(stuId) {
            const store = this.$store.diary;
            return (store.tasks || []).filter(t => t.schueler_id === stuId);
        },

        /**
         * Offene Notiz aus der Namensspalte abschließen (wie v1 complete-entry-btn).
         */
        async completeOpenEntry(entryId) {
            try {
                const resp = await fetch(`/paed-diary/entry/${entryId}/complete`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': csrfToken(),
                        'Accept': 'application/json'
                    },
                    body: JSON.stringify({ completed_at: new Date().toISOString().slice(0, 10) })
                });
                const j = await resp.json();
                if (j.success) this.$store.diary.loadWeek();
            } catch (_) {
                alert('Fehler beim Abschließen der Notiz');
            }
        },

        /**
         * Offene Aufgabe aus der Namensspalte ausblenden (wie v1 close-task-btn).
         */
        async closeOpenTask(taskId) {
            try {
                const resp = await fetch(`/paed-diary/task/${taskId}/close`, {
                    method: 'POST',
                    headers: { 'X-CSRF-TOKEN': csrfToken(), 'Accept': 'application/json' }
                });
                const j = await resp.json();
                if (j.success) this.$store.diary.loadWeek();
            } catch (_) {
                alert('Fehler beim Schließen der Aufgabe');
            }
        },

        /**
         * Prüft ob vor diesem Schüler eine Klassentrennzeile angezeigt werden soll
         * (nur im Gruppenmodus).
         */
        shouldShowClassDivider(stu, index) {
            const store = this.$store.diary;
            if (!store.is_group) return false;
            if (index === 0) return true;
            const prev = store.schueler[index - 1];
            return prev && prev.klasse_id !== stu.klasse_id;
        },

        /**
         * Gibt den Klassennamen für eine Klassen-ID zurück.
         */
        getKlasseName(klasseId) {
            const store = this.$store.diary;
            const k = (store.klassen || []).find(k => k.id === klasseId);
            return k ? k.name : ('Klasse ' + klasseId);
        },

        /**
         * Gibt die Farbe einer Klasse zurück.
         */
        getKlasseColor(klasseId) {
            const store = this.$store.diary;
            const k = (store.klassen || []).find(k => k.id === klasseId);
            return k?.color || null;
        },

        /**
         * Gibt das Kürzel einer Klasse zurück.
         */
        getKlasseKuerzel(stuKlasseId) {
            const store = this.$store.diary;
            const k = (store.klassen || []).find(k => k.id === stuKlasseId);
            return k?.kuerzel || '';
        },

        // ── Aktionen ───────────────────────────────────────────────

        /**
         * Eintrag an einem Tag pausieren (optimistisches UI-Update).
         */
        async pauseEntry(entryId, stuId, date) {
            const store = this.$store.diary;
            const fd = new FormData();
            fd.append('schueler_id', stuId);
            fd.append('date', date);

            try {
                const resp = await fetch(`/paed-diary/entry/${entryId}/pause-day`, {
                    method: 'POST',
                    headers: { 'X-CSRF-TOKEN': csrfToken(), 'Accept': 'application/json' },
                    body: fd
                });
                const j = await resp.json();
                if (j.success) {
                    store.pauses = [...store.pauses, {
                        entry_id: parseInt(entryId),
                        schueler_id: parseInt(stuId),
                        date: date
                    }];
                } else {
                    alert(j.message || 'Fehler beim Pausieren');
                }
            } catch (_) {
                alert('Fehler beim Pausieren');
            }
        },

        /**
         * Pause für einen Eintrag aufheben (optimistisches UI-Update).
         */
        async unpauseEntry(entryId, stuId, date) {
            const store = this.$store.diary;
            const fd = new FormData();
            fd.append('schueler_id', stuId);
            fd.append('date', date);

            try {
                const resp = await fetch(`/paed-diary/entry/${entryId}/unpause-day`, {
                    method: 'POST',
                    headers: { 'X-CSRF-TOKEN': csrfToken(), 'Accept': 'application/json' },
                    body: fd
                });
                const j = await resp.json();
                if (j.success) {
                    store.pauses = store.pauses.filter(p =>
                        !(String(p.entry_id) === String(entryId) &&
                          String(p.schueler_id) === String(stuId) &&
                          p.date === date)
                    );
                } else {
                    alert(j.message || 'Fehler beim Reaktivieren');
                }
            } catch (_) {
                alert('Fehler beim Reaktivieren');
            }
        },

        /**
         * Eintrag als erledigt markieren.
         */
        async completeEntry(entryId, date) {
            try {
                const resp = await fetch(`/paed-diary/entry/${entryId}/complete`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': csrfToken(),
                        'Accept': 'application/json'
                    },
                    body: JSON.stringify({ completed_at: date })
                });
                const j = await resp.json();
                if (j.success) {
                    this.$store.diary.loadWeek();
                } else {
                    alert(j.message || 'Fehler');
                }
            } catch (_) {
                alert('Fehler beim Abschließen');
            }
        },

        /**
         * Abwesenheit für einen Schüler an einem Tag toggeln (optimistisches UI-Update).
         */
        async toggleAbsence(stuId, klasseId, date) {
            const store = this.$store.diary;
            try {
                const resp = await fetch('/paed-diary/absence', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': csrfToken(),
                        'Accept': 'application/json'
                    },
                    body: JSON.stringify({ schueler_id: stuId, klasse_id: klasseId, datum: date })
                });
                const j = await resp.json();
                if (j.success) {
                    if (j.absent) {
                        store.absences = [...store.absences, { schueler_id: parseInt(stuId), datum: date }];
                        // Neue Pausen übernehmen
                        if (Array.isArray(j.pauses)) {
                            const newPauses = [...store.pauses];
                            j.pauses.forEach(p => {
                                const exists = newPauses.some(cp =>
                                    cp.entry_id === p.entry_id &&
                                    cp.schueler_id === p.schueler_id &&
                                    cp.date === p.date
                                );
                                if (!exists) newPauses.push(p);
                            });
                            store.pauses = newPauses;
                        }
                    } else {
                        store.absences = store.absences.filter(a =>
                            !(String(a.schueler_id) === String(stuId) && a.datum === date)
                        );
                        // Pausen entfernen
                        const removedIds = Array.isArray(j.removed_entry_ids) ? j.removed_entry_ids : [];
                        store.pauses = store.pauses.filter(p =>
                            !(p.schueler_id === parseInt(stuId) && p.date === date &&
                              (removedIds.length === 0 || removedIds.includes(p.entry_id)))
                        );
                    }
                } else {
                    alert(j.message || 'Fehler beim Setzen der Abwesenheit');
                }
            } catch (_) {
                alert('Fehler beim Setzen der Abwesenheit');
            }
        },

        // ── Kategorie-Filter ───────────────────────────────────────

        toggleCategoryHeadings() {
            const store = this.$store.diary;
            store.hideAllCategoryHeadings = !store.hideAllCategoryHeadings;
            localStorage.setItem('paedDiary_hideAllHeadings', store.hideAllCategoryHeadings ? '1' : '0');
        },

        toggleFilterUncategorized() {
            const store = this.$store.diary;
            store.filterUncategorized = !store.filterUncategorized;
            localStorage.setItem('paedDiary_filterUncategorized', store.filterUncategorized ? '1' : '0');
        },

        async toggleCategoryHidden(catId) {
            const store = this.$store.diary;
            const id = Number(catId);
            const isNowHidden = !store.hidden_category_ids.includes(id);

            // Optimistisches UI-Update
            if (isNowHidden) {
                store.hidden_category_ids = [...store.hidden_category_ids, id];
            } else {
                store.hidden_category_ids = store.hidden_category_ids.filter(x => x !== id);
            }

            try {
                const resp = await fetch(`/paed-diary/categories/${catId}/toggle-hidden`, {
                    method: 'POST',
                    headers: { 'X-CSRF-TOKEN': csrfToken(), 'Accept': 'application/json' }
                });
                const j = await resp.json();
                if (!j.success) {
                    // Revert
                    if (isNowHidden) {
                        store.hidden_category_ids = store.hidden_category_ids.filter(x => x !== id);
                    } else {
                        store.hidden_category_ids = [...store.hidden_category_ids, id];
                    }
                }
            } catch (_) {
                // Revert bei Netzwerkfehler
                if (isNowHidden) {
                    store.hidden_category_ids = store.hidden_category_ids.filter(x => x !== id);
                } else {
                    store.hidden_category_ids = [...store.hidden_category_ids, id];
                }
            }
        },

        isCategoryVisible(catId) {
            return !this.$store.diary.hidden_category_ids.includes(Number(catId));
        },

        // ── Termin-Helfer ──────────────────────────────────────────

        /**
         * Termine für den Tages-Header (Klassen-/Gruppentermine).
         */
        getHeaderAppointments(date) {
            return (this.$store.diary.appointments || []).filter(a => {
                if (a.date !== date) return false;
                const schuelerArr = Array.isArray(a.schueler) ? a.schueler : [];
                const klassenArr = Array.isArray(a.klassen) ? a.klassen : [];
                const groupsArr = Array.isArray(a.groups) ? a.groups : [];
                const hasOnlyIndividual = schuelerArr.length > 0 && klassenArr.length === 0 && groupsArr.length === 0;
                return !hasOnlyIndividual;
            });
        },

        /**
         * Individuelle Termine für einen Schüler an einem Tag.
         */
        getStudentAppointments(stuId, date) {
            return (this.$store.diary.appointments || []).filter(a => {
                if (a.date !== date) return false;
                const schuelerArr = Array.isArray(a.schueler) ? a.schueler : [];
                const klassenArr = Array.isArray(a.klassen) ? a.klassen : [];
                const groupsArr = Array.isArray(a.groups) ? a.groups : [];
                const hasOnlyIndividual = schuelerArr.length > 0 && klassenArr.length === 0 && groupsArr.length === 0;
                return hasOnlyIndividual && schuelerArr.some(s => String(s.id) === String(stuId));
            });
        },

        /**
         * Klassen-/Gruppentermine für die Schüler-Zelle.
         */
        getClassAppointmentsForStudent(stuId, date) {
            const store = this.$store.diary;
            const stu = (store.schueler || []).find(s => s.id === stuId);
            if (!stu) return [];
            return (store.appointments || []).filter(a => {
                if (a.date !== date) return false;
                const klassenArr = Array.isArray(a.klassen) ? a.klassen : [];
                const groupsArr = Array.isArray(a.groups) ? a.groups : [];
                if (klassenArr.length === 0 && groupsArr.length === 0) return false;
                // Prüfen ob die Klasse des Schülers betroffen ist
                return klassenArr.length === 0 || klassenArr.some(k => String(k.id) === String(stu.klasse_id));
            });
        },

        /**
         * Label für eine Termin-Anzeige formatieren.
         */
        formatAppointmentLabel(apt) {
            let label = '';
            if (apt.start_time) {
                label += formatTime(apt.start_time);
                if (apt.end_time) label += ' - ' + formatTime(apt.end_time);
                label += ' ';
            }
            label += trimText(apt.title, 20);
            return label;
        },

        formatAppointmentShortLabel(apt) {
            let label = '';
            if (apt.start_time) label += formatTime(apt.start_time) + ' ';
            label += trimText(apt.title, 18);
            return label;
        },

        // ── Export ─────────────────────────────────────────────────

        get exportUrl() {
            const store = this.$store.diary;
            const params = new URLSearchParams({
                week_start: formatDate(store.currentWeekStart)
            });
            if (store.selectedGroupId) {
                params.append('group_id', store.selectedGroupId);
            } else {
                params.append('klasse_id', store.selectedKlasseId);
            }
            return '/export/paed-diary/excel?' + params.toString();
        },

        // ── Utils (Delegation) ─────────────────────────────────────

        trimText(str, len) { return trimText(str, len); },
        escapeHtml(str) { return escapeHtml(str); },
        getBrightness(hex) { return getBrightness(hex); },
    }));
}

