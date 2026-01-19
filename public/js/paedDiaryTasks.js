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

