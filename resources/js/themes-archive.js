/*
 * Themenarchiv – Alpine.js Komponente (Vite-Bundle)
 *
 * Ersetzt das bisherige Alpine-CDN. Registriert die Komponente auf der global
 * (in sidebar.js) gestarteten Alpine-Instanz – KEIN eigener Alpine.start(),
 * damit das in sidebar.js installierte Collapse-Plugin (x-collapse) genutzt wird.
 */
function registerArchiveApp(Alpine) {
    Alpine.data('archiveApp', (initialOpenDays = []) => ({
        searchQuery: '',
        filterType: '',
        filterCreator: '',
        openDays: Array.isArray(initialOpenDays) ? initialOpenDays : [],
        availableTypes: [],
        availableCreators: [],

        init() {
            const types = new Set();
            const creators = new Set();

            document.querySelectorAll('.theme-row, .theme-card').forEach((item) => {
                if (item.dataset.type) types.add(item.dataset.type);
                if (item.dataset.creator) creators.add(item.dataset.creator);
            });

            this.availableTypes = Array.from(types).map((t) => t.charAt(0).toUpperCase() + t.slice(1));
            this.availableCreators = Array.from(creators).map((c) => c.charAt(0).toUpperCase() + c.slice(1));
        },

        isThemeVisible(theme, type, creator, goal) {
            const searchTerm = this.searchQuery.toLowerCase();
            const selectedType = this.filterType.toLowerCase();
            const selectedCreator = this.filterCreator.toLowerCase();

            const matchesSearch = !searchTerm
                || theme.includes(searchTerm)
                || goal.includes(searchTerm)
                || type.includes(searchTerm)
                || creator.includes(searchTerm);

            const matchesType = !selectedType || type === selectedType;
            const matchesCreator = !selectedCreator || creator === selectedCreator;

            return matchesSearch && matchesType && matchesCreator;
        },

        isDaySectionVisible(dayId) {
            const section = document.querySelector(`[data-day-id="${dayId}"]`);
            if (!section) return false;
            const themes = section.querySelectorAll('.theme-row, .theme-card');
            return Array.from(themes).some((theme) => this.isThemeVisible(
                theme.dataset.theme, theme.dataset.type, theme.dataset.creator, theme.dataset.goal,
            ));
        },

        getVisibleThemesCount(dayId) {
            const section = document.querySelector(`[data-day-id="${dayId}"]`);
            if (!section) return 0;
            const themes = section.querySelectorAll('.theme-row, .theme-card');
            return Array.from(themes).filter((theme) => this.isThemeVisible(
                theme.dataset.theme, theme.dataset.type, theme.dataset.creator, theme.dataset.goal,
            )).length;
        },

        toggleDay(dayId) {
            const index = this.openDays.indexOf(dayId);
            if (index > -1) {
                this.openDays.splice(index, 1);
            } else {
                this.openDays.push(dayId);
            }
        },

        resetFilters() {
            this.searchQuery = '';
            this.filterType = '';
            this.filterCreator = '';
        },

        getFilteredThemesCount() {
            const themes = document.querySelectorAll('.theme-row, .theme-card');
            return Array.from(themes).filter((theme) => this.isThemeVisible(
                theme.dataset.theme, theme.dataset.type, theme.dataset.creator, theme.dataset.goal,
            )).length;
        },
    }));
}

// Auf der global gestarteten Alpine-Instanz registrieren – kein eigener Alpine.start()!
if (window.Alpine) {
    registerArchiveApp(window.Alpine);
} else {
    document.addEventListener('alpine:init', () => registerArchiveApp(window.Alpine));
}

