// paedDiaryEntries.js - Modul für Eintragsverwaltung (Pause/Unpause, Render, Editor)
function initializeEntriesModule(options){
    //console.debug('initializeEntriesModule called');
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
        saveColumnValue, // optional function from columns module
        noteCategory,
        noteNewCategory,
        categoryTogglesContainer,
        renderAppointments
    } = options;

    let debounceTimers = {};
    let pauseMap = {};
    let absenceMap = {}; // [schueler_id][date] = true

    // UI-Präferenzen: werden im localStorage gespeichert, damit sie seitenübergreifend erhalten bleiben
    // hideAllCategoryHeadings: globaler Schalter – blendet ALLE Kategorieüberschriften aus
    // filterUncategorized:     Filter  – blendet Einträge ohne Kategorie aus
    let hideAllCategoryHeadings = (localStorage.getItem('paedDiary_hideAllHeadings') === '1');
    let filterUncategorized     = (localStorage.getItem('paedDiary_filterUncategorized') === '1');

    /**
     * Rendert die Tabelle neu und injiziert danach Termine wieder in den DOM.
     * Wird von allen internen Toggle-Handlern genutzt, die render() direkt aufrufen
     * (ohne den äußeren render()-Wrapper aus paedDiary.js, der appointmentsModule.loadAppointments enthält).
     */
    function renderWithAppointments(){
        render();
        if(typeof renderAppointments === 'function') renderAppointments();
    }

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

    function rebuildAbsenceMap(){
        absenceMap = {};
        const cache = getCache();
        if(!cache || !cache.absences) return;
        cache.absences.forEach(a=>{
            if(!absenceMap[a.schueler_id]) absenceMap[a.schueler_id] = {};
            absenceMap[a.schueler_id][a.datum] = true;
        });
    }

    function isAbsent(schuelerId, date){
        return !!(absenceMap[schuelerId] && absenceMap[schuelerId][date]);
    }
    function isPaused(entryId, stuId, date){
        const entryMap = pauseMap[entryId]; if(!entryMap) return false; const stuMap = entryMap[stuId]; if(!stuMap) return false; return !!stuMap[date];
    }

    function buildEntryMap(){
        const cache = getCache();
        const m={};
        if(!cache || !cache.entries) return m;

        // Filter-Sets aus Cache + localStorage
        const hiddenCatIds = new Set((cache.hidden_category_ids || []).map(id => Number(id)));

        const weekDates = (cache.days||[]).map(d=>d.date);
        if(!weekDates.length) return m;
        const weekStartStr = weekDates[0];
        const weekEndStr = weekDates[weekDates.length-1];
        const weekStartDate = new Date(weekStartStr+ 'T00:00:00');
        const weekEndDate = new Date(weekEndStr+ 'T00:00:00');
        const today = new Date(); today.setHours(0,0,0,0);

        (cache.entries||[]).forEach(e=>{
            // Kategoriefilter: Einträge ausgeblendeter Kategorien ausblenden
            if(e.category_id && hiddenCatIds.has(Number(e.category_id))) return;
            // Filter "Ohne Kategorie": Einträge ohne category_id ausblenden wenn aktiv
            if(!e.category_id && filterUncategorized) return;

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

    /**
     * Rendert den Inhalt des Kategorie-Dropdowns:
     *  1. Hinweistext zum Verhalten
     *  2. Globaler Schalter "Überschriften anzeigen" → blendet ALLE Kategorieüberschriften aus/ein
     *  3. Trennlinie + Abschnitt "Einträge filtern"
     *  4. Pro Kategorie ein Filter-Toggle → blendet Einträge dieser Kategorie aus
     *  5. Filter-Toggle "Ohne Kategorie" → blendet Einträge ohne Kategorie aus
     */
    function renderCategoryToggles(){
        if(!categoryTogglesContainer) return;
        const cache = getCache();
        const categories = cache.categories || [];
        const hiddenIds = new Set((cache.hidden_category_ids || []).map(id => Number(id)));


        // ── Globaler Überschriften-Toggle ────────────────────────────────────
        let html =
            `<div class="px-3 pt-2 mb-1">`+
            `<div class="custom-control custom-switch">`+
            `<input type="checkbox" class="custom-control-input" id="showAllHeadingsToggle"`+
            `${hideAllCategoryHeadings ? '' : ' checked'}>`+
            `<label class="custom-control-label small font-weight-bold" for="showAllHeadingsToggle">`+
            `Überschriften anzeigen</label>`+
            `</div>`+
            `</div>`;

        // ── Trennlinie + Abschnittsüberschrift ───────────────────────────────
        html += `<div class="dropdown-divider my-2"></div>`;
        html +=
            `<div class="px-3 mb-1">`+
            `<small class="text-muted text-uppercase" style="font-size:.65rem;letter-spacing:.05em;">`+
            `Einträge filtern</small>`+
            `</div>`;

        // ── Pro-Kategorie-Filter-Toggles ─────────────────────────────────────
        if(categories.length){
            categories.forEach(cat => {
                const isVisible = !hiddenIds.has(Number(cat.id));
                html +=
                    `<div class="px-3 mb-1">`+
                    `<div class="custom-control custom-switch">`+
                    `<input type="checkbox" class="custom-control-input category-filter-toggle"`+
                    ` id="catFilter_${cat.id}" data-category-id="${cat.id}"${isVisible ? ' checked' : ''}>`+
                    `<label class="custom-control-label small" for="catFilter_${cat.id}">`+
                    `${escapeHtml(cat.name)}</label>`+
                    `</div>`+
                    `</div>`;
            });
        } else {
            html += `<div class="px-3 mb-1"><small class="text-muted">Keine Kategorien vorhanden</small></div>`;
        }

        // ── Filter "Ohne Kategorie" ──────────────────────────────────────────
        html +=
            `<div class="px-3 mb-2">`+
            `<div class="custom-control custom-switch">`+
            `<input type="checkbox" class="custom-control-input" id="filterUncategorizedToggle"`+
            `${filterUncategorized ? '' : ' checked'}>`+
            `<label class="custom-control-label small" for="filterUncategorizedToggle">`+
            `Ohne Kategorie</label>`+
            `</div>`+
            `</div>`;

        categoryTogglesContainer.innerHTML = html;
    }

    // Dropdown bleibt offen beim Klicken auf Toggle-Schalter (Bootstrap 4 würde es sonst schließen)
    if(categoryTogglesContainer){
        categoryTogglesContainer.addEventListener('click', function(e){ e.stopPropagation(); });
    }

    // ── Globaler Überschriften-Toggle ────────────────────────────────────────
    document.addEventListener('change', function(e){
        if(e.target.id !== 'showAllHeadingsToggle') return;
        hideAllCategoryHeadings = !e.target.checked; // checked = Überschriften anzeigen
        localStorage.setItem('paedDiary_hideAllHeadings', hideAllCategoryHeadings ? '1' : '0');
        renderWithAppointments();
    });

    // ── Filter "Ohne Kategorie" ──────────────────────────────────────────────
    document.addEventListener('change', function(e){
        if(e.target.id !== 'filterUncategorizedToggle') return;
        filterUncategorized = !e.target.checked; // checked = Einträge sichtbar
        localStorage.setItem('paedDiary_filterUncategorized', filterUncategorized ? '1' : '0');
        renderWithAppointments();
    });

    // ── Pro-Kategorie-Eintragsfilter (server-persistiert) ────────────────────
    document.addEventListener('change', function(e){
        const toggle = e.target.closest('.category-filter-toggle');
        if(!toggle) return;
        const catId = toggle.dataset.categoryId;
        if(!catId) return;

        const cache = getCache();
        cache.hidden_category_ids = (cache.hidden_category_ids || []).map(Number);
        const id = Number(catId);
        const isNowHidden = !toggle.checked;

        // Optimistisches UI-Update: Cache sofort anpassen und neu rendern
        if(isNowHidden){
            if(!cache.hidden_category_ids.includes(id)) cache.hidden_category_ids.push(id);
        } else {
            cache.hidden_category_ids = cache.hidden_category_ids.filter(x => x !== id);
        }
        renderWithAppointments();

        // Persistieren via AJAX
        fetch(`paed-diary/categories/${catId}/toggle-hidden`, {
            method: 'POST',
            headers: {'X-CSRF-TOKEN': csrf, 'Accept': 'application/json'}
        }).then(r => r.json()).then(j => {
            if(!j.success){
                // Revert bei Fehler
                if(isNowHidden){
                    cache.hidden_category_ids = cache.hidden_category_ids.filter(x => x !== id);
                } else {
                    if(!cache.hidden_category_ids.includes(id)) cache.hidden_category_ids.push(id);
                }
                renderWithAppointments();
            }
        }).catch(() => {
            // Revert bei Netzwerkfehler
            if(isNowHidden){
                cache.hidden_category_ids = cache.hidden_category_ids.filter(x => x !== id);
            } else {
                if(!cache.hidden_category_ids.includes(id)) cache.hidden_category_ids.push(id);
            }
            renderWithAppointments();
        });
    });

    function render(){
        const cache = getCache();
        if(!cache) return;
        rebuildPauseMap();
        rebuildAbsenceMap();
        // populate category select if present
        try{
            if(noteCategory && cache.categories){
                // preserve current selection
                const cur = noteCategory.value || '';
                noteCategory.innerHTML = '<option value="">-- Keine --</option>' + (cache.categories||[]).map(c=>`<option value="${c.id}">${escapeHtml(c.name)}</option>`).join('');
                if(cur) noteCategory.value = cur;
            }
        }catch(_){ }

        const showPaused = showPausedToggle ? !!showPausedToggle.checked : false;
        diaryHead.innerHTML='';
        const todayStr = formatDate(new Date());
        diaryHead.insertAdjacentHTML('beforeend','<tr><th class="name_column">Schüler</th>' + (cache.days||[]).map(d=>{
            const isToday=d.date===todayStr;
            const isFerienTag = d.is_ferien || false;
            const ferienTitle = isFerienTag && d.ferien_name ? ` title="${escapeHtml(d.ferien_name)}"` : '';
            const ferienClass = isFerienTag ? ' ferien-header' : '';
            return `<th class="text-center${isToday? ' today-header':''}${ferienClass}" data-date="${d.date}"${ferienTitle}>${d.label}${isFerienTag?' 🏖️':''}</th>`;
        }).join('') + '</tr>');
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

            let row = `<th class="align-top schueler_name_field" style="font-size:.72rem;">`+
                      `<a href="paed-diary/schueler/${stu.id}" class="text-decoration-none" title="Detailansicht öffnen">${escapeHtml(stu.name)} <i class=\"fas fa-external-link-alt small ml-1\"></i></a>`+
                      `<span class="badge badge-light ml-1" title="Klasse">${(cache.klassen.find(k=>k.id===stu.klasse_id)||{}).kuerzel||''}</span>`+
                      `${stagesModule.renderStageSymbol(stu)}`+
                      `</th>`;

            (cache.days||[]).forEach(d=>{
                const entries = (entryMap[stu.id]?.[d.date]) || [];
                const isFerienTag = d.is_ferien || false;
                // Group entries by category while preserving category order (categories from sorted list)
                const entriesSorted = (entries||[]).slice().sort((a,b)=>{
                    const ca = (a.category||'').toString().toLowerCase();
                    const cb = (b.category||'').toString().toLowerCase();
                    if(ca === cb) return (Number(a.id) || 0) - (Number(b.id) || 0);
                    if(!ca) return 1; // empty category goes last
                    if(!cb) return -1;
                    return ca.localeCompare(cb, 'de');
                });

                // build groups
                const groups = {};
                const order = [];
                entriesSorted.forEach(e=>{
                    const key = e.category || ''; // empty string for no category
                    if(!(key in groups)){
                        groups[key] = [];
                        order.push(key);
                    }
                    groups[key].push(e);
                });

                // helper to render single entry
                const renderEntry = (e)=>{
                    const enc = encodeURIComponent(e.content||'');
                    const isOpen = !e.completed_at;
                    const pauseBtn = isOpen ? `<button type="button" class="diary-btn diary-btn-pause entry-pause-btn" data-entry-id="${e.id}" data-stu="${stu.id}" data-date="${d.date}" title="Notiz an diesem Tag ausblenden" aria-label="Pausieren">⏸</button>` : '';
                    const completeBtn = isOpen ? `<button type="button" class="diary-btn diary-btn-complete entry-complete-btn" data-entry-id="${e.id}" title="Notiz abschließen" aria-label="Abschließen">✔</button>` : '';
                    const catIdAttr = e.category_id? `data-category-id="${e.category_id}"` : '';
                    const catNameAttr = e.category? `data-category-name="${escapeHtml(e.category)}"` : '';
                    return `<div class="entry-item d-flex align-items-start" data-entry="${e.id}" data-content="${enc}" data-date-display="${d.date}" ${catIdAttr} ${catNameAttr}>`+
                           `<div class="flex-grow-1">${e.user? `<span class="author">${escapeHtml(e.user)}</span>` : ''}<span class="text">${escapeHtml(trimText(e.content,120))}</span>${isOpen && e.virtual_date!==e.date? ' <span class="badge badge-warning badge-pill ml-1" title="Fortlaufende offene Notiz">laufend</span>':''}</div>`+
                           `<div class="ml-1 d-flex">${completeBtn}${pauseBtn}</div>`+
                           `</div>`;
                };

                // assemble grouped HTML with category headers and visual separator
                let entriesHtml = '';
                order.forEach((catKey, idx)=>{
                    const catLabel = catKey ? catKey : 'Ohne Kategorie';
                    // Überschrift anzeigen, wenn globaler "Überschriften ausblenden"-Schalter nicht aktiv
                    if(!hideAllCategoryHeadings){
                        entriesHtml += `<div class="entry-category-header">${escapeHtml(catLabel)}</div>`;
                    }
                    entriesHtml += `<div class="category-entries">${groups[catKey].map(e => renderEntry(e)).join('')}</div>`;
                    // optional spacing between category groups
                    if(idx < order.length - 1) entriesHtml += `<div style="height:6px"></div>`;
                });
                // if there were no entries, entriesHtml remains empty
                 let pausedHtml = '';
                 if(showPaused){
                     (cache.entries||[]).forEach(e=>{
                         if(e.completed_at) return;
                         if(!e.schueler_ids.includes(stu.id)) return;
                         if(isPaused(e.id, stu.id, d.date)){
                             pausedHtml += `<div class="entry-item paused-entry d-flex align-items-start text-muted" data-entry="${e.id}" data-date-display="${d.date}">`+
                                           `<div class="flex-grow-1"><em>${escapeHtml(trimText(e.content,100))}</em> <span class="badge badge-light ml-1" title="Pausiert">Pause</span></div>`+
                                           `<div class="ml-1 d-flex"><button type="button" class="diary-btn diary-btn-unpause entry-unpause-btn" data-entry-id="${e.id}" data-stu="${stu.id}" data-date="${d.date}" title="Notiz an diesem Tag wieder anzeigen" aria-label="Reaktivieren">▶</button></div>`+
                                           `</div>`;
                         }
                     });
                 }
                const isToday = d.date === todayStr;
                const ferienClass = isFerienTag ? ' ferien-cell' : '';
                const absent = isAbsent(stu.id, d.date);
                const absentClass = absent ? ' absent-cell' : '';
                const absenceBtn = `<button type="button" class="absence-toggle diary-btn${absent?' diary-btn-absent':' diary-btn-present'}" data-stu="${stu.id}" data-klasse="${stu.klasse_id}" data-date="${d.date}" title="${absent?'Abwesenheit aufheben':'Als abwesend markieren'}">${absent?'🚫':'👤'}</button>`;
                const absentBanner = absent ? `<div class="absent-banner text-danger" style="font-size:.68rem;font-weight:bold;padding:1px 2px;">🚫 Abwesend</div>` : '';
                // pausedHtml rendered outside of .entry-list to avoid creating scrollbars inside the cell
                row += `<td class="note-cell${taskStudentIds.has(stu.id)?' stu-has-task-cell':''}${isToday? ' today-cell':''}${ferienClass}${absentClass}" data-stu="${stu.id}" data-date="${d.date}">`+
                       `<div class="entry-add-space" style="min-height:18px; cursor:pointer;" title="Neue Notiz erstellen"><div style="float:right">${absenceBtn}</div></div>`+
                       `${absentBanner}`+
                       `<div class="entry-list"${absent?' style="opacity:0.4"':''}>${entriesHtml}</div>`+
                       `<div class="paused-entries">${pausedHtml}</div>`+
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
        // NEU (TODO 9): Kategorie-Toggles nach jedem Render neu zeichnen
        renderCategoryToggles();
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
    function clearEditor(){ if(!noteForm) return; noteForm.reset(); noteEntryIdInput.value=''; noteStatus.textContent=''; if(noteStudentsDiv){ noteStudentsDiv.querySelectorAll('input[type=checkbox]').forEach(cb=> cb.checked=false); }
        if(noteCategory) try{ noteCategory.value=''; }catch(_){ }
        if(noteNewCategory) try{ noteNewCategory.value=''; }catch(_){ }
    }
    function populateForNew(cell){ showEditor(); clearEditor(); noteEditorTitle.textContent='Notiz erfassen'; noteDeleteBtn.classList.add('d-none'); if(cell){ const d = cell.dataset.date; if(d) noteDateInput.value = d; const stu = cell.dataset.stu; if(stu){ const cb = document.getElementById('stu_chk_'+stu); if(cb) cb.checked=true; } }
        // Ensure category controls are cleared
        if(noteCategory) try{ noteCategory.value=''; }catch(_){ }
        if(noteNewCategory) try{ noteNewCategory.value=''; }catch(_){ }
        // Scroll to editor when opening (Neuer Eintrag)
        try{ if(noteEditorCard && typeof noteEditorCard.scrollIntoView === 'function'){ noteEditorCard.scrollIntoView({behavior:'smooth', block:'center'}); } }catch(_){ }
    }
    function populateForEdit(entryEl){ if(!entryEl) return; const entryId = entryEl.dataset.entry; const cache = getCache(); const entryObj = (cache.entries||[]).find(e=> String(e.id)===String(entryId)); if(!entryObj) return; showEditor(); clearEditor(); noteEditorTitle.textContent='Notiz bearbeiten'; noteDeleteBtn.classList.remove('d-none'); noteEntryIdInput.value = entryId; const dateDisp = entryEl.dataset.dateDisplay || entryObj.date; if(dateDisp) noteDateInput.value = dateDisp; try{ noteContentInput.value = decodeURIComponent(entryEl.dataset.content||''); }catch(_){ noteContentInput.value = entryEl.dataset.content||''; } (entryObj.schueler_ids||[]).forEach(id=>{ const cb = document.getElementById('stu_chk_'+id); if(cb) cb.checked=true; });
        // set category fields if present
        try{
            if(noteCategory){
                // prefer server-provided category_id from entryObj or the data attribute
                const cid = entryObj.category_id || entryEl.dataset.categoryId || '';
                noteCategory.value = cid || '';
            }
            if(noteNewCategory){ noteNewCategory.value = ''; }
        }catch(_){ }
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

         const dossier_onlyCheckbox = document.getElementById('noteCompleted');
        if(dossier_onlyCheckbox && !dossier_onlyCheckbox.checked){ fd.delete('dossier_only'); } else { fd.set('dossier_only','1'); }

         const id = noteEntryIdInput.value;
         const url = id?`paed-diary/entry/${id}`:'paed-diary/entry';
         fetch(url,{method:'POST',headers:{'X-CSRF-TOKEN':csrf,'Accept':'application/json'},body:fd}).then(r=>r.json()).then(j=>{ if(j.success){ noteStatus.textContent='Gespeichert'; loadWeek(); // Schließe das Formular nach erfolgreichem Speichern
                try{ hideEditor(); }catch(_){ }
            } else { noteStatus.textContent=j.message||'Fehler'; } }).catch(()=> noteStatus.textContent='Fehler beim Speichern').finally(()=>{ noteSubmitting = false; if(noteSaveBtn) noteSaveBtn.disabled = false; });
    });
    noteDeleteBtn && noteDeleteBtn.addEventListener('click', ()=>{ const id = noteEntryIdInput.value; if(!id) return; if(!confirm('Eintrag wirklich löschen?')) return; noteStatus.textContent='Lösche...';
    try{
        const cache = getCache ? getCache() : null;
        // berechne klasseId außerhalb von try/catch, damit es im Fallback verfügbar ist
        const klasseId = (cache && cache.klasse_id) ? cache.klasse_id : (document.getElementById('noteKlasseId')?.value || '');
        const fd = new FormData();
        if(klasseId) fd.append('klasse_id', klasseId);
        fetch(`paed-diary/entry/${id}`,{method:'DELETE',headers:{'X-CSRF-TOKEN':csrf,'Accept':'application/json'},body:fd}).then(r=>r.json()).then(j=>{ if(j.success){ noteStatus.textContent='Gelöscht'; loadWeek(); // Schließe das Formular nach erfolgreichem Löschen
                try{ hideEditor(); }catch(_){ }
            } else { noteStatus.textContent=j.message||'Löschen fehlgeschlagen'; } }).catch(()=> noteStatus.textContent='Löschen fehlgeschlagen');
    }catch(_){
        // fallback: send klasse_id als query param wenn Body nicht unterstützt
        const cache = getCache ? getCache() : null;
        const klasseId = (cache && cache.klasse_id) ? cache.klasse_id : (document.getElementById('noteKlasseId')?.value || '');
        const url = klasseId ? `paed-diary/entry/${id}?klasse_id=${encodeURIComponent(klasseId)}` : `paed-diary/entry/${id}`;
        fetch(url,{method:'DELETE',headers:{'X-CSRF-TOKEN':csrf,'Accept':'application/json'}}).then(r=>r.json()).then(j=>{ if(j.success){ noteStatus.textContent='Gelöscht'; loadWeek(); try{ hideEditor(); }catch(_){ } } else { noteStatus.textContent='Löschen fehlgeschlagen'; } }).catch(()=> noteStatus.textContent='Löschen fehlgeschlagen');
   }
    });

    // Wire optional editor open/clear/cancel buttons if provided
    if(openNoteInline) openNoteInline.addEventListener('click', ()=> populateForNew(null));
    if(noteClearBtn) noteClearBtn.addEventListener('click', ()=> populateForNew(null));
    if(noteEditorCancel) noteEditorCancel.addEventListener('click', hideEditor);

    // ── Abwesenheits-Toggle ───────────────────────────────────────────────────
    document.addEventListener('click', function(e){
        const btn = e.target.closest('.absence-toggle');
        if(!btn) return;
        e.stopImmediatePropagation();
        btn.disabled = true;
        fetch('paed-diary/absence', {
            method: 'POST',
            headers: {'X-CSRF-TOKEN': csrf, 'Content-Type': 'application/json', 'Accept': 'application/json'},
            body: JSON.stringify({
                schueler_id: btn.dataset.stu,
                klasse_id:   btn.dataset.klasse,
                datum:       btn.dataset.date,
            }),
        })
        .then(r => r.json())
        .then(j => {
            if(j.success){
                const cache = getCache();
                cache.absences = cache.absences || [];
                cache.pauses   = cache.pauses   || [];
                if(j.absent){
                    // Abwesenheit eintragen
                    cache.absences.push({id: null, schueler_id: parseInt(btn.dataset.stu), datum: btn.dataset.date});
                    // Neu erzeugte Pausen aus der Server-Antwort in den Cache schreiben
                    if(Array.isArray(j.pauses)){
                        j.pauses.forEach(p => {
                            const already = cache.pauses.some(cp =>
                                cp.entry_id   === p.entry_id &&
                                cp.schueler_id === p.schueler_id &&
                                cp.date        === p.date
                            );
                            if(!already){
                                cache.pauses.push({
                                    entry_id:    p.entry_id,
                                    schueler_id: p.schueler_id,
                                    date:        p.date,
                                });
                            }
                        });
                    }
                } else {
                    // Abwesenheit entfernen
                    cache.absences = cache.absences.filter(a =>
                        !(String(a.schueler_id) === String(btn.dataset.stu) && a.datum === btn.dataset.date)
                    );
                    // Pausen des Schülers an diesem Tag aus dem Cache entfernen
                    const stuId = parseInt(btn.dataset.stu);
                    const date  = btn.dataset.date;
                    const removedIds = Array.isArray(j.removed_entry_ids) ? j.removed_entry_ids : [];
                    cache.pauses = cache.pauses.filter(p =>
                        !(p.schueler_id === stuId && p.date === date &&
                          (removedIds.length === 0 || removedIds.includes(p.entry_id)))
                    );
                }
                rebuildAbsenceMap();
                rebuildPauseMap();
                render();
                if(typeof renderAppointments === 'function') renderAppointments();
            } else {
                alert(j.message || 'Fehler beim Setzen der Abwesenheit');
                btn.disabled = false;
            }
        })
        .catch(() => { alert('Fehler beim Setzen der Abwesenheit'); btn.disabled = false; });
    });

    // expose API
    return { render, renderCategoryToggles, populateForNew, populateForEdit, showEditor, hideEditor, clearEditor, rebuildPauseMap, rebuildAbsenceMap, isPaused, isAbsent, getBrightness };
}
