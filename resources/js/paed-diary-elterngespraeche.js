/**
 * Elterngespräche in der bisherigen Schülerakte (paedDiary.schueler) – Vite-Entrypoint.
 * Die neue Ansicht bindet dieselbe Komponente über paed-diary-neu.js ein. Das CSS der neuen
 * Ansicht ist vollständig pdn-präfixiert und wird hier im .pdn-Wrapper genutzt.
 */

import '../css/paed-diary-neu.css';
import { registerElterngespraeche } from './paed-diary-v2/elterngespraeche.js';

if (window.Alpine) {
    registerElterngespraeche(window.Alpine);
} else {
    document.addEventListener('alpine:init', () => {
        if (window.Alpine) registerElterngespraeche(window.Alpine);
    });
}
