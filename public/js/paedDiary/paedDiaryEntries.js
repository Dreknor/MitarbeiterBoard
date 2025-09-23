// paedDiaryEntries.js - Modul für Eintragsverwaltung (Pause/Unpause, Render, Editor)
function initializeEntriesModule(options){
    console.debug('initializeEntriesModule called');
    const {
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
        showPausedToggle,
        renderColumnInputs,
        stagesModule,
        tasksModule,
        getCache,
        formatDate,
        addDays,
        escapeHtml,
        trimText,
        loadWeek,
        saveColumnValue // optional function from columns module
    } = options;

    let debounceTimers = {};
    let pauseMap = {};

    function rebuildPauseMap(){
        pauseMap = {};
        const cache = getCache();
        if(!cache || !cache.pauses) return;
        cache.pauses.forEach(p=>{
            if(!pauseMap[p.entry_id]) pauseMap[p.entry_id] = {};
            if(!pauseMap[p.entry_id][p.schueler_id]) pauseMap[p.entry_id][p.schueler_id] = {};
            pauseMap[p.entry_id][p.schueler_id][p.date] = true;
        });
    }
    function isPaused(entryId, stuId, date){
        const entryMap = pauseMap[entryId]; if(!entryMap) return false; const stuMap = entryMap[stuId]; if(!stuMap) return false; return !!stuMap[date];
    }

    function buildEntryMap(){
        const cache = getCache();
        const m={};
        if(!cache || !cache.entries) return m;
        const weekDates = (cache.days||[]).map(d=>d.date);
        if(!weekDates.length) return m;
        const weekStartStr = weekDates[0];
        const weekEndStr = weekDates[weekDates.length-1];
        const weekStartDate = new Date(weekStartStr+ 'T00:00:00');
        const weekEndDate = new Date(weekEndStr+ 'T00:00:00');
        const today = new Date(); today.setHours(0,0,0,0);

        (cache.entries||[]).forEach(e=>{
            const entryStartDate = new Date(e.date+'T00:00:00');
            const isCompleted = !!e.completed_at;
            if(isCompleted){
                (e.schueler_ids||[]).forEach(sid=>{
                    if(isPaused(e.id, sid, e.date)) return;
                    (m[sid]||(m[sid]={}))[e.date] = (m[sid][e.date]||[]);
                    if(!m[sid][e.date].some(x=>x.id===e.id)) m[sid][e.date].push(Object.assign({}, e, {virtual_date:e.date}));
                });
                return;
            }
            const from = entryStartDate < weekStartDate ? weekStartDate : entryStartDate;
            // Show open entries for the whole week (including days after today in the current week)
            let to = weekEndDate;
            if(to < from) to = from;
            for(let d=new Date(from); d<=to; d.setDate(d.getDate()+1)){
                const dateStr = formatDate(d);
                (e.schueler_ids||[]).forEach(sid=>{
                    if(isPaused(e.id, sid, dateStr)) return;
                    (m[sid]||(m[sid]={}))[dateStr] = (m[sid][dateStr]||[]);
                    if(!m[sid][dateStr].some(x=>x.id===e.id)) m[sid][dateStr].push(Object.assign({}, e, {virtual_date:dateStr}));
                });
            }
        });
        return m;
    }

    function getBrightness(hexColor) {
        const hex = (hexColor||'').replace('#','');
        if(hex.length<6) return 255;
        const r = parseInt(hex.substr(0,2),16);
        const g = parseInt(hex.substr(2,2),16);
        const b = parseInt(hex.substr(4,2),16);
        return ((r*299)+(g*587)+(b*114))/1000;
    }

    function render(){
        const cache = getCache();
        if(!cache) return;
        rebuildPauseMap();
        const showPaused = showPausedToggle ? !!showPausedToggle.checked : false;
        diaryHead.innerHTML='';
        const todayStr = formatDate(new Date());
        diaryHead.insertAdjacentHTML('beforeend','<tr><th style="min-width:180px;">Schüler</th>' + (cache.days||[]).map(d=>{const isToday=d.date===todayStr;return `<th class="text-center${isToday? ' today-header':''}" data-date="${d.date}">${d.label}</th>`;}).join('') + '</tr>');
        const entryMap = buildEntryMap();
        diaryBody.innerHTML='';
        const taskStudentIds = new Set((cache.tasks||[]).map(t=>t.schueler_id));
        let lastKlasseId = null;

        (cache.schueler||[]).forEach(stu=>{
            if(cache.is_group && stu.klasse_id !== lastKlasseId){
                lastKlasseId = stu.klasse_id;
                const kObj = (cache.klassen||[]).find(k=>k.id===stu.klasse_id);
                const divider = document.createElement('tr'); divider.className='class-divider-row';
                const td = document.createElement('td'); td.colSpan = (cache.days||[]).length + 1; td.textContent = (kObj? kObj.name : ('Klasse ' + stu.klasse_id));
                if(kObj && kObj.color){ td.style.backgroundColor = kObj.color; td.style.color = getBrightness(kObj.color)>128? '#000000':'#ffffff'; }
                divider.appendChild(td); diaryBody.appendChild(divider);
            }

            let row = `<th class="align-top" style="font-size:.72rem;">`+
                      `<a href="paed-diary/schueler/${stu.id}" class="text-decoration-none" title="Detailansicht öffnen">${escapeHtml(stu.name)} <i class=\"fas fa-external-link-alt small ml-1\"></i></a>`+
                      `<span class="badge badge-light ml-1" title="Klasse">${(cache.klassen.find(k=>k.id===stu.klasse_id)||{}).kuerzel||''}</span>`+
                      `${stagesModule.renderStageSymbol(stu)}`+
                      `</th>`;

            (cache.days||[]).forEach(d=>{
                const entries = (entryMap[stu.id]?.[d.date]) || [];
                const entriesHtml = entries.map(e=>{
                    const enc = encodeURIComponent(e.content||'');
                    const isOpen = !e.completed_at;
                    const pauseBtn = isOpen ? `<button type="button" class="diary-btn diary-btn-pause entry-pause-btn" data-entry-id="${e.id}" data-stu="${stu.id}" data-date="${d.date}" title="Notiz an diesem Tag ausblenden" aria-label="Pausieren">⏸</button>` : '';
                    const completeBtn = isOpen ? `<button type="button" class="diary-btn diary-btn-complete entry-complete-btn" data-entry-id="${e.id}" title="Notiz abschließen" aria-label="Abschließen">✔</button>` : '';
                    return `<div class=\"entry-item d-flex align-items-start\" data-entry=\"${e.id}\" data-content=\"${enc}\" data-date-display=\"${d.date}\">`+
                           `<div class=\"flex-grow-1\">${e.user? `<span class=\"author\">${escapeHtml(e.user)}</span>` : ''}<span class=\"text\">${escapeHtml(trimText(e.content,120))}</span>${isOpen && e.virtual_date!==e.date? ' <span class=\"badge badge-warning badge-pill ml-1\" title=\"Fortlaufende offene Notiz\">laufend</span>':''}</div>`+
                           `<div class=\"ml-1 d-flex\">${completeBtn}${pauseBtn}</div>`+
                           `</div>`;
                }).join('');
                let pausedHtml = '';
                if(showPaused){
                    (cache.entries||[]).forEach(e=>{
                        if(e.completed_at) return;
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
                row += `<td class="note-cell${taskStudentIds.has(stu.id)?' stu-has-task-cell':''}${isToday? ' today-cell':''}" data-stu="${stu.id}" data-date="${d.date}">`+
                       `<div class="entry-add-space" style="min-height:18px; cursor:pointer;\" title="Neue Notiz erstellen"></div>`+
                       `<div class="entry-list">${entriesHtml}${pausedHtml}</div>`+
                       `<div class="col-inputs-row"><div class="col-inputs">${renderColumnInputs(stu.id,d.date)}</div></div>`+
                       `</td>`;
            });

            const tr = document.createElement('tr'); if(taskStudentIds.has(stu.id)) tr.classList.add('stu-has-task'); tr.innerHTML = row; diaryBody.appendChild(tr);
        });

        const endWeek = addDays(getCache().currentWeekStart || new Date(),4);
        weekLabel.textContent = `${(getCache().currentWeekStart || new Date()).toLocaleDateString()} - ${endWeek.toLocaleDateString()}`;
        // student-checkboxes and tasks update (tasksModule provided by caller)
        if(typeof options.renderStudentCheckboxes === 'function') options.renderStudentCheckboxes();
        tasksModule && typeof tasksModule.updateTaskStudentSelect === 'function' && tasksModule.updateTaskStudentSelect();
    }

    // Event handlers attached once
    diaryBody.addEventListener('click', e=>{
        const pauseBtn = e.target.closest('.entry-pause-btn');
        if(pauseBtn){
            e.stopImmediatePropagation();
            const entryId = pauseBtn.dataset.entryId; const stu = pauseBtn.dataset.stu; const date = pauseBtn.dataset.date;
            pauseBtn.disabled = true; const fd = new FormData(); fd.append('schueler_id', stu); fd.append('date', date);
            fetch(`paed-diary/entry/${entryId}/pause-day`, {method:'POST', headers:{'X-CSRF-TOKEN':csrf,'Accept':'application/json'}, body:fd})
                .then(r=>r.json())
                .then(j=>{ if(j.success){ const cache = getCache(); cache.pauses = cache.pauses || []; cache.pauses.push({entry_id: parseInt(entryId), schueler_id: parseInt(stu), date: date}); rebuildPauseMap(); render(); } else { alert(j.message||'Fehler beim Pausieren'); pauseBtn.disabled=false; } })
                .catch(()=>{ alert('Fehler beim Pausieren'); pauseBtn.disabled=false; });
            return;
        }
        const unpauseBtn = e.target.closest('.entry-unpause-btn');
        if(unpauseBtn){
            e.stopImmediatePropagation();
            const entryId = unpauseBtn.dataset.entryId; const stu = unpauseBtn.dataset.stu; const date = unpauseBtn.dataset.date;
            unpauseBtn.disabled = true; const fd = new FormData(); fd.append('schueler_id', stu); fd.append('date', date);
            fetch(`paed-diary/entry/${entryId}/unpause-day`, {method:'POST', headers:{'X-CSRF-TOKEN':csrf,'Accept':'application/json'}, body:fd})
                .then(r=>r.json())
                .then(j=>{ if(j.success){ const cache = getCache(); cache.pauses = (cache.pauses||[]).filter(p=> !(String(p.entry_id)===String(entryId) && String(p.schueler_id)===String(stu) && p.date===date)); rebuildPauseMap(); render(); } else { alert(j.message||'Fehler beim Reaktivieren'); unpauseBtn.disabled=false; } })
                .catch(()=>{ alert('Fehler beim Reaktivieren'); unpauseBtn.disabled=false; });
            return;
        }
        const completeBtn = e.target.closest('.entry-complete-btn');
        if(completeBtn){
            e.stopImmediatePropagation();
            const entryId = completeBtn.dataset.entryId; completeBtn.disabled=true;
            const entryEl = completeBtn.closest('.entry-item');
            const completedAtDate = (entryEl && (entryEl.dataset.dateDisplay || entryEl.dataset.date)) ? (entryEl.dataset.dateDisplay || entryEl.dataset.date) : formatDate(new Date());
            fetch(`paed-diary/entry/${entryId}/complete`, {
                method: 'POST',
                headers: {'Content-Type':'application/json','X-CSRF-TOKEN':csrf,'Accept':'application/json'},
                body: JSON.stringify({completed_at: completedAtDate})
            })
            .then(r=>r.json())
            .then(j=>{ if(j.success){ loadWeek(); } else { alert(j.message||'Fehler'); completeBtn.disabled=false; } })
            .catch(()=>{ alert('Fehler'); completeBtn.disabled=false; });
            return;
        }

        // Editor opening
        if(e.target.closest('.entry-item')){
            const entry = e.target.closest('.entry-item'); populateForEdit(entry); return;
        }
        const cell = e.target.closest('.note-cell');
        if(cell && !e.target.closest('.col-inputs')){ populateForNew(cell); }
    });

    diaryBody.addEventListener('input', e=>{ const inp = e.target.closest('.col-val-input'); if(!inp) return; const key = `${inp.dataset.col}-${inp.dataset.stu}-${inp.dataset.date}`; clearTimeout(debounceTimers[key]); const val = inp.value.trim(); debounceTimers[key] = setTimeout(()=>{ if(typeof saveColumnValue === 'function'){ saveColumnValue(inp.dataset.col, inp.dataset.stu, inp.dataset.date, val).catch(()=>{ inp.classList.add('border-danger'); setTimeout(()=> inp.classList.remove('border-danger'),1200); }); } else { console.warn('saveColumnValue not provided'); } },400); });

    diaryBody.addEventListener('click', e=>{ const btn = e.target.closest('.bool-btn'); if(!btn) return; const newVal = btn.dataset.value==='1'? '':'1'; btn.disabled=true; const fn = typeof saveColumnValue === 'function' ? saveColumnValue : ()=>Promise.resolve(); fn(btn.dataset.col, btn.dataset.stu, btn.dataset.date, newVal).then(()=>{ btn.dataset.value=newVal; btn.classList.toggle('btn-success', newVal==='1'); btn.classList.toggle('btn-outline-secondary', newVal!=='1'); }).catch(()=>{ btn.classList.add('btn-danger'); setTimeout(()=>btn.classList.remove('btn-danger'),1000); }).finally(()=>btn.disabled=false); });

    // Editor helper functions and handlers
    function showEditor(){ if(noteEditorCard) noteEditorCard.classList.remove('d-none'); }
    function hideEditor(){ if(noteEditorCard) noteEditorCard.classList.add('d-none'); }
    function clearEditor(){ if(!noteForm) return; noteForm.reset(); noteEntryIdInput.value=''; noteStatus.textContent=''; if(noteStudentsDiv){ noteStudentsDiv.querySelectorAll('input[type=checkbox]').forEach(cb=> cb.checked=false); } }
    function populateForNew(cell){ showEditor(); clearEditor(); noteEditorTitle.textContent='Notiz erfassen'; noteDeleteBtn.classList.add('d-none'); if(cell){ const d = cell.dataset.date; if(d) noteDateInput.value = d; const stu = cell.dataset.stu; if(stu){ const cb = document.getElementById('stu_chk_'+stu); if(cb) cb.checked=true; } }
        // Scroll to editor when opening (Neuer Eintrag)
        try{ if(noteEditorCard && typeof noteEditorCard.scrollIntoView === 'function'){ noteEditorCard.scrollIntoView({behavior:'smooth', block:'center'}); } }catch(_){ }
    }
    function populateForEdit(entryEl){ if(!entryEl) return; const entryId = entryEl.dataset.entry; const cache = getCache(); const entryObj = (cache.entries||[]).find(e=> String(e.id)===String(entryId)); if(!entryObj) return; showEditor(); clearEditor(); noteEditorTitle.textContent='Notiz bearbeiten'; noteDeleteBtn.classList.remove('d-none'); noteEntryIdInput.value = entryId; const dateDisp = entryEl.dataset.dateDisplay || entryObj.date; if(dateDisp) noteDateInput.value = dateDisp; try{ noteContentInput.value = decodeURIComponent(entryEl.dataset.content||''); }catch(_){ noteContentInput.value = entryEl.dataset.content||''; } (entryObj.schueler_ids||[]).forEach(id=>{ const cb = document.getElementById('stu_chk_'+id); if(cb) cb.checked=true; });
        // Automatisch zum Editor scrollen, damit der Benutzer das Formular sofort sieht
        try{ if(noteEditorCard && typeof noteEditorCard.scrollIntoView === 'function'){ noteEditorCard.scrollIntoView({behavior:'smooth', block:'center'}); } }catch(_){ }
    }

    // Wire note form and delete handlers (with submit guard to avoid double submissions)
    let noteSubmitting = false;
    const noteSaveBtn = document.getElementById('noteSaveBtn');
    noteForm && noteForm.addEventListener('submit', ev=>{
        console.debug('entriesModule: noteForm submit handler called');
        ev.preventDefault();
        if(noteSubmitting){ console.debug('entriesModule: submit ignored, already submitting'); return; }
        noteSubmitting = true;
        if(noteSaveBtn) noteSaveBtn.disabled = true;
        noteStatus.textContent='Speichere...'; const fd = new FormData(noteForm);
         const cache = getCache();
         if(cache && cache.group_id && cache.group_id !== ''){ fd.set('group_id', cache.group_id); }
         // set klasse_id if present in cache
         if(cache && cache.klasse_id) fd.set('klasse_id', cache.klasse_id);
         const completedCheckbox = document.getElementById('noteCompleted');
         if(completedCheckbox && !completedCheckbox.checked){ fd.delete('completed'); } else { fd.set('completed','1'); }
         const id = noteEntryIdInput.value;
         const url = id?`paed-diary/entry/${id}`:'paed-diary/entry';
         fetch(url,{method:'POST',headers:{'X-CSRF-TOKEN':csrf,'Accept':'application/json'},body:fd}).then(r=>r.json()).then(j=>{ if(j.success){ noteStatus.textContent='Gespeichert'; loadWeek(); // Schließe das Formular nach erfolgreichem Speichern
                try{ hideEditor(); }catch(_){ }
            } else { noteStatus.textContent=j.message||'Fehler'; } }).catch(()=> noteStatus.textContent='Fehler beim Speichern').finally(()=>{ noteSubmitting = false; if(noteSaveBtn) noteSaveBtn.disabled = false; });
    });
    noteDeleteBtn && noteDeleteBtn.addEventListener('click', ()=>{ const id = noteEntryIdInput.value; if(!id) return; if(!confirm('Eintrag wirklich löschen?')) return; noteStatus.textContent='Lösche...'; fetch(`paed-diary/entry/${id}`,{method:'DELETE',headers:{'X-CSRF-TOKEN':csrf,'Accept':'application/json'}}).then(r=>r.json()).then(j=>{ if(j.success){ noteStatus.textContent='Gelöscht'; loadWeek(); // Schließe das Formular nach erfolgreichem Löschen
                try{ hideEditor(); }catch(_){ }
            } else { noteStatus.textContent='Löschen fehlgeschlagen'; } }).catch(()=> noteStatus.textContent='Löschen fehlgeschlagen'); });

    // Wire optional editor open/clear/cancel buttons if provided
    if(openNoteInline) openNoteInline.addEventListener('click', ()=> populateForNew(null));
    if(noteClearBtn) noteClearBtn.addEventListener('click', ()=> populateForNew(null));
    if(noteEditorCancel) noteEditorCancel.addEventListener('click', hideEditor);

    // expose API
    return { render, populateForNew, populateForEdit, showEditor, hideEditor, clearEditor, rebuildPauseMap, isPaused, getBrightness };
}
