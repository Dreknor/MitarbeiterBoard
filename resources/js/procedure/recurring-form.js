/**
 * Prozesse-Modul – Formular für wiederkehrende Prozesse
 * Blendet je nach gewähltem faelligkeit_typ die passenden Felder ein.
 */

document.addEventListener('alpine:init', () => {
    window.Alpine.data('recurringForm', () => ({
        typ: '',

        get showDatum() {
            return this.typ === 'datum';
        },
        get showFerienFields() {
            return this.typ === 'vor_ferien' || this.typ === 'nach_ferien';
        },
        get showWochentag() {
            return this.typ === 'wochentag';
        },
        get showSchuljahresStichtag() {
            return this.typ === 'schuljahres_stichtag';
        },
    }));
});

