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

    const openTaskModalBtn = document.getElementById('openTaskModal');
    const exportCsvBtn = document.getElementById('exportCsvBtn');


    // Termine-Elemente
    const openAppointmentModalBtn = document.getElementById('openAppointmentModal');


    // --- State ---
    let currentWeekStart = startOfWeek(new Date());
    let cache = { days:[], schueler:[], entries:[], columns:[], column_values:{}, tasks:[], klassen:[], is_group:false, appointments:[] };
    let debounceTimers = {}; // für Spaltenwerte
    let groupsCache = [];
   let pauseMap = {}; // Neuer Map: entryId -> schuelerId -> date -> true

    // --- Initialize Modules ---
    const columnsModule = initializeColumnsModule({
        csrf,
        klasseSelect,
        groupSelect,
        currentWeekStart,
        formatDate,
        escapeHtml,
        loadWeek,
        getCache: () => cache // Pass a function to get the latest cache
    });

    const tasksModule = initializeTasksModule({
        csrf,
        cache,
        klasseSelect,
        groupSelect,
        escapeHtml,
        trimText,
        loadWeek,
        getCache: () => cache
    });

    const appointmentsModule = initializeAppointmentsModule({
        csrf,
        cache,
        groupSelect,
        klasseSelect,
        formatDate,
        addDays,
        escapeHtml,
        trimText,
        loadWeek,
        getCache: () => cache
    });

    // Stages module (ausgelagert nach public/js/stages.js)
    const stagesModule = initializeStagesModule({
        csrf,
        getCache: () => cache,
        escapeHtml,
        loadWeek
    });

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
        return columnsModule.renderColumnInputs(stuId, date);
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

    // --- Neu hinzugefügt: Aufgaben & offene Notizen Panel (ausgelagert) ---
    function renderTasks() {
        tasksModule.renderTasks();
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
                if(columnsCardWrapper && !columnsCardWrapper.classList.contains('d-none')) {
                    // The columns module handles its own data loading
                }
                appointmentsModule.loadAppointments(currentWeekStart);
                appointmentsModule.updateAppointmentSchuelerList();
            })
            .catch(()=>{});
    }

    // Aktualisiert die Schüler-Liste für Termine basierend auf den verfügbaren Schülern im Cache
    function updateAppointmentSchuelerList(){
        appointmentsModule.updateAppointmentSchuelerList();
    }

    function loadAppointments(){
        appointmentsModule.loadAppointments(currentWeekStart);
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
    // Eintrags-Logik ist ausgelagert in `public/js/paedDiaryEntries.js`.
    const entriesModule = initializeEntriesModule({
         csrf,
         diaryHead,
         diaryBody,
         weekLabel,
         noteEditorCard,
         noteEditorTitle,
         noteEditorCancel,
         noteClearBtn,
         openNoteInline,
         noteForm,
         noteEntryIdInput,
         noteDateInput,
         noteContentInput,
         noteDeleteBtn,
         noteStudentsDiv,
         noteStatus,
         showPausedToggle: typeof showPausedToggle !== 'undefined' ? showPausedToggle : null,
         renderColumnInputs,
         stagesModule,
         tasksModule,
         getCache: () => cache,
         formatDate,
         addDays,
         escapeHtml,
         trimText,
         loadWeek,
         saveColumnValue: (columnsModule && typeof columnsModule.saveColumnValue === 'function') ? columnsModule.saveColumnValue : null,
         renderStudentCheckboxes
     });

    // Wrapper-Render: ruft das Eintrags-Rendering auf und führt restliche UI-Aufgaben aus
    function render(){
        entriesModule.render();
        if(!cache.schueler.length){ entriesModule.hideEditor && entriesModule.hideEditor(); }
        if(columnsCardWrapper && !columnsCardWrapper.classList.contains('d-none')) {
            // The columns module handles its own data loading
        }
        appointmentsModule.loadAppointments(currentWeekStart);
        appointmentsModule.updateAppointmentSchuelerList();

        exportCsvBtn.classList.remove('disabled');
        if(groupSelect && groupSelect.value){
            exportCsvBtn.href = `/export/paed-diary/excel?group_id=${encodeURIComponent(groupSelect.value)}&week_start=${encodeURIComponent(formatDate(currentWeekStart))}`;
            exportCsvBtn.title='CSV Export (Gruppe)';
            if (manageColumnsBtn) manageColumnsBtn.classList.add('disabled');
        } else {
            exportCsvBtn.href = `/export/paed-diary/excel?klasse_id=${encodeURIComponent(klasseSelect.value)}&week_start=${encodeURIComponent(formatDate(currentWeekStart))}`;
            exportCsvBtn.title='CSV Export';
            if (manageColumnsBtn) manageColumnsBtn.classList.remove('disabled');
        }
        renderTasks();
    }

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

    if(groupSelect){ groupSelect.addEventListener('change', ()=>{ if(groupSelect.value){ if (columnsCardWrapper) columnsCardWrapper.classList.add('d-none'); } loadWeek(); }); }

    // --- Navigation Events ---
    prevWeekBtn.addEventListener('click', ()=>{ currentWeekStart=addDays(currentWeekStart,-7); loadWeek(); });
    nextWeekBtn.addEventListener('click', ()=>{ currentWeekStart=addDays(currentWeekStart,7); loadWeek(); });
    todayWeekBtn.addEventListener('click', ()=>{ currentWeekStart=startOfWeek(new Date()); loadWeek(); });
    klasseSelect.addEventListener('change', ()=>{ if(groupSelect.value){ groupSelect.value=''; } currentWeekStart=startOfWeek(new Date()); loadWeek(); });

    // Note-Editor Events werden vom ausgelagerten `entriesModule` verwaltet (keine Doppel-Registrierung hier).

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
