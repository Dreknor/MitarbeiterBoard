// paedDiary.js - ausgelagerter JavaScript-Code für das pädagogische Tagebuch

(function(){
    // --- Vorhandene / neue Variablen ---
    const csrf = document.querySelector('meta[name=csrf-token]').content;
    const klasseSelect = document.getElementById('klasseSelect');
    const groupSelect = document.getElementById('groupSelect');
    const manageGroupsBtn = document.getElementById('manageGroupsBtn');
    const modeBadge = document.getElementById('modeBadge');
    const weekLabel = document.getElementById('weekLabel');
    const diaryHead = document.getElementById('diaryHead');
    const diaryBody = document.getElementById('diaryBody');
    const prevWeekBtn = document.getElementById('prevWeek');
    const nextWeekBtn = document.getElementById('nextWeek');
    const todayWeekBtn = document.getElementById('todayWeek');
    const manageColumnsBtn = document.getElementById('manageColumnsBtn');
    const columnsCardWrapper = document.getElementById('columnsCardWrapper');
    const columnsCloseBtn = document.getElementById('columnsCloseBtn');
    const columnsList = document.getElementById('columnsList');
    const columnsFeedback = document.getElementById('columnsFeedback');
    const addColumnForm = document.getElementById('addColumnForm');

    const groupModal = $('#groupModal');
    const groupForm = document.getElementById('groupForm');
    const groupsListDiv = document.getElementById('groupsList');
    const groupFeedback = document.getElementById('groupFeedback');
    const groupStatus = document.getElementById('groupStatus');
    const groupIdInput = document.getElementById('groupId');
    const groupNameInput = document.getElementById('groupName');
    const groupCancelEdit = document.getElementById('groupCancelEdit');

    const noteEditorCard = document.getElementById('noteEditorCard');
    const noteEditorTitle = document.getElementById('noteEditorTitle');
    const noteEditorCancel = document.getElementById('noteEditorCancel');
    const noteClearBtn = document.getElementById('noteClearBtn');
    const openNoteInline = document.getElementById('openNoteInline');
    const noteForm = document.getElementById('noteForm');
    const noteEntryIdInput = document.getElementById('noteEntryId');
    const noteDateInput = document.getElementById('noteDate');
    const noteContentInput = document.getElementById('noteContent');
    const noteDeleteBtn = document.getElementById('noteDeleteBtn');
    const noteStudentsDiv = document.getElementById('noteStudents');
    const noteStatus = document.getElementById('noteStatus');

    const taskModal = $('#taskModal');
    const taskForm = document.getElementById('taskForm');
    const taskSchuelerSelect = document.getElementById('taskSchueler');
    const openTaskModalBtn = document.getElementById('openTaskModal');
    const exportCsvBtn = document.getElementById('exportCsvBtn');
    const tasksPanel = document.getElementById('tasksPanel');
    const tasksList = document.getElementById('tasksList');
    const refreshTasksBtn = document.getElementById('refreshTasks');

    // Termine-Elemente
    const appointmentModal = $('#appointmentModal');
    const appointmentForm = document.getElementById('appointmentForm');
    const appointmentIdInput = document.getElementById('appointmentId');
    const appointmentTitleInput = document.getElementById('appointmentTitle');
    const appointmentDescriptionInput = document.getElementById('appointmentDescription');
    const appointmentStartDateInput = document.getElementById('appointmentStartDate');
    const appointmentStartTimeInput = document.getElementById('appointmentStartTime');
    const appointmentEndTimeInput = document.getElementById('appointmentEndTime');
    const appointmentIsRecurringInput = document.getElementById('appointmentIsRecurring');
    const appointmentRecurringTypeSelect = document.getElementById('appointmentRecurringType');
    const appointmentRecurringIntervalInput = document.getElementById('appointmentRecurringInterval');
    const appointmentRecurringEndDateInput = document.getElementById('appointmentRecurringEndDate');
    const appointmentDeleteBtn = document.getElementById('appointmentDeleteBtn');
    const appointmentPauseBtn = document.getElementById('appointmentPauseBtn');
    const appointmentStatus = document.getElementById('appointmentStatus');
    const appointmentFeedback = document.getElementById('appointmentFeedback');
    const appointmentModalTitle = document.getElementById('appointmentModalTitle');
    const openAppointmentModalBtn = document.getElementById('openAppointmentModal');
    const recurringOptions = document.getElementById('recurringOptions');
    const appointmentStudentsBox = document.getElementById('appointmentStudentsBox');
    const appointmentSchuelerList = document.getElementById('appointmentSchuelerList');

    // --- State ---
    let currentWeekStart = startOfWeek(new Date());
    let cache = { days:[], schueler:[], entries:[], columns:[], column_values:{}, tasks:[], klassen:[], is_group:false, appointments:[] };
    let columnsAllCache = []; // inkl. deaktivierte
    let debounceTimers = {}; // für Spaltenwerte
    let groupsCache = [];
   let pauseMap = {}; // Neuer Map: entryId -> schuelerId -> date -> true

    function rebuildPauseMap(){
        pauseMap = {};
        if(!cache.pauses) return;
        cache.pauses.forEach(p=>{
            if(!pauseMap[p.entry_id]) pauseMap[p.entry_id]={};
            if(!pauseMap[p.entry_id][p.schueler_id]) pauseMap[p.entry_id][p.schueler_id]={};
            pauseMap[p.entry_id][p.schueler_id][p.date]=true;
        });
    }

    function isPaused(entryId, stuId, date){
        // Robust: prüfe verschachtelte Maps ohne Referenzfehler
        const entryMap = pauseMap[entryId];
        if(!entryMap) return false;
        const stuMap = entryMap[stuId];
        if(!stuMap) return false;
        return !!stuMap[date];
    }

    // --- Utils ---
    function startOfWeek(d){const dt=new Date(d);const wd=dt.getDay();const diff=(wd===0?-6:1-wd);dt.setDate(dt.getDate()+diff);dt.setHours(0,0,0,0);return dt;}
    function formatDate(d){const year=d.getFullYear();const month=String(d.getMonth()+1).padStart(2,'0');const day=String(d.getDate()).padStart(2,'0');return `${year}-${month}-${day}`;}
    function addDays(d,x){const n=new Date(d);n.setDate(n.getDate()+x);return n;}
    function escapeHtml(str){return String(str).replace(/[&<>"']/g,s=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;','\'':'&#39;'}[s]));}
    function trimText(str,len){return str.length<=len?str:str.slice(0,len-1)+'…';}
    function setModeBadge(){ if(cache.is_group){ modeBadge.classList.remove('d-none'); } else { modeBadge.classList.add('d-none'); } }

    // Berechnet die Helligkeit einer Farbe für besseren Kontrast
    function getBrightness(hexColor) {
        // Entferne # falls vorhanden
        const hex = hexColor.replace('#', '');

        // Konvertiere zu RGB
        const r = parseInt(hex.substr(0, 2), 16);
        const g = parseInt(hex.substr(2, 2), 16);
        const b = parseInt(hex.substr(4, 2), 16);

        // Berechne relative Helligkeit nach W3C-Formel
        return ((r * 299) + (g * 587) + (b * 114)) / 1000;
    }

    // --- Neu: Kategorisierte Darstellung der Zusatzspalten pro Zelle ---
    function renderColumnInputs(stuId, date){
        if(!cache.columns || !cache.columns.length) return '';

        const student = cache.schueler.find(s => String(s.id)===String(stuId));
        if(!student) return '';

        const columnsForStudent = cache.is_group
            ? cache.columns.filter(col => String(col.klasse_id)===String(student.klasse_id))
            : cache.columns;

        // Gruppiere nach category (falls nicht vorhanden -> 'Unkategorisiert')
        const byCat = {};
        columnsForStudent.forEach(col=>{
            const cat = col.category || 'Unkategorisiert';
            (byCat[cat] = byCat[cat] || []).push(col);
        });

        const cats = Object.keys(byCat).sort((a,b)=>{
            if(a==='Unkategorisiert') return 1;
            if(b==='Unkategorisiert') return -1;
            return a.localeCompare(b,'de');
        });

        let html = '';
        cats.forEach(cat=>{
            html += `<div class="col-cat-group" data-cat="${escapeHtml(cat)}"><div class="col-cat-label small text-muted mb-1">${escapeHtml(cat)}</div>`;
            byCat[cat].forEach(col=>{
                const val = (cache.column_values?.[col.id]?.[stuId]?.[date]) || '';
                if(col.type === 'boolean'){
                    const active = val === '1';
                    html += `<button type="button" class="btn btn-xs bool-btn ${active?'btn-success':'btn-outline-secondary'}" data-col="${col.id}" data-stu="${stuId}" data-date="${date}" data-value="${active?'1':''}" data-name="${escapeHtml(col.name)}" title="${escapeHtml(col.name)}">${escapeHtml(col.name)}</button>`;
                } else {
                    html += `<input type="text" maxlength="255" class="form-control form-control-sm col-val-input" data-col="${col.id}" data-stu="${stuId}" data-date="${date}" value="${escapeHtml(val)}" placeholder="${escapeHtml(col.name)}" title="${escapeHtml(col.name)}">`;
                }
            });
            html += `</div>`;
        });
        return html;
    }

    // --- Neu hinzugefügt: Schüler-Checkboxen für Notizeditor ---
    function renderStudentCheckboxes(){
        if(!noteStudentsDiv) return;
        if(!cache.schueler || !cache.schueler.length){ noteStudentsDiv.innerHTML='<span class="text-muted">Keine Schüler</span>'; return; }
        // Gruppierung nach Klasse falls Gruppenmodus
        const byClass = {};
        cache.schueler.forEach(s=>{ (byClass[s.klasse_id] = byClass[s.klasse_id] || []).push(s); });
        let html='';
        Object.keys(byClass).sort().forEach(klasseId=>{
            const klasse = (cache.klassen||[]).find(k=>k.id==klasseId);
            if(cache.is_group){ html += `<div class="text-primary font-weight-bold small border-top pt-1 mt-1">${escapeHtml(klasse? klasse.name : ('Klasse '+klasseId))}</div>`; }
            byClass[klasseId].sort((a,b)=> a.name.localeCompare(b.name,'de')).forEach(s=>{
                html += `<label class="custom-checkbox-wrapper" style="font-size:.65rem;">`+
                        `<input type="checkbox" class="custom-checkbox-input" id="stu_chk_${s.id}" value="${s.id}" name="schueler_ids[]">`+
                        `<span class="custom-checkbox-label">${escapeHtml(s.name)}</span>`+
                        `</label>`;
            });
        });
        noteStudentsDiv.innerHTML=html;
    }

    // --- Neu hinzugefügt: Aufgaben & offene Notizen Panel ---
    function renderTasks(){
        if(!tasksPanel || !tasksList) return;
        const hasTasks = Array.isArray(cache.tasks) && cache.tasks.length>0;
        const openNotes = (cache.entries||[]).filter(e=>!e.completed_at);
        const hasOpenNotes = openNotes.length>0;
        if(!hasTasks && !hasOpenNotes){ tasksPanel.style.display='none'; return; }
        tasksPanel.style.display='block';
        let html='';
        if(hasTasks){
            html += cache.tasks.map(t=>{
                const stu = cache.schueler.find(s=>s.id===t.schueler_id);
                const name = stu? stu.name : 'Schüler';
                const due = t.due_date? new Date(t.due_date).toLocaleDateString() : '';
                return `<div class="task-item mb-2 p-2 border rounded ${t.highlighted?'border-warning bg-light':'border-secondary'}" data-task-id="${t.id}">`+
                       `<div class="d-flex justify-content-between align-items-start">`+
                       `<div class="flex-grow-1"><div class="font-weight-bold small">${escapeHtml(t.title)}</div>`+
                       `<div class="text-muted small">${escapeHtml(name)}${due?' · Fällig: '+due:''}</div>`+
                       `</div><button class="btn btn-sm btn-success close-task-btn" data-task-id="${t.id}" title="Erledigt"><i class="fas fa-check"></i></button></div></div>`;}).join('');
        }
        if(hasOpenNotes){
            html += '<div class="mt-2 mb-1 small font-weight-bold">Offene Notizen</div>';
            html += openNotes.slice(0,100).map(e=>{
                const students = e.schueler_ids.map(id=>{ const s=cache.schueler.find(x=>x.id===id); return s? s.name: 'n/a'; }).join(', ');
                return `<div class="task-item mb-2 p-2 border rounded border-danger bg-light" data-entry-id="${e.id}">`+
                       `<div class="d-flex justify-content-between align-items-start">`+
                       `<div class="flex-grow-1"><div class="font-weight-bold small">${escapeHtml(trimText(e.content,140))}</div>`+
                       `<div class="text-muted small">${escapeHtml(students)}</div>`+
                       `<div class="text-muted small">${escapeHtml(e.date)}</div>`+
                       `</div><button class="btn btn-sm btn-success complete-entry-btn" data-entry-id="${e.id}" title="Abschließen"><i class="fas fa-check"></i></button></div></div>`;}).join('');
        }
        tasksList.innerHTML = html;
    }

    // Rendert das Symbol/Badge für die Graduierungsstufe eines Schülers (jetzt immer klickbar bei Berechtigung)
    function renderStageSymbol(student) {
        const canManage = cache.can_manage_grading;
        const baseData = `data-stu="${student.id}" data-klasse="${student.klasse_id}"`;
        let inner;
        if(!student.stage){
            inner = '<span class="badge badge-light" title="Stufe setzen">Stufe</span>';
        } else if(student.stage.image_url){
            inner = `<img src="${escapeHtml(student.stage.image_url)}" alt="${escapeHtml(student.stage.name)}" title="${escapeHtml(student.stage.name)}" class="stage-image" style="width:20px;height:20px;object-fit:contain;">`;
        } else if(student.stage.symbol){
            inner = `<span class="badge badge-info" title="${escapeHtml(student.stage.name)}">${escapeHtml(student.stage.symbol)}</span>`;
        } else {
            inner = `<span class="badge badge-secondary" title="${escapeHtml(student.stage.name)}">${escapeHtml(student.stage.name)}</span>`;
        }
        return canManage ? `<span class="stage-change ml-1" ${baseData}>${inner}</span>` : inner;
    }

    // Formatiert Zeit von "HH:MM:SS" zu "HH:MM" oder von ISO 8601 DateTime zu "HH:MM" (lokale Zeitzone)
    function formatTime(timeStr) {
        if (!timeStr) return '';

        // Falls es ein ISO 8601 DateTime-String ist (YYYY-MM-DDTHH:MM:SS)
        if (timeStr.includes('T')) {
            try {
                // Konvertiere zu Date-Objekt und formatiere in lokaler Zeitzone
                const date = new Date(timeStr);
                if (!isNaN(date.getTime())) {
                    const hours = String(date.getHours()).padStart(2, '0');
                    const minutes = String(date.getMinutes()).padStart(2, '0');
                    return `${hours}:${minutes}`;
                }
            } catch (e) {
                // Fallback: einfache String-Extraktion
                const timePart = timeStr.split('T')[1];
                if (timePart && timePart.includes(':')) {
                    const parts = timePart.split(':');
                    if (parts.length >= 2) {
                        return `${parts[0]}:${parts[1]}`;
                    }
                }
            }
        }

        // Einfache String-Manipulation für HH:MM:SS -> HH:MM
        if (timeStr.includes(':')) {
            const parts = timeStr.split(':');
            if (parts.length >= 2) {
                return `${parts[0]}:${parts[1]}`;
            }
        }
        return timeStr;
    }

    // --- Daten laden ---
    function loadWeek(){
        const params = new URLSearchParams({week_start:formatDate(currentWeekStart)});
        if(groupSelect && groupSelect.value){
            params.append('group_id', groupSelect.value);
        } else {
            params.append('klasse_id', klasseSelect.value);
        }
        fetch('paed-diary/week?'+params.toString(),{headers:{'Accept':'application/json'}})
            .then(r=>r.json())
            .then(data=>{
                cache=data;
                rebuildPauseMap(); // neu
                setModeBadge();
                render();
                if(!cache.schueler.length){hideEditor();}
                if(!columnsCardWrapper.classList.contains('d-none')) loadAllColumns();
                loadAppointments();
                updateAppointmentSchuelerList();
            })
            .catch(()=>{});
    }

    // Aktualisiert die Schüler-Liste für Termine basierend auf den verfügbaren Schülern im Cache
    function updateAppointmentSchuelerList(){
        if(!appointmentSchuelerList) return;

        appointmentSchuelerList.innerHTML = '';

        const schuelerToShow = cache.schueler || [];

        if(schuelerToShow.length > 0){
            schuelerToShow.forEach(s => {
                const div = document.createElement('div');
                div.className = 'form-check-inline mb-1';
                div.innerHTML = `
                    <input class="form-check-input" type="checkbox" id="app_sch_${s.id}" value="${s.id}" name="schueler_ids[]">
                    <label class="form-check-label" for="app_sch_${s.id}">${escapeHtml(s.name)}</label>
                `;
                appointmentSchuelerList.appendChild(div);
            });
        } else {
            appointmentSchuelerList.innerHTML = '<span class="text-muted small">Keine Schüler in der aktuellen Auswahl.</span>';
        }
    }

    function loadAppointments(){
        const params = new URLSearchParams({
            start_date: formatDate(currentWeekStart),
            end_date: formatDate(addDays(currentWeekStart, 6))
        });

        if(groupSelect && groupSelect.value){
            params.append('group_id', groupSelect.value);
        } else {
            params.append('klasse_id', klasseSelect.value);
        }

        fetch('paed-diary/appointments?' + params.toString(), {
            headers: {'Accept': 'application/json'}
        })
        .then(r => r.json())
        .then(data => {
            cache.appointments = data.appointments || [];
            renderAppointments();
        })
        .catch(() => {
            cache.appointments = [];
        });
    }

    function renderAppointments(){
        // Zuerst alle bestehenden Termine-Anzeigen entfernen
        document.querySelectorAll('.day-appointments, .student-appointments').forEach(el => el.remove());

        cache.days.forEach(day => {
            const dayAppointments = cache.appointments.filter(app => app.date === day.date);
            const dayHeader = document.querySelector(`th[data-date="${day.date}"]`);

            if(dayAppointments.length > 0) {
                // Termine nach Typ sortieren: Klassen-/Gruppen-Termine vs. individuelle Schüler-Termine
                const classGroupAppointments = [];
                const individualAppointments = [];

                dayAppointments.forEach(appointment => {
                    // Überprüfen ob schueler, klassen und groups Arrays sind oder existieren
                    const schuelerArray = Array.isArray(appointment.schueler) ? appointment.schueler : [];
                    const klassenArray = Array.isArray(appointment.klassen) ? appointment.klassen : [];
                    const groupsArray = Array.isArray(appointment.groups) ? appointment.groups : [];

                    // Prüfen ob es ein individueller Schüler-Termin ist
                    const hasOnlyIndividualStudents = schuelerArray.length > 0 &&
                                                      klassenArray.length === 0 &&
                                                      groupsArray.length === 0;

                    if(hasOnlyIndividualStudents) {
                        individualAppointments.push(appointment);
                    } else {
                        classGroupAppointments.push(appointment);
                    }
                });

                // Klassen-/Gruppen-Termine im Spaltenkopf anzeigen
                if(dayHeader && classGroupAppointments.length > 0) {
                    const appointmentsDiv = document.createElement('div');
                    appointmentsDiv.className = 'day-appointments mt-1';
                    appointmentsDiv.style.fontSize = '0.7rem';

                    classGroupAppointments.forEach(appointment => {
                        const appointmentSpan = document.createElement('div');
                        appointmentSpan.className = 'appointment-item bg-warning text-dark px-1 mb-1 rounded';
                        appointmentSpan.style.cursor = 'pointer';
                        appointmentSpan.title = appointment.description || appointment.title;


                        let timeText = '';
                        if(appointment.start_time){
                            timeText = formatTime(appointment.start_time);
                            if(appointment.end_time){
                                timeText += ` - ${formatTime(appointment.end_time)}`;
                            }
                            timeText += ' ';
                        }

                        // In der Gruppenansicht Klassennamen hinzufügen
                        let titleText = escapeHtml(trimText(appointment.title, 20));
                        if(cache.is_group && appointment.klassen && appointment.klassen.length > 0) {
                            const klassenNames = appointment.klassen.map(k => k.name).join(', ');
                            titleText += ` (${escapeHtml(klassenNames)})`;
                        }

                        appointmentSpan.innerHTML = `${timeText}${titleText}`;

                        // Click-Event zum Bearbeiten
                        appointmentSpan.addEventListener('click', () => {
                            editAppointment(appointment);
                        });

                        appointmentsDiv.appendChild(appointmentSpan);
                    });

                    dayHeader.appendChild(appointmentsDiv);
                }

                // Individuelle Schüler-Termine in den entsprechenden Schüler-Zeilen anzeigen
                individualAppointments.forEach(appointment => {
                    const schuelerArray = Array.isArray(appointment.schueler) ? appointment.schueler : [];

                    schuelerArray.forEach(schueler => {
                        const studentCell = document.querySelector(`td[data-stu="${schueler.id}"][data-date="${day.date}"]`);
                        if(studentCell) {
                            // Prüfen ob bereits ein Container für Schüler-Termine existiert
                            let studentAppointmentsDiv = studentCell.querySelector('.student-appointments');
                            if(!studentAppointmentsDiv) {
                                studentAppointmentsDiv = document.createElement('div');
                                studentAppointmentsDiv.className = 'student-appointments mt-1';
                                studentAppointmentsDiv.style.fontSize = '0.65rem';
                                // Container nach den Einträgen aber vor den Spalten-Inputs einfügen
                                const colInputsRow = studentCell.querySelector('.col-inputs-row');
                                if(colInputsRow) {
                                    studentCell.insertBefore(studentAppointmentsDiv, colInputsRow);
                                } else {
                                    studentCell.appendChild(studentAppointmentsDiv);
                                }
                            }

                            const appointmentSpan = document.createElement('div');
                            appointmentSpan.className = 'appointment-item bg-info text-white px-1 mb-1 rounded';
                            appointmentSpan.style.cursor = 'pointer';
                            appointmentSpan.title = appointment.description || appointment.title;

                            let timeText = '';
                            if(appointment.start_time){
                                timeText = formatTime(appointment.start_time);
                                if(appointment.end_time){
                                    timeText += ` - ${formatTime(appointment.end_time)}`;
                                }
                                timeText += ' ';
                            }

                            appointmentSpan.innerHTML = `${timeText}${escapeHtml(trimText(appointment.title, 15))}`;

                            // Click-Event zum Bearbeiten
                            appointmentSpan.addEventListener('click', () => {
                                editAppointment(appointment);
                            });

                            studentAppointmentsDiv.appendChild(appointmentSpan);
                        }
                    });
                });
            }
        });
    }

    function editAppointment(appointment){
        appointmentForm.reset();
        appointmentIdInput.value = appointment.id;
        appointmentTitleInput.value = appointment.title;
        appointmentDescriptionInput.value = appointment.description || '';
        appointmentStartDateInput.value = appointment.date;

        // Zeiten richtig formatieren - von ISO DateTime oder Time zu HH:MM
        appointmentStartTimeInput.value = formatTime(appointment.start_time) || '';
        appointmentEndTimeInput.value = formatTime(appointment.end_time) || '';

        appointmentIsRecurringInput.checked = appointment.is_recurring || false;

        if(appointment.is_recurring){
            recurringOptions.classList.remove('d-none');
            appointmentRecurringTypeSelect.value = appointment.recurring_type || 'weekly';
            appointmentRecurringIntervalInput.value = appointment.recurring_interval || 1;
            appointmentRecurringEndDateInput.value = appointment.recurring_end_date || '';
        } else {
            recurringOptions.classList.add('d-none');
        }

        // Klassenzuweisungen übernehmen
        if(appointmentStudentsBox){
            // Alle Checkboxen zurücksetzen
            appointmentStudentsBox.querySelectorAll('input[type="checkbox"]').forEach(cb => {
                cb.checked = false;
            });

            // Zugewiesene Klassen/Gruppen ankreuzen
            if(appointment.klassen && appointment.klassen.length > 0){
                appointment.klassen.forEach(klasse => {
                    const checkbox = appointmentStudentsBox.querySelector(`input[name="klasse_ids[]"][value="${klasse.id}"]`);
                    if(checkbox){
                        checkbox.checked = true;
                    }
                });
            }

            if(appointment.groups && appointment.groups.length > 0){
                appointment.groups.forEach(group => {
                    const checkbox = appointmentStudentsBox.querySelector(`input[name="group_ids[]"][value="${group.id}"]`);
                    if(checkbox){
                        checkbox.checked = true;
                    }
                });
            }

            // Zugewiesene Schüler ankreuzen
            if(appointment.schueler && appointment.schueler.length > 0){
                appointment.schueler.forEach(schueler => {
                    const checkbox = appointmentStudentsBox.querySelector(`input[name="schueler_ids[]"][value="${schueler.id}"]`);
                    if(checkbox){
                        checkbox.checked = true;
                    }
                });
            }
        }

        appointmentModalTitle.textContent = 'Termin bearbeiten';
        appointmentDeleteBtn.classList.remove('d-none');

        if(appointment.is_recurring){
            appointmentPauseBtn.classList.remove('d-none');
            appointmentPauseBtn.textContent = appointment.is_paused ? 'Reaktivieren' : 'Pausieren';
        } else {
            appointmentPauseBtn.classList.add('d-none');
        }

        setAppointmentFeedback('','');
        appointmentModal.modal('show');
    }

    function loadAllColumns(){
        if(groupSelect.value){ return; } // Spaltenverwaltung nur im Klassenmodus
        const p = new URLSearchParams({ klasse_id: klasseSelect.value });
        fetch('paed-diary/columns/all?' + p.toString(), { headers: { 'Accept': 'application/json' } })
            .then(r => { if (!r.ok) throw new Error('Failed'); return r.json(); })
            .then(data => { columnsAllCache = data.columns || []; renderColumnsList(); populateColumnCategoryControls(); })
            .catch(()=> setColumnsFeedback('Fehler beim Laden der Spalten','danger'));
    }

    // Baut/füllt das Category-Auswahlfeld im Add-Column-Form
    function populateColumnCategoryControls(){
        if(!addColumnForm) return;
        // Erzeuge Select falls noch nicht vorhanden
        let sel = addColumnForm.querySelector('select[name="category_select"]');
        let newInput = addColumnForm.querySelector('input[name="new_category"]');
        if(!sel){
            sel = document.createElement('select');
            sel.name = 'category_select';
            sel.className = 'form-control form-control-sm mr-1 mb-1';
            sel.innerHTML = '<option value="">-- Kategorie (neu oder wählen) --</option>';
            addColumnForm.insertBefore(sel, addColumnForm.children[1]);
        }
        if(!newInput){
            newInput = document.createElement('input');
            newInput.type = 'text';
            newInput.name = 'new_category';
            newInput.placeholder = 'Neue Kategorie (optional)';
            newInput.className = 'form-control form-control-sm mr-1 mb-1';
            addColumnForm.insertBefore(newInput, addColumnForm.children[2]);
        }
        // Fülle Optionen aus vorhandenen Kategorien aus cache
        const cats = Array.from(new Set(columnsAllCache.map(c=>c.category).filter(Boolean))).sort((a,b)=>a.localeCompare(b,'de'));
        sel.innerHTML = '<option value="">-- Keine / Neue --</option>' + cats.map(c=>`<option value="${escapeHtml(c)}">${escapeHtml(c)}</option>`).join('');
    }

    // Gruppen laden
    function loadGroups(){
        return fetch('paed-diary/class-groups',{headers:{'Accept':'application/json'}})
            .then(r=>r.json())
            .then(j=>{ groupsCache = j.groups||[]; renderGroupsList(); refreshGroupSelect(); })
            .catch(()=>{});
    }
    function renderGroupsList(){
        if(!groupsListDiv) return; if(!groupsCache.length){ groupsListDiv.innerHTML='<span class="text-muted">Keine Kopplungen</span>'; return; }
        groupsListDiv.innerHTML = groupsCache.map(g=>{
            const cls = g.klassen.map(k=>escapeHtml(k.name)).join(', ');
            return `<div class="border rounded p-2 mb-1" data-group-id="${g.id}">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <strong>${escapeHtml(g.name)}</strong><br><span class="text-muted">${cls}</span>
                    </div>
                    <div class="btn-group btn-group-sm">
                        <button type="button" class="btn btn-outline-primary edit-group" data-id="${g.id}" title="Bearbeiten"><i class="fas fa-edit"></i></button>
                        <button type="button" class="btn btn-outline-danger del-group" data-id="${g.id}" title="Löschen"><i class="fas fa-trash"></i></button>
                    </div>
                </div>
            </div>`;
        }).join('');
    }
    function refreshGroupSelect(){
        if(!groupSelect) return; const sel = groupSelect.value; const opts = ['<option value="">-- Gruppe --</option>'].concat(groupsCache.map(g=>`<option value="${g.id}" ${String(g.id)===String(sel)?'selected':''}>${escapeHtml(g.name)}</option>`));
        groupSelect.innerHTML = opts.join('');
    }
    function setGroupFeedback(msg,type='info'){ if(groupFeedback){ const colors={info:'#17a2b8',success:'#28a745',warning:'#ffc107',danger:'#dc3545'}; groupFeedback.innerHTML=`<span style="color:${colors[type]||'#6c757d'}">${escapeHtml(msg)}</span>`; } }
    function setColumnsFeedback(msg,type='info'){ if(!columnsFeedback) return; const colors={info:'#17a2b8',success:'#28a745',warning:'#ffc107',danger:'#dc3545'}; columnsFeedback.innerHTML = `<span style="color:${colors[type]||'#6c757d'}">${escapeHtml(msg)}</span>`; }

    // --- Rendering ---
    function buildEntryMap(){
        const m={};
        if(!cache.entries) return m;
        const weekDates = (cache.days||[]).map(d=>d.date);
        if(!weekDates.length) return m;
        const weekStartStr = weekDates[0];
        const weekEndStr = weekDates[weekDates.length-1];
        const weekStartDate = new Date(weekStartStr+ 'T00:00:00');
        const weekEndDate = new Date(weekEndStr+ 'T00:00:00');
        const today = new Date(); today.setHours(0,0,0,0);

        cache.entries.forEach(e=>{
            const entryStartDate = new Date(e.date+'T00:00:00');
            const isCompleted = !!e.completed_at;
            // Wenn abgeschlossen: nur an seinem eigenen Datum (fertige Klone existieren ggf. schon für andere Tage separat)
            if(isCompleted){
                e.schueler_ids.forEach(sid=>{
                    if(isPaused(e.id, sid, e.date)) return; // pausiert -> ausblenden
                    (m[sid]||(m[sid]={}))[e.date]=(m[sid][e.date]||[]);
                    if(!m[sid][e.date].some(x=>x.id===e.id)) m[sid][e.date].push(Object.assign({}, e, {virtual_date:e.date}));
                });
                return;
            }
            // Offen: ab max(Startdatum, Wochenstart) bis min(heute, Wochenende) anzeigen
            const from = entryStartDate < weekStartDate ? weekStartDate : entryStartDate;
            let to;
            if(weekStartDate > today){
                // Zukunftswoche: komplette Woche projizieren
                to = weekEndDate;
            } else {
                // Aktuelle / Vergangene Woche: nur bis heute (oder Wochenende, falls früher)
                to = today < weekEndDate ? today : weekEndDate;
            }

            if(to < from) to = from; // Sicherheit
            for(let d=new Date(from); d<=to; d.setDate(d.getDate()+1)){
                const dateStr = formatDate(d);
                e.schueler_ids.forEach(sid=>{
                    if(isPaused(e.id, sid, dateStr)) return; // pausiert an diesem Tag
                    (m[sid]||(m[sid]={}))[dateStr]=(m[sid][dateStr]||[]);
                    if(!m[sid][dateStr].some(x=>x.id===e.id)) m[sid][dateStr].push(Object.assign({}, e, {virtual_date:dateStr}));
                });
            }
        });
        return m;
    }
    function render(){
        const showPaused = showPausedToggle ? !!showPausedToggle.checked : false;
        diaryHead.innerHTML='';
        const todayStr = formatDate(new Date());
        diaryHead.insertAdjacentHTML('beforeend','<tr><th style="min-width:180px;">Schüler</th>' + cache.days.map(d=>{const isToday=d.date===todayStr;return `<th class="text-center${isToday? ' today-header':''}" data-date="${d.date}">${d.label}</th>`;}).join('') + '</tr>');
        const entryMap = buildEntryMap();
        diaryBody.innerHTML='';
        const taskStudentIds = new Set((cache.tasks||[]).map(t=>t.schueler_id));
        let lastKlasseId = null;

        (cache.schueler||[]).forEach(stu=>{
            // Klassen-Divider in Gruppenmodus
            if(cache.is_group && stu.klasse_id !== lastKlasseId){
                lastKlasseId = stu.klasse_id;
                const kObj = (cache.klassen||[]).find(k=>k.id===stu.klasse_id);
                const divider = document.createElement('tr');
                divider.className = 'class-divider-row';
                const td = document.createElement('td');
                td.colSpan = (cache.days||[]).length + 1;
                td.textContent = (kObj? kObj.name : ('Klasse ' + stu.klasse_id));
                if(kObj && kObj.color){
                    td.style.backgroundColor = kObj.color;
                    const brightness = getBrightness(kObj.color);
                    td.style.color = brightness > 128 ? '#000000' : '#ffffff';
                }
                divider.appendChild(td);
                diaryBody.appendChild(divider);
            }

            // Kopfzelle mit Schülerlink und Stage
            let row = `<th class="align-top" style="font-size:.72rem;">`+
                      `<a href="paed-diary/schueler/${stu.id}" class="text-decoration-none" title="Detailansicht öffnen">${escapeHtml(stu.name)} <i class=\"fas fa-external-link-alt small ml-1\"></i></a>`+
                      `<span class="badge badge-light ml-1" title="Klasse">${(cache.klassen.find(k=>k.id===stu.klasse_id)||{}).kuerzel||''}</span>`+
                      `${renderStageSymbol(stu)}`+
                      `</th>`;

            // Zellen für Tage
            (cache.days||[]).forEach(d=>{
                const entries = (entryMap[stu.id]?.[d.date]) || [];
                const entriesHtml = entries.map(e=>{
                    const enc = encodeURIComponent(e.content||'');
                    const isOpen = !e.completed_at;
                    const pauseBtn = isOpen ? `<button type="button" class="diary-btn diary-btn-pause entry-pause-btn" data-entry-id="${e.id}" data-stu="${stu.id}" data-date="${d.date}" title="Notiz an diesem Tag ausblenden" aria-label="Pausieren">⏸</button>` : '';
                    const completeBtn = isOpen ? `<button type="button" class="diary-btn diary-btn-complete entry-complete-btn" data-entry-id="${e.id}" title="Notiz abschließen" aria-label="Abschließen">✔</button>` : '';
                    return `<div class=\"entry-item d-flex align-items-start\" data-entry=\"${e.id}\" data-content=\"${enc}\" data-date-display=\"${d.date}\">`+
                           `<div class=\"flex-grow-1\">${e.user? `<span class=\"author\">${escapeHtml(e.user)}</span>` : ''}<span class=\"text\">${escapeHtml(trimText(e.content,120))}</span>${isOpen && e.virtual_date!==e.date? ' <span class=\"badge badge-warning badge-pill ml-1\" title="Fortlaufende offene Notiz">laufend</span>':''}</div>`+
                           `<div class=\"ml-1 d-flex\">${completeBtn}${pauseBtn}</div>`+
                           `</div>`;
                }).join('');
                // Pausierte offene Einträge als Platzhalter anzeigen
                let pausedHtml = '';
                if(showPaused){
                    (cache.entries||[]).forEach(e=>{
                        if(e.completed_at) return; // nur offene
                        if(!e.schueler_ids.includes(stu.id)) return;
                        if(isPaused(e.id, stu.id, d.date)){
                            pausedHtml += `<div class=\"entry-item paused-entry d-flex align-items-start text-muted\" data-entry=\"${e.id}\" data-date-display=\"${d.date}\">`+
                                          `<div class=\"flex-grow-1\"><em>${escapeHtml(trimText(e.content,100))}</em> <span class=\"badge badge-light ml-1\" title=\"Pausiert\">Pause</span></div>`+
                                          `<div class=\"ml-1 d-flex\"><button type=\"button\" class=\"diary-btn diary-btn-unpause entry-unpause-btn\" data-entry-id=\"${e.id}\" data-stu=\"${stu.id}\" data-date=\"${d.date}\" title=\"Notiz an diesem Tag wieder anzeigen\" aria-label=\"Reaktivieren\">▶</button></div>`+
                                          `</div>`;
                        }
                    });
                }
                const isToday = d.date === todayStr;
                row += `<td class=\"note-cell${taskStudentIds.has(stu.id)?' stu-has-task-cell':''}${isToday? ' today-cell':''}\" data-stu=\"${stu.id}\" data-date=\"${d.date}\">`+
                       `<div class=\"entry-add-space\" style=\"min-height:18px; cursor:pointer;\" title=\"Neue Notiz erstellen\"></div>`+
                       `<div class=\"entry-list\">${entriesHtml}${pausedHtml}</div>`+
                       `<div class=\"col-inputs-row\"><div class=\"col-inputs\">${renderColumnInputs(stu.id,d.date)}</div></div>`+
                       `</td>`;
            });

            const tr = document.createElement('tr');
            if(taskStudentIds.has(stu.id)) tr.classList.add('stu-has-task');
            tr.innerHTML = row;
            diaryBody.appendChild(tr);
        });

        const endWeek = addDays(currentWeekStart,4);
        weekLabel.textContent = `${currentWeekStart.toLocaleDateString()} - ${endWeek.toLocaleDateString()}`;
        renderStudentCheckboxes();
        taskSchuelerSelect.innerHTML = '<option value="">-- Schüler --</option>' + (cache.schueler||[]).map(s=>`<option value="${s.id}">${escapeHtml(s.name)}</option>`).join('');
        exportCsvBtn.classList.remove('disabled');
        if(groupSelect && groupSelect.value){
            exportCsvBtn.href = `/export/paed-diary/excel?group_id=${encodeURIComponent(groupSelect.value)}&week_start=${encodeURIComponent(formatDate(currentWeekStart))}`;
            exportCsvBtn.title='CSV Export (Gruppe)';
            manageColumnsBtn.classList.add('disabled');
        } else {
            exportCsvBtn.href = `/export/paed-diary/excel?klasse_id=${encodeURIComponent(klasseSelect.value)}&week_start=${encodeURIComponent(formatDate(currentWeekStart))}`;
            exportCsvBtn.title='CSV Export';
            manageColumnsBtn.classList.remove('disabled');
        }
        renderTasks();
    }

    // Event Listener Erweiterung für Pause/Complete Buttons in diaryBody
    diaryBody.addEventListener('click', e=>{
        const pauseBtn = e.target.closest('.entry-pause-btn');
        if(pauseBtn){
            e.stopImmediatePropagation(); // verhindert andere Listener auf diaryBody
            const entryId = pauseBtn.dataset.entryId;
            const stu = pauseBtn.dataset.stu;
            const date = pauseBtn.dataset.date;
            pauseBtn.disabled = true;
            const fd = new FormData(); fd.append('schueler_id', stu); fd.append('date', date);
            fetch(`paed-diary/entry/${entryId}/pause-day`, {method:'POST', headers:{'X-CSRF-TOKEN':csrf,'Accept':'application/json'}, body:fd})
                .then(r=>r.json())
                .then(j=>{ if(j.success){ cache.pauses.push({entry_id: parseInt(entryId), schueler_id: parseInt(stu), date: date}); rebuildPauseMap(); render(); } else { alert(j.message||'Fehler beim Pausieren'); pauseBtn.disabled=false; } })
                .catch(()=>{ alert('Fehler beim Pausieren'); pauseBtn.disabled=false; });
            return;
        }
        const unpauseBtn = e.target.closest('.entry-unpause-btn');
        if(unpauseBtn){
            e.stopImmediatePropagation(); // verhindert andere Listener auf diaryBody
            const entryId = unpauseBtn.dataset.entryId;
            const stu = unpauseBtn.dataset.stu;
            const date = unpauseBtn.dataset.date;
            unpauseBtn.disabled = true;
            const fd = new FormData(); fd.append('schueler_id', stu); fd.append('date', date);
            fetch(`paed-diary/entry/${entryId}/unpause-day`, {method:'POST', headers:{'X-CSRF-TOKEN':csrf,'Accept':'application/json'}, body:fd})
                .then(r=>r.json())
                .then(j=>{ if(j.success){ cache.pauses = cache.pauses.filter(p=> !(String(p.entry_id)===String(entryId) && String(p.schueler_id)===String(stu) && p.date===date)); rebuildPauseMap(); render(); } else { alert(j.message||'Fehler beim Reaktivieren'); unpauseBtn.disabled=false; } })
                .catch(()=>{ alert('Fehler beim Reaktivieren'); unpauseBtn.disabled=false; });
            return;
        }
        const completeBtn = e.target.closest('.entry-complete-btn');
        if(completeBtn){
            e.stopImmediatePropagation(); // verhindert andere Listener auf diaryBody
            const entryId = completeBtn.dataset.entryId;
            completeBtn.disabled=true;
            fetch(`paed-diary/entry/${entryId}/complete`,{method:'POST',headers:{'Content-Type':'application/json','X-CSRF-TOKEN':csrf,'Accept':'application/json'},body:JSON.stringify({klasse_id:klasseSelect.value})})
                .then(r=>r.json())
                .then(j=>{ if(j.success){ loadWeek(); } else { alert(j.message||'Fehler'); completeBtn.disabled=false; } })
                .catch(()=>{ alert('Fehler'); completeBtn.disabled=false; });
            return;
        }
    });

    // --- Gruppen UI Events ---
    manageGroupsBtn && manageGroupsBtn.addEventListener('click', ()=>{ groupForm.reset(); groupIdInput.value=''; groupCancelEdit.classList.add('d-none'); setGroupFeedback('',''); loadGroups().then(()=> groupModal.modal('show')); });
    groupCancelEdit && groupCancelEdit.addEventListener('click', ()=>{ groupForm.reset(); groupIdInput.value=''; groupCancelEdit.classList.add('d-none'); });
    groupsListDiv && groupsListDiv.addEventListener('click', e=>{
        const editBtn=e.target.closest('.edit-group'); const delBtn=e.target.closest('.del-group');
        if(editBtn){ const id=editBtn.dataset.id; const g=groupsCache.find(x=>String(x.id)===String(id)); if(!g) return; groupIdInput.value=g.id; groupNameInput.value=g.name;
            document.querySelectorAll('#groupKlassenBox input[type=checkbox]').forEach(cb=> cb.checked = g.klassen.some(k=>String(k.id)===cb.value)); groupCancelEdit.classList.remove('d-none'); }
        if(delBtn){ const id=delBtn.dataset.id; if(!confirm('Gruppe wirklich löschen?')) return; fetch(`paed-diary/class-groups/${id}`,{method:'DELETE',headers:{'X-CSRF-TOKEN':csrf,'Accept':'application/json'}}).then(r=>r.json()).then(j=>{ if(j.success){ setGroupFeedback('Gelöscht','success'); loadGroups(); if(groupSelect.value===String(id)){ groupSelect.value=''; loadWeek(); } } }); }
    });
    groupForm && groupForm.addEventListener('submit', e=>{ e.preventDefault(); const id=groupIdInput.value.trim(); const name=groupNameInput.value.trim(); const klasseIds=[...document.querySelectorAll('#groupKlassenBox input[type=checkbox]:checked')].map(cb=>cb.value); if(klasseIds.length<2){ setGroupFeedback('Mindestens 2 Klassen wählen','warning'); return; } groupStatus.textContent='Speichere...'; const payload=new FormData(); payload.append('name',name); klasseIds.forEach(idv=> payload.append('klasse_ids[]', idv)); const url = id? `paed-diary/class-groups/${id}` : 'paed-diary/class-groups'; const method = id? 'PUT':'POST'; fetch(url,{method,headers:{'X-CSRF-TOKEN':csrf,'Accept':'application/json'},body:payload}).then(r=>r.json()).then(j=>{ if(j.success){ groupStatus.textContent='Gespeichert'; loadGroups(); refreshGroupSelect(); if(!id && j.group){ groupSelect.value=j.group.id; loadWeek(); } } else { groupStatus.textContent=j.message||'Fehler'; } }).catch(()=> groupStatus.textContent='Fehler'); });

    if(groupSelect){ groupSelect.addEventListener('change', ()=>{ if(groupSelect.value){ columnsCardWrapper.classList.add('d-none'); } loadWeek(); }); }

    // --- Navigation Events ---
    prevWeekBtn.addEventListener('click', ()=>{ currentWeekStart=addDays(currentWeekStart,-7); loadWeek(); });
    nextWeekBtn.addEventListener('click', ()=>{ currentWeekStart=addDays(currentWeekStart,7); loadWeek(); });
    todayWeekBtn.addEventListener('click', ()=>{ currentWeekStart=startOfWeek(new Date()); loadWeek(); });
    klasseSelect.addEventListener('change', ()=>{ if(groupSelect.value){ groupSelect.value=''; } currentWeekStart=startOfWeek(new Date()); loadWeek(); });

    // --- Note Editor Actions ---
    openNoteInline.addEventListener('click', ()=> populateForNew(null));
    noteClearBtn.addEventListener('click', ()=> populateForNew(null));
    noteEditorCancel.addEventListener('click', hideEditor);
    noteForm.addEventListener('submit', ev=>{ ev.preventDefault(); noteStatus.textContent='Speichere...'; const fd=new FormData(noteForm); if(groupSelect.value){ fd.set('group_id', groupSelect.value); } fd.set('klasse_id',klasseSelect.value); const completedCheckbox=document.getElementById('noteCompleted'); if(completedCheckbox && !completedCheckbox.checked){ fd.delete('completed'); } else { fd.set('completed','1'); } const id=noteEntryIdInput.value; const url=id?`paed-diary/entry/${id}`:'paed-diary/entry'; fetch(url,{method:'POST',headers:{'X-CSRF-TOKEN':csrf,'Accept':'application/json'},body:fd}).then(r=>r.json()).then(j=>{ if(j.success){ noteStatus.textContent='Gespeichert'; loadWeek(); if(!id){ clearEditor(); } } else { noteStatus.textContent=j.message||'Fehler'; } }).catch(()=> noteStatus.textContent='Fehler beim Speichern'); });
    noteDeleteBtn.addEventListener('click', ()=>{ const id=noteEntryIdInput.value; if(!id) return; if(!confirm('Eintrag wirklich löschen?')) return; noteStatus.textContent='Lösche...'; fetch(`paed-diary/entry/${id}?klasse_id=${encodeURIComponent(klasseSelect.value)}`,{method:'DELETE',headers:{'X-CSRF-TOKEN':csrf,'Accept':'application/json'}}).then(r=>r.json()).then(j=>{ if(j.success){ noteStatus.textContent='Gelöscht'; loadWeek(); clearEditor(); } else { noteStatus.textContent='Löschen fehlgeschlagen'; } }).catch(()=> noteStatus.textContent='Löschen fehlgeschlagen'); });

    // --- Aufgaben ---
    openTaskModalBtn.addEventListener('click', ()=>{ taskForm.reset(); document.getElementById('taskKlasseId').value=klasseSelect.value; taskModal.modal('show'); });
    taskForm.addEventListener('submit', e=>{
        e.preventDefault();
        const fd=new FormData(taskForm);
        if(groupSelect.value){
            fd.set('group_id', groupSelect.value);
        }
        fd.set('klasse_id',klasseSelect.value);
        if(!fd.get('highlighted')) fd.set('highlighted','0');
        fetch('paed-diary/task',{
            method:'POST',
            headers:{
                'X-CSRF-TOKEN':csrf,
                'Accept':'application/json'
            },
            body:fd
        }).then(r=>r.json()).then(j=>{
            if(j.success){
                taskModal.modal('hide');
                loadWeek();
            }
        }).catch(()=>{});
    });

    // --- Termine ---
    openAppointmentModalBtn.addEventListener('click', ()=>{
        appointmentForm.reset();
        appointmentIdInput.value = '';
        appointmentModalTitle.textContent = 'Termin erstellen';
        appointmentDeleteBtn.classList.add('d-none');
        appointmentPauseBtn.classList.add('d-none');
        setAppointmentFeedback('','');
        appointmentModal.modal('show');
    });

    appointmentIsRecurringInput.addEventListener('change', ()=>{
        if(appointmentIsRecurringInput.checked){
            recurringOptions.classList.remove('d-none');
        } else {
            recurringOptions.classList.add('d-none');
        }
    });

    appointmentForm.addEventListener('submit', e=>{
        e.preventDefault();
        appointmentStatus.textContent = 'Speichere...';

        const fd = new FormData(appointmentForm);

        // Prüfen ob bereits Klassen oder Gruppen explizit ausgewählt wurden
        const hasKlassenSelected = fd.getAll('klasse_ids[]').length > 0;
        const hasGroupsSelected = fd.getAll('group_ids[]').length > 0;
        const hasSchuelerSelected = fd.getAll('schueler_ids[]').length > 0;

        // Nur als Fallback die aktuell gewählte Klasse/Gruppe hinzufügen,
        // wenn keine explizite Auswahl getroffen wurde
        if (!hasKlassenSelected && !hasGroupsSelected && !hasSchuelerSelected) {
            if(groupSelect.value){
                fd.append('group_ids[]', groupSelect.value);
            } else {
                fd.append('klasse_ids[]', klasseSelect.value);
            }
        }

        const id = appointmentIdInput.value;
        const url = id ? `paed-diary/appointments/${id}` : 'paed-diary/appointments';

        // Laravel/PHP kann PUT-Requests mit FormData nicht direkt parsen.
        // Wir müssen einen POST-Request mit einem _method-Feld senden.
        const method = 'POST';
        if (id) {
            fd.append('_method', 'PUT');
        }

        fetch(url, {
            method: method,
            headers: {
                'X-CSRF-TOKEN': csrf,
                'Accept': 'application/json'
            },
            body: fd
        })
        .then(r => r.json())
        .then(j => {
            if(j.success){
                appointmentStatus.textContent = 'Gespeichert';
                setAppointmentFeedback('Termin erfolgreich gespeichert', 'success');
                setTimeout(() => {
                    appointmentModal.modal('hide');
                    loadWeek();
                }, 1000);
            } else {
                appointmentStatus.textContent = j.message || 'Fehler';
                setAppointmentFeedback(j.message || 'Fehler beim Speichern', 'danger');
            }
        })
        .catch(() => {
            appointmentStatus.textContent = 'Fehler beim Speichern';
            setAppointmentFeedback('Fehler beim Speichern', 'danger');
        });
    });

    appointmentDeleteBtn.addEventListener('click', ()=>{
        const id = appointmentIdInput.value;
        if(!id) return;
        if(!confirm('Termin wirklich löschen?')) return;

        appointmentStatus.textContent = 'Lösche...';
        fetch(`paed-diary/appointments/${id}`, {
            method: 'DELETE',
            headers: {
                'X-CSRF-TOKEN': csrf,
                'Accept': 'application/json'
            }
        })
        .then (r => r.json())
        .then(j => {
            if(j.success){
                appointmentStatus.textContent = 'Gelöscht';
                setAppointmentFeedback('Termin gelöscht', 'success');
                setTimeout(() => {
                    appointmentModal.modal('hide');
                    loadWeek();
                }, 1000);
            } else {
                appointmentStatus.textContent = 'Löschen fehlgeschlagen';
                setAppointmentFeedback('Löschen fehlgeschlagen', 'danger');
            }
        })
        .catch(() => {
            appointmentStatus.textContent = 'Löschen fehlgeschlagen';
            setAppointmentFeedback('Löschen fehlgeschlagen', 'danger');
        });
    });

    function setAppointmentFeedback(msg, type='info'){
        if(!appointmentFeedback) return;
        const alertClass = `alert-${type}`;
        appointmentFeedback.innerHTML = `<div class="alert ${alertClass} small p-2 mb-0">${escapeHtml(msg)}</div>`;
        if(msg === ''){
            appointmentFeedback.innerHTML = '';
        }
    }

    // --- Editor Hilfsfunktionen (neu hinzugefügt) ---
    function showEditor(){ if(noteEditorCard) noteEditorCard.classList.remove('d-none'); }
    function hideEditor(){ if(noteEditorCard) noteEditorCard.classList.add('d-none'); }
    function clearEditor(){ if(!noteForm) return; noteForm.reset(); noteEntryIdInput.value=''; noteStatus.textContent=''; if(noteStudentsDiv){ noteStudentsDiv.querySelectorAll('input[type=checkbox]').forEach(cb=> cb.checked=false); } }
    function populateForNew(cell){ showEditor(); clearEditor(); noteEditorTitle.textContent='Notiz erfassen'; noteDeleteBtn.classList.add('d-none'); if(cell){ const d=cell.dataset.date; if(d) noteDateInput.value=d; const stu=cell.dataset.stu; if(stu){ const cb=document.getElementById('stu_chk_'+stu); if(cb) cb.checked=true; } } }
    function populateForEdit(entryEl){ if(!entryEl) return; const entryId=entryEl.dataset.entry; const entryObj=(cache.entries||[]).find(e=> String(e.id)===String(entryId)); if(!entryObj) return; showEditor(); clearEditor(); noteEditorTitle.textContent='Notiz bearbeiten'; noteDeleteBtn.classList.remove('d-none'); noteEntryIdInput.value=entryId; const dateDisp=entryEl.dataset.dateDisplay || entryObj.date; if(dateDisp) noteDateInput.value=dateDisp; try{ noteContentInput.value=decodeURIComponent(entryEl.dataset.content||''); }catch(_){ noteContentInput.value=entryEl.dataset.content||''; } (entryObj.schueler_ids||[]).forEach(id=>{ const cb=document.getElementById('stu_chk_'+id); if(cb) cb.checked=true; }); }

    // Zusätzlicher Listener für Editor-Interaktionen (neu)
    diaryBody.addEventListener('click', function(e){
        const entryEl = e.target.closest('.entry-item');
        if(entryEl && !e.target.closest('.entry-pause-btn') && !e.target.closest('.entry-unpause-btn') && !e.target.closest('.entry-complete-btn')){
            populateForEdit(entryEl);
            return;
        }
        const cell = e.target.closest('.note-cell');
        if(cell){
            // Nur neue Notiz, wenn in den freien Bereich oder entry-add-space geklickt wird und kein Button/Spalteneingabe
            if(e.target.classList.contains('entry-add-space') || e.target.closest('.entry-add-space')){
                populateForNew(cell);
            }
        }
    });

// --- Events Diary ---
diaryBody.addEventListener('click', e=>{
    // Guard: wenn einer der Steuer-Buttons geklickt wurde, Editor nicht öffnen
    if(e.target.closest('.entry-pause-btn, .entry-unpause-btn, .entry-complete-btn')) return;
    const entry=e.target.closest('.entry-item');
    if(entry){ populateForEdit(entry); return; }
    const cell=e.target.closest('.note-cell');
    if(cell && !e.target.closest('.col-inputs')){ populateForNew(cell); }
});
diaryBody.addEventListener('input', e=>{ const inp=e.target.closest('.col-val-input'); if(!inp) return; const key=`${inp.dataset.col}-${inp.dataset.stu}-${inp.dataset.date}`; clearTimeout(debounceTimers[key]); const val=inp.value.trim(); debounceTimers[key]=setTimeout(()=>{ saveColumnValue(inp.dataset.col, inp.dataset.stu, inp.dataset.date, val).catch(()=>{inp.classList.add('border-danger'); setTimeout(()=>inp.classList.remove('border-danger'),1200);}); },400); });
diaryBody.addEventListener('click', e=>{ const btn=e.target.closest('.bool-btn'); if(!btn) return; const newVal=btn.dataset.value==='1'? '':'1'; btn.disabled=true; saveColumnValue(btn.dataset.col, btn.dataset.stu, btn.dataset.date, newVal).then(()=>{ btn.dataset.value=newVal; btn.classList.toggle('btn-success', newVal==='1'); btn.classList.toggle('btn-outline-secondary', newVal!=='1'); }).catch(()=>{btn.classList.add('btn-danger'); setTimeout(()=>btn.classList.remove('btn-danger'),1000);}).finally(()=>btn.disabled=false); });

// --- Columns Management Events ---
manageColumnsBtn.addEventListener('click', ()=>{ if(groupSelect.value){return;} columnsCardWrapper.classList.toggle('d-none'); if(!columnsCardWrapper.classList.contains('d-none')) loadAllColumns(); });
columnsCloseBtn.addEventListener('click', ()=> columnsCardWrapper.classList.add('d-none'));
columnsList.addEventListener('click', e=>{
    const rem=e.target.closest('.remove-col');
    const res=e.target.closest('.restore-col');
    if(rem){
        const chip=rem.closest('.column-chip');
        const id=chip.dataset.id;
        const col=columnsAllCache.find(c=>String(c.id)===String(id));
        if(!col) return;
        const ws=formatDate(currentWeekStart);
        if(!confirm(`Spalte "${col.name}" ab dieser Woche deaktivieren?`)) return;
        fetch(`paed-diary/column/${id}?week_start=${encodeURIComponent(ws)}&klasse_id=${encodeURIComponent(klasseSelect.value)}`,{
            method:'DELETE',
            headers:{'X-CSRF-TOKEN':csrf,'Accept':'application/json'}
        }).then(r=>r.json()).then(j=>{
            if(j.success){
                setColumnsFeedback('Spalte deaktiviert','warning');
                loadWeek();
                loadAllColumns();
            }
        });
    } else if(res){
        const chip=res.closest('.column-chip');
        const id=chip.dataset.id;
        fetch(`paed-diary/column/${id}/restore`,{method:'POST',headers:{'X-CSRF-TOKEN':csrf,'Accept':'application/json'}}).then(r=>r.json()).then(j=>{ if(j.success){ setColumnsFeedback('Spalte reaktiviert','success'); loadWeek(); loadAllColumns(); } });
    }
});
if(addColumnForm){
    addColumnForm.addEventListener('submit', e=>{
        e.preventDefault(); if(groupSelect.value) return;
        // gather values
        const name = addColumnForm.querySelector('input[name="name"]').value.trim();
        const type = addColumnForm.querySelector('select[name="type"]').value;
        const sel = addColumnForm.querySelector('select[name="category_select"]');
        const newCatInput = addColumnForm.querySelector('input[name="new_category"]');
        let category = '';
        if(newCatInput && newCatInput.value.trim()){ category = newCatInput.value.trim(); }
        else if(sel && sel.value){ category = sel.value; }
        const fd = new FormData();
        fd.append('name', name);
        if(type) fd.append('type', type);
        fd.append('klasse_id', klasseSelect.value);
        if(category) fd.append('category', category);
        fetch('paed-diary/column',{method:'POST',headers:{'X-CSRF-TOKEN':csrf,'Accept':'application/json'},body:fd}).then(r=>r.json()).then(j=>{ if(j.success){ addColumnForm.reset(); setColumnsFeedback('Spalte angelegt','success'); loadWeek(); loadAllColumns(); } else { setColumnsFeedback(j.message||'Fehler','danger'); } }).catch(()=> setColumnsFeedback('Fehler beim Anlegen','danger'));
    });
}

// --- Spaltenverwaltung: Deaktivierte ausblenden/anzeigen ---
let showDeactivatedColumns = false;

function ensureDeactivatedToggle() {
    if (!columnsList) return;
    let toggle = document.getElementById('showDeactivatedColumns');
    if (!toggle) {
        toggle = document.createElement('input');
        toggle.type = 'checkbox';
        toggle.id = 'showDeactivatedColumns';
        toggle.className = 'mr-2';
        toggle.style.verticalAlign = 'middle';
        const label = document.createElement('label');
        label.htmlFor = 'showDeactivatedColumns';
        label.className = 'small mr-3';
        label.textContent = 'Deaktivierte anzeigen';
        columnsList.parentNode.insertBefore(toggle, columnsList);
        columnsList.parentNode.insertBefore(label, columnsList);
        toggle.addEventListener('change', function() {
            showDeactivatedColumns = this.checked;
            renderColumnsList();
        });
    }
    toggle.checked = showDeactivatedColumns;
}

function renderColumnsList(){
    if(!columnsList) return;
    ensureDeactivatedToggle();
    if(!columnsAllCache.length){ columnsList.innerHTML='<span class="text-muted small">Keine Spalten</span>'; return; }

    // Gruppiere Spalten nach category
    const grouped = {};
    columnsAllCache.forEach(c=>{ const cat = c.category || 'Unkategorisiert'; (grouped[cat]=grouped[cat]||[]).push(c); });
    const cats = Object.keys(grouped).sort((a,b)=>{
        if(a==='Unkategorisiert') return 1;
        if(b==='Unkategorisiert') return -1;
        return a.localeCompare(b,'de');
    });

    // Build global category options for per-column selects
    const allCats = cats.slice();
    const optionsHtml = allCats.map(c=>`<option value="${escapeHtml(c)}">${escapeHtml(c)}</option>`).join('');

    columnsList.innerHTML = cats.map(cat=>{
        // Filter: Zeige deaktivierte nur wenn Option aktiv
        const cols = grouped[cat].filter(c => showDeactivatedColumns || !c.deactivated_from);
        if (!cols.length) return '';
        const colsHtml = cols.map(c=>{
            const deac = !!c.deactivated_from;
            // per-column category select
            const sel = `<select class="col-cat-select form-control form-control-sm" data-id="${c.id}"><option value="">-- Keine --</option>${optionsHtml}</select>`;
            return `<div class="column-chip ${deac?'deactivated':''}" data-id="${c.id}" title="${escapeHtml(c.name)} (${c.type})${deac?` deaktiviert ab ${c.deactivated_from}`:''}"><div class="d-flex align-items-center"><span class="mr-2">${escapeHtml(c.name)}</span>${sel}${!deac?`<button type="button" class="remove-col btn btn-link btn-sm ml-2" title="Deaktivieren">&times;</button>`:`<button type="button" class="restore restore-col btn btn-link btn-sm ml-2" title="Reaktivieren">&#8634;</button>`}</div></div>`;
        }).join('');
        return `<div class="column-category"><div class="small text-primary font-weight-bold mb-1">${escapeHtml(cat)}</div><div class="column-category-list d-flex flex-wrap">${colsHtml}</div></div>`;
    }).join('');
    // set current values for selects
    document.querySelectorAll('.col-cat-select').forEach(sel=>{ const id=sel.dataset.id; const col = columnsAllCache.find(c=>String(c.id)===String(id)); if(col && col.category) sel.value = col.category; });
}

// Listen for per-column category changes
columnsList.addEventListener('change', e=>{
    const sel = e.target.closest('.col-cat-select');
    if(!sel) return;
    const id = sel.dataset.id;
    const category = sel.value || null;
    sel.disabled = true;
    fetch(`paed-diary/column/${id}/category`,{
        method:'POST',
        headers: {'X-CSRF-TOKEN':csrf, 'Accept':'application/json'},
        body: new URLSearchParams({category: category})
    }).then(r=>r.json()).then(j=>{
        if(j.success){ setColumnsFeedback('Kategorie aktualisiert','success'); loadAllColumns(); } else { setColumnsFeedback(j.message||'Fehler','danger'); }
    }).catch(()=> setColumnsFeedback('Fehler beim Speichern','danger')).finally(()=> sel.disabled=false);
});

// --- Editor-Funktionen ---
function showEditor(){ noteEditorCard.classList.remove('d-none'); }
function hideEditor(){ noteEditorCard.classList.add('d-none'); clearEditor(); }
function clearEditor(){ noteEntryIdInput.value=''; noteContentInput.value=''; noteDeleteBtn.classList.add('d-none'); noteEditorCard.classList.remove('editing'); noteEditorTitle.textContent='Notiz erfassen'; noteStatus.textContent=''; }
function populateForNew(cell){ clearEditor(); const date=cell? cell.dataset.date : formatDate(new Date()); noteDateInput.value=date; [...noteStudentsDiv.querySelectorAll('input[type=checkbox]')].forEach(cb=> cb.checked=false); if(cell){ const cb=document.getElementById('stu_chk_'+cell.dataset.stu); cb && (cb.checked=true); } showEditor(); noteContentInput.focus(); }
function populateForEdit(entryDiv){ clearEditor(); const id=entryDiv.dataset.entry; const entry=cache.entries.find(e=>String(e.id)===String(id)); noteEntryIdInput.value=id; noteEditorTitle.textContent='Notiz bearbeiten'; noteEditorCard.classList.add('editing'); noteDeleteBtn.classList.remove('d-none'); const cell=entryDiv.closest('.note-cell'); if(cell){ noteDateInput.value=cell.dataset.date; [...noteStudentsDiv.querySelectorAll('input[type=checkbox]')].forEach(cb=> cb.checked=false); if(entry?.schueler_ids){ noteStudentsDiv.querySelectorAll('input[type=checkbox]').forEach(cb=> cb.checked = entry.schueler_ids.includes(parseInt(cb.value))); } }
    noteContentInput.value = entry?.content || decodeURIComponent(entryDiv.dataset.content||''); const completedCheckbox=document.getElementById('noteCompleted'); completedCheckbox && (completedCheckbox.checked=!!entry?.completed_at); showEditor(); noteContentInput.focus(); }
function saveColumnValue(colId, stuId, date, value){ return fetch('paed-diary/column/value',{method:'POST',headers:{'Content-Type':'application/json','X-CSRF-TOKEN':csrf,'Accept':'application/json'},body:JSON.stringify({column_id:colId,schueler_id:stuId,date:date,value:value})}).then(r=>{ if(!r.ok) throw new Error('fail'); return r.json(); }).then(()=>{ if(!cache.column_values[colId]) cache.column_values[colId]={}; if(!cache.column_values[colId][stuId]) cache.column_values[colId][stuId]={}; if(value===''){ delete cache.column_values[colId][stuId][date]; } else { cache.column_values[colId][stuId][date]=value; } }); }

// --- Tasks Events (close / complete) ---
tasksPanel.addEventListener('click', e=>{ const closeBtn=e.target.closest('.close-task-btn'); if(closeBtn){ const taskId=closeBtn.dataset.taskId; closeBtn.disabled=true; fetch(`paed-diary/task/${taskId}/close`,{method:'POST',headers:{'X-CSRF-TOKEN':csrf,'Accept':'application/json'}}).then(r=>r.json()).then(j=>{ if(j.success){ cache.tasks=cache.tasks.filter(t=>String(t.id)!==String(taskId)); renderTasks(); render(); } else { closeBtn.disabled=false; } }).catch(()=> closeBtn.disabled=false); return; } const completeBtn=e.target.closest('.complete-entry-btn'); if(completeBtn){ const entryId=completeBtn.dataset.entryId; completeBtn.disabled=true; fetch(`paed-diary/entry/${entryId}/complete`,{method:'POST',headers:{'Content-Type':'application/json','X-CSRF-TOKEN':csrf,'Accept':'application/json'},body:JSON.stringify({klasse_id:klasseSelect.value})}).then(r=>r.json()).then(j=>{ if(j.success){ loadWeek(); } else { alert(j.message||'Fehler'); completeBtn.disabled=false; } }).catch(()=>{ alert('Fehler'); completeBtn.disabled=false; }); } });
refreshTasksBtn.addEventListener('click', ()=> loadWeek());


// --- Initialisierung ---
    loadWeek();
    loadGroups();
    if(showPausedToggle){
        showPausedToggle.addEventListener('change', ()=> {
            // Nur Darstellung neu zeichnen (Daten bleiben gleich)
            render();
        });
    }

})();
