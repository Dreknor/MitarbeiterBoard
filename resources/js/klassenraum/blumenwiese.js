/*
 * Klassenraum-Tool „Blumenwiese“ – Vite-Entrypoint (Lautstärke-Tool).
 * Motor und Bedienung: shared/lautstaerke.js, Szene: szenen/blumenwiese.js
 */
import {starteLautstaerkeTool} from './shared/lautstaerke';
import {erzeugeBlumenwiese} from './szenen/blumenwiese';

starteLautstaerkeTool({schluessel: 'blumenwiese', szene: erzeugeBlumenwiese()});
