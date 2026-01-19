// public/js/paedDiary/columns.js

function initializeColumnsModule(dependencies) {
    const {
        csrf,
        klasseSelect,
        groupSelect,
        currentWeekStart,
        formatDate,
        escapeHtml,
        loadWeek
    } = dependencies;

    const manageColumnsBtn = document.getElementById('manageColumnsBtn');
    const columnsCardWrapper = document.getElementById('columnsCardWrapper');
    const columnsCloseBtn = document.getElementById('columnsCloseBtn');
    const columnsList = document.getElementById('columnsList');
    const columnsFeedback = document.getElementById('columnsFeedback');
    const addColumnForm = document.getElementById('addColumnForm');
    const diaryBody = document.getElementById('diaryBody');

    // Kategorien-Verwaltung Elemente
    const categoriesList = document.getElementById('categoriesList');
    const categoriesFeedback = document.getElementById('categoriesFeedback');
    const showCategoriesToggle = document.getElementById('showCategoriesToggle');

    let columnsAllCache = [];
    let debounceTimers = {};
    let showDeactivatedColumns = false;
    let categoriesCache = [];

    function setColumnsFeedback(msg, type = 'info') {
        if (!columnsFeedback) return;
        const colors = {
            info: '#17a2b8',
            success: '#28a745',
            warning: '#ffc107',
            danger: '#dc3545'
        };
        columnsFeedback.innerHTML = `<span style="color:${colors[type]||'#6c757d'}">${escapeHtml(msg)}</span>`;
    }

    function setCategoriesFeedback(msg, type = 'info') {
        if (!categoriesFeedback) return;
        const colors = {
            info: '#17a2b8',
            success: '#28a745',
            warning: '#ffc107',
            danger: '#dc3545'
        };
        categoriesFeedback.innerHTML = `<span style="color:${colors[type]||'#6c757d'}">${escapeHtml(msg)}</span>`;
    }

    // Kategorien laden
    function loadCategories() {
        fetch('paed-diary/categories', {
            headers: { 'Accept': 'application/json' }
        })
        .then(r => {
            if (!r.ok) throw new Error('Failed');
            return r.json();
        })
        .then(data => {
            categoriesCache = data.categories || [];
            renderCategoriesList();
        })
        .catch(() => setCategoriesFeedback('Fehler beim Laden der Kategorien', 'danger'));
    }

    // Kategorien-Liste rendern
    function renderCategoriesList() {
        if (!categoriesList) return;
        if (!categoriesCache.length) {
            categoriesList.innerHTML = '<span class="text-muted small">Keine Kategorien vorhanden</span>';
            return;
        }

        categoriesList.innerHTML = categoriesCache.map(cat => {
            if (!cat.can_edit) {
                return `<div class="badge badge-secondary mr-1 mb-1">${escapeHtml(cat.name)} <span class="small">(global)</span></div>`;
            }
            return `<div class="badge badge-primary mr-1 mb-1 d-inline-flex align-items-center">
                <span class="category-name" data-id="${cat.id}">${escapeHtml(cat.name)}</span>
                <button type="button" class="btn btn-link btn-sm p-0 ml-1 rename-category" data-id="${cat.id}" title="Umbenennen" style="font-size:0.7rem;"><i class="fas fa-edit"></i></button>
                <button type="button" class="btn btn-link btn-sm p-0 ml-1 delete-category" data-id="${cat.id}" title="Löschen" style="font-size:0.7rem;color:#dc3545;"><i class="fas fa-trash"></i></button>
            </div>`;
        }).join('');
    }

    // Kategorie umbenennen
    function renameCategory(categoryId) {
        const cat = categoriesCache.find(c => String(c.id) === String(categoryId));
        if (!cat) return;

        const newName = prompt('Neuer Name für Kategorie:', cat.name);
        if (!newName || newName.trim() === '' || newName === cat.name) return;

        fetch(`paed-diary/categories/${categoryId}/rename`, {
            method: 'PUT',
            headers: {
                'X-CSRF-TOKEN': csrf,
                'Accept': 'application/json',
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({ name: newName.trim() })
        })
        .then(r => {
            if (!r.ok) throw new Error('Failed');
            return r.json();
        })
        .then(j => {
            if (j.success) {
                setCategoriesFeedback('Kategorie umbenannt', 'success');
                loadCategories();
                loadAllColumns();
                loadWeek();
            } else {
                setCategoriesFeedback(j.message || 'Fehler beim Umbenennen', 'danger');
            }
        })
        .catch(() => setCategoriesFeedback('Fehler beim Umbenennen', 'danger'));
    }

    // Kategorie löschen
    function deleteCategory(categoryId) {
        const cat = categoriesCache.find(c => String(c.id) === String(categoryId));
        if (!cat) return;

        if (!confirm(`Kategorie "${cat.name}" wirklich löschen?\n\nAlle Einträge und Spalten mit dieser Kategorie werden auf "keine Kategorie" gesetzt.`)) return;

        fetch(`paed-diary/categories/${categoryId}`, {
            method: 'DELETE',
            headers: {
                'X-CSRF-TOKEN': csrf,
                'Accept': 'application/json'
            }
        })
        .then(r => {
            if (!r.ok) throw new Error('Failed');
            return r.json();
        })
        .then(j => {
            if (j.success) {
                setCategoriesFeedback('Kategorie gelöscht', 'success');
                loadCategories();
                loadAllColumns();
                loadWeek();
            } else {
                setCategoriesFeedback(j.message || 'Fehler beim Löschen', 'danger');
            }
        })
        .catch(() => setCategoriesFeedback('Fehler beim Löschen', 'danger'));
    }

    // Toggle für Kategorieanzeige
    if (showCategoriesToggle) {
        showCategoriesToggle.addEventListener('change', function() {
            const showCategories = this.checked;

            fetch('paed-diary/settings/show-categories', {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': csrf,
                    'Accept': 'application/json',
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({ show_column_categories: showCategories })
            })
            .then(r => {
                if (!r.ok) throw new Error('Failed');
                return r.json();
            })
            .then(j => {
                if (j.success) {
                    setCategoriesFeedback(showCategories ? 'Kategorien werden angezeigt' : 'Kategorien ausgeblendet', 'success');
                    loadWeek(); // Reload to apply the setting
                }
            })
            .catch(() => setCategoriesFeedback('Fehler beim Speichern', 'danger'));
        });
    }

    // Event-Listener für Kategorien-Aktionen
    if (categoriesList) {
        categoriesList.addEventListener('click', e => {
            const renameBtn = e.target.closest('.rename-category');
            const deleteBtn = e.target.closest('.delete-category');

            if (renameBtn) {
                const id = renameBtn.dataset.id;
                renameCategory(id);
            } else if (deleteBtn) {
                const id = deleteBtn.dataset.id;
                deleteCategory(id);
            }
        });
    }

    function loadAllColumns() {
        if (groupSelect.value) {
            return;
        }
        const p = new URLSearchParams({
            klasse_id: klasseSelect.value
        });
        fetch('paed-diary/columns/all?' + p.toString(), {
                headers: {
                    'Accept': 'application/json'
                }
            })
            .then(r => {
                if (!r.ok) throw new Error('Failed');
                return r.json();
            })
            .then(data => {
                columnsAllCache = data.columns || [];
                renderColumnsList();
                populateColumnCategoryControls();
            })
            .catch(() => setColumnsFeedback('Fehler beim Laden der Spalten', 'danger'));
    }

    function populateColumnCategoryControls() {
        if (!addColumnForm) return;
        let sel = addColumnForm.querySelector('select[name="category_select"]');
        let newInput = addColumnForm.querySelector('input[name="new_category"]');
        if (!sel) {
            sel = document.createElement('select');
            sel.name = 'category_select';
            sel.className = 'form-control form-control-sm mr-1 mb-1';
            sel.innerHTML = '<option value="">-- Kategorie (neu oder wählen) --</option>';
            addColumnForm.insertBefore(sel, addColumnForm.children[1]);
        }
        if (!newInput) {
            newInput = document.createElement('input');
            newInput.type = 'text';
            newInput.name = 'new_category';
            newInput.placeholder = 'Neue Kategorie (optional)';
            newInput.className = 'form-control form-control-sm mr-1 mb-1';
            addColumnForm.insertBefore(newInput, addColumnForm.children[2]);
        }
        const cats = Array.from(new Set(columnsAllCache.map(c => c.category).filter(Boolean))).sort((a, b) => a.localeCompare(b, 'de'));
        sel.innerHTML = '<option value="">-- Keine / Neue --</option>' + cats.map(c => `<option value="${escapeHtml(c)}">${escapeHtml(c)}</option>`).join('');
    }

    function ensureDeactivatedToggle() {
        if (!columnsList) return;
        let toggle = document.getElementById('showDeactivatedColumns');
        if (!toggle) {
            toggle = document.createElement('input');
            toggle.type = 'checkbox';
            toggle.id = 'showDeactivatedColumns';
            toggle.className = 'mr-2';
            toggle.style.verticalAlign = 'middle';
            const label = document.createElement('label');
            label.htmlFor = 'showDeactivatedColumns';
            label.className = 'small mr-3';
            label.textContent = 'Deaktivierte anzeigen';
            columnsList.parentNode.insertBefore(toggle, columnsList);
            columnsList.parentNode.insertBefore(label, columnsList);
            toggle.addEventListener('change', function() {
                showDeactivatedColumns = this.checked;
                renderColumnsList();
            });
        }
        toggle.checked = showDeactivatedColumns;
    }

    function renderColumnsList() {
        if (!columnsList) return;
        ensureDeactivatedToggle();
        if (!columnsAllCache.length) {
            columnsList.innerHTML = '<span class="text-muted small">Keine Spalten</span>';
            return;
        }

        const grouped = {};
        columnsAllCache.forEach(c => {
            const cat = c.category || 'Unkategorisiert';
            (grouped[cat] = grouped[cat] || []).push(c);
        });
        const cats = Object.keys(grouped).sort((a, b) => {
            if (a === 'Unkategorisiert') return 1;
            if (b === 'Unkategorisiert') return -1;
            return a.localeCompare(b, 'de');
        });

        const allCats = cats.slice();
        const optionsHtml = allCats.map(c => `<option value="${escapeHtml(c)}">${escapeHtml(c)}</option>`).join('');

        columnsList.innerHTML = cats.map(cat => {
            const cols = grouped[cat].filter(c => showDeactivatedColumns || !c.deactivated_from);
            if (!cols.length) return '';
            const colsHtml = cols.map(c => {
                const deac = !!c.deactivated_from;
                const sel = `<select class="col-cat-select form-control form-control-sm" data-id="${c.id}"><option value="">-- Keine --</option>${optionsHtml}</select>`;
                return `<div class="column-chip ${deac?'deactivated':''}" data-id="${c.id}" title="${escapeHtml(c.name)} (${c.type})${deac?` deaktiviert ab ${c.deactivated_from}`:''}"><div class="d-flex align-items-center"><span class="mr-2">${escapeHtml(c.name)}</span>${sel}${!deac?`<button type="button" class="remove-col btn btn-link btn-sm ml-2" title="Deaktivieren">&times;</button>`:`<button type="button" class="restore restore-col btn btn-link btn-sm ml-2" title="Reaktivieren">&#8634;</button>`}</div></div>`;
            }).join('');
            return `<div class="column-category"><div class="small text-primary font-weight-bold mb-1">${escapeHtml(cat)}</div><div class="column-category-list d-flex flex-wrap">${colsHtml}</div></div>`;
        }).join('');

        document.querySelectorAll('.col-cat-select').forEach(sel => {
            const id = sel.dataset.id;
            const col = columnsAllCache.find(c => String(c.id) === String(id));
            if (col && col.category) sel.value = col.category;
        });
    }

    function saveColumnValue(colId, stuId, date, value) {
        return fetch('paed-diary/column/value', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrf,
                    'Accept': 'application/json'
                },
                body: JSON.stringify({
                    column_id: colId,
                    schueler_id: stuId,
                    date: date,
                    value: value
                })
            })
            .then(r => {
                if (!r.ok) throw new Error('fail');
                return r.json();
            })
            .then(data => {
                // Update local cache if needed by the main module
                if (dependencies.cache.column_values) {
                    if (!dependencies.cache.column_values[colId]) dependencies.cache.column_values[colId] = {};
                    if (!dependencies.cache.column_values[colId][stuId]) dependencies.cache.column_values[colId][stuId] = {};
                    if (value === '') {
                        delete dependencies.cache.column_values[colId][stuId][date];
                    } else {
                        dependencies.cache.column_values[colId][stuId][date] = value;
                    }
                }
                return data;
            });
    }


    // Event Listeners
    if (manageColumnsBtn) {
        manageColumnsBtn.addEventListener('click', () => {
            if (groupSelect.value) {
                return;
            }
            columnsCardWrapper.classList.toggle('d-none');
            if (!columnsCardWrapper.classList.contains('d-none')) {
                loadAllColumns();
                loadCategories();
                // Setze Toggle-Status basierend auf Cache
                const cache = dependencies.getCache();
                if (showCategoriesToggle) {
                    showCategoriesToggle.checked = cache.show_column_categories === true;
                }
            }
        });
    }

    if (columnsCloseBtn) {
        columnsCloseBtn.addEventListener('click', () => columnsCardWrapper.classList.add('d-none'));
    }

    if (columnsList) {
        columnsList.addEventListener('click', e => {
            const rem = e.target.closest('.remove-col');
            const res = e.target.closest('.restore-col');
            if (rem) {
                const chip = rem.closest('.column-chip');
                const id = chip.dataset.id;
                const col = columnsAllCache.find(c => String(c.id) === String(id));
                if (!col) return;
                const ws = formatDate(currentWeekStart);
                if (!confirm(`Spalte "${col.name}" ab dieser Woche deaktivieren?`)) return;
                fetch(`paed-diary/column/${id}?week_start=${encodeURIComponent(ws)}&klasse_id=${encodeURIComponent(klasseSelect.value)}`, {
                        method: 'DELETE',
                        headers: {
                            'X-CSRF-TOKEN': csrf,
                            'Accept': 'application/json'
                        }
                    }).then(r => r.json())
                    .then(j => {
                        if (j.success) {
                            setColumnsFeedback('Spalte deaktiviert', 'warning');
                            loadWeek();
                            loadAllColumns();
                        }
                    });
            } else if (res) {
                const chip = res.closest('.column-chip');
                const id = chip.dataset.id;
                fetch(`paed-diary/column/${id}/restore`, {
                        method: 'POST',
                        headers: {
                            'X-CSRF-TOKEN': csrf,
                            'Accept': 'application/json'
                        }
                    }).then(r => r.json())
                    .then(j => {
                        if (j.success) {
                            setColumnsFeedback('Spalte reaktiviert', 'success');
                            loadWeek();
                            loadAllColumns();
                        }
                    });
            }
        });

        columnsList.addEventListener('change', e => {
            const sel = e.target.closest('.col-cat-select');
            if (!sel) return;
            const id = sel.dataset.id;
            const category = sel.value || null;
            sel.disabled = true;
            fetch(`paed-diary/column/${id}/category`, {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': csrf,
                    'Accept': 'application/json'
                },
                body: new URLSearchParams({
                    category: category
                })
            }).then(r => r.json()).then(j => {
                if (j.success) {
                    setColumnsFeedback('Kategorie aktualisiert', 'success');
                    loadAllColumns();
                } else {
                    setColumnsFeedback(j.message || 'Fehler', 'danger');
                }
            }).catch(() => setColumnsFeedback('Fehler beim Speichern', 'danger')).finally(() => sel.disabled = false);
        });
    }

    if (addColumnForm) {
        addColumnForm.addEventListener('submit', e => {
            e.preventDefault();
            if (groupSelect.value) return;
            const name = addColumnForm.querySelector('input[name="name"]').value.trim();
            const type = addColumnForm.querySelector('select[name="type"]').value;
            const sel = addColumnForm.querySelector('select[name="category_select"]');
            const newCatInput = addColumnForm.querySelector('input[name="new_category"]');
            let category = '';
            if (newCatInput && newCatInput.value.trim()) {
                category = newCatInput.value.trim();
            } else if (sel && sel.value) {
                category = sel.value;
            }
            const fd = new FormData();
            fd.append('name', name);
            if (type) fd.append('type', type);
            fd.append('klasse_id', klasseSelect.value);
            if (category) fd.append('category', category);
            fetch('paed-diary/column', {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': csrf,
                        'Accept': 'application/json'
                    },
                    body: fd
                }).then (r => r.json())
                .then(j => {
                    if (j.success) {
                        addColumnForm.reset();
                        setColumnsFeedback('Spalte angelegt', 'success');
                        loadWeek();
                        loadAllColumns();
                    } else {
                        setColumnsFeedback(j.message || 'Fehler', 'danger');
                    }
                }).catch(() => setColumnsFeedback('Fehler beim Anlegen', 'danger'));
        });
    }

    if (diaryBody) {
        diaryBody.addEventListener('input', e => {
            const inp = e.target.closest('.col-val-input');
            if (!inp) return;
            const key = `${inp.dataset.col}-${inp.dataset.stu}-${inp.dataset.date}`;
            clearTimeout(debounceTimers[key]);
            const val = inp.value.trim();
            debounceTimers[key] = setTimeout(() => {
                saveColumnValue(inp.dataset.col, inp.dataset.stu, inp.dataset.date, val)
                    .catch(() => {
                        inp.classList.add('border-danger');
                        setTimeout(() => inp.classList.remove('border-danger'), 1200);
                    });
            }, 400);
        });

        diaryBody.addEventListener('click', e => {
            const btn = e.target.closest('.bool-btn');
            if (!btn) return;
            const newVal = btn.dataset.value === '1' ? '' : '1';
            btn.disabled = true;
            saveColumnValue(btn.dataset.col, btn.dataset.stu, btn.dataset.date, newVal)
                .then(() => {
                    btn.dataset.value = newVal;
                    btn.classList.toggle('btn-success', newVal === '1');
                    btn.classList.toggle('btn-outline-secondary', newVal !== '1');
                })
                .catch(() => {
                    btn.classList.add('btn-danger');
                    setTimeout(() => btn.classList.remove('btn-danger'), 1000);
                })
                .finally(() => btn.disabled = false);
        });
    }

    // Public API for the module
    return {
        renderColumnInputs(stuId, date) {
            const cache = dependencies.getCache(); // Use the getter function
            if (!cache.columns || !cache.columns.length) return '';

            const student = cache.schueler.find(s => String(s.id) === String(stuId));
            if (!student) return '';

            const columnsForStudent = cache.is_group ?
                cache.columns.filter(col => String(col.klasse_id) === String(student.klasse_id)) :
                cache.columns;

            // Prüfen ob Kategorien angezeigt werden sollen
            const showCategories = cache.show_column_categories === true;

            if (!showCategories) {
                // Ohne Kategorien: Alle Spalten direkt anzeigen (wie bisher)
                let html = '';
                columnsForStudent.forEach(col => {
                    const val = (cache.column_values?.[col.id]?.[stuId]?.[date]) || '';
                    if (col.type === 'boolean') {
                        const active = val === '1';
                        html += `<button type="button" class="btn btn-xs bool-btn ${active?'btn-success':'btn-outline-secondary'}" data-col="${col.id}" data-stu="${stuId}" data-date="${date}" data-value="${active?'1':''}" data-name="${escapeHtml(col.name)}" title="${escapeHtml(col.name)}">${escapeHtml(col.name)}</button>`;
                    } else {
                        html += `<input type="text" maxlength="255" class="form-control form-control-sm col-val-input" data-col="${col.id}" data-stu="${stuId}" data-date="${date}" value="${escapeHtml(val)}" placeholder="${escapeHtml(col.name)}" title="${escapeHtml(col.name)}">`;
                    }
                });
                return html;
            }

            // Mit Kategorien: Gruppieren und mit Kategorie-Labels anzeigen
            const byCat = {};
            columnsForStudent.forEach(col => {
                const cat = col.category || 'Unkategorisiert';
                (byCat[cat] = byCat[cat] || []).push(col);
            });

            const cats = Object.keys(byCat).sort((a, b) => {
                if (a === 'Unkategorisiert') return 1;
                if (b === 'Unkategorisiert') return -1;
                return a.localeCompare(b, 'de');
            });

            let html = '';
            cats.forEach(cat => {
                html += `<div class="col-cat-group" data-cat="${escapeHtml(cat)}"><div class="col-cat-label small text-muted mb-1">${escapeHtml(cat)}</div>`;
                byCat[cat].forEach(col => {
                    const val = (cache.column_values?.[col.id]?.[stuId]?.[date]) || '';
                    if (col.type === 'boolean') {
                        const active = val === '1';
                        html += `<button type="button" class="btn btn-xs bool-btn ${active?'btn-success':'btn-outline-secondary'}" data-col="${col.id}" data-stu="${stuId}" data-date="${date}" data-value="${active?'1':''}" data-name="${escapeHtml(col.name)}" title="${escapeHtml(col.name)}">${escapeHtml(col.name)}</button>`;
                    } else {
                        html += `<input type="text" maxlength="255" class="form-control form-control-sm col-val-input" data-col="${col.id}" data-stu="${stuId}" data-date="${date}" value="${escapeHtml(val)}" placeholder="${escapeHtml(col.name)}" title="${escapeHtml(col.name)}">`;
                    }
                });
                html += `</div>`;
            });
            return html;
        }
    };
}
