/**
 * PaedDiary v2 – Vite-Entrypoint
 *
 * Registriert alle Alpine-Components und den Store.
 * Alpine wird von sidebar.js global geladen und bei window.load gestartet.
 * Wir registrieren hier nur die Components auf window.Alpine.
 */

import { registerDiaryStore } from './paed-diary-v2/store.js';
import { registerDiaryTable } from './paed-diary-v2/diary-table.js';
import { registerNoteEditor } from './paed-diary-v2/note-editor.js';
import { registerColumnsManager } from './paed-diary-v2/columns-manager.js';
import { registerTaskPanel } from './paed-diary-v2/task-panel.js';
import { registerStageDropdown } from './paed-diary-v2/stage-dropdown.js';
import { registerAppointmentManager } from './paed-diary-v2/appointment-manager.js';
import { registerGroupManager } from './paed-diary-v2/group-manager.js';

/**
 * Registriert alle Components auf einer Alpine-Instanz.
 */
function registerAll(Alpine) {
    registerDiaryStore(Alpine);
    registerDiaryTable(Alpine);
    registerNoteEditor(Alpine);
    registerColumnsManager(Alpine);
    registerTaskPanel(Alpine);
    registerStageDropdown(Alpine);
    registerAppointmentManager(Alpine);
    registerGroupManager(Alpine);
}

// Falls Alpine bereits global verfügbar ist (via sidebar.js), sofort registrieren
if (window.Alpine) {
    registerAll(window.Alpine);
} else {
    // Fallback: auf alpine:init warten (sidebar.js startet Alpine verzögert via window.load)
    document.addEventListener('alpine:init', () => {
        if (window.Alpine) {
            registerAll(window.Alpine);
        }
    });
}

