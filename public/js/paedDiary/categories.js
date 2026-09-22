// public/js/paedDiary/categories.js
// AJAX-CRUD für den Kategorie-Verwaltungs-View (/paed-diary/categories/manage)

(function () {
    const csrf = document.querySelector('meta[name="csrf-token"]')?.content ?? '';

    // ── DOM-Referenzen ────────────────────────────────────────────────────────
    const catFeedback     = document.getElementById('catFeedback');
    const globalCatList   = document.getElementById('globalCatList');
    const ownCatList      = document.getElementById('ownCatList');
    const addCatForm      = document.getElementById('addCatForm');
    const addGlobalCatForm = document.getElementById('addGlobalCatForm');

    const colGroupList    = document.getElementById('colGroupList');
    const colGroupFeedback = document.getElementById('colGroupFeedback');

    // ── Hilfsfunktionen ───────────────────────────────────────────────────────
    function escapeHtml(str) {
        return String(str ?? '')
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;');
    }

    function setFeedback(el, msg, type = 'info') {
        if (!el) return;
        const cls = {
            success: 'mb-4 text-sm flex items-center gap-2 px-4 py-2.5 rounded-xl bg-emerald-50 border border-emerald-200 text-emerald-800',
            danger:  'mb-4 text-sm flex items-center gap-2 px-4 py-2.5 rounded-xl bg-red-50 border border-red-200 text-red-800',
            info:    'mb-4 text-sm flex items-center gap-2 px-4 py-2.5 rounded-xl bg-blue-50 border border-blue-200 text-blue-800',
            warning: 'mb-4 text-sm flex items-center gap-2 px-4 py-2.5 rounded-xl bg-amber-50 border border-amber-200 text-amber-800',
        };
        el.className = cls[type] ?? cls.info;
        el.textContent = msg;
        setTimeout(() => {
            if (el.textContent === msg) {
                el.textContent = '';
                el.className = 'mb-4 text-sm hidden';
            }
        }, 4000);
    }

    // ── Kategorien laden & rendern ────────────────────────────────────────────
    function loadCategories() {
        fetch('/paed-diary/categories', { headers: { 'Accept': 'application/json' } })
            .then(r => r.json())
            .then(data => renderCategories(data.categories ?? []))
            .catch(() => setFeedback(catFeedback, 'Fehler beim Laden der Kategorien', 'danger'));
    }

    function renderCategories(categories) {
        const globals = categories.filter(c => c.is_global);
        const own     = categories.filter(c => !c.is_global);

        if (globalCatList) {
            if (!globals.length) {
                globalCatList.innerHTML = '<li class="text-gray-400 text-sm py-3 px-3 text-center italic">Keine globalen Kategorien vorhanden</li>';
            } else {
                globalCatList.innerHTML = globals.map(cat => `
                    <li class="flex items-center justify-between px-3 py-2 rounded-lg bg-white border border-gray-200 hover:border-indigo-200 transition-all" data-id="${cat.id}">
                        <span class="text-sm text-gray-700 flex items-center gap-2">
                            ${escapeHtml(cat.name)}
                            <span class="text-xs bg-indigo-100 text-indigo-700 px-1.5 py-0.5 rounded-full font-medium leading-none">global</span>
                        </span>
                        ${cat.can_edit ? `
                        <span class="flex gap-1 shrink-0">
                            <button type="button" class="rename-global-cat w-7 h-7 flex items-center justify-center rounded-lg hover:bg-indigo-50 text-indigo-400 hover:text-indigo-700 transition-colors" data-id="${cat.id}" data-name="${escapeHtml(cat.name)}" title="Umbenennen">
                                <i class="fas fa-edit text-xs"></i>
                            </button>
                            <button type="button" class="delete-global-cat w-7 h-7 flex items-center justify-center rounded-lg hover:bg-red-50 text-red-400 hover:text-red-600 transition-colors" data-id="${cat.id}" data-name="${escapeHtml(cat.name)}" title="Löschen">
                                <i class="fas fa-trash text-xs"></i>
                            </button>
                        </span>` : ''}
                    </li>`).join('');
            }
        }

        if (ownCatList) {
            if (!own.length) {
                ownCatList.innerHTML = '<li class="text-gray-400 text-sm py-3 px-3 text-center italic">Noch keine eigenen Kategorien erstellt</li>';
            } else {
                ownCatList.innerHTML = own.map(cat => `
                    <li class="flex items-center justify-between px-3 py-2 rounded-lg bg-white border border-gray-200 hover:border-emerald-200 transition-all" data-id="${cat.id}">
                        <span class="text-sm text-gray-700">${escapeHtml(cat.name)}</span>
                        <span class="flex gap-1 shrink-0">
                            <button type="button" class="rename-own-cat w-7 h-7 flex items-center justify-center rounded-lg hover:bg-indigo-50 text-indigo-400 hover:text-indigo-700 transition-colors" data-id="${cat.id}" data-name="${escapeHtml(cat.name)}" title="Umbenennen">
                                <i class="fas fa-edit text-xs"></i>
                            </button>
                            <button type="button" class="delete-own-cat w-7 h-7 flex items-center justify-center rounded-lg hover:bg-red-50 text-red-400 hover:text-red-600 transition-colors" data-id="${cat.id}" data-name="${escapeHtml(cat.name)}" title="Löschen">
                                <i class="fas fa-trash text-xs"></i>
                            </button>
                        </span>
                    </li>`).join('');
            }
        }
    }

    // ── Eigene Kategorie erstellen ────────────────────────────────────────────
    if (addCatForm) {
        addCatForm.addEventListener('submit', e => {
            e.preventDefault();
            const nameInput = addCatForm.querySelector('input[name="name"]');
            const name = nameInput.value.trim();
            if (!name) return;

            fetch('/paed-diary/categories', {
                method: 'POST',
                headers: { 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json', 'Content-Type': 'application/json' },
                body: JSON.stringify({ name }),
            })
            .then(r => r.json())
            .then(j => {
                if (j.success) {
                    nameInput.value = '';
                    setFeedback(catFeedback, 'Kategorie erstellt', 'success');
                    loadCategories();
                } else {
                    setFeedback(catFeedback, j.message ?? 'Fehler', 'danger');
                }
            })
            .catch(() => setFeedback(catFeedback, 'Fehler beim Erstellen', 'danger'));
        });
    }

    // ── Globale Kategorie erstellen ───────────────────────────────────────────
    if (addGlobalCatForm) {
        addGlobalCatForm.addEventListener('submit', e => {
            e.preventDefault();
            const nameInput = addGlobalCatForm.querySelector('input[name="name"]');
            const name = nameInput.value.trim();
            if (!name) return;

            fetch('/paed-diary/categories/global', {
                method: 'POST',
                headers: { 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json', 'Content-Type': 'application/json' },
                body: JSON.stringify({ name }),
            })
            .then(r => r.json())
            .then(j => {
                if (j.success) {
                    nameInput.value = '';
                    setFeedback(catFeedback, 'Globale Kategorie erstellt', 'success');
                    loadCategories();
                } else {
                    setFeedback(catFeedback, j.message ?? 'Fehler', 'danger');
                }
            })
            .catch(() => setFeedback(catFeedback, 'Fehler beim Erstellen', 'danger'));
        });
    }

    // ── Event-Delegation: Eigene Kategorien ──────────────────────────────────
    if (ownCatList) {
        ownCatList.addEventListener('click', e => {
            const renameBtn = e.target.closest('.rename-own-cat');
            const deleteBtn = e.target.closest('.delete-own-cat');

            if (renameBtn) {
                const id   = renameBtn.dataset.id;
                const name = renameBtn.dataset.name;
                const newName = prompt('Neuer Name:', name);
                if (!newName || newName.trim() === '' || newName.trim() === name) return;

                fetch(`/paed-diary/categories/${id}/rename`, {
                    method: 'PUT',
                    headers: { 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json', 'Content-Type': 'application/json' },
                    body: JSON.stringify({ name: newName.trim() }),
                })
                .then(r => r.json())
                .then(j => {
                    if (j.success) {
                        setFeedback(catFeedback, 'Kategorie umbenannt', 'success');
                        loadCategories();
                    } else {
                        setFeedback(catFeedback, j.message ?? 'Fehler', 'danger');
                    }
                })
                .catch(() => setFeedback(catFeedback, 'Fehler beim Umbenennen', 'danger'));
            }

            if (deleteBtn) {
                const id   = deleteBtn.dataset.id;
                const name = deleteBtn.dataset.name;
                if (!confirm(`Kategorie „${name}" wirklich löschen?\n\nAlle Einträge mit dieser Kategorie werden auf „keine Kategorie" gesetzt.`)) return;

                fetch(`/paed-diary/categories/${id}`, {
                    method: 'DELETE',
                    headers: { 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json' },
                })
                .then(r => r.json())
                .then(j => {
                    if (j.success) {
                        setFeedback(catFeedback, 'Kategorie gelöscht', 'success');
                        loadCategories();
                    } else {
                        setFeedback(catFeedback, j.message ?? 'Fehler', 'danger');
                    }
                })
                .catch(() => setFeedback(catFeedback, 'Fehler beim Löschen', 'danger'));
            }
        });
    }

    // ── Event-Delegation: Globale Kategorien ─────────────────────────────────
    if (globalCatList) {
        globalCatList.addEventListener('click', e => {
            const renameBtn = e.target.closest('.rename-global-cat');
            const deleteBtn = e.target.closest('.delete-global-cat');

            if (renameBtn) {
                const id   = renameBtn.dataset.id;
                const name = renameBtn.dataset.name;
                const newName = prompt('Neuer Name:', name);
                if (!newName || newName.trim() === '' || newName.trim() === name) return;

                fetch(`/paed-diary/categories/global/${id}`, {
                    method: 'PUT',
                    headers: { 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json', 'Content-Type': 'application/json' },
                    body: JSON.stringify({ name: newName.trim() }),
                })
                .then(r => r.json())
                .then(j => {
                    if (j.success) {
                        setFeedback(catFeedback, 'Globale Kategorie umbenannt', 'success');
                        loadCategories();
                    } else {
                        setFeedback(catFeedback, j.message ?? 'Fehler', 'danger');
                    }
                })
                .catch(() => setFeedback(catFeedback, 'Fehler beim Umbenennen', 'danger'));
            }

            if (deleteBtn) {
                const id   = deleteBtn.dataset.id;
                const name = deleteBtn.dataset.name;
                if (!confirm(`Globale Kategorie „${name}" wirklich löschen?\n\nAlle Einträge und Spalten mit dieser Kategorie werden auf „keine Kategorie" gesetzt.`)) return;

                fetch(`/paed-diary/categories/global/${id}`, {
                    method: 'DELETE',
                    headers: { 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json' },
                })
                .then(r => r.json())
                .then(j => {
                    if (j.success) {
                        setFeedback(catFeedback, 'Globale Kategorie gelöscht', 'success');
                        loadCategories();
                    } else {
                        setFeedback(catFeedback, j.message ?? 'Fehler', 'danger');
                    }
                })
                .catch(() => setFeedback(catFeedback, 'Fehler beim Löschen', 'danger'));
            }
        });
    }

    // ── Spaltengruppen laden & rendern ────────────────────────────────────────
    function loadColumnGroups() {
        if (!colGroupList) return;
        fetch('/paed-diary/column-groups', { headers: { 'Accept': 'application/json' } })
            .then(r => r.json())
            .then(data => renderColumnGroups(data.groups ?? []))
            .catch(() => setFeedback(colGroupFeedback, 'Fehler beim Laden der Spaltengruppen', 'danger'));
    }

    function renderColumnGroups(groups) {
        if (!colGroupList) return;
        if (!groups.length) {
            colGroupList.innerHTML = '<tr><td colspan="3" class="text-gray-400 text-sm text-center py-8 italic">Keine Spaltengruppen vorhanden</td></tr>';
            return;
        }
        colGroupList.innerHTML = groups.map(g => `
            <tr data-group="${escapeHtml(g.name)}" class="hover:bg-gray-50 transition-colors">
                <td class="px-4 py-3 text-sm text-gray-700">${escapeHtml(g.name)}</td>
                <td class="px-4 py-3 text-center">
                    <span class="inline-flex items-center justify-center bg-gray-100 text-gray-600 text-xs font-semibold rounded-full w-7 h-7">${g.count}</span>
                </td>
                <td class="px-4 py-3 text-right">
                    <button type="button" class="rename-group inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-medium bg-indigo-50 hover:bg-indigo-100 text-indigo-700 rounded-lg transition-colors" data-name="${escapeHtml(g.name)}" title="Umbenennen">
                        <i class="fas fa-edit"></i> Umbenennen
                    </button>
                </td>
            </tr>`).join('');
    }

    // ── Event-Delegation: Spaltengruppen ─────────────────────────────────────
    if (colGroupList) {
        colGroupList.addEventListener('click', e => {
            const renameBtn = e.target.closest('.rename-group');
            if (!renameBtn) return;

            const oldName = renameBtn.dataset.name;
            const newName = prompt(`Spaltengruppe „${oldName}" umbenennen in:`, oldName);
            if (!newName || newName.trim() === '' || newName.trim() === oldName) return;

            fetch('/paed-diary/column-groups/rename', {
                method: 'POST',
                headers: { 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json', 'Content-Type': 'application/json' },
                body: JSON.stringify({ old_name: oldName, new_name: newName.trim() }),
            })
            .then(r => r.json())
            .then(j => {
                if (j.success) {
                    setFeedback(colGroupFeedback, `${j.updated} Spalte(n) umbenannt`, 'success');
                    loadColumnGroups();
                } else {
                    setFeedback(colGroupFeedback, j.message ?? 'Fehler', 'danger');
                }
            })
            .catch(() => setFeedback(colGroupFeedback, 'Fehler beim Umbenennen', 'danger'));
        });
    }

    // ── Initial laden ─────────────────────────────────────────────────────────
    loadCategories();
    loadColumnGroups();
})();





