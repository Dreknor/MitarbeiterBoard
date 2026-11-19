/*
 * Klassenraum-Tool „Polarlicht“ – Vite-Entrypoint (Lautstärke-Tool).
 * Motor und Bedienung: shared/lautstaerke.js, Szene: szenen/polarlicht.js
 * Eigene Einstellung „farben“ (Index in FARBWELTEN, Auswahl über den Registry-Eintrag „auswahl“).
 */
import {starteLautstaerkeTool} from './shared/lautstaerke';
import {erzeugePolarlicht} from './szenen/polarlicht';

starteLautstaerkeTool({schluessel: 'polarlicht', szene: erzeugePolarlicht(), standard: {farben: 0}});
