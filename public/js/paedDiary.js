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

    // --- State ---
    let currentWeekStart = startOfWeek(new Date());
    let cache = { days:[], schueler:[], entries:[], columns:[], column_values:{}, tasks:[], klassen:[], is_group:false, appointments:[] };
    let columnsAllCache = []; // inkl. deaktivierte
    let debounceTimers = {}; // für Spaltenwerte
    let groupsCache = [];

    // --- Utils ---
    function startOfWeek(d){const dt=new Date(d);const wd=dt.getDay();const diff=(wd===0?-6:1-wd);dt.setDate(dt.getDate()+diff);dt.setHours(0,0,0,0);return dt;}
    function formatDate(d){const year=d.getFullYear();const month=String(d.getMonth()+1).padStart(2,'0');const day=String(d.getDate()).padStart(2,'0');return `${year}-${month}-${day}`;}
    function addDays(d,x){const n=new Date(d);n.setDate(n.getDate()+x);return n;}
    function escapeHtml(str){return String(str).replace(/[&<>"']/g,s=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;','\'':'&#39;'}[s]));}
    function trimText(str,len){return str.length<=len?str:str.slice(0,len-1)+'…';}
    function setModeBadge(){ if(cache.is_group){ modeBadge.classList.remove('d-none'); } else { modeBadge.classList.add('d-none'); } }

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
            .then(data=>{cache=data; setModeBadge(); render(); if(!cache.schueler.length){hideEditor();} if(!columnsCardWrapper.classList.contains('d-none')) loadAllColumns(); loadAppointments();})
            .catch(()=>{});
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
        // Termine zu den entsprechenden Tagen hinzufügen
        cache.days.forEach(day => {
            const dayAppointments = cache.appointments.filter(app => app.date === day.date);

            // Termine in der Tagesheader anzeigen
            const dayHeader = document.querySelector(`th[data-date="${day.date}"]`);
            if(dayHeader && dayAppointments.length > 0){
                // Entferne vorherige Termine-Anzeige
                const existingAppointments = dayHeader.querySelector('.day-appointments');
                if(existingAppointments){
                    existingAppointments.remove();
                }

                const appointmentsDiv = document.createElement('div');
                appointmentsDiv.className = 'day-appointments mt-1';
                appointmentsDiv.style.fontSize = '0.7rem';

                dayAppointments.forEach(appointment => {
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

                    appointmentSpan.innerHTML = `${timeText}${escapeHtml(trimText(appointment.title, 20))}`;

                    // Click-Event zum Bearbeiten
                    appointmentSpan.addEventListener('click', () => {
                        editAppointment(appointment);
                    });

                    appointmentsDiv.appendChild(appointmentSpan);
                });

                dayHeader.appendChild(appointmentsDiv);
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
    function setModeBadge(){ if(cache.is_group){ modeBadge.classList.remove('d-none'); } else { modeBadge.classList.add('d-none'); } }

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
            .then(data=>{cache=data; setModeBadge(); render(); if(!cache.schueler.length){hideEditor();} if(!columnsCardWrapper.classList.contains('d-none')) loadAllColumns(); loadAppointments();})
            .catch(()=>{});
    }

    function loadAllColumns(){
        if(groupSelect.value){ return; } // Spaltenverwaltung nur im Klassenmodus
        const p = new URLSearchParams({ klasse_id: klasseSelect.value });
        fetch('paed-diary/columns/all?' + p.toString(), { headers: { 'Accept': 'application/json' } })
            .then(r => { if (!r.ok) throw new Error('Failed'); return r.json(); })
            .then(data => { columnsAllCache = data.columns || []; renderColumnsList(); })
            .catch(()=> setColumnsFeedback('Fehler beim Laden der Spalten','danger'));
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

    // --- Rendering ---
    function buildEntryMap(){const m={};cache.entries.forEach(e=>e.schueler_ids.forEach(s=>{(m[s]||(m[s]={}))[e.date]=(m[s][e.date]||[]);m[s][e.date].push(e);}));return m;}
    function render(){
        diaryHead.innerHTML='';
        const todayStr = formatDate(new Date());
        diaryHead.insertAdjacentHTML('beforeend','<tr><th style="min-width:180px;">Schüler</th>' + cache.days.map(d=>{const isToday=d.date===todayStr;return `<th class="text-center${isToday?' today-header':''}" data-date="${d.date}">${d.label}</th>`;}).join('') + '</tr>');
        const entryMap=buildEntryMap();
        diaryBody.innerHTML='';
        const taskStudentIds=new Set(cache.tasks.map(t=>t.schueler_id));
        let lastKlasseId=null;
        cache.schueler.forEach(stu=>{
            if(cache.is_group && stu.klasse_id!==lastKlasseId){
                lastKlasseId=stu.klasse_id;
                const kObj = (cache.klassen||[]).find(k=>k.id===stu.klasse_id);
                const divider=document.createElement('tr');
                divider.className='class-divider-row';
                const td=document.createElement('td');
                td.colSpan = cache.days.length+1;
                td.textContent = (kObj? kObj.name : ('Klasse '+stu.klasse_id));
                divider.appendChild(td);
                diaryBody.appendChild(divider);
            }
            let row = `<th class="align-top" style="font-size:.72rem;">
                <a href="paed-diary/schueler/${stu.id}" class="text-decoration-none" title="Detailansicht öffnen">${stu.name} <i class="fas fa-external-link-alt small ml-1"></i></a>
                <span class="badge badge-light ml-1" title="Klasse">${(cache.klassen.find(k=>k.id===stu.klasse_id)||{}).kuerzel||''}</span>
            </th>`;
            cache.days.forEach(d=>{
                const entries=(entryMap[stu.id]?.[d.date])||[];
                const entriesHtml=entries.map(e=>{const enc=encodeURIComponent(e.content||'');return `<div class="entry-item" data-entry="${e.id}" data-content="${enc}">`+(e.user?`<span class="author">${escapeHtml(e.user)}</span>`:'')+`<span class="text">${escapeHtml(trimText(e.content,120))}</span></div>`;}).join('');
                const isToday = d.date === todayStr;
                row += `<td class="note-cell${taskStudentIds.has(stu.id)?' stu-has-task-cell':''}${isToday?' today-cell':''}" data-stu="${stu.id}" data-date="${d.date}"><div class="entry-list">${entriesHtml}</div><div class="col-inputs-row"><div class="col-inputs">${renderColumnInputs(stu.id,d.date)}</div></div></td>`;
            });
            const tr=document.createElement('tr');
            if(taskStudentIds.has(stu.id)) tr.classList.add('stu-has-task');
            tr.innerHTML=row; diaryBody.appendChild(tr);
        });
        const endWeek=addDays(currentWeekStart,4);
        weekLabel.textContent=`${currentWeekStart.toLocaleDateString()} - ${endWeek.toLocaleDateString()}`;
        renderStudentCheckboxes();
        taskSchuelerSelect.innerHTML='<option value="">-- Schüler --</option>'+cache.schueler.map(s=>`<option value="${s.id}">${s.name}</option>`).join('');
        // Export-Button immer aktiv, auch im Gruppenmodus
        exportCsvBtn.classList.remove('disabled');
        if(groupSelect.value){
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
    function renderColumnInputs(stuId, date) {
        if (!cache.columns.length) return '';
        const student = cache.schueler.find(s=>s.id===stuId);
        let cols = cache.columns;
        if(cache.is_group && student){
            cols = cols.filter(c=>c.klasse_id === student.klasse_id);
        }
        return cols.map(col => {
            const v = cache.column_values?.[col.id]?.[stuId]?.[date] || '';
            if (col.type === 'boolean') {
                const active = v === '1';
                return `<button type="button" class="btn btn-${active?'success':'outline-secondary'} bool-btn" data-col="${col.id}" data-stu="${stuId}" data-date="${date}" data-value="${active?1:0}" title="${escapeHtml(col.name)}">${escapeHtml(col.name)}</button>`;
            } else if (col.type === 'number') {
                return `<input type="number" class="form-control form-control-sm col-val-input" data-col="${col.id}" data-stu="${stuId}" data-date="${date}" value="${escapeHtml(v)}" title="${escapeHtml(col.name)}" />`;
            } else {
                return `<input type="text" class="form-control form-control-sm col-val-input" data-col="${col.id}" data-stu="${stuId}" data-date="${date}" value="${escapeHtml(v)}" title="${escapeHtml(col.name)}" maxlength="50" />`;
            }
        }).join('');
    }
    function renderStudentCheckboxes(){
        noteStudentsDiv.innerHTML = '<div class="mb-1">'+
            '<button type="button" class="btn btn-xs btn-outline-primary mr-1" id="studentsSelectAll">Alle</button>'+
            '<button type="button" class="btn btn-xs btn-outline-secondary" id="studentsSelectNone">Keine</button>'+
            '</div>' +
            cache.schueler.map(s=>`<div class="form-check-inline mb-1"><input class="form-check-input" type="checkbox" name="schueler_ids[]" id="stu_chk_${s.id}" value="${s.id}"><label class="form-check-label small" for="stu_chk_${s.id}">${escapeHtml(s.name)}</label></div>`).join('');
        const allBtn=document.getElementById('studentsSelectAll'); const noneBtn=document.getElementById('studentsSelectNone');
        allBtn&&allBtn.addEventListener('click',()=> noteStudentsDiv.querySelectorAll('input[type=checkbox]').forEach(cb=> cb.checked=true));
        noneBtn&&noneBtn.addEventListener('click',()=> noteStudentsDiv.querySelectorAll('input[type=checkbox]').forEach(cb=> cb.checked=false));
    }

    // --- Tasks Rendering (unverändert weitgehend) ---
    function renderTasks(){
        const hasTasks = cache.tasks && cache.tasks.length > 0;
        const openNotes = cache.entries.filter(e => !e.completed_at);
        const hasOpenNotes = openNotes.length > 0;
        if(!hasTasks && !hasOpenNotes){ tasksPanel.style.display='none'; return; }
        tasksPanel.style.display='block';
        let html='';
        if(hasTasks){ html += cache.tasks.map(task=>{ const student = cache.schueler.find(s=>s.id===task.schueler_id); const studentName = student?student.name:'Unbekannt'; const dueDateStr = task.due_date ? new Date(task.due_date).toLocaleDateString():''; return `<div class="task-item mb-2 p-2 border rounded ${task.highlighted?'border-warning bg-light':'border-secondary'}" data-task-id="${task.id}"><div class="d-flex justify-content-between align-items-start"><div class="flex-grow-1"><div class="font-weight-bold small">${escapeHtml(task.title)}</div><div class="text-muted small">Schüler: ${escapeHtml(studentName)}</div>${dueDateStr?`<div class="text-muted small">Fällig: ${dueDateStr}</div>`:''}</div><button class="btn btn-sm btn-success close-task-btn" data-task-id="${task.id}" title="Als erledigt markieren"><i class="fas fa-check"></i></button></div></div>`; }).join(''); }
        if(hasOpenNotes){ html += '<div class="mt-3"><span class="font-weight-bold small">Offene Notizen</span></div>'; html += openNotes.map(entry=>{ const schuelerNamen = entry.schueler_ids.map(id=>{ const s=cache.schueler.find(stu=>stu.id===id); return s?escapeHtml(s.name):'Unbekannt'; }).join(', '); return `<div class="task-item mb-2 p-2 border rounded border-danger bg-light" data-entry-id="${entry.id}"><div class="d-flex justify-content-between align-items-start"><div class="flex-grow-1"><div class="font-weight-bold small">${escapeHtml(entry.content)}</div><div class="text-muted small">Schüler: ${schuelerNamen}</div><div class="text-muted small">Datum: ${escapeHtml(entry.date)}</div></div><button class="btn btn-sm btn-success complete-entry-btn" data-entry-id="${entry.id}" title="Notiz abschließen"><i class="fas fa-check"></i></button></div></div>`; }).join(''); }
        tasksList.innerHTML = html;
    }

    // --- Events Diary ---
    diaryBody.addEventListener('click', e=>{ const entry=e.target.closest('.entry-item'); if(entry){ populateForEdit(entry); return; } const cell=e.target.closest('.note-cell'); if(cell && !e.target.closest('.col-inputs')){ populateForNew(cell); }});
    diaryBody.addEventListener('input', e=>{ const inp=e.target.closest('.col-val-input'); if(!inp) return; const key=`${inp.dataset.col}-${inp.dataset.stu}-${inp.dataset.date}`; clearTimeout(debounceTimers[key]); const val=inp.value.trim(); debounceTimers[key]=setTimeout(()=>{ saveColumnValue(inp.dataset.col, inp.dataset.stu, inp.dataset.date, val).catch(()=>{inp.classList.add('border-danger'); setTimeout(()=>inp.classList.remove('border-danger'),1200);}); },400); });
    diaryBody.addEventListener('click', e=>{ const btn=e.target.closest('.bool-btn'); if(!btn) return; const newVal=btn.dataset.value==='1'? '':'1'; btn.disabled=true; saveColumnValue(btn.dataset.col, btn.dataset.stu, btn.dataset.date, newVal).then(()=>{ btn.dataset.value=newVal; btn.classList.toggle('btn-success', newVal==='1'); btn.classList.toggle('btn-outline-secondary', newVal!=='1'); }).catch(()=>{btn.classList.add('btn-danger'); setTimeout(()=>btn.classList.remove('btn-danger'),1000);}).finally(()=>btn.disabled=false); });

    // --- Columns Management Events ---
    manageColumnsBtn.addEventListener('click', ()=>{ if(groupSelect.value){return;} columnsCardWrapper.classList.toggle('d-none'); if(!columnsCardWrapper.classList.contains('d-none')) loadAllColumns(); });
    columnsCloseBtn.addEventListener('click', ()=> columnsCardWrapper.classList.add('d-none'));
    columnsList.addEventListener('click', e=>{ const rem=e.target.closest('.remove-col'); const res=e.target.closest('.restore-col'); if(rem){ const chip=rem.closest('.column-chip'); const id=chip.dataset.id; const col=columnsAllCache.find(c=>String(c.id)===String(id)); if(!col) return; const ws=formatDate(currentWeekStart); if(!confirm(`Spalte "${col.name}" ab dieser Woche deaktivieren?`)) return; fetch(`paed-diary/column/${id}?week_start=${encodeURIComponent(ws)}`,{method:'DELETE',headers:{'X-CSRF-TOKEN':csrf,'Accept':'application/json'}}).then(r=>r.json()).then(j=>{ if(j.success){ setColumnsFeedback('Spalte deaktiviert','warning'); loadWeek(); loadAllColumns(); } }); } else if(res){ const chip=res.closest('.column-chip'); const id=chip.dataset.id; fetch(`paed-diary/column/${id}/restore`,{method:'POST',headers:{'X-CSRF-TOKEN':csrf,'Accept':'application/json'}}).then(r=>r.json()).then(j=>{ if(j.success){ setColumnsFeedback('Spalte reaktiviert','success'); loadWeek(); loadAllColumns(); } }); } });
    addColumnForm && addColumnForm.addEventListener('submit', e=>{ e.preventDefault(); if(groupSelect.value) return; const fd=new FormData(addColumnForm); fd.append('klasse_id', klasseSelect.value); fetch('paed-diary/column',{method:'POST',headers:{'X-CSRF-TOKEN':csrf,'Accept':'application/json'},body:fd}).then(r=>r.json()).then(j=>{ if(j.success){ addColumnForm.reset(); setColumnsFeedback('Spalte angelegt','success'); loadWeek(); loadAllColumns(); } else { setColumnsFeedback(j.message||'Fehler','danger'); } }); });

    function renderColumnsList(){ if(!columnsList) return; if(!columnsAllCache.length){ columnsList.innerHTML='<span class="text-muted small">Keine Spalten</span>'; return; } columnsList.innerHTML = columnsAllCache.map(c=>{ const deac=!!c.deactivated_from; return `<span class="column-chip ${deac?'deactivated':''}" data-id="${c.id}" title="${escapeHtml(c.name)} (${c.type})${deac?` deaktiviert ab ${c.deactivated_from}`:''}"><span>${escapeHtml(c.name)}</span>${!deac?`<button type="button" class="remove-col" title="Deaktivieren">&times;</button>`:`<button type="button" class="restore restore-col" title="Reaktivieren">&#8634;</button>`}</span>`; }).join(''); }
    function setColumnsFeedback(msg,type='info'){ if(!columnsFeedback) return; const colors={info:'#17a2b8',success:'#28a745',warning:'#ffc107',danger:'#dc3545'}; columnsFeedback.innerHTML = `<span style="color:${colors[type]||'#6c757d'}">${escapeHtml(msg)}</span>`; }

    // --- Editor-Funktionen ---
    function showEditor(){ noteEditorCard.classList.remove('d-none'); }
    function hideEditor(){ noteEditorCard.classList.add('d-none'); clearEditor(); }
    function clearEditor(){ noteEntryIdInput.value=''; noteContentInput.value=''; noteDeleteBtn.classList.add('d-none'); noteEditorCard.classList.remove('editing'); noteEditorTitle.textContent='Notiz erfassen'; noteStatus.textContent=''; }
    function populateForNew(cell){ /* entfernt Klassenmodus-Block */ clearEditor(); const date=cell? cell.dataset.date : formatDate(new Date()); noteDateInput.value=date; [...noteStudentsDiv.querySelectorAll('input[type=checkbox]')].forEach(cb=> cb.checked=false); if(cell){ const cb=document.getElementById('stu_chk_'+cell.dataset.stu); cb && (cb.checked=true); } showEditor(); noteContentInput.focus(); }
    function populateForEdit(entryDiv){ /* entfernt Klassenmodus-Block */ clearEditor(); const id=entryDiv.dataset.entry; const entry=cache.entries.find(e=>String(e.id)===String(id)); noteEntryIdInput.value=id; noteEditorTitle.textContent='Notiz bearbeiten'; noteEditorCard.classList.add('editing'); noteDeleteBtn.classList.remove('d-none'); const cell=entryDiv.closest('.note-cell'); if(cell){ noteDateInput.value=cell.dataset.date; [...noteStudentsDiv.querySelectorAll('input[type=checkbox]')].forEach(cb=> cb.checked=false); if(entry?.schueler_ids){ noteStudentsDiv.querySelectorAll('input[type=checkbox]').forEach(cb=> cb.checked = entry.schueler_ids.includes(parseInt(cb.value))); } }
        noteContentInput.value = entry?.content || decodeURIComponent(entryDiv.dataset.content||''); const completedCheckbox=document.getElementById('noteCompleted'); completedCheckbox && (completedCheckbox.checked=!!entry?.completed_at); showEditor(); noteContentInput.focus(); }
    function saveColumnValue(colId, stuId, date, value){ return fetch('paed-diary/column/value',{method:'POST',headers:{'Content-Type':'application/json','X-CSRF-TOKEN':csrf,'Accept':'application/json'},body:JSON.stringify({column_id:colId,schueler_id:stuId,date:date,value:value})}).then(r=>{ if(!r.ok) throw new Error('fail'); return r.json(); }).then(()=>{ if(!cache.column_values[colId]) cache.column_values[colId]={}; if(!cache.column_values[colId][stuId]) cache.column_values[colId][stuId]={}; if(value===''){ delete cache.column_values[colId][stuId][date]; } else { cache.column_values[colId][stuId][date]=value; } }); }

    // --- Tasks Events (close / complete) ---
    tasksPanel.addEventListener('click', e=>{ const closeBtn=e.target.closest('.close-task-btn'); if(closeBtn){ const taskId=closeBtn.dataset.taskId; closeBtn.disabled=true; fetch(`paed-diary/task/${taskId}/close`,{method:'POST',headers:{'X-CSRF-TOKEN':csrf,'Accept':'application/json'}}).then(r=>r.json()).then(j=>{ if(j.success){ cache.tasks=cache.tasks.filter(t=>String(t.id)!==String(taskId)); renderTasks(); render(); } else { closeBtn.disabled=false; } }).catch(()=> closeBtn.disabled=false); return; } const completeBtn=e.target.closest('.complete-entry-btn'); if(completeBtn){ const entryId=completeBtn.dataset.entryId; completeBtn.disabled=true; fetch(`paed-diary/entry/${entryId}/complete`,{method:'POST',headers:{'Content-Type':'application/json','X-CSRF-TOKEN':csrf,'Accept':'application/json'},body:JSON.stringify({klasse_id:klasseSelect.value})}).then(r=>r.json()).then(j=>{ if(j.success){ loadWeek(); } else { alert(j.message||'Fehler'); completeBtn.disabled=false; } }).catch(()=>{ alert('Fehler'); completeBtn.disabled=false; }); } });
    refreshTasksBtn.addEventListener('click', ()=> loadWeek());

    // --- Gruppen UI Events ---
    manageGroupsBtn && manageGroupsBtn.addEventListener('click', ()=>{ groupForm.reset(); groupIdInput.value=''; groupCancelEdit.classList.add('d-none'); setGroupFeedback('',''); loadGroups().then(()=> groupModal.modal('show')); });
    groupCancelEdit && groupCancelEdit.addEventListener('click', ()=>{ groupForm.reset(); groupIdInput.value=''; groupCancelEdit.classList.add('d-none'); });
    groupsListDiv && groupsListDiv.addEventListener('click', e=>{
        const editBtn=e.target.closest('.edit-group'); const delBtn=e.target.closest('.del-group');
        if(editBtn){ const id=editBtn.dataset.id; const g=groupsCache.find(x=>String(x.id)===String(id)); if(!g) return; groupIdInput.value=g.id; groupNameInput.value=g.name; // Klassen ankreuzen
            document.querySelectorAll('#groupKlassenBox input[type=checkbox]').forEach(cb=> cb.checked = g.klassen.some(k=>String(k.id)===cb.value)); groupCancelEdit.classList.remove('d-none'); }
        if(delBtn){ const id=delBtn.dataset.id; if(!confirm('Gruppe wirklich löschen?')) return; fetch(`paed-diary/class-groups/${id}`,{method:'DELETE',headers:{'X-CSRF-TOKEN':csrf,'Accept':'application/json'}}).then(r=>r.json()).then(j=>{ if(j.success){ setGroupFeedback('Gelöscht','success'); loadGroups(); if(groupSelect.value===String(id)){ groupSelect.value=''; loadWeek(); } } }); }
    });
    groupForm && groupForm.addEventListener('submit', e=>{ e.preventDefault(); const id=groupIdInput.value.trim(); const name=groupNameInput.value.trim(); const klasseIds=[...document.querySelectorAll('#groupKlassenBox input[type=checkbox]:checked')].map(cb=>cb.value); if(klasseIds.length<2){ setGroupFeedback('Mindestens 2 Klassen wählen','warning'); return; } groupStatus.textContent='Speichere...'; const payload=new FormData(); payload.append('name',name); klasseIds.forEach(idv=> payload.append('klasse_ids[]', idv)); const url = id? `paed-diary/class-groups/${id}` : 'paed-diary/class-groups'; const method = id? 'PUT':'POST'; fetch(url,{method,headers:{'X-CSRF-TOKEN':csrf,'Accept':'application/json'},body:payload}).then(r=>r.json()).then(j=>{ if(j.success){ groupStatus.textContent='Gespeichert'; loadGroups(); refreshGroupSelect(); if(!id && j.group){ groupSelect.value=j.group.id; loadWeek(); } } else { groupStatus.textContent=j.message||'Fehler'; } }).catch(()=> groupStatus.textContent='Fehler'); });

    if(groupSelect){ groupSelect.addEventListener('change', ()=>{ if(groupSelect.value){ // Gruppenmodus aktiv
            columnsCardWrapper.classList.add('d-none');
        }
        loadWeek();
    }); }

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

        // Aktuell gewählte Klasse/Gruppe hinzufügen falls keine spezifische Auswahl
        if(groupSelect.value){
            fd.append('group_ids[]', groupSelect.value);
        } else {
            fd.append('klasse_ids[]', klasseSelect.value);
        }

        const id = appointmentIdInput.value;
        const url = id ? `paed-diary/appointments/${id}` : 'paed-diary/appointments';
        const method = id ? 'PUT' : 'POST';

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
        .then(r => r.json())
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

    appointmentPauseBtn.addEventListener('click', ()=>{
        const id = appointmentIdInput.value;
        if(!id) return;

        appointmentStatus.textContent = 'Aktualisiere...';
        fetch(`paed-diary/appointments/${id}/toggle-pause`, {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': csrf,
                'Accept': 'application/json'
            }
        })
        .then(r => r.json())
        .then(j => {
            if(j.success){
                const newText = j.is_paused ? 'Reaktivieren' : 'Pausieren';
                appointmentPauseBtn.textContent = newText;
                appointmentStatus.textContent = j.is_paused ? 'Pausiert' : 'Reaktiviert';
                setAppointmentFeedback(j.is_paused ? 'Termin pausiert' : 'Termin reaktiviert', 'success');
            } else {
                appointmentStatus.textContent = 'Fehler';
                setAppointmentFeedback('Fehler beim Pausieren/Reaktivieren', 'danger');
            }
        })
        .catch(() => {
            appointmentStatus.textContent = 'Fehler';
            setAppointmentFeedback('Fehler beim Pausieren/Reaktivieren', 'danger');
        });
    });

    function setAppointmentFeedback(msg, type = 'info') {
        if(!appointmentFeedback) return;
        const colors = {
            info: '#17a2b8',
            success: '#28a745',
            warning: '#ffc107',
            danger: '#dc3545'
        };
        appointmentFeedback.innerHTML = `<span style="color:${colors[type] || '#6c757d'}">${escapeHtml(msg)}</span>`;
    }

    // initial
    loadWeek();

// Ende IIFE
})();
