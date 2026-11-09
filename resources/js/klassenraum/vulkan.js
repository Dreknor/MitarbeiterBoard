/*
 * Klassenraum-Tool „Vulkan“ – Vite-Entrypoint (Lautstärke-Tool).
 * Motor und Bedienung: shared/lautstaerke.js, Szene: szenen/vulkan.js
 */
import {starteLautstaerkeTool} from './shared/lautstaerke';
import {erzeugeVulkan} from './szenen/vulkan';

starteLautstaerkeTool({schluessel: 'vulkan', szene: erzeugeVulkan()});
