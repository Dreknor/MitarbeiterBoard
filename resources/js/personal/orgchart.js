/**
 * Personal-Modul – Organigramm Alpine.js Komponente
 * Registrierung via window.Alpine.data() – kein eigenes Alpine.start()
 */

window.addEventListener('load', function () {
    if (!window.Alpine) return;

    window.Alpine.data('orgChart', (treeData) => ({
        tree: treeData,
        expanded: {},
        search: '',
        selectedPosition: null,

        init() {
            // Erste Ebene aufklappen
            if (this.tree) {
                this.expanded[this.tree.id] = true;
            }
        },

        toggle(id) {
            this.expanded[id] = !this.expanded[id];
        },

        isExpanded(id) {
            return !!this.expanded[id];
        },

        expandAll(node) {
            if (!node) return;
            this.expanded[node.id] = true;
            (node.children || []).forEach(c => this.expandAll(c));
        },

        collapseAll(node) {
            if (!node) return;
            this.expanded[node.id] = false;
            (node.children || []).forEach(c => this.collapseAll(c));
        },

        selectPosition(position) {
            this.selectedPosition = position;
        },

        closePanel() {
            this.selectedPosition = null;
        },

        // Prüft ob ein Knoten oder seine Kinder dem Suchbegriff entsprechen
        matchesSearch(node, term) {
            if (!term) return true;
            const lc = term.toLowerCase();
            const nameMatch  = node.name.toLowerCase().includes(lc);
            const userMatch  = (node.users || []).some(u => u.name.toLowerCase().includes(lc));
            const childMatch = (node.children || []).some(c => this.matchesSearch(c, lc));
            return nameMatch || userMatch || childMatch;
        },

        // Baum aufklappen bis zu Treffern
        expandToMatches(node, term) {
            if (!term || !node) return;
            if ((node.children || []).some(c => this.matchesSearch(c, term))) {
                this.expanded[node.id] = true;
                node.children.forEach(c => this.expandToMatches(c, term));
            }
        },
    }));
});

