// public/js/paedDiary/tasks.js

function initializeTasksModule(dependencies) {
    const {
        csrf,
        klasseSelect,
        groupSelect,
        escapeHtml,
        trimText,
        loadWeek,
        getCache,
        diaryBody // neu: DOM-Element der Tabelle, um inline-Buttons zu behandeln
    } = dependencies;

    // --- DOM-Elemente ---
    const taskModal = $('#taskModal');
    const taskForm = document.getElementById('taskForm');
    const taskSchuelerSelect = document.getElementById('taskSchueler');
    const openTaskModalBtn = document.getElementById('openTaskModal');
    const tasksPanel = document.getElementById('tasksPanel');
    const tasksList = document.getElementById('tasksList');
    const refreshTasksBtn = document.getElementById('refreshTasks');

    // --- Kernfunktionen ---

    function renderTasks() {
        const localCache = getCache();
        if (!tasksList) return;

        const openEntries = (localCache.entries || []).filter(e => !e.completed_at);
        const allTasks = (localCache.tasks || []).concat(openEntries.map(e => ({
            id: 'entry-' + e.id,
            schueler_id: e.schueler_ids[0], // Annahme: offene Notizen sind oft für einen Schüler
            title: e.content,
            is_entry: true,
            entry_id: e.id,
            user: e.user,
            schueler_ids: e.schueler_ids,
        })));

        if (allTasks.length === 0) {
            tasksPanel.style.display = 'none';
            return;
        }

        tasksPanel.style.display = 'block';
        tasksList.innerHTML = '';

        const tasksByStudent = {};
        allTasks.forEach(task => {
            const studentIds = task.is_entry ? task.schueler_ids : [task.schueler_id];
            studentIds.forEach(studentId => {
                if (!tasksByStudent[studentId]) {
                    tasksByStudent[studentId] = [];
                }
                tasksByStudent[studentId].push(task);
            });
        });

        const sortedStudentIds = Object.keys(tasksByStudent).sort((a, b) => {
            const studentA = (localCache.schueler || []).find(s => String(s.id) === String(a));
            const studentB = (localCache.schueler || []).find(s => String(s.id) === String(b));
            if (studentA && studentB) {
                return studentA.name.localeCompare(studentB.name, 'de');
            }
            return 0;
        });

        sortedStudentIds.forEach(studentId => {
            const student = (localCache.schueler || []).find(s => String(s.id) === String(studentId));
            if (!student) return;

            const studentTasks = tasksByStudent[studentId];
            const studentDiv = document.createElement('div');
            studentDiv.className = 'mb-3';
            let studentHtml = `<strong class="small d-block border-bottom mb-1">${escapeHtml(student.name)}</strong>`;

            studentTasks.forEach(task => {
                if (task.is_entry) {
                    studentHtml += `
                        <div class="d-flex justify-content-between align-items-start  text-info mb-1 open-entry-large">
                            <div class="flex-grow-1">
                                <i class="fas fa-comment-alt mr-1"></i> ${escapeHtml(trimText(task.title, 60))}
                                <span class="text-muted small">(${escapeHtml(task.user)})</span>
                            </div>
                            <button class="diary-btn diary-btn-complete complete-entry-btn ml-2" title="Notiz abschließen" data-entry-id="${task.entry_id}">✔</button>
                        </div>`;
                } else {
                    const isOverdue = task.due_date && new Date(task.due_date) < new Date();
                    studentHtml += `
                        <div class="d-flex justify-content-between align-items-start  ${task.highlighted ? 'text-danger font-weight-bold' : ''} mb-1">
                            <div class="flex-grow-1">
                                <i class="fas fa-tasks mr-1"></i> ${escapeHtml(task.title)}
                                ${task.due_date ? `<span class="text-muted small ml-1">(${isOverdue ? 'Fällig: ' : ''}${new Date(task.due_date).toLocaleDateString()})</span>` : ''}
                            </div>
                            <button class="diary-btn diary-btn-complete close-task-btn ml-2" title="Aufgabe ausblenden" data-task-id="${task.id}">✕</button>
                        </div>`;
                }
            });
            studentDiv.innerHTML = studentHtml;
            tasksList.appendChild(studentDiv);
        });
    }

    function updateTaskStudentSelect() {
        const localCache = getCache();
        if (taskSchuelerSelect) {
            taskSchuelerSelect.innerHTML = '<option value="">-- Schüler --</option>' + (localCache.schueler || []).map(s => `<option value="${s.id}">${escapeHtml(s.name)}</option>`).join('');
        }
    }


    // --- Neu: Task-Badges neben Schülernamen (zentrale Darstellung in tasks module) ---
    function getTasksForStudent(studentId){
        const localCache = getCache();
        if(!localCache) return [];
        const openEntries = (localCache.entries || []).filter(e => !e.completed_at);
        const entryTasks = openEntries.map(e => ({
            is_entry: true,
            entry_id: e.id,
            title: e.content,
            user: e.user,
            schueler_ids: e.schueler_ids || []
        }));
        const normalTasks = (localCache.tasks || []).map(t => Object.assign({}, t));
        const allTasks = normalTasks.concat(entryTasks);
        return allTasks.filter(t => {
            if(t.is_entry) return (t.schueler_ids||[]).map(String).includes(String(studentId));
            return String(t.schueler_id) === String(studentId);
        });
    }

    function renderTaskBadgesOnNames(diaryBodyEl){
        if(!diaryBodyEl) return;
        const rows = Array.from(diaryBodyEl.querySelectorAll('tr'));
        rows.forEach(tr => {
            const noteCell = tr.querySelector('td.note-cell');
            let stuId = noteCell ? noteCell.dataset.stu : null;
            if(!stuId) return;
            const th = tr.querySelector('th');
            if(!th) return;
            const existing = th.querySelector('.student-tasks-inline');
            if(existing) existing.remove();
            const tasks = getTasksForStudent(stuId);
            if(!tasks || tasks.length === 0) return;
            const wrapper = document.createElement('div');
            wrapper.className = 'student-tasks-inline';
            const visible = tasks.slice(0,3);
            visible.forEach(t => {
                const item = document.createElement('div');
                item.className = 'student-tasks-item d-flex justify-content-between align-items-center';
                if(t.is_entry){
                    item.classList.add('task-entry');
                    item.innerHTML = `<span class="task-inline-title text-truncate">${escapeHtml(trimText(t.title || (t.content||''), 80))}</span>`+
                                     ` <button type="button" class="btn btn-link btn-sm p-0 inline-complete-entry-btn" data-entry-id="${t.entry_id}" title="Notiz abschließen">✔</button>`;
                } else {
                    const overdue = t.due_date && new Date(t.due_date) < new Date();
                    item.classList.add('task-normal');
                    if(overdue) item.classList.add('task-overdue');
                    item.innerHTML = `<span class="task-inline-title text-truncate">${escapeHtml(trimText(t.title || '', 80))}`+
                                     `${t.due_date? ` <small class="text-muted">(${new Date(t.due_date).toLocaleDateString()})</small>` : ''}</span>`+
                                     ` <button type="button" class="btn btn-link btn-sm p-0 inline-close-task-btn" data-task-id="${t.id}" title="Aufgabe abschließen">✕</button>`;
                }
                wrapper.appendChild(item);
            });
            if(tasks.length > 3){
                const more = document.createElement('div');
                more.className = 'text-muted small';
                more.textContent = `+${tasks.length - 3}`;
                wrapper.appendChild(more);
            }
            th.appendChild(wrapper);
        });
    }

    // Wenn diaryBody übergeben wurde, wire Event-Handler für die inline-Buttons
    if(diaryBody){
        diaryBody.addEventListener('click', function(e){
            const closeTaskBtn = e.target.closest('.inline-close-task-btn');
            if(closeTaskBtn){
                e.preventDefault(); e.stopPropagation();
                const taskId = closeTaskBtn.dataset.taskId; if(!taskId) return; closeTaskBtn.disabled = true;
                fetch(`paed-diary/task/${taskId}/close`, { method: 'POST', headers: { 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json' } })
                    .then(r=>r.json()).then(j=>{ if(j.success){ loadWeek(); } else { closeTaskBtn.disabled = false; alert(j.message||'Fehler'); } })
                    .catch(()=>{ closeTaskBtn.disabled = false; alert('Fehler'); });
                return;
            }
            const completeEntryBtn = e.target.closest('.inline-complete-entry-btn');
            if(completeEntryBtn){
                e.preventDefault(); e.stopPropagation();
                const entryId = completeEntryBtn.dataset.entryId; if(!entryId) return; completeEntryBtn.disabled = true;
                const completedAtDate = new Date().toISOString().slice(0,10);
                fetch(`paed-diary/entry/${entryId}/complete`, {
                    method: 'POST', headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json' },
                    body: JSON.stringify({ completed_at: completedAtDate })
                }).then(r=>r.json()).then(j=>{ if(j.success){ loadWeek(); } else { completeEntryBtn.disabled = false; alert(j.message || 'Fehler'); } })
                .catch(()=>{ completeEntryBtn.disabled = false; alert('Fehler'); });
                return;
            }
        });
    }

    // --- Event Listeners ---
    if (openTaskModalBtn) {
        openTaskModalBtn.addEventListener('click', () => {
            if (taskForm) taskForm.reset();
            const taskKlasseId = document.getElementById('taskKlasseId');
            if (taskKlasseId) taskKlasseId.value = klasseSelect.value;
            taskModal.modal('show');
        });
    }

    if (taskForm) {
        taskForm.addEventListener('submit', e => {
            e.preventDefault();
            const fd = new FormData(taskForm);
            if (groupSelect.value) {
                fd.set('group_id', groupSelect.value);
            }
            fd.set('klasse_id', klasseSelect.value);
            if (!fd.get('highlighted')) fd.set('highlighted', '0');

            fetch('paed-diary/task', {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': csrf,
                        'Accept': 'application/json'
                    },
                    body: fd
                })
                .then(r => r.json())
                .then(j => {
                    if (j.success) {
                        taskModal.modal('hide');
                        loadWeek();
                    }
                }).catch(() => {});
        });
    }

    if (tasksPanel) {
        tasksPanel.addEventListener('click', e => {
            const closeBtn = e.target.closest('.close-task-btn');
            if (closeBtn) {
                const taskId = closeBtn.dataset.taskId;
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
                            loadWeek();
                        } else {
                            closeBtn.disabled = false;
                        }
                    }).catch(() => closeBtn.disabled = false);
                return;
            }

            const completeBtn = e.target.closest('.complete-entry-btn');
            if (completeBtn) {
                const entryId = completeBtn.dataset.entryId;
                completeBtn.disabled = true;
                const completedAtDate = new Date().toISOString().slice(0, 10); // YYYY-MM-DD

                fetch(`paed-diary/entry/${entryId}/complete`, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': csrf,
                            'Accept': 'application/json'
                        },
                        body: JSON.stringify({
                            klasse_id: klasseSelect.value,
                            completed_at: completedAtDate
                        })
                    })
                    .then(r => r.json())
                    .then(j => {
                        if (j.success) {
                            loadWeek();
                        } else {
                            alert(j.message || 'Fehler');
                            completeBtn.disabled = false;
                        }
                    })
                    .catch(() => {
                        alert('Fehler');
                        completeBtn.disabled = false;
                    });
            }
        });
    }

    if (refreshTasksBtn) {
        refreshTasksBtn.addEventListener('click', () => loadWeek());
    }


    // --- Öffentliche API ---
    return {
        renderTasks,
        updateTaskStudentSelect,
        renderTaskBadgesOnNames // neue API zum Rendern der Namen-Badges
    };
}
