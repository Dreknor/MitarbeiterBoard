// public/js/paedDiary/tasks.js

function initializeTasksModule(dependencies) {
    const {
        csrf,
        klasseSelect,
        groupSelect,
        escapeHtml,
        trimText,
        loadWeek,
        getCache
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
        updateTaskStudentSelect
    };
}
