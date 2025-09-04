// paedDiary.js - ausgelagerter JavaScript-Code für das pädagogische Tagebuch

(function(){
    // --- Vorhandene Variablen ---
    const csrf = document.querySelector('meta[name=csrf-token]').content;
    const klasseSelect = document.getElementById('klasseSelect');
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

    // --- State ---
    let currentWeekStart = startOfWeek(new Date());
    let cache = { days:[], schueler:[], entries:[], columns:[], column_values:{}, tasks:[] };
    let columnsAllCache = []; // inkl. deaktivierte
    let debounceTimers = {}; // für Spaltenwerte

    // --- Utils ---
    function startOfWeek(d){const dt=new Date(d);const wd=dt.getDay();const diff=(wd===0?-6:1-wd);dt.setDate(dt.getDate()+diff);dt.setHours(0,0,0,0);return dt;}
    function formatDate(d) {
        const year = d.getFullYear();
        const month = String(d.getMonth() + 1).padStart(2, '0');
        const day = String(d.getDate()).padStart(2, '0');
        return `${year}-${month}-${day}`;
    }
    function addDays(d,x){const n=new Date(d);n.setDate(n.getDate()+x);return n;}
    function escapeHtml(str){return String(str).replace(/[&<>"']/g,s=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;','\'':'&#39;'}[s]));}
    function trimText(str,len){return str.length<=len?str:str.slice(0,len-1)+'…';}

    // --- Daten laden ---
    function loadWeek(){
        const p=new URLSearchParams({klasse_id:klasseSelect.value,week_start:formatDate(currentWeekStart)});
        fetch('paed-diary/week?'+p.toString(),{headers:{'Accept':'application/json'}})
            .then(r=>r.json())
            .then(data=>{cache=data; render(); if(!cache.schueler.length){hideEditor();} if(!columnsCardWrapper.classList.contains('d-none')) loadAllColumns();});
    }
    function loadAllColumns(){
        const p = new URLSearchParams({ klasse_id: klasseSelect.value });
        fetch('paed-diary/columns/all?' + p.toString(), { headers: { 'Accept': 'application/json' } })
            .then(r => {
                if (!r.ok) throw new Error('Failed to load columns');
                return r.json();
            })
            .then(data => {
                columnsAllCache = data.columns || [];
                renderColumnsList();
            })
            .catch(err => {
                console.error('Error loading columns:', err);
                setColumnsFeedback('Fehler beim Laden der Spalten', 'danger');
            });
    }

    // --- Rendering ---
    function buildEntryMap(){const m={};cache.entries.forEach(e=>e.schueler_ids.forEach(s=>{(m[s]||(m[s]={}))[e.date]=(m[s][e.date]||[]);m[s][e.date].push(e);}));return m;}
    function render(){
        diaryHead.innerHTML='';
        const todayStr = formatDate(new Date());
        diaryHead.insertAdjacentHTML('beforeend','<tr><th style="min-width:160px;">Schüler</th>' + cache.days.map(d=>{
            const isToday = d.date === todayStr;
            return `<th class="text-center${isToday ? ' today-header' : ''}" data-date="${d.date}">${d.label}</th>`;
        }).join('') + '</tr>');
        const entryMap=buildEntryMap();
        diaryBody.innerHTML='';
        const taskStudentIds=new Set(cache.tasks.map(t=>t.schueler_id));
        cache.schueler.forEach(stu=>{
            let row = `<th class=\"align-top\" style=\"font-size:.72rem;\">
                <a href="paed-diary/schueler/${stu.id}" class="text-decoration-none" title="Detailansicht öffnen">
                    ${stu.name} <i class="fas fa-external-link-alt small ml-1"></i>
                </a>
            </th>`;
            cache.days.forEach(d=>{
                const entries=(entryMap[stu.id]?.[d.date])||[];
                const entriesHtml=entries.map(e=>{const enc=encodeURIComponent(e.content||'');return `<div class=\"entry-item\" data-entry=\"${e.id}\" data-content=\"${enc}\">`+(e.user?`<span class=\"author\">${escapeHtml(e.user)}</span>`:'')+`<span class=\"text\">${escapeHtml(trimText(e.content,120))}</span></div>`;}).join('');
                const isToday = d.date === todayStr;
                row += `<td class=\"note-cell${taskStudentIds.has(stu.id)?' stu-has-task-cell':''}${isToday ? ' today-cell' : ''}\" data-stu=\"${stu.id}\" data-date=\"${d.date}\">`+
                    `<div class=\"entry-list\">${entriesHtml}</div>`+
                    `<div class=\"col-inputs-row\"><div class=\"col-inputs\">${renderColumnInputs(stu.id,d.date)}</div></div>`+
                    `</td>`;
            });
            const tr=document.createElement('tr');
            if(taskStudentIds.has(stu.id)) tr.classList.add('stu-has-task');
            tr.innerHTML=row; diaryBody.appendChild(tr);
        });
        const endWeek=addDays(currentWeekStart,4);
        weekLabel.textContent=`${currentWeekStart.toLocaleDateString()} - ${endWeek.toLocaleDateString()}`;
        renderStudentCheckboxes();
        taskSchuelerSelect.innerHTML='<option value="">-- Schüler --</option>'+cache.schueler.map(s=>`<option value="${s.id}">${s.name}</option>`).join('');
        // Korrigierter Export-Pfad (entsprechend routes/web.php: export/paed-diary/excel)
        exportCsvBtn.href = `/export/paed-diary/excel?klasse_id=${encodeURIComponent(klasseSelect.value)}&week_start=${encodeURIComponent(formatDate(currentWeekStart))}`;

        // Aufgaben anzeigen wenn vorhanden
        renderTasks();
    }
    function renderColumnInputs(stuId, date) {
        if (!cache.columns.length) return '';
        return cache.columns.map(col => {
            const v = cache.column_values?.[col.id]?.[stuId]?.[date] || '';

            if (col.type === 'boolean') {
                const active = v === '1';
                return `<button type="button" class="btn btn-${active ? 'success' : 'outline-secondary'} bool-btn"
                            data-col="${col.id}"
                            data-stu="${stuId}"
                            data-date="${date}"
                            data-value="${active ? 1 : 0}"
                            title="${escapeHtml(col.name)}">
                            ${escapeHtml(col.name)}
                        </button>`;
            } else if (col.type === 'number') {
                return `<input type="number" class="form-control form-control-sm col-val-input"
                            data-col="${col.id}"
                            data-stu="${stuId}"
                            data-date="${date}"
                            value="${escapeHtml(v)}"
                            title="${escapeHtml(col.name)}" />`;
            } else {
                return `<input type="text" class="form-control form-control-sm col-val-input"
                            data-col="${col.id}"
                            data-stu="${stuId}"
                            data-date="${date}"
                            value="${escapeHtml(v)}"
                            title="${escapeHtml(col.name)}"
                            maxlength="50" />`;
            }
        }).join('');
    }

    function renderStudentCheckboxes(){
        noteStudentsDiv.innerHTML = '<div class="mb-1">' +
            '<button type="button" class="btn btn-xs btn-outline-primary mr-1" id="studentsSelectAll">Alle</button>' +
            '<button type="button" class="btn btn-xs btn-outline-secondary" id="studentsSelectNone">Keine</button>' +
            '</div>' +
            cache.schueler.map(s =>
                `<div class="form-check-inline mb-1">
                    <input class="form-check-input" type="checkbox" name="schueler_ids[]" id="stu_chk_${s.id}" value="${s.id}">
                    <label class="form-check-label small" for="stu_chk_${s.id}">${escapeHtml(s.name)}</label>
                </div>`
            ).join('');
        const allBtn = document.getElementById('studentsSelectAll');
        const noneBtn = document.getElementById('studentsSelectNone');
        if(allBtn) allBtn.addEventListener('click',()=> noteStudentsDiv.querySelectorAll('input[type=checkbox]').forEach(cb=> cb.checked=true));
        if(noneBtn) noneBtn.addEventListener('click',()=> noteStudentsDiv.querySelectorAll('input[type=checkbox]').forEach(cb=> cb.checked=false));
    }

    // --- Tasks Rendering ---
    function renderTasks(){
        const tasksPanel = document.getElementById('tasksPanel');
        const tasksList = document.getElementById('tasksList');

        // Aufgaben und offene Einträge kombinieren
        const hasTasks = cache.tasks && cache.tasks.length > 0;
        // Offene Notizen als Aufgaben anzeigen
        const openNotes = cache.entries.filter(e => !e.completed_at);
        const hasOpenNotes = openNotes.length > 0;
        const hasOpenEntries = cache.open_entries && cache.open_entries.length > 0;
        if(!hasTasks && !hasOpenEntries && !hasOpenNotes) {
            tasksPanel.style.display = 'none';
            return;
        }
        tasksPanel.style.display = 'block';

        let html = '';
        // Aufgaben
        if(hasTasks) {
            html += cache.tasks.map(task => {
                const student = cache.schueler.find(s => s.id === task.schueler_id);
                const studentName = student ? student.name : 'Unbekannt';
                const dueDateStr = task.due_date ? new Date(task.due_date).toLocaleDateString() : '';
                return `<div class="task-item mb-2 p-2 border rounded ${task.highlighted ? 'border-warning bg-light' : 'border-secondary'}" data-task-id="${task.id}">
                    <div class="d-flex justify-content-between align-items-start">
                        <div class="flex-grow-1">
                            <div class="font-weight-bold small">${escapeHtml(task.title)}</div>
                            <div class="text-muted small">Schüler: ${escapeHtml(studentName)}</div>
                            ${dueDateStr ? `<div class="text-muted small">Fällig: ${dueDateStr}</div>` : ''}
                        </div>
                        <button class="btn btn-sm btn-success close-task-btn" data-task-id="${task.id}" title="Als erledigt markieren">
                            <i class="fas fa-check"></i>
                        </button>
                    </div>
                </div>`;
            }).join('');
        }
        // Offene Notizen
        if(hasOpenNotes) {
            html += '<div class="mt-3"><span class="font-weight-bold small">Offene Notizen</span></div>';
            html += openNotes.map(entry => {
                const schuelerNamen = entry.schueler_ids.map(id => {
                    const s = cache.schueler.find(stu => stu.id === id);
                    return s ? escapeHtml(s.name) : 'Unbekannt';
                }).join(', ');
                return `<div class="task-item mb-2 p-2 border rounded border-danger bg-light" data-entry-id="${entry.id}">
                    <div class="d-flex justify-content-between align-items-start">
                        <div class="flex-grow-1">
                            <div class="font-weight-bold small">${escapeHtml(entry.content)}</div>
                            <div class="text-muted small">Schüler: ${schuelerNamen}</div>
                            <div class="text-muted small">Datum: ${escapeHtml(entry.date)}</div>
                        </div>
                         <button class="btn btn-sm btn-success  complete-entry-btn" data-entry-id="${entry.id}" title="Notiz abschließen">
                            <i class="fas fa-check"></i>
                        </button>
                    </div>
                </div>`;
            }).join('');
        }

        tasksList.innerHTML = html;
    }

    // Event-Handler für das Schließen von Aufgaben und Abschließen von Einträgen
    tasksPanel.addEventListener('click', e => {
        const closeBtn = e.target.closest('.close-task-btn');
        if (closeBtn) {
            const taskId = closeBtn.dataset.taskId;
            if (!taskId) return;
            closeBtn.disabled = true;
            fetch(`paed-diary/task/${taskId}/close`, {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': csrf,
                    'Accept': 'application/json'
                }
            })
            .then(r => r.json())
            .then(j => {
                if (j.success) {
                    cache.tasks = cache.tasks.filter(t => String(t.id) !== String(taskId));
                    renderTasks();
                    render();
                } else {
                    closeBtn.disabled = false;
                }
            })
            .catch(() => {
                closeBtn.disabled = false;
            });
            return;
        }
        // Abschließen einer offenen Notiz
        const completeBtn = e.target.closest('.complete-entry-btn');
        if (completeBtn) {
            const entryId = completeBtn.dataset.entryId;
            if (!entryId) return;
            completeBtn.disabled = true;
            // Kein completed_at mehr senden – Server nutzt aktuelles Datum
            fetch(`paed-diary/entry/${entryId}/complete`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrf,
                    'Accept': 'application/json'
                },
                body: JSON.stringify({ klasse_id: klasseSelect.value })
            })
            .then(r => r.json())
            .then(j => {
                if (j.success) {
                    loadWeek();
                } else {
                    alert(j.message || 'Fehler beim Abschließen der Notiz');
                    completeBtn.disabled = false;
                }
            })
            .catch(() => {
                alert('Fehler beim Abschließen der Notiz');
                completeBtn.disabled = false;
            });
        }
    });

    // --- Columns Management Rendering ---
    function renderColumnsList(){
        if(!columnsList) return;
        if(!columnsAllCache.length){
            columnsList.innerHTML='<span class="text-muted small">Keine Spalten</span>';
            return;
        }

        columnsList.innerHTML = columnsAllCache.map(c => {
            const deac = !!c.deactivated_from;
            return `<span class="column-chip ${deac ? 'deactivated' : ''}" data-id="${c.id}" title="${escapeHtml(c.name)} (${c.type})${deac ? ` deaktiviert ab ${c.deactivated_from}` : ''}">` +
                `<span>${escapeHtml(c.name)}</span>` +
                (!deac
                    ? `<button type="button" class="remove-col" title="Deaktivieren">&times;</button>`
                    : `<button type="button" class="restore restore-col" title="Reaktivieren">&#8634;</button>`
                ) +
            `</span>`;
        }).join('');
    }
    function setColumnsFeedback(msg,type='info'){
        if(!columnsFeedback) return;
        const colors={info:'#17a2b8',success:'#28a745',warning:'#ffc107',danger:'#dc3545'};
        columnsFeedback.innerHTML = `<span style="color:${colors[type]||'#6c757d'}">${escapeHtml(msg)}</span>`;
    }

    // --- Editor-Funktionen ---
    function showEditor(){ noteEditorCard.classList.remove('d-none'); }
    function hideEditor(){ noteEditorCard.classList.add('d-none'); clearEditor(); }
    function clearEditor(){ noteEntryIdInput.value=''; noteContentInput.value=''; noteDeleteBtn.classList.add('d-none'); noteEditorCard.classList.remove('editing'); noteEditorTitle.textContent='Notiz erfassen'; noteStatus.textContent=''; }
    function populateForNew(cell){
        clearEditor();
        const date = cell? cell.dataset.date : formatDate(new Date());
        noteDateInput.value = date;
        [...noteStudentsDiv.querySelectorAll('input[type=checkbox]')].forEach(cb=> cb.checked=false);
        if(cell){ const cb=document.getElementById('stu_chk_'+cell.dataset.stu); if(cb) cb.checked=true; }
        showEditor(); noteContentInput.focus(); scrollIntoViewEditor();
    }
    function populateForEdit(entryDiv){
        clearEditor();
        const id = entryDiv.dataset.entry;
        const raw = entryDiv.dataset.content ? decodeURIComponent(entryDiv.dataset.content) : '';
        const entry = cache.entries.find(e => e.id === id); // Updated here
        noteEntryIdInput.value = id;
        noteEditorTitle.textContent = 'Notiz bearbeiten';
        noteEditorCard.classList.add('editing');
        noteDeleteBtn.classList.remove('d-none');
        const cell = entryDiv.closest('.note-cell');
        if (cell) {
            noteDateInput.value = cell.dataset.date;
            const sid = parseInt(cell.dataset.stu);
            [...noteStudentsDiv.querySelectorAll('input[type=checkbox]')].forEach(cb => cb.checked = false);
            if (entry?.schueler_ids) {
                noteStudentsDiv.querySelectorAll('input[type=checkbox]').forEach(cb => cb.checked = entry.schueler_ids.includes(parseInt(cb.value)));
            } else {
                const cb = document.getElementById('stu_chk_' + sid);
                if (cb) cb.checked = true;
            }
        }
        noteContentInput.value = entry?.content || raw;
        // Checkbox für abgeschlossen setzen
        const completedCheckbox = document.getElementById('noteCompleted');
        if (completedCheckbox) {
            completedCheckbox.checked = !!entry?.completed_at;
        }
        showEditor();
        noteContentInput.focus();
        scrollIntoViewEditor();
    }
    function scrollIntoViewEditor(){ noteEditorCard.scrollIntoView({behavior:'smooth',block:'start'}); }

    // --- Spaltenwerte speichern ---
    function saveColumnValue(colId, stuId, date, value){
        return fetch('paed-diary/column/value', {
            method:'POST',
            headers:{'Content-Type':'application/json','X-CSRF-TOKEN':csrf,'Accept':'application/json'},
            body:JSON.stringify({column_id:colId, schueler_id:stuId, date:date, value:value})
        }).then(r=>{ if(!r.ok) throw new Error('fail'); return r.json(); }).then(()=>{
            if(!cache.column_values[colId]) cache.column_values[colId]={};
            if(!cache.column_values[colId][stuId]) cache.column_values[colId][stuId]={};
            if(value==='') delete cache.column_values[colId][stuId][date]; else cache.column_values[colId][stuId][date]=value;
        });
    }

    // --- Events Diary ---
    diaryBody.addEventListener('click', e=>{
        const entry=e.target.closest('.entry-item'); if(entry){ populateForEdit(entry); return; }
        const cell=e.target.closest('.note-cell'); if(cell && !e.target.closest('.col-inputs')){ populateForNew(cell); }
    });

    diaryBody.addEventListener('input', e=>{
        const inp = e.target.closest('.col-val-input');
        if(!inp) return;
        const key = `${inp.dataset.col}-${inp.dataset.stu}-${inp.dataset.date}`;
        clearTimeout(debounceTimers[key]);
        const val = inp.value.trim();
        debounceTimers[key] = setTimeout(()=>{ saveColumnValue(inp.dataset.col, inp.dataset.stu, inp.dataset.date, val).catch(()=>{inp.classList.add('border-danger'); setTimeout(()=>inp.classList.remove('border-danger'),1200);}); },400);
    });
    diaryBody.addEventListener('click', e=>{
        const btn = e.target.closest('.bool-btn');
        if(!btn) return;
        const newVal = btn.dataset.value==='1' ? '' : '1';
        btn.disabled=true;
        saveColumnValue(btn.dataset.col, btn.dataset.stu, btn.dataset.date, newVal)
            .then(()=>{
                btn.dataset.value=newVal;
                btn.classList.toggle('btn-success', newVal==='1');
                btn.classList.toggle('btn-outline-secondary', newVal!=='1');
            })
            .catch(()=>{btn.classList.add('btn-danger'); setTimeout(()=>btn.classList.remove('btn-danger'),1000);})
            .finally(()=>btn.disabled=false);
    });

    // --- Columns Management Events ---
    manageColumnsBtn.addEventListener('click', () => {
        columnsCardWrapper.classList.toggle('d-none');
        if (!columnsCardWrapper.classList.contains('d-none')) {
            loadAllColumns();
        }
    });

    columnsCloseBtn.addEventListener('click', () => {
        columnsCardWrapper.classList.add('d-none');
    });

    columnsList.addEventListener('click', e=>{
        const rem = e.target.closest('.remove-col');
        const res = e.target.closest('.restore-col');
        if(rem){
            const chip = rem.closest('.column-chip');
            const id = chip.dataset.id;
            const col = columnsAllCache.find(c=>c.id===id); // Updated here
            if(!col) return;
            const ws = formatDate(currentWeekStart);
            if(!confirm(`Spalte "${col.name}" ab dieser Woche deaktivieren?`)) return;
            fetch(`paed-diary/column/${id}?week_start=${encodeURIComponent(ws)}`,{method:'DELETE',headers:{'X-CSRF-TOKEN':csrf,'Accept':'application/json'}})
                .then(r=>r.json()).then(j=>{ if(j.success){ setColumnsFeedback('Spalte deaktiviert','warning'); loadWeek(); loadAllColumns(); } });
        } else if(res){
            const chip = res.closest('.column-chip'); const id=chip.dataset.id;
            fetch(`paed-diary/column/${id}/restore`,{method:'POST',headers:{'X-CSRF-TOKEN':csrf,'Accept':'application/json'}})
                .then(r=>r.json()).then(j=>{ if(j.success){ setColumnsFeedback('Spalte reaktiviert','success'); loadWeek(); loadAllColumns(); } });
        }
    });

    if(addColumnForm){
        addColumnForm.addEventListener('submit', e=>{
            e.preventDefault();
            const fd = new FormData(addColumnForm); fd.append('klasse_id', klasseSelect.value);
            fetch('paed-diary/column',{method:'POST',headers:{'X-CSRF-TOKEN':csrf,'Accept':'application/json'},body:fd})
                .then(r=>r.json()).then(j=>{ if(j.success){ addColumnForm.reset(); setColumnsFeedback('Spalte angelegt','success'); loadWeek(); loadAllColumns(); } else { setColumnsFeedback(j.message||'Fehler','danger'); } });
        });
    }

    // --- Note Editor Actions ---
    openNoteInline.addEventListener('click', ()=> populateForNew(null));
    noteClearBtn.addEventListener('click', ()=> populateForNew(null));
    noteEditorCancel.addEventListener('click', hideEditor);

    // --- Navigation Events ---
    prevWeekBtn.addEventListener('click', ()=>{ currentWeekStart=addDays(currentWeekStart,-7); loadWeek(); });
    nextWeekBtn.addEventListener('click', ()=>{ currentWeekStart=addDays(currentWeekStart,7); loadWeek(); });
    todayWeekBtn.addEventListener('click', ()=>{ currentWeekStart=startOfWeek(new Date()); loadWeek(); });
    klasseSelect.addEventListener('change', ()=>{ currentWeekStart=startOfWeek(new Date()); loadWeek(); });

    // --- Speichern Notiz ---
    noteForm.addEventListener('submit', ev=>{
        ev.preventDefault();
        noteStatus.textContent='Speichere...';
        const fd=new FormData(noteForm); fd.set('klasse_id',klasseSelect.value);
        // completed_at nur setzen, wenn Checkbox aktiviert
        const completedCheckbox = document.getElementById('noteCompleted');
        if (completedCheckbox && !completedCheckbox.checked) {
            fd.delete('completed');
        } else {
            fd.set('completed', '1');
        }
        const id=noteEntryIdInput.value; const url=id?`paed-diary/entry/${id}`:'paed-diary/entry';
        fetch(url,{method:'POST',headers:{'X-CSRF-TOKEN':csrf,'Accept':'application/json'},body:fd})
            .then(r=>r.json()).then(j=>{ if(j.success){ noteStatus.textContent='Gespeichert'; loadWeek(); if(!id){ clearEditor(); } } else { noteStatus.textContent=j.message||'Fehler'; } })
            .catch(()=> noteStatus.textContent='Fehler beim Speichern');
    });

    // --- Löschen Notiz ---
    noteDeleteBtn.addEventListener('click', ()=>{
        const id=noteEntryIdInput.value; if(!id) return; if(!confirm('Eintrag wirklich löschen?')) return;
        noteStatus.textContent='Lösche...';
        fetch(`paed-diary/entry/${id}?klasse_id=${encodeURIComponent(klasseSelect.value)}`,{method:'DELETE',headers:{'X-CSRF-TOKEN':csrf,'Accept':'application/json'}})
            .then(r=>r.json()).then(j=>{ if(j.success){ noteStatus.textContent='Gelöscht'; loadWeek(); clearEditor(); } else { noteStatus.textContent='Löschen fehlgeschlagen'; } })
            .catch(()=> noteStatus.textContent='Löschen fehlgeschlagen');
    });

    // --- Aufgaben ---
    openTaskModalBtn.addEventListener('click', ()=>{ taskForm.reset(); document.getElementById('taskKlasseId').value=klasseSelect.value; taskModal.modal('show'); });
    taskForm.addEventListener('submit', e=>{
        e.preventDefault();
        const fd=new FormData(taskForm); fd.set('klasse_id',klasseSelect.value); if(!fd.get('highlighted')) fd.set('highlighted','0');
        fetch('paed-diary/task',{method:'POST',headers:{'X-CSRF-TOKEN':csrf,'Accept':'application/json'},body:fd})
            .then(r=>r.json()).then(j=>{ if(j.success){ taskModal.modal('hide'); cache.tasks.push(j.task); render(); } });
    });

    // --- Tasks Events ---
    // Entfernte doppelte Deklarationen (bereits oben definiert)
    // const tasksPanel = document.getElementById('tasksPanel');
    // const refreshTasksBtn = document.getElementById('refreshTasks');

    // Event-Handler für das Schließen von Aufgaben
    tasksPanel.addEventListener('click', e => {
        const closeBtn = e.target.closest('.close-task-btn');
        if (!closeBtn) return;

        const taskId = closeBtn.dataset.taskId;
        if (!taskId) return;

        closeBtn.disabled = true;

        fetch(`paed-diary/task/${taskId}/close`, {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': csrf,
                'Accept': 'application/json'
            }
        })
        .then(r => r.json())
        .then(j => {
            if (j.success) {
                // Aufgabe aus dem Cache entfernen
                cache.tasks = cache.tasks.filter(t => String(t.id) !== String(taskId));
                renderTasks();
                // Tabelle neu rendern um Hervorhebungen zu aktualisieren
                render();
            } else {
                closeBtn.disabled = false;
            }
        })
        .catch(() => {
            closeBtn.disabled = false;
        });
    });

    // Event-Handler für das Aktualisieren der Aufgaben
    refreshTasksBtn.addEventListener('click', () => {
        loadWeek();
    });

    // --- Stage Cards (inline, keine Modal) ---
    // cache for available stages per class
    let gradingStagesCache = null;

    function renderStudentStage(stu) {
        const stage = stu.stage;
        if (!stage) return `<div class="stage-badge text-muted small">-</div>`;
        const img = stage.image_url ? `<img src="${stage.image_url}" alt="${escapeHtml(stage.name)}" style="width:28px;height:28px;object-fit:cover;border-radius:4px;margin-right:6px;">` : '';
        const symbol = (!stage.image_url && stage.symbol) ? `<i class="${escapeHtml(stage.symbol)} mr-1" aria-hidden="true"></i>` : '';
        return `<div class="stage-badge d-flex align-items-center" data-stage-id="${stage.id}">${img}${symbol}<span class="small">${escapeHtml(stage.name)}</span></div>`;
    }

    // Inserts an extra row after the student's row with cards to select stages
    function toggleStageCardRow(studentRow, stuId) {
        const next = studentRow.nextElementSibling;
        // if already open for this student, remove
        if (next && next.classList.contains('stage-card-row') && Number(next.dataset.stu) === Number(stuId)) {
            next.remove();
            return;
        }
        // otherwise remove any existing and open new
        document.querySelectorAll('.stage-card-row').forEach(r=>r.remove());
        const tr = document.createElement('tr');
        tr.classList.add('stage-card-row');
        tr.dataset.stu = stuId;
        const td = document.createElement('td');
        td.colSpan = diaryHead.querySelector('tr').children.length;
        td.innerHTML = '<div class="d-flex flex-wrap stage-cards-wrapper p-2"></div>';
        tr.appendChild(td);
        studentRow.parentNode.insertBefore(tr, studentRow.nextSibling);
        const wrapper = td.querySelector('.stage-cards-wrapper');
        // ensure stages loaded
        const load = gradingStagesCache ? Promise.resolve(gradingStagesCache) : fetchStagesForClass();
        load.then(stages => {
            gradingStagesCache = stages;
            if (!stages || !stages.length) {
                wrapper.innerHTML = '<div class="text-muted small">Keine Stufen konfiguriert</div>';
                return;
            }
            wrapper.innerHTML = stages.map(s=>{
                const img = s.image_url ? `<img src="${s.image_url}" alt="${escapeHtml(s.name)}" style="width:48px;height:48px;object-fit:cover;border-radius:6px;display:block;margin-bottom:6px;">` : '';
                const symbol = (!s.image_url && s.symbol) ? `<i class="${escapeHtml(s.symbol)} fa-2x mb-1" aria-hidden="true"></i>` : '';
                return `<div class="grading-card border rounded mr-2 mb-2 p-2 text-center" data-stage-id="${s.id}" style="width:120px;cursor:pointer;">${img}${symbol}<div class="small mt-1">${escapeHtml(s.name)}</div></div>`;
            }).join('');
            // attach click handlers
            wrapper.querySelectorAll('.grading-card').forEach(card=>{
                card.addEventListener('click', ()=>{
                    const stageId = card.dataset.stageId;
                    changeStudentStage(stuId, stageId, studentRow);
                });
            });
        }).catch(()=>{ wrapper.innerHTML = '<div class="text-danger small">Fehler beim Laden der Stufen</div>'; });
    }

    function fetchStagesForClass(){
        return fetch(`paed-diary/klasse/${encodeURIComponent(klasseSelect.value)}/stages`,{headers:{'Accept':'application/json'}})
            .then(r=>{ if(!r.ok) throw new Error('fail'); return r.json(); })
            .then(j=> j.stages || []);
    }

    function changeStudentStage(schuelerId, gradingStageId){
        const body = { schueler_id: schuelerId, grading_stage_id: gradingStageId };
        fetch('paed-diary/change-stage',{
            method:'POST',
            headers:{'Content-Type':'application/json','X-CSRF-TOKEN':csrf,'Accept':'application/json'},
            body:JSON.stringify(body)
        }).then(r=>r.json()).then(j=>{
            if (j.success) {
                // reload week to reflect changes
                loadWeek();
            } else {
                alert(j.message || 'Fehler beim Ändern der Stufe');
            }
        }).catch(()=> alert('Fehler beim Ändern der Stufe'));
    }

    // Enhance render() to include stage badge in student header
    const oldRender = render;
    render = function(){
        oldRender();
        // After table rendered, inject stage badges into first TH of each student row
        document.querySelectorAll('#diaryBody tr').forEach(tr=>{
            const th = tr.querySelector('th');
            if (!th) return;
            const stuLink = th.querySelector('a');
            const href = stuLink?.getAttribute('href') || '';
            const match = href.match(/paed-diary\/schueler\/(\d+)/);
            if (!match) return;
            const stuId = parseInt(match[1]);
            const student = cache.schueler.find(s=>Number(s.id) === Number(stuId));
            if (!student) return;
            // remove existing stage container
            let existing = th.querySelector('.stage-badge-container');
            if (existing) existing.remove();
            const container = document.createElement('div');
            container.className = 'stage-badge-container d-inline-block ml-2';
            container.innerHTML = renderStudentStage(student);
            th.appendChild(container);
            // If user can manage grading, make clickable to open cards
            if (cache.can_manage_grading) {
                container.style.cursor = 'pointer';
                container.addEventListener('click', (ev)=>{
                    ev.stopPropagation();
                    toggleStageCardRow(tr, stuId);
                });
            }
        });
    };

    // initial load
    loadWeek();

})();
