// public/js/paedDiary/appointments.js

function initializeAppointmentsModule(dependencies) {
    const {
        csrf,
        cache,
        groupSelect,
        klasseSelect,
        formatDate,
        addDays,
        escapeHtml,
        trimText,
        loadWeek,
        getCache // Funktion um den aktuellen Cache zu erhalten
    } = dependencies;

    // --- DOM-Elemente ---
    const appointmentModal = $('#appointmentModal');
    const appointmentForm = document.getElementById('appointmentForm');
    const appointmentIdInput = document.getElementById('appointmentId');
    const appointmentTitleInput = document.getElementById('appointmentTitle');
    const appointmentDescriptionInput = document.getElementById('appointmentDescription');
    const appointmentStartDateInput = document.getElementById('appointmentStartDate');
    const appointmentStartTimeInput = document.getElementById('appointmentStartTime');
    const appointmentEndTimeInput = document.getElementById('appointmentEndTime');
    const appointmentIsRecurringInput = document.getElementById('appointmentIsRecurring');
    const appointmentRecurringTypeSelect = document.getElementById('appointmentRecurringType');
    const appointmentRecurringIntervalInput = document.getElementById('appointmentRecurringInterval');
    const appointmentRecurringEndDateInput = document.getElementById('appointmentRecurringEndDate');
    const appointmentDeleteBtn = document.getElementById('appointmentDeleteBtn');
    const appointmentPauseBtn = document.getElementById('appointmentPauseBtn');
    const appointmentStatus = document.getElementById('appointmentStatus');
    const appointmentFeedback = document.getElementById('appointmentFeedback');
    const appointmentModalTitle = document.getElementById('appointmentModalTitle');
    const openAppointmentModalBtn = document.getElementById('openAppointmentModal');
    const recurringOptions = document.getElementById('recurringOptions');
    const appointmentStudentsBox = document.getElementById('appointmentStudentsBox');
    const appointmentSchuelerList = document.getElementById('appointmentSchuelerList');

    // --- Hilfsfunktionen ---

    // Formatiert Zeit von "HH:MM:SS" zu "HH:MM" oder von ISO 8601 DateTime zu "HH:MM" (lokale Zeitzone)
    function formatTime(timeStr) {
        if (!timeStr) return '';
        if (timeStr.includes('T')) {
            try {
                const date = new Date(timeStr);
                if (!isNaN(date.getTime())) {
                    const hours = String(date.getHours()).padStart(2, '0');
                    const minutes = String(date.getMinutes()).padStart(2, '0');
                    return `${hours}:${minutes}`;
                }
            } catch (e) {
                const timePart = timeStr.split('T')[1];
                if (timePart && timePart.includes(':')) {
                    const parts = timePart.split(':');
                    if (parts.length >= 2) return `${parts[0]}:${parts[1]}`;
                }
            }
        }
        if (timeStr.includes(':')) {
            const parts = timeStr.split(':');
            if (parts.length >= 2) return `${parts[0]}:${parts[1]}`;
        }
        return timeStr;
    }

    function setAppointmentFeedback(msg, type = 'info') {
        if (!appointmentFeedback) return;
        const alertClass = `alert-${type}`;
        appointmentFeedback.innerHTML = `<div class="alert ${alertClass} small p-2 mb-0">${escapeHtml(msg)}</div>`;
        if (msg === '') {
            appointmentFeedback.innerHTML = '';
        }
    }

    // --- Kernfunktionen ---

    function loadAppointments(currentWeekStart) {
        const params = new URLSearchParams({
            start_date: formatDate(currentWeekStart),
            end_date: formatDate(addDays(currentWeekStart, 6))
        });

        if (groupSelect && groupSelect.value) {
            params.append('group_id', groupSelect.value);
        } else {
            params.append('klasse_id', klasseSelect.value);
        }

        return fetch('paed-diary/appointments?' + params.toString(), {
                headers: {
                    'Accept': 'application/json'
                }
            })
            .then(r => r.json())
            .then(data => {
                const localCache = getCache();
                localCache.appointments = data.appointments || [];
                renderAppointments();
            })
            .catch(() => {
                const localCache = getCache();
                localCache.appointments = [];
            });
    }

    function renderAppointments() {
        const localCache = getCache();
        // Zuerst alle bestehenden Termine-Anzeigen entfernen
        document.querySelectorAll('.day-appointments, .student-appointments').forEach(el => el.remove());

        localCache.days.forEach(day => {
            const dayAppointments = localCache.appointments.filter(app => app.date === day.date);
            const dayHeader = document.querySelector(`th[data-date="${day.date}"]`);

            if (dayAppointments.length > 0) {
                const classGroupAppointments = [];
                const individualAppointments = [];

                dayAppointments.forEach(appointment => {
                    const schuelerArray = Array.isArray(appointment.schueler) ? appointment.schueler : [];
                    const klassenArray = Array.isArray(appointment.klassen) ? appointment.klassen : [];
                    const groupsArray = Array.isArray(appointment.groups) ? appointment.groups : [];

                    const hasOnlyIndividualStudents = schuelerArray.length > 0 &&
                        klassenArray.length === 0 &&
                        groupsArray.length === 0;

                    if (hasOnlyIndividualStudents) {
                        individualAppointments.push(appointment);
                    } else {
                        classGroupAppointments.push(appointment);
                    }
                });

                if (dayHeader && classGroupAppointments.length > 0) {
                    const appointmentsDiv = document.createElement('div');
                    appointmentsDiv.className = 'day-appointments mt-1';
                    appointmentsDiv.style.fontSize = '0.7rem';

                    classGroupAppointments.forEach(appointment => {
                        const appointmentSpan = document.createElement('div');
                        appointmentSpan.className = 'appointment-item bg-warning text-dark px-1 mb-1 rounded';
                        appointmentSpan.style.cursor = 'pointer';
                        appointmentSpan.title = appointment.description || appointment.title;

                        let timeText = '';
                        if (appointment.start_time) {
                            timeText = formatTime(appointment.start_time);
                            if (appointment.end_time) {
                                timeText += ` - ${formatTime(appointment.end_time)}`;
                            }
                            timeText += ' ';
                        }

                        let titleText = escapeHtml(trimText(appointment.title, 20));
                        if (localCache.is_group && appointment.klassen && appointment.klassen.length > 0) {
                            const klassenNames = appointment.klassen.map(k => k.name).join(', ');
                            titleText += ` (${escapeHtml(klassenNames)})`;
                        }

                        appointmentSpan.innerHTML = `${timeText}${titleText}`;
                        appointmentSpan.addEventListener('click', () => editAppointment(appointment));
                        appointmentsDiv.appendChild(appointmentSpan);
                    });
                    dayHeader.appendChild(appointmentsDiv);
                }

                individualAppointments.forEach(appointment => {
                    const schuelerArray = Array.isArray(appointment.schueler) ? appointment.schueler : [];
                    schuelerArray.forEach(schueler => {
                        const studentCell = document.querySelector(`td[data-stu="${schueler.id}"][data-date="${day.date}"]`);
                        if (studentCell) {
                            let studentAppointmentsDiv = studentCell.querySelector('.student-appointments');
                            if (!studentAppointmentsDiv) {
                                studentAppointmentsDiv = document.createElement('div');
                                studentAppointmentsDiv.className = 'student-appointments mt-1';
                                studentAppointmentsDiv.style.fontSize = '0.65rem';
                                const colInputsRow = studentCell.querySelector('.col-inputs-row');
                                if (colInputsRow) {
                                    studentCell.insertBefore(studentAppointmentsDiv, colInputsRow);
                                } else {
                                    studentCell.appendChild(studentAppointmentsDiv);
                                }
                            }

                            const appointmentSpan = document.createElement('div');
                            appointmentSpan.className = 'appointment-item bg-info text-white px-1 mb-1 rounded';
                            appointmentSpan.style.cursor = 'pointer';
                            appointmentSpan.title = appointment.description || appointment.title;

                            let timeText = '';
                            if (appointment.start_time) {
                                timeText = formatTime(appointment.start_time);
                                if (appointment.end_time) {
                                    timeText += ` - ${formatTime(appointment.end_time)}`;
                                }
                                timeText += ' ';
                            }

                            appointmentSpan.innerHTML = `${timeText}${escapeHtml(trimText(appointment.title, 15))}`;
                            appointmentSpan.addEventListener('click', () => editAppointment(appointment));
                            studentAppointmentsDiv.appendChild(appointmentSpan);
                        }
                    });
                });
            }
        });
    }

    function editAppointment(appointment) {
        appointmentForm.reset();
        appointmentIdInput.value = appointment.id;
        appointmentTitleInput.value = appointment.title;
        appointmentDescriptionInput.value = appointment.description || '';
        appointmentStartDateInput.value = appointment.date;
        appointmentStartTimeInput.value = formatTime(appointment.start_time) || '';
        appointmentEndTimeInput.value = formatTime(appointment.end_time) || '';
        appointmentIsRecurringInput.checked = appointment.is_recurring || false;

        if (appointment.is_recurring) {
            recurringOptions.classList.remove('d-none');
            appointmentRecurringTypeSelect.value = appointment.recurring_type || 'weekly';
            appointmentRecurringIntervalInput.value = appointment.recurring_interval || 1;
            appointmentRecurringEndDateInput.value = appointment.recurring_end_date || '';
        } else {
            recurringOptions.classList.add('d-none');
        }

        if (appointmentStudentsBox) {
            appointmentStudentsBox.querySelectorAll('input[type="checkbox"]').forEach(cb => cb.checked = false);
            if (appointment.klassen && appointment.klassen.length > 0) {
                appointment.klassen.forEach(klasse => {
                    const checkbox = appointmentStudentsBox.querySelector(`input[name="klasse_ids[]"][value="${klasse.id}"]`);
                    if (checkbox) checkbox.checked = true;
                });
            }
            if (appointment.groups && appointment.groups.length > 0) {
                appointment.groups.forEach(group => {
                    const checkbox = appointmentStudentsBox.querySelector(`input[name="group_ids[]"][value="${group.id}"]`);
                    if (checkbox) checkbox.checked = true;
                });
            }
            if (appointment.schueler && appointment.schueler.length > 0) {
                appointment.schueler.forEach(schueler => {
                    const checkbox = appointmentStudentsBox.querySelector(`input[name="schueler_ids[]"][value="${schueler.id}"]`);
                    if (checkbox) checkbox.checked = true;
                });
            }
        }

        appointmentModalTitle.textContent = 'Termin bearbeiten';
        appointmentDeleteBtn.classList.remove('d-none');

        if (appointment.is_recurring) {
            appointmentPauseBtn.classList.remove('d-none');
            appointmentPauseBtn.textContent = appointment.is_paused ? 'Reaktivieren' : 'Pausieren';
        } else {
            appointmentPauseBtn.classList.add('d-none');
        }

        setAppointmentFeedback('', '');
        appointmentModal.modal('show');
    }

    function updateAppointmentSchuelerList() {
        if (!appointmentSchuelerList) return;
        const localCache = getCache();
        appointmentSchuelerList.innerHTML = '';
        const schuelerToShow = localCache.schueler || [];

        if (schuelerToShow.length > 0) {
            schuelerToShow.forEach(s => {
                const div = document.createElement('div');
                div.className = 'form-check-inline mb-1';
                div.innerHTML = `
                    <input class="form-check-input" type="checkbox" id="app_sch_${s.id}" value="${s.id}" name="schueler_ids[]">
                    <label class="form-check-label" for="app_sch_${s.id}">${escapeHtml(s.name)}</label>
                `;
                appointmentSchuelerList.appendChild(div);
            });
        } else {
            appointmentSchuelerList.innerHTML = '<span class="text-muted small">Keine Schüler in der aktuellen Auswahl.</span>';
        }
    }

    // --- Event Listeners ---
    if (openAppointmentModalBtn) {
        openAppointmentModalBtn.addEventListener('click', () => {
            appointmentForm.reset();
            appointmentIdInput.value = '';
            appointmentModalTitle.textContent = 'Termin erstellen';
            appointmentDeleteBtn.classList.add('d-none');
            appointmentPauseBtn.classList.add('d-none');
            setAppointmentFeedback('', '');
            appointmentModal.modal('show');
        });
    }

    if (appointmentIsRecurringInput) {
        appointmentIsRecurringInput.addEventListener('change', () => {
            if (appointmentIsRecurringInput.checked) {
                recurringOptions.classList.remove('d-none');
            } else {
                recurringOptions.classList.add('d-none');
            }
        });
    }

    if (appointmentForm) {
        appointmentForm.addEventListener('submit', e => {
            e.preventDefault();
            appointmentStatus.textContent = 'Speichere...';
            const fd = new FormData(appointmentForm);
            const hasKlassenSelected = fd.getAll('klasse_ids[]').length > 0;
            const hasGroupsSelected = fd.getAll('group_ids[]').length > 0;
            const hasSchuelerSelected = fd.getAll('schueler_ids[]').length > 0;

            if (!hasKlassenSelected && !hasGroupsSelected && !hasSchuelerSelected) {
                if (groupSelect.value) {
                    fd.append('group_ids[]', groupSelect.value);
                } else {
                    fd.append('klasse_ids[]', klasseSelect.value);
                }
            }

            const id = appointmentIdInput.value;
            const url = id ? `paed-diary/appointments/${id}` : 'paed-diary/appointments';
            const method = 'POST';
            if (id) {
                fd.append('_method', 'PUT');
            }

            fetch(url, {
                    method: method,
                    headers: {
                        'X-CSRF-TOKEN': csrf,
                        'Accept': 'application/json'
                    },
                    body: fd
                })
                .then(r => r.json())
                .then(j => {
                    if (j.success) {
                        appointmentStatus.textContent = 'Gespeichert';
                        setAppointmentFeedback('Termin erfolgreich gespeichert', 'success');
                        setTimeout(() => {
                            appointmentModal.modal('hide');
                            loadWeek(); // Haupt-Ladefunktion aufrufen
                        }, 1000);
                    } else {
                        appointmentStatus.textContent = j.message || 'Fehler';
                        setAppointmentFeedback(j.message || 'Fehler beim Speichern', 'danger');
                    }
                })
                .catch(() => {
                    appointmentStatus.textContent = 'Fehler beim Speichern';
                    setAppointmentFeedback('Fehler beim Speichern', 'danger');
                });
        });
    }

    if (appointmentDeleteBtn) {
        appointmentDeleteBtn.addEventListener('click', () => {
            const id = appointmentIdInput.value;
            if (!id || !confirm('Termin wirklich löschen?')) return;

            appointmentStatus.textContent = 'Lösche...';
            fetch(`paed-diary/appointments/${id}`, {
                    method: 'DELETE',
                    headers: {
                        'X-CSRF-TOKEN': csrf,
                        'Accept': 'application/json'
                    }
                })
                .then(r => r.json())
                .then(j => {
                    if (j.success) {
                        appointmentStatus.textContent = 'Gelöscht';
                        setAppointmentFeedback('Termin gelöscht', 'success');
                        setTimeout(() => {
                            appointmentModal.modal('hide');
                            loadWeek(); // Haupt-Ladefunktion aufrufen
                        }, 1000);
                    } else {
                        appointmentStatus.textContent = 'Löschen fehlgeschlagen';
                        setAppointmentFeedback('Löschen fehlgeschlagen', 'danger');
                    }
                })
                .catch(() => {
                    appointmentStatus.textContent = 'Löschen fehlgeschlagen';
                    setAppointmentFeedback('Löschen fehlgeschlagen', 'danger');
                });
        });
    }

    // --- Öffentliche API ---
    return {
        loadAppointments,
        updateAppointmentSchuelerList
    };
}
