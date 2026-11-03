/*
 * Online-Updater – Vite-Entrypoint
 *
 * Alpine-Komponente "updater": Live-Status (Polling von updater/status),
 * Phasen-Fortschritt, Konsole mit Auto-Scroll und Bestätigungsdialog vor dem Start.
 *
 * Alpine wird von sidebar.js global bereitgestellt und erst bei window.load
 * gestartet – Komponenten können daher hier direkt registriert werden.
 */

// Reihenfolge der Schritte aus App\Console\Commands\AppUpdate, zu Phasen zusammengefasst
const PHASES = [
    {label: 'Vorbereitung & Vorabprüfung', match: /^(Vorbereitung|Wird gestartet|Lade Änderungen|Vorabprüfung)/},
    {label: 'Wartungsmodus aktivieren', match: /^Wartungsmodus/},
    {label: 'Code aktualisieren', match: /^(Code aktualisieren|Zurücksetzen)/},
    {label: 'Abhängigkeiten & Frontend', match: /(composer|npm|Frontend)/},
    {label: 'Migration & Caches', match: /^php artisan/},
];

const STATES = {
    idle: 'Kein Update gelaufen',
    queued: 'Wird gestartet',
    running: 'Läuft',
    success: 'Erfolgreich',
    failed: 'Fehlgeschlagen',
};

function phaseIndex(step) {
    if (!step) return -1;
    return PHASES.findIndex(phase => phase.match.test(step));
}

function registerComponents(Alpine) {
    Alpine.data('updater', ({url, status, log}) => ({
        status,
        log,
        phases: PHASES,
        phase: phaseIndex(status.step),
        waiting: false,
        confirmOpen: false,
        failures: 0,

        get active() {
            return ['queued', 'running'].includes(this.status.state);
        },
        get stateLabel() {
            return STATES[this.status.state] || STATES.idle;
        },

        init() {
            this.$nextTick(() => this.scrollLog(true));
            if (this.active) {
                setTimeout(() => this.poll(), 1000);
            }
        },

        phaseClass(index) {
            if (this.status.state === 'success') return 'upd-phase-done';
            if (index < this.phase) return 'upd-phase-done';
            if (index === this.phase) return this.status.state === 'failed' ? 'upd-phase-failed' : 'upd-phase-active';
            return 'upd-phase-todo';
        },

        scrollLog(force = false) {
            const el = this.$refs.log;
            if (!el) return;
            if (force || el.scrollTop + el.clientHeight >= el.scrollHeight - 40) {
                el.scrollTop = el.scrollHeight;
            }
        },

        async poll() {
            try {
                const response = await fetch(url, {headers: {'Accept': 'application/json'}});
                if (!response.ok) throw new Error(response.status);
                const data = await response.json();
                this.failures = 0;
                this.waiting = false;

                const el = this.$refs.log;
                const atBottom = !el || el.scrollTop + el.clientHeight >= el.scrollHeight - 40;
                this.status = data.status;
                this.log = data.log || '';
                const index = phaseIndex(data.status.step);
                if (index >= 0) this.phase = index;
                if (atBottom) this.$nextTick(() => this.scrollLog(true));

                if (this.active) {
                    setTimeout(() => this.poll(), 2000);
                } else {
                    // Seite neu laden, damit installierter Stand und Änderungen aktuell sind
                    setTimeout(() => window.location.reload(), 3000);
                }
            } catch (e) {
                // Während composer/Wartungsmodus kann die Anwendung kurz nicht antworten
                this.failures++;
                this.waiting = true;
                setTimeout(() => this.poll(), Math.min(2000 * this.failures, 10000));
            }
        },
    }));
}

if (window.Alpine) {
    registerComponents(window.Alpine);
} else {
    document.addEventListener('alpine:init', () => registerComponents(window.Alpine));
}
