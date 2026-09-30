/**
 * Personal-Modul – Alpine.js
 *
 * Nur initialisieren wenn noch nicht global (z.B. durch app.js) gestartet.
 */
import Alpine from 'alpinejs';

// Organigramm-Komponente
import './personal/orgchart.js';

// Gemeinsame Alpine-Komponente: Tab-Navigation
Alpine.data('personalTabs', (defaultTab = null) => ({
    activeTab: defaultTab,

    setTab(tab) {
        this.activeTab = tab;
        // Tab-Namen im URL-Hash speichern (Direktlink)
        if (history.replaceState) {
            history.replaceState(null, '', '#' + tab);
        }
    },

    isActive(tab) {
        return this.activeTab === tab;
    },

    init() {
        // Tab aus URL-Hash lesen
        const hash = window.location.hash.replace('#', '');
        if (hash) this.activeTab = hash;
    },
}));

// Vertragsformular: Stellenanteil und – bei Lehrkräften – Wochenstunden live aus dem Deputat berechnen
//   Wochenstunden = Deputat ÷ Regeldeputat der Schulart × Vollzeit-Wochenstunden der Stundenart
Alpine.data('contractForm', (cfg) => ({
    type: cfg.type,
    contractType: cfg.contractType,
    schoolTypeId: cfg.schoolTypeId ?? '',
    deputat: cfg.deputat ?? '',
    hourTypeId: cfg.hourTypeId ?? '',
    hours: cfg.hours ?? '',
    manual: cfg.manual ?? false,
    hasExitDate: cfg.hasExitDate ?? false,
    schools: cfg.schools ?? {},
    hourTypes: cfg.hourTypes ?? {},
    vollzeit: cfg.vollzeit ?? 40,

    get isTeacher() { return this.type === 'lehrer'; },
    get isFixedTerm() { return this.contractType === 'befristet' || this.contractType === 'befristet_sachgrund'; },
    get showEnd() { return this.isFixedTerm || this.hasExitDate; },
    get fulltime() { const v = parseFloat(this.hourTypes[this.hourTypeId]); return v > 0 ? v : null; },
    get regeldeputat() { const v = parseFloat(this.schools[this.schoolTypeId]); return v > 0 ? v : null; },
    get calculated() {
        const d = parseFloat(this.deputat);
        if (!this.isTeacher || !this.fulltime || !this.regeldeputat || isNaN(d)) return null;
        return Math.round(d / this.regeldeputat * this.fulltime * 100) / 100;
    },
    get percent() {
        const h = parseFloat(this.hours);
        return this.fulltime && !isNaN(h) ? Math.round(h / this.fulltime * 1000) / 10 : null;
    },
    get sollWoche() {
        return this.percent === null ? null : Math.round(this.percent / 100 * this.vollzeit * 100) / 100;
    },
    get hoursLocked() { return this.isTeacher && this.calculated !== null && !this.manual; },

    init() {
        this.$watch('calculated', (v) => { if (this.hoursLocked && v !== null) this.hours = v; });
        this.$watch('manual', () => { if (this.hoursLocked && this.calculated !== null) this.hours = this.calculated; });
        this.$watch('schoolTypeId', () => { if (this.deputat === '' && this.regeldeputat) this.deputat = this.regeldeputat; });
        if (this.hoursLocked && this.calculated !== null) this.hours = this.calculated;
    },
}));

// Alpine.js starten (nur wenn noch nicht gestartet)
if (!window.Alpine) {
    window.Alpine = Alpine;
    Alpine.start();
}

