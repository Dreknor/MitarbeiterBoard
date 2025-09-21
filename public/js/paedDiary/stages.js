// stages.js - ausgelagerte Logik für Grading-Stages

function initializeStagesModule({csrf, getCache, escapeHtml, loadWeek}){
    // Referenz zu loadWeek, damit Linter nicht über unbenutzten Parameter klagt (wir rufen reload nicht automatisch auf)
    void(loadWeek);

    // Einfaches Modul: rendert die Stage-UI und behandelt Klicks zum Setzen/Entfernen der Stufe
    function renderStageSymbol(student){
        const cache = getCache();
        const canManage = !!(cache && cache.can_manage_grading);
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

    // Hilfsfunktionen für das Dropdown
    function closeStageDropdown(){
        const existing = document.querySelector('.paed-stage-dropdown');
        if(existing) existing.remove();
        document.removeEventListener('click', onDocumentBodyClickForDropdown);
    }
    function onDocumentBodyClickForDropdown(e){
        if(e.target.closest && e.target.closest('.paed-stage-dropdown')) return;
        if(e.target.closest && e.target.closest('.stage-change')) return; // clicking another opener will recreate
        closeStageDropdown();
    }

    // Klick-Handler: öffnet ein kleines Dropdown neben dem Klick-Element
    async function onDocumentClick(e){
        const opener = e.target.closest && e.target.closest('.stage-change');
        if(!opener) return;
        e.preventDefault();
        e.stopPropagation();

        // vorheriges Dropdown schließen
        closeStageDropdown();

        const studentId = opener.getAttribute('data-stu');
        const klasseId = opener.getAttribute('data-klasse');
        if(!studentId) return;

        const cache = getCache();
        const student = (cache && cache.schueler || []).find(s=>String(s.id)===String(studentId));
        const effectiveKlasse = klasseId || (student ? student.klasse_id : null);

        // Container bauen
        const container = document.createElement('div');
        container.className = 'paed-stage-dropdown';
        // Basales Styling (leicht angepasst) — kann durch CSS ersetzt werden
        container.style.position = 'absolute';
        container.style.zIndex = 9999;
        container.style.background = '#ffffff';
        container.style.border = '1px solid #ddd';
        container.style.boxShadow = '0 2px 8px rgba(0,0,0,0.12)';
        container.style.padding = '6px';
        container.style.borderRadius = '4px';
        container.style.minWidth = '140px';
        container.style.display = 'flex';
        container.style.flexDirection = 'column';
        container.style.gap = '6px';

        // Positionierung: rechts unter dem opener, sofern Platz
        const rect = opener.getBoundingClientRect();
        const scrollTop = window.pageYOffset || document.documentElement.scrollTop || document.body.scrollTop || 0;
        const scrollLeft = window.pageXOffset || document.documentElement.scrollLeft || document.body.scrollLeft || 0;
        container.style.top = (rect.bottom + scrollTop + 6) + 'px';
        container.style.left = (rect.left + scrollLeft) + 'px';

        // Lade Stufen
        const listInfo = document.createElement('div');
        listInfo.className = 'small text-muted';
        listInfo.textContent = 'Lade...';
        container.appendChild(listInfo);
        document.body.appendChild(container);

        try{
            if(!effectiveKlasse){
                listInfo.textContent = 'Keine Stufen verfügbar';
                document.addEventListener('click', onDocumentBodyClickForDropdown);
                return;
            }
            const res = await fetch(`paed-diary/klasse/${encodeURIComponent(effectiveKlasse)}/stages`, { headers: {'Accept':'application/json'} });
            if(!res.ok){
                container.innerHTML = '';
                const errDiv = document.createElement('div');
                errDiv.className = 'text-danger small';
                errDiv.textContent = 'Fehler beim Laden der Stufen';
                container.appendChild(errDiv);
                document.addEventListener('click', onDocumentBodyClickForDropdown);
                return;
            }
            const j = await res.json();
            const stages = j.stages || [];

            // Leere Container und fülle
            container.innerHTML = '';

            // Option: Keine Stufe
            const noneBtn = document.createElement('button');
            noneBtn.type = 'button';
            noneBtn.className = 'btn btn-sm btn-outline-secondary';
            noneBtn.style.display = 'flex';
            noneBtn.style.alignItems = 'center';
            noneBtn.style.gap = '8px';
            noneBtn.innerHTML = '<span style="width:20px;display:inline-block;text-align:center;">—</span><span>Keine Stufe</span>';
            noneBtn.addEventListener('click', (ev)=>{ ev.stopPropagation(); applyStageSelection(opener, studentId, '', container); });
            container.appendChild(noneBtn);

            stages.forEach(s=>{
                const btn = document.createElement('button');
                btn.type = 'button';
                btn.className = 'btn btn-sm btn-outline-secondary text-left';
                btn.style.display = 'flex';
                btn.style.alignItems = 'center';
                btn.style.gap = '8px';
                btn.style.justifyContent = 'flex-start';
                btn.dataset.stageId = s.id;

                // Fallback-Label: name oder symbol oder Stufe {id}
                const rawName = (s.name || '').toString().trim();
                const rawSymbol = (s.symbol || '').toString().trim();
                const labelText = rawName || rawSymbol || ('Stufe ' + s.id);
                btn.title = labelText;
                btn.setAttribute('aria-label', labelText);

                if(s.image_url){
                    btn.innerHTML = `<img src="${escapeHtml(s.image_url)}" alt="${escapeHtml(labelText)}" style="width:20px;height:20px;object-fit:contain;"> <span class=\"stage-label\">${escapeHtml(labelText)}</span>`;
                } else if(s.symbol){
                    btn.innerHTML = `<span class=\"badge badge-info\">${escapeHtml(s.symbol)}</span> <span class=\"stage-label\">${escapeHtml(labelText)}</span>`;
                } else {
                    btn.innerHTML = `<span class=\"stage-label\">${escapeHtml(labelText)}</span>`;
                }

                btn.addEventListener('click', (ev)=>{ ev.stopPropagation(); applyStageSelection(opener, studentId, s.id, container); });
                container.appendChild(btn);
            });

            // Setze Fokus & Body-Click zum Schließen
            document.addEventListener('click', onDocumentBodyClickForDropdown);

        }catch(err){
            container.innerHTML = '';
            const errDiv = document.createElement('div');
            errDiv.className = 'text-danger small';
            errDiv.textContent = 'Fehler beim Laden der Stufen';
            container.appendChild(errDiv);
            document.addEventListener('click', onDocumentBodyClickForDropdown);
        }
    }

    // Senden der Auswahl und UI-Aktualisierung
    function applyStageSelection(openerEl, schuelerId, gradingStageId, dropdownEl){
        // feedback UI (klein)
        const saving = document.createElement('div');
        saving.className = 'small text-muted';
        saving.textContent = 'Speichere...';
        dropdownEl.appendChild(saving);

        const fd = new FormData();
        fd.append('grading_stage_id', gradingStageId);
        fd.append('schueler_id', schuelerId);

        fetch('paed-diary/change-stage', { method: 'POST', headers: {'X-CSRF-TOKEN': csrf, 'Accept': 'application/json'}, body: fd })
            .then(r=>r.json())
            .then(j=>{
                if(j.success){
                    // update cache -> student
                    const cache = getCache();
                    if(cache && Array.isArray(cache.schueler)){
                        const stu = cache.schueler.find(s=>String(s.id)===String(schuelerId));
                        if(stu){
                            stu.stage = j.new_stage ? { id: j.new_stage.id, name: j.new_stage.name, symbol: j.new_stage.symbol, image_url: j.new_stage.image_url || null } : null;
                        }
                    }
                    // Update DOM: ersetze openerEl durch neues HTML
                    // Erzeuge temporär student-Objekt für render und ersetze opener-HTML
                    const studentObj = { id: schuelerId, klasse_id: openerEl.getAttribute('data-klasse'), stage: (j.new_stage ? ( { id: j.new_stage.id, name: j.new_stage.name, symbol: j.new_stage.symbol, image_url: j.new_stage.image_url || null } ) : null ) };
                    openerEl.outerHTML = renderStageSymbol(studentObj);
                    // close dropdown
                    closeStageDropdown();
                } else {
                    saving.textContent = j.message || 'Fehler beim Speichern';
                    setTimeout(()=>{ closeStageDropdown(); }, 1500);
                }
            })
            .catch(()=>{
                saving.textContent = 'Netzwerkfehler';
                setTimeout(()=>{ closeStageDropdown(); }, 1500);
            });
    }

    // Eventlistener registrieren
    document.addEventListener('click', onDocumentClick);

    return {
        renderStageSymbol
    };
}
