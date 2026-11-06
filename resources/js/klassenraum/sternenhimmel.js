/*
 * Klassenraum-Tool „Sternenhimmel“ – Vite-Entrypoint (Lautstärke-Tool).
 * Motor und Bedienung: shared/lautstaerke.js, Szene: szenen/sternenhimmel.js
 */
import {starteLautstaerkeTool} from './shared/lautstaerke';
import {erzeugeSternenhimmel} from './szenen/sternenhimmel';

starteLautstaerkeTool({schluessel: 'sternenhimmel', szene: erzeugeSternenhimmel()});
