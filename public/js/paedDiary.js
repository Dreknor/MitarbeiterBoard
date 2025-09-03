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

    const taskForm = document.getElementById('taskForm');
    const taskSchuelerSelect = document.getElementById('taskSchueler');
    const openTaskModalBtn = document.getElementById('openTaskModal');
    const exportCsvBtn = document.getElementById('exportCsvBtn');
    // Neue Card-Elemente für Aufgaben (ersetzt Modal)
    const taskEditorCard = document.getElementById('taskEditorCard');
    const taskEditorCancel = document.getElementById('taskEditorCancel');
    const taskClearBtn = document.getElementById('taskClearBtn');
    const taskStatus = document.getElementById('taskStatus');
    const taskStudentsDiv = document.getElementById('taskStudents');

    const toggleSearchCardBtn = document.getElementById('toggleSearchCard');
    const searchCardWrapper = document.getElementById('searchCardWrapper');
    const searchCardClose = document.getElementById('searchCardClose');
    const searchForm = document.getElementById('searchForm');
    const searchResults = document.getElementById('searchResults');
    const searchQ = document.getElementById('searchQ');
    const searchDateFrom = document.getElementById('searchDateFrom');
    const searchDateTo = document.getElementById('searchDateTo');
    const searchSetSchoolYear = document.getElementById('searchSetSchoolYear');
    const searchResetBtn = document.getElementById('searchResetBtn');
    const searchStage = document.getElementById('searchStage'); // neu

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
            let row = `<th class=\"align-top\" style=\"font-size:.72rem;\">\n                <a href="paed-diary/schueler/${stu.id}" class="text-decoration-none" title="Detailansicht öffnen">\n                    ${stu.name} <i class="fas fa-external-link-alt small ml-1"></i>\n                </a>\n            </th>`;
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
        renderTaskStudentCheckboxes();
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
    function renderTaskStudentCheckboxes(){
        if(!taskStudentsDiv) return;
        taskStudentsDiv.innerHTML = '<div class="mb-1">'
            + '<button type="button" class="btn btn-xs btn-outline-primary mr-1" id="taskStudentsSelectAll">Alle</button>'
            + '<button type="button" class="btn btn-xs btn-outline-secondary" id="taskStudentsSelectNone">Keine</button>'
            + '</div>'
            + cache.schueler.map(s=>`<div class=\"form-check-inline mb-1\">\n <input class=\"form-check-input\" type=\"checkbox\" name=\"schueler_ids[]\" id=\"task_stu_${s.id}\" value=\"${s.id}\">\n <label class=\"form-check-label small\" for=\"task_stu_${s.id}\">${escapeHtml(s.name)}</label>\n</div>`).join('');
        const allBtn=document.getElementById('taskStudentsSelectAll');
        const noneBtn=document.getElementById('taskStudentsSelectNone');
        if(allBtn) allBtn.addEventListener('click',()=> taskStudentsDiv.querySelectorAll('input[type=checkbox]').forEach(cb=> cb.checked=true));
        if(noneBtn) noneBtn.addEventListener('click',()=> taskStudentsDiv.querySelectorAll('input[type=checkbox]').forEach(cb=> cb.checked=false));
    }

    // --- Tasks Rendering ---
    function renderTasks(){
        const tasksPanel = document.getElementById('tasksPanel');
        const tasksList = document.getElementById('tasksList');

        if(!cache.tasks || cache.tasks.length === 0) {
            tasksPanel.style.display = 'none';
            return;
        }

        tasksPanel.style.display = 'block';

        tasksList.innerHTML = cache.tasks.map(task => {
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
    // Reimplementierte Notiz-Editor Hilfsfunktionen
    function populateForNew(cell){
        clearEditor();
        const date = cell? cell.dataset.date : formatDate(new Date());
        if(noteDateInput) noteDateInput.value = date;
        if(noteStudentsDiv){
            [...noteStudentsDiv.querySelectorAll('input[type=checkbox]')].forEach(cb=> cb.checked=false);
            if(cell){ const cb=document.getElementById('stu_chk_'+cell.dataset.stu); if(cb) cb.checked=true; }
        }
        showEditor();
        if(noteContentInput) noteContentInput.focus();
        scrollIntoViewEditor();
    }
    function populateForEdit(entryDiv){
        clearEditor();
        const id = entryDiv.dataset.entry;
        const raw = entryDiv.dataset.content ? decodeURIComponent(entryDiv.dataset.content) : '';
        const entry = cache.entries.find(e => e.id == id);
        noteEntryIdInput.value = id;
        noteEditorTitle.textContent = 'Notiz bearbeiten';
        noteEditorCard.classList.add('editing');
        noteDeleteBtn.classList.remove('d-none');
        const cell = entryDiv.closest('.note-cell');
        if(cell && noteDateInput) noteDateInput.value = cell.dataset.date;
        if(noteStudentsDiv){
            [...noteStudentsDiv.querySelectorAll('input[type=checkbox]')].forEach(cb=> cb.checked=false);
            if(entry?.schueler_ids){
                noteStudentsDiv.querySelectorAll('input[type=checkbox]').forEach(cb=> cb.checked = entry.schueler_ids.includes(parseInt(cb.value)));
            } else if(cell){
                const cb=document.getElementById('stu_chk_'+cell.dataset.stu); if(cb) cb.checked=true;
            }
        }
        if(noteContentInput) noteContentInput.value = entry?.content || raw;
        showEditor();
        if(noteContentInput) noteContentInput.focus();
        scrollIntoViewEditor();
    }
    function scrollIntoViewEditor(){ if(noteEditorCard) noteEditorCard.scrollIntoView({behavior:'smooth',block:'start'}); }
    // Task Editor Funktionen (neu)
    function showTaskEditor(){ if(taskEditorCard){ taskEditorCard.classList.remove('d-none'); taskEditorCard.scrollIntoView({behavior:'smooth',block:'start'}); const first=taskEditorCard.querySelector('select, input[type=text]'); if(first) first.focus(); } }
    function hideTaskEditor(){ if(taskEditorCard){ taskEditorCard.classList.add('d-none'); clearTaskEditor(); } }
    function clearTaskEditor(){ if(taskForm){ taskForm.reset(); const kid=document.getElementById('taskKlasseId'); if(kid) kid.value=klasseSelect.value; if(taskStatus) taskStatus.textContent=''; if(taskStudentsDiv){ taskStudentsDiv.querySelectorAll('input[type=checkbox]').forEach(cb=> cb.checked=false); } } }

    // Wiederhergestellte Funktion zum Speichern von Spaltenwerten
    function saveColumnValue(colId, stuId, date, value){
        return fetch('paed-diary/column/value', {
            method:'POST',
            headers:{'Content-Type':'application/json','X-CSRF-TOKEN':csrf,'Accept':'application/json'},
            body:JSON.stringify({column_id:colId, schueler_id:stuId, date:date, value:value})
        }).then(r=>{ if(!r.ok) throw new Error('fail'); return r.json(); }).then(()=>{
            if(!cache.column_values[colId]) cache.column_values[colId]={};
            if(!cache.column_values[colId][stuId]) cache.column_values[colId][stuId]={};
            if(value===''||value===null){ delete cache.column_values[colId][stuId][date]; }
            else { cache.column_values[colId][stuId][date]=value; }
        });
    }

    // --- Daten speichern ---
    function saveNote(){
        noteStatus.textContent='Speichere...';
        const fd=new FormData(noteForm); fd.set('klasse_id',klasseSelect.value);
        const id=noteEntryIdInput.value; const url=id?`paed-diary/entry/${id}`:'paed-diary/entry';
        fetch(url,{method:'POST',headers:{'X-CSRF-TOKEN':csrf,'Accept':'application/json'},body:fd})
            .then(r=>r.json()).then(j=>{ if(j.success){ noteStatus.textContent='Gespeichert'; loadWeek(); if(!id){ clearEditor(); } } else { noteStatus.textContent=j.message||'Fehler'; } })
            .catch(()=> noteStatus.textContent='Fehler beim Speichern');
    }
    function deleteNote(){
        const id=noteEntryIdInput.value; if(!id) return; if(!confirm('Eintrag wirklich löschen?')) return;
        noteStatus.textContent='Lösche...';
        fetch(`paed-diary/entry/${id}?klasse_id=${encodeURIComponent(klasseSelect.value)}`,{method:'DELETE',headers:{'X-CSRF-TOKEN':csrf,'Accept':'application/json'}})
            .then(r=>r.json()).then(j=>{ if(j.success){ noteStatus.textContent='Gelöscht'; loadWeek(); clearEditor(); } else { noteStatus.textContent='Löschen fehlgeschlagen'; } })
            .catch(()=> noteStatus.textContent='Löschen fehlgeschlagen');
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
    if(openTaskModalBtn){ openTaskModalBtn.addEventListener('click', ()=>{ clearTaskEditor(); showTaskEditor(); }); }
    if(taskForm){ taskForm.addEventListener('submit', e=>{
        e.preventDefault();
        // Sammle ausgewählte Schüler
        const selected = taskStudentsDiv ? [...taskStudentsDiv.querySelectorAll('input[name="schueler_ids[]"]:checked')].map(cb=>cb.value) : [];
        if(!selected.length){ taskStatus && (taskStatus.textContent='Bitte Schüler wählen'); return; }
        const fd=new FormData(taskForm); fd.set('klasse_id',klasseSelect.value); if(!fd.get('highlighted')) fd.set('highlighted','0');
        // Entferne evtl. leeres schueler_id Feld (Legacy)
        fd.delete('schueler_id');
        // Füge Mehrfach-IDs hinzu
        selected.forEach(id=> fd.append('schueler_ids[]', id));
        taskStatus && (taskStatus.textContent='Speichere...');
        fetch('paed-diary/task',{method:'POST',headers:{'X-CSRF-TOKEN':csrf,'Accept':'application/json'},body:fd})
            .then(r=>r.json()).then(j=>{ if(j.success){
                if(Array.isArray(j.tasks)){ j.tasks.forEach(t=> cache.tasks.push(t)); }
                else if(j.task){ cache.tasks.push(j.task); }
                renderTasks(); clearTaskEditor(); taskStatus && (taskStatus.textContent='Gespeichert'); render();
            } else { taskStatus && (taskStatus.textContent=j.message||'Fehler'); } })
            .catch(()=>{ taskStatus && (taskStatus.textContent='Fehler beim Speichern'); });
    }); }

    if(taskEditorCancel){ taskEditorCancel.addEventListener('click', hideTaskEditor); }
    if(taskClearBtn){ taskClearBtn.addEventListener('click', ()=>{ clearTaskEditor(); }); }

    // --- Tasks Events ---
    const tasksPanel = document.getElementById('tasksPanel');
    const refreshTasksBtn = document.getElementById('refreshTasks');

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
                cache.tasks = cache.tasks.filter(t => t.id != taskId);
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
        if (next && next.classList.contains('stage-card-row') && next.dataset.stu == stuId) {
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
                const img = s.image_url ? `<img src="${s.image_url}" style="width:48px;height:48px;object-fit:cover;border-radius:6px;display:block;margin-bottom:6px;">` : '';
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
        }).catch(err=>{ wrapper.innerHTML = '<div class="text-danger small">Fehler beim Laden der Stufen</div>'; });
    }

    function fetchStagesForClass(){
        return fetch(`paed-diary/klasse/${encodeURIComponent(klasseSelect.value)}/stages`,{headers:{'Accept':'application/json'}})
            .then(r=>{ if(!r.ok) throw new Error('fail'); return r.json(); })
            .then(j=> j.stages || []);
    }

    function changeStudentStage(schuelerId, gradingStageId, studentRow){
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
            const student = cache.schueler.find(s=>s.id==stuId);
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

    // --- Such-/Filter-Funktionen ---
    function computeSchoolYearStart(){
        const today=new Date();
        const year = today.getMonth()>=7 ? today.getFullYear() : today.getFullYear()-1;
        return `${year}-08-01`;
    }
    function ensureSearchDatesDefaults(){
        if(!searchDateFrom.value) searchDateFrom.value = computeSchoolYearStart();
        if(!searchDateTo.value){
            const t=new Date();
            const m=String(t.getMonth()+1).padStart(2,'0');
            const d=String(t.getDate()).padStart(2,'0');
            searchDateTo.value = `${t.getFullYear()}-${m}-${d}`;
        }
    }
    function toggleSearchCard(show){
        if(!searchCardWrapper) return;
        const s = typeof show === 'boolean' ? show : searchCardWrapper.classList.contains('d-none');
        if(s){
            searchCardWrapper.classList.remove('d-none');
            ensureSearchDatesDefaults();
            // Stufen laden
            if(searchStage){
                searchStage.innerHTML = '<option value="">(Alle / keine Auswahl)</option>';
                fetchStagesForClass().then(stages=>{
                    stages.forEach(st=>{
                        const opt=document.createElement('option');
                        opt.value=st.id; opt.textContent=st.name; searchStage.appendChild(opt);
                    });
                }).catch(()=>{});
            }
        } else {
            searchCardWrapper.classList.add('d-none');
        }
    }
    function renderSearchResults(data){
        if(!searchResults) return;
        let html='';
        if(data.entries && data.entries.length){
            html += `<div class="mb-2"><strong class="small">Treffer (${data.entries.length})</strong></div>`;
            html += '<ul class="list-unstyled mb-3>'+data.entries.map(e=>`<li class="mb-1 border rounded p-1 bg-white">
                <div class="d-flex justify-content-between"><span class="font-weight-bold">${escapeHtml(e.date)}</span><span class="text-muted">${escapeHtml(e.author||'')}</span></div>
                <div class="small mb-1">${escapeHtml(e.content)}</div>
                <div class="text-muted small">${escapeHtml(e.schueler?.name||'')}</div>
            </li>`).join('')+'</ul>';
        } else {
            html += '<div class="text-muted small mb-3">Keine passenden Einträge</div>';
        }
        searchResults.innerHTML = html;
    }
    function runSearch(){
        if(!searchForm) return;
        searchStatus.textContent='Suche...';
        const params = new URLSearchParams();
        params.set('klasse_id', klasseSelect.value);
        const q=searchQ.value.trim(); if(q) params.set('q', q);
        const stageVal = searchStage && searchStage.value ? searchStage.value : '';
        if(stageVal) params.set('stage_id', stageVal);
        if(searchDateFrom.value) params.set('date_from', searchDateFrom.value);
        if(searchDateTo.value) params.set('date_to', searchDateTo.value);
        if(!q && !stageVal){ searchStatus.textContent='Bitte Suchbegriff oder Stufe wählen'; return; }
        fetch('search/paed-diary/?'+params.toString(), {headers:{'Accept':'application/json'}})
            .then(r=>{ const status=r.status; return r.json().then(j=>({status,json:j})); })
            .then(({status,json})=>{ if(!json.success){ searchStatus.textContent=json.message || 'Fehler bei Suche'; return; } searchStatus.textContent=`${json.entries.length} Einträge`; renderSearchResults(json); if(!searchDateFrom.value) searchDateFrom.value=json.range.default_from; })
            .catch(()=>{ searchStatus.textContent='Fehler bei Suche'; });
    }

    // initial load
    loadWeek();

    // --- Event Bindings ---
    if(toggleSearchCardBtn){ toggleSearchCardBtn.addEventListener('click', ()=> toggleSearchCard()); }
    if(searchCardClose){ searchCardClose.addEventListener('click', ()=> toggleSearchCard(false)); }
    if(searchForm){ searchForm.addEventListener('submit', e=>{ e.preventDefault(); runSearch(); }); }
    if(searchSetSchoolYear){ searchSetSchoolYear.addEventListener('click', ()=>{ searchDateFrom.value=computeSchoolYearStart(); const t=new Date(); searchDateTo.value=`${t.getFullYear()}-${String(t.getMonth()+1).padStart(2,'0')}-${String(t.getDate()).padStart(2,'0')}`; }); }
    if(searchResetBtn){ searchResetBtn.addEventListener('click', ()=>{ searchForm.reset(); searchResults.innerHTML=''; searchStatus.textContent=''; ensureSearchDatesDefaults(); }); }
    // Klasse Wechsel -> nur Status leeren
    klasseSelect.addEventListener('change', ()=>{ if(!searchCardWrapper.classList.contains('d-none')){ searchResults.innerHTML=''; searchStatus.textContent=''; } });

})();
