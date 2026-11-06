/*
 * Klassenraum-Tool „Aquarium“ – Vite-Entrypoint (Lautstärke-Tool).
 * Motor und Bedienung: shared/lautstaerke.js, Szene: szenen/aquarium.js
 */
import {starteLautstaerkeTool} from './shared/lautstaerke';
import {erzeugeAquarium} from './szenen/aquarium';

starteLautstaerkeTool({schluessel: 'aquarium', szene: erzeugeAquarium()});
