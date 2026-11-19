/*
 * Klassenraum-Tool „Zoo“ – Vite-Entrypoint (Lautstärke-Tool).
 * Motor und Bedienung: shared/lautstaerke.js, Szene: szenen/zoo.js
 */
import {starteLautstaerkeTool} from './shared/lautstaerke';
import {erzeugeZoo} from './szenen/zoo';

starteLautstaerkeTool({schluessel: 'zoo', szene: erzeugeZoo()});
